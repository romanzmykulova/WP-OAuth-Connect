<?php
declare(strict_types=1);

/**
 * GitHub profile fetch — /user plus /user/emails for verified primary.
 */

namespace WpOAuthConnect\Provider\Adapter;

use WpOAuthConnect\Exceptions\OAuthHttpException;
use WpOAuthConnect\OAuthProfile;
use WpOAuthConnect\Provider\ProviderDefinition;
use WpOAuthConnect\Support\HttpClient;

final class GithubProfileAdapter implements ProfileAdapter
{
    private const USER_URL    = 'https://api.github.com/user';
    private const EMAILS_URL  = 'https://api.github.com/user/emails';
    private const ACCEPT_JSON = 'application/vnd.github+json';

    public function fetchProfile(
        HttpClient $http,
        string $accessToken,
        ProviderDefinition $definition,
    ): OAuthProfile {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept'        => self::ACCEPT_JSON,
            'User-Agent'    => 'wp-oauth-connect',
        ];

        $userResponse = $http->get(self::USER_URL, $headers);
        if (!$userResponse->isSuccess()) {
            throw new OAuthHttpException('GitHub /user fetch failed with status ' . $userResponse->status);
        }

        $user = json_decode($userResponse->body, true);
        if (!is_array($user) || !isset($user['id'])) {
            throw new OAuthHttpException('GitHub /user response is not valid JSON.');
        }

        $emailsResponse = $http->get(self::EMAILS_URL, $headers);
        if (!$emailsResponse->isSuccess()) {
            throw new OAuthHttpException('GitHub /user/emails fetch failed with status ' . $emailsResponse->status);
        }

        $emails = json_decode($emailsResponse->body, true);
        if (!is_array($emails)) {
            throw new OAuthHttpException('GitHub /user/emails response is not valid JSON.');
        }

        [$email, $emailVerified] = $this->resolveEmail($emails, isset($user['email']) ? (string) $user['email'] : '');

        $login = isset($user['login']) ? (string) $user['login'] : '';
        $name  = isset($user['name']) && trim((string) $user['name']) !== ''
            ? trim((string) $user['name'])
            : $login;

        return new OAuthProfile(
            providerSlug: $definition->slug,
            providerUserId: (string) $user['id'],
            email: $email,
            emailVerified: $emailVerified,
            displayName: $name !== '' ? $name : $email,
            avatarUrl: isset($user['avatar_url']) ? (string) $user['avatar_url'] : '',
            rawLogin: $login,
        );
    }

    /**
     * @param list<array<string, mixed>> $emails
     * @return array{0: string, 1: bool}
     */
    private function resolveEmail(array $emails, string $fallbackFromUser): array
    {
        $primaryVerified = '';
        $anyVerified     = '';
        $noreply         = '';

        foreach ($emails as $row) {
            if (!is_array($row) || !isset($row['email'])) {
                continue;
            }
            $address  = strtolower(trim((string) $row['email']));
            $verified = filter_var($row['verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $primary  = filter_var($row['primary'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($verified && $primary) {
                $primaryVerified = $address;
            }
            if ($verified && $anyVerified === '') {
                $anyVerified = $address;
            }
            if (str_contains($address, 'noreply.github.com')) {
                $noreply = $address;
            }
        }

        if ($primaryVerified !== '') {
            return [$primaryVerified, true];
        }
        if ($anyVerified !== '') {
            return [$anyVerified, true];
        }
        if ($noreply !== '') {
            return [$noreply, false];
        }

        $fallback = strtolower(trim($fallbackFromUser));
        if ($fallback !== '') {
            $isNoreply = str_contains($fallback, 'noreply.github.com');
            return [$fallback, !$isNoreply];
        }

        return ['', false];
    }
}