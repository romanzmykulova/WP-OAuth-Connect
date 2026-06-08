<?php
declare(strict_types=1);

/**
 * Runtime provider contract implemented by OidcProvider and OAuth2Provider.
 */

namespace WpOAuthConnect\Provider;

use WpOAuthConnect\OAuthProfile;

interface Provider
{
    public function slug(): string;

    public function label(): string;

    public function authorizeUrl(string $state, string $redirectUri): string;

    public function exchangeCode(string $code, string $redirectUri): string;

    public function fetchProfile(string $accessToken): OAuthProfile;

    public function credentialKeys(): ProviderCredentialKeys;

    public function requiresVerifiedEmail(): bool;
}