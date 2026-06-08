<?php
declare(strict_types=1);

/**
 * Typed wrapper around wp-config constants and plugin options.
 * All get_option/update_option calls for this plugin live here.
 */

namespace WpOAuthConnect\Options;

final class Settings
{
    public const SCHEMA_VERSION_OPTION = 'woc_schema_version';

    public const STATE_KEY_CONSTANT = 'OAUTH_STATE_KEY';

    public const NATIVE_LOGIN_ENABLED_OPTION = 'woc_oauth_native_login_enabled';

    /** @var list<string> */
    public const BUILTIN_PROVIDER_SLUGS = [
        'linkedin',
        'google',
        'github',
        'microsoft',
    ];

    public static function schemaVersion(): int
    {
        return (int) \get_option(self::SCHEMA_VERSION_OPTION, 0);
    }

    public static function setSchemaVersion(int $version): void
    {
        \update_option(self::SCHEMA_VERSION_OPTION, $version, false);
    }

    public static function stateKey(): string
    {
        return self::readConstant(self::STATE_KEY_CONSTANT);
    }

    public static function isStateKeyConfigured(): bool
    {
        return self::stateKey() !== '';
    }

    /**
     * @return list<string>
     */
    public static function builtinProviderSlugs(): array
    {
        return self::BUILTIN_PROVIDER_SLUGS;
    }

    public static function providerEnabledOptionKey(string $slug): string
    {
        return 'woc_oauth_' . $slug . '_enabled';
    }

    public static function isProviderEnabled(string $slug): bool
    {
        return (string) \get_option(self::providerEnabledOptionKey($slug), '0') === '1';
    }

    public static function setProviderEnabled(string $slug, bool $enabled): void
    {
        \update_option(self::providerEnabledOptionKey($slug), $enabled ? '1' : '0', false);
    }

    public static function providerClientIdConstant(string $slug): string
    {
        return 'OAUTH_' . self::slugToConstantSuffix($slug) . '_CLIENT_ID';
    }

    public static function providerClientSecretConstant(string $slug): string
    {
        return 'OAUTH_' . self::slugToConstantSuffix($slug) . '_CLIENT_SECRET';
    }

    public static function providerClientId(string $slug): string
    {
        return self::readConstant(self::providerClientIdConstant($slug));
    }

    public static function providerClientSecret(string $slug): string
    {
        return self::readConstant(self::providerClientSecretConstant($slug));
    }

    public static function hasProviderCredentials(string $slug): bool
    {
        return self::providerClientId($slug) !== ''
            && self::providerClientSecret($slug) !== '';
    }

    /**
     * Provider is routable when creds, state key, and enable flag are all set.
     */
    public static function isProviderOperational(string $slug): bool
    {
        if (!self::isStateKeyConfigured()) {
            return false;
        }

        if (!self::hasProviderCredentials($slug)) {
            return false;
        }

        $enabled = self::isProviderEnabled($slug);
        return (bool) \apply_filters('woc_oauth_provider_enabled', $enabled, $slug);
    }

    public static function isNativeLoginEnabled(): bool
    {
        $default = (string) \get_option(self::NATIVE_LOGIN_ENABLED_OPTION, '1') === '1';
        return (bool) \apply_filters('woc_oauth_native_login_enabled', $default);
    }

    public static function setNativeLoginEnabled(bool $enabled): void
    {
        \update_option(self::NATIVE_LOGIN_ENABLED_OPTION, $enabled ? '1' : '0', false);
    }

    /**
     * Admin status badge: missing creds, disabled, or ready.
     *
     * @return 'missing_creds'|'disabled'|'missing_state_key'|'ready'
     */
    public static function providerAdminStatus(string $slug): string
    {
        if (!self::isStateKeyConfigured()) {
            return 'missing_state_key';
        }
        if (!self::hasProviderCredentials($slug)) {
            return 'missing_creds';
        }
        if (!self::isProviderEnabled($slug)) {
            return 'disabled';
        }
        return 'ready';
    }

    private static function slugToConstantSuffix(string $slug): string
    {
        return strtoupper(str_replace('-', '_', $slug));
    }

    private static function readConstant(string $name): string
    {
        if (!defined($name)) {
            return '';
        }
        $value = constant($name);
        return is_string($value) ? $value : '';
    }
}