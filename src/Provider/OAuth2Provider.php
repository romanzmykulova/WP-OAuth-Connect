<?php
declare(strict_types=1);

/**
 * Generic OAuth2 engine — explicit authorize/token/profile URLs + claim map.
 */

namespace WpOAuthConnect\Provider;

use WpOAuthConnect\Exceptions\OAuthEmailNotVerified;
use WpOAuthConnect\Exceptions\OAuthHttpException;
use WpOAuthConnect\OAuthProfile;
use WpOAuthConnect\Provider\Adapter\GithubProfileAdapter;
use WpOAuthConnect\Provider\Adapter\ProfileAdapter;
use WpOAuthConnect\Support\HttpClient;

final class OAuth2Provider implements Provider
{
    public function __construct(
        private readonly ProviderDefinition $definition,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly HttpClient $http,
        private readonly ?ProfileAdapter $profileAdapter = null,
    ) {}

    public function slug(): string
    {
        return $this->definition->slug;
    }

    public function label(): string
    {
        return $this->definition->label;
    }

    public function authorizeUrl(string $state, string $redirectUri): string
    {
        $this->requireUrl($this->definition->authorizeUrl, 'authorize_url');
        $params = [
            'client_id'    => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope'        => implode(' ', $this->definition->scopes),
            'state'        => $state,
        ];

        return $this->definition->authorizeUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, string $redirectUri): string
    {
        $this->requireUrl($this->definition->tokenUrl, 'token_url');
        $body = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
        ];

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept'       => 'application/json',
        ];

        if ($this->definition->tokenAuthMethod === 'client_secret_basic') {
            $headers['Authorization'] = 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret);
            unset($body['client_id'], $body['client_secret']);
        }

        $response = $this->http->post($this->definition->tokenUrl, $headers, $body);
        if (!$response->isSuccess()) {
            throw new OAuthHttpException('OAuth2 token exchange failed with status ' . $response->status);
        }

        $json = json_decode($response->body, true);
        if (!is_array($json) || !isset($json['access_token'])) {
            throw new OAuthHttpException('OAuth2 token response missing access_token.');
        }

        return (string) $json['access_token'];
    }

    public function fetchProfile(string $accessToken): OAuthProfile
    {
        $adapter = $this->profileAdapter ?? $this->resolveProfileAdapter();
        if ($adapter !== null) {
            return $adapter->fetchProfile($this->http, $accessToken, $this->definition);
        }

        $this->requireUrl($this->definition->profileUrl, 'profile_url');
        $response = $this->http->get(
            $this->definition->profileUrl,
            ['Authorization' => 'Bearer ' . $accessToken],
        );

        if (!$response->isSuccess()) {
            throw new OAuthHttpException('OAuth2 profile fetch failed with status ' . $response->status);
        }

        $json = json_decode($response->body, true);
        if (!is_array($json)) {
            throw new OAuthHttpException('OAuth2 profile response is not valid JSON.');
        }

        $profile = $this->mapClaims($json);
        if ($this->requiresVerifiedEmail() && !$profile->emailVerified) {
            throw new OAuthEmailNotVerified('OAuth2 email is not verified for provider ' . $this->slug());
        }

        return $profile;
    }

    public function credentialKeys(): ProviderCredentialKeys
    {
        return new ProviderCredentialKeys(
            clientIdConstant: 'OAUTH_' . strtoupper(str_replace('-', '_', $this->slug())) . '_CLIENT_ID',
            clientSecretConstant: 'OAUTH_' . strtoupper(str_replace('-', '_', $this->slug())) . '_CLIENT_SECRET',
        );
    }

    public function requiresVerifiedEmail(): bool
    {
        return $this->profileAdapter === null && $this->definition->profileAdapter === '';
    }

    private function resolveProfileAdapter(): ?ProfileAdapter
    {
        return match ($this->definition->profileAdapter) {
            GithubProfileAdapter::class, 'GithubProfileAdapter' => new GithubProfileAdapter(),
            default => null,
        };
    }

    private function requireUrl(string $url, string $field): void
    {
        if ($url === '') {
            throw new OAuthHttpException('OAuth2 provider ' . $this->slug() . ' missing ' . $field);
        }
    }

    /**
     * @param array<string, mixed> $json
     */
    private function mapClaims(array $json): OAuthProfile
    {
        $claimMap = $this->definition->claimMap !== []
            ? $this->definition->claimMap
            : [
                'id'             => 'id',
                'email'          => 'email',
                'email_verified' => 'email_verified',
                'name'           => 'name',
                'picture'        => 'picture',
            ];

        $providerUserId = $this->claimValue($json, $claimMap['id'] ?? 'id');
        $email          = strtolower(trim($this->claimValue($json, $claimMap['email'] ?? 'email')));
        $verifiedRaw    = $this->claimValue($json, $claimMap['email_verified'] ?? 'email_verified');
        $name           = trim($this->claimValue($json, $claimMap['name'] ?? 'name'));
        $picture        = trim($this->claimValue($json, $claimMap['picture'] ?? 'picture'));

        return new OAuthProfile(
            providerSlug: $this->slug(),
            providerUserId: $providerUserId,
            email: $email,
            emailVerified: filter_var($verifiedRaw, FILTER_VALIDATE_BOOLEAN),
            displayName: $name !== '' ? $name : $email,
            avatarUrl: $picture,
        );
    }

    /**
     * @param array<string, mixed> $json
     */
    private function claimValue(array $json, string $key): string
    {
        return isset($json[$key]) ? (string) $json[$key] : '';
    }
}