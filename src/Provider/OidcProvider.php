<?php
declare(strict_types=1);

/**
 * Generic OIDC engine — discovery, authorize, token exchange, userinfo.
 */

namespace WpOAuthConnect\Provider;

use WpOAuthConnect\Exceptions\OAuthEmailNotVerified;
use WpOAuthConnect\Exceptions\OAuthHttpException;
use WpOAuthConnect\OAuthProfile;
use WpOAuthConnect\Support\HttpClient;

final class OidcProvider implements Provider
{
    /** @var array<string, string>|null */
    private ?array $endpoints = null;

    public function __construct(
        private readonly ProviderDefinition $definition,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly HttpClient $http,
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
        $endpoints = $this->resolveEndpoints();
        $params    = [
            'client_id'     => $this->clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => implode(' ', $this->definition->scopes),
            'state'         => $state,
        ];

        return $endpoints['authorization_endpoint'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code, string $redirectUri): string
    {
        $endpoints = $this->resolveEndpoints();
        $body      = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
        ];

        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        if ($this->definition->tokenAuthMethod === 'client_secret_basic') {
            $headers['Authorization'] = 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret);
        } else {
            $body['client_id']     = $this->clientId;
            $body['client_secret'] = $this->clientSecret;
        }

        $response = $this->http->post($endpoints['token_endpoint'], $headers, $body);
        if (!$response->isSuccess()) {
            throw new OAuthHttpException('OIDC token exchange failed with status ' . $response->status);
        }

        $json = json_decode($response->body, true);
        if (!is_array($json) || !isset($json['access_token'])) {
            throw new OAuthHttpException('OIDC token response missing access_token.');
        }

        return (string) $json['access_token'];
    }

    public function fetchProfile(string $accessToken): OAuthProfile
    {
        $endpoints = $this->resolveEndpoints();
        $response  = $this->http->get(
            $endpoints['userinfo_endpoint'],
            ['Authorization' => 'Bearer ' . $accessToken],
        );

        if (!$response->isSuccess()) {
            throw new OAuthHttpException('OIDC userinfo fetch failed with status ' . $response->status);
        }

        $claims = json_decode($response->body, true);
        if (!is_array($claims)) {
            throw new OAuthHttpException('OIDC userinfo response is not valid JSON.');
        }

        $profile = $this->mapClaims($claims);
        if ($this->requiresVerifiedEmail() && !$profile->emailVerified) {
            throw new OAuthEmailNotVerified('OIDC email is not verified for provider ' . $this->slug());
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
        return true;
    }

    /**
     * @return array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: string}
     */
    private function resolveEndpoints(): array
    {
        if ($this->endpoints !== null) {
            return $this->endpoints;
        }

        if ($this->definition->authorizeUrl !== ''
            && $this->definition->tokenUrl !== ''
            && $this->definition->profileUrl !== '') {
            $this->endpoints = [
                'authorization_endpoint' => $this->definition->authorizeUrl,
                'token_endpoint'         => $this->definition->tokenUrl,
                'userinfo_endpoint'      => $this->definition->profileUrl,
            ];
            return $this->endpoints;
        }

        if ($this->definition->issuer === '') {
            throw new OAuthHttpException('OIDC provider ' . $this->slug() . ' has no issuer or explicit endpoints.');
        }

        $discoveryUrl = rtrim($this->definition->issuer, '/') . '/.well-known/openid-configuration';
        $response     = $this->http->get($discoveryUrl);
        if (!$response->isSuccess()) {
            throw new OAuthHttpException('OIDC discovery failed for ' . $discoveryUrl);
        }

        $json = json_decode($response->body, true);
        if (!is_array($json)) {
            throw new OAuthHttpException('OIDC discovery response is not valid JSON.');
        }

        foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $key) {
            if (!isset($json[$key]) || !is_string($json[$key]) || $json[$key] === '') {
                throw new OAuthHttpException('OIDC discovery missing ' . $key);
            }
        }

        $this->endpoints = [
            'authorization_endpoint' => $json['authorization_endpoint'],
            'token_endpoint'         => $json['token_endpoint'],
            'userinfo_endpoint'      => $json['userinfo_endpoint'],
        ];

        return $this->endpoints;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function mapClaims(array $claims): OAuthProfile
    {
        $claimMap = $this->definition->claimMap !== []
            ? $this->definition->claimMap
            : [
                'id'              => 'sub',
                'email'           => 'email',
                'email_verified'  => 'email_verified',
                'name'            => 'name',
                'picture'         => 'picture',
            ];

        $providerUserId = $this->claimValue($claims, $claimMap['id'] ?? 'sub');
        $email          = strtolower(trim($this->claimValue($claims, $claimMap['email'] ?? 'email')));
        $verifiedRaw    = $this->claimValue($claims, $claimMap['email_verified'] ?? 'email_verified');
        $name           = trim($this->claimValue($claims, $claimMap['name'] ?? 'name'));
        $picture        = trim($this->claimValue($claims, $claimMap['picture'] ?? 'picture'));

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
     * @param array<string, mixed> $claims
     */
    private function claimValue(array $claims, string $key): string
    {
        return isset($claims[$key]) ? (string) $claims[$key] : '';
    }
}