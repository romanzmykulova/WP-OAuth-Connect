<?php
declare(strict_types=1);

/**
 * Short-lived pending-bind records between OAuth callback and password confirmation.
 */

namespace WpOAuthConnect\BindPrompt;

use WpOAuthConnect\OAuthProfile;

final class BindPromptStore
{
    public const TTL_SECONDS = 600;

    private const TRANSIENT_PREFIX = 'woc_oauth_bind_';

    /**
     * @param array<string, mixed> $payload
     */
    public function create(\WP_User $existingUser, OAuthProfile $profile, array $payload): string
    {
        $token = bin2hex(random_bytes(16));
        \set_transient(self::TRANSIENT_PREFIX . $token, [
            'user_id'       => $existingUser->ID,
            'profile'       => $this->serializeProfile($profile),
            'state_payload' => $payload,
        ], self::TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{user_id: int, profile: OAuthProfile, state_payload: array<string, mixed>}|null
     */
    public function consume(string $token): ?array
    {
        $key  = self::TRANSIENT_PREFIX . $token;
        $data = \get_transient($key);
        \delete_transient($key);

        if (!is_array($data)) {
            return null;
        }

        $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        if ($userId <= 0) {
            return null;
        }

        $profile = $this->deserializeProfile($data['profile'] ?? null);
        if ($profile === null) {
            return null;
        }

        $payload = $data['state_payload'] ?? [];
        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'user_id'       => $userId,
            'profile'       => $profile,
            'state_payload' => $payload,
        ];
    }

    /**
     * @return array{user_id: int, profile: OAuthProfile, state_payload: array<string, mixed>}|null
     */
    public function peek(string $token): ?array
    {
        $data = \get_transient(self::TRANSIENT_PREFIX . $token);
        if (!is_array($data)) {
            return null;
        }

        $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        if ($userId <= 0) {
            return null;
        }

        $profile = $this->deserializeProfile($data['profile'] ?? null);
        if ($profile === null) {
            return null;
        }

        $payload = $data['state_payload'] ?? [];
        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'user_id'       => $userId,
            'profile'       => $profile,
            'state_payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProfile(OAuthProfile $profile): array
    {
        return [
            'provider_slug'    => $profile->providerSlug,
            'provider_user_id' => $profile->providerUserId,
            'email'            => $profile->email,
            'email_verified'   => $profile->emailVerified,
            'display_name'     => $profile->displayName,
            'avatar_url'       => $profile->avatarUrl,
            'raw_login'        => $profile->rawLogin,
        ];
    }

    /**
     * @param mixed $raw
     */
    private function deserializeProfile(mixed $raw): ?OAuthProfile
    {
        if (!is_array($raw)) {
            return null;
        }

        return new OAuthProfile(
            providerSlug: (string) ($raw['provider_slug'] ?? ''),
            providerUserId: (string) ($raw['provider_user_id'] ?? ''),
            email: (string) ($raw['email'] ?? ''),
            emailVerified: (bool) ($raw['email_verified'] ?? false),
            displayName: (string) ($raw['display_name'] ?? ''),
            avatarUrl: (string) ($raw['avatar_url'] ?? ''),
            rawLogin: (string) ($raw['raw_login'] ?? ''),
        );
    }
}