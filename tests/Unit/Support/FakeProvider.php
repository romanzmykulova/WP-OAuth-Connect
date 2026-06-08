<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit\Support;

use WpOAuthConnect\OAuthProfile;
use WpOAuthConnect\Provider\Provider;
use WpOAuthConnect\Provider\ProviderCredentialKeys;

final class FakeProvider implements Provider
{
    public function __construct(
        private readonly string $slug = 'github',
        private readonly string $label = 'Continue with GitHub',
        private readonly OAuthProfile $profile = new OAuthProfile(
            providerSlug: 'github',
            providerUserId: 'gh-123',
            email: 'dev@example.com',
            emailVerified: true,
            displayName: 'Dev User',
        ),
    ) {}

    public function slug(): string
    {
        return $this->slug;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function authorizeUrl(string $state, string $redirectUri): string
    {
        return 'https://github.example/authorize?state=' . rawurlencode($state)
            . '&redirect_uri=' . rawurlencode($redirectUri);
    }

    public function exchangeCode(string $code, string $redirectUri): string
    {
        return 'access-token-for-' . $code;
    }

    public function fetchProfile(string $accessToken): OAuthProfile
    {
        return $this->profile;
    }

    public function credentialKeys(): ProviderCredentialKeys
    {
        return new ProviderCredentialKeys('OAUTH_GITHUB_CLIENT_ID', 'OAUTH_GITHUB_CLIENT_SECRET');
    }

    public function requiresVerifiedEmail(): bool
    {
        return true;
    }
}