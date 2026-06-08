<?php
declare(strict_types=1);

/**
 * Normalized identity returned after OAuth code exchange + profile fetch.
 */

namespace WpOAuthConnect;

final readonly class OAuthProfile
{
    public function __construct(
        public string $providerSlug,
        public string $providerUserId,
        public string $email,
        public bool $emailVerified,
        public string $displayName,
        public string $avatarUrl = '',
        public string $rawLogin = '',
    ) {}

    /**
     * @param array<string, mixed> $claims
     */
    public static function fromOidcClaims(string $providerSlug, array $claims): self
    {
        $sub = isset($claims['sub']) ? (string) $claims['sub'] : '';
        $email = isset($claims['email']) ? strtolower(trim((string) $claims['email'])) : '';
        $verified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $name = isset($claims['name']) ? trim((string) $claims['name']) : '';
        $picture = isset($claims['picture']) ? trim((string) $claims['picture']) : '';

        return new self(
            providerSlug: $providerSlug,
            providerUserId: $sub,
            email: $email,
            emailVerified: $verified,
            displayName: $name !== '' ? $name : $email,
            avatarUrl: $picture,
        );
    }

    public function usermetaKey(): string
    {
        return 'oauth_' . $this->providerSlug . '_user_id';
    }
}