<?php
declare(strict_types=1);

/**
 * Typed wrapper around wp-config constants and plugin options.
 * All get_option/update_option calls for this plugin live here.
 */

namespace WpOAuthConnect\Options;

use WpOAuthConnect\Support\StateKeyInstallResult;
use WpOAuthConnect\Support\WpConfigStateKeyInstaller;

final class Settings
{
    public const SCHEMA_VERSION_OPTION = 'woc_schema_version';

    public const STATE_KEY_CONSTANT = 'OAUTH_STATE_KEY';

    /** Runtime fallback until wp-config is updated (or auto-write succeeds). */
    public const STATE_KEY_OPTION = 'woc_oauth_state_key';

    /** Set to 1 when wp-config must be edited manually. */
    public const STATE_KEY_MANUAL_PENDING_OPTION = 'woc_oauth_state_key_manual_pending';

    public const NATIVE_LOGIN_ENABLED_OPTION = 'woc_oauth_native_login_enabled';

    /** Comma-separated provider slugs for login/join button order (e.g. linkedin,google,github). */
    public const LOGIN_BUTTON_ORDER_OPTION = 'woc_oauth_login_button_order';

    /** @var list<string> */
    public const DEFAULT_LOGIN_BUTTON_ORDER = [
        'linkedin',
        'google',
        'github',
    ];

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

    /**
     * Ensures OAUTH_STATE_KEY exists: use wp-config when present, otherwise
     * auto-write wp-config.php or fall back to a stored option + admin snippet.
     */
    public static function ensureStateKey(): void
    {
        if (self::readConstant(self::STATE_KEY_CONSTANT) !== '') {
            self::clearStateKeyFallbackOptions();
            return;
        }

        $installer = new WpConfigStateKeyInstaller();
        $result    = $installer->installIfMissing((string) \get_option(self::STATE_KEY_OPTION, ''));

        if ($result->status === StateKeyInstallResult::STATUS_ALREADY_CONFIGURED) {
            self::clearStateKeyFallbackOptions();
            return;
        }

        if ($result->writtenToWpConfig()) {
            self::clearStateKeyFallbackOptions();
            return;
        }

        \update_option(self::STATE_KEY_OPTION, $result->key, false);
        \update_option(self::STATE_KEY_MANUAL_PENDING_OPTION, '1', false);
    }

    public static function stateKey(): string
    {
        $fromConstant = self::readConstant(self::STATE_KEY_CONSTANT);
        if ($fromConstant !== '') {
            return $fromConstant;
        }

        self::ensureStateKey();

        return (string) \get_option(self::STATE_KEY_OPTION, '');
    }

    public static function stateKeyNeedsManualInstall(): bool
    {
        return !self::isStateKeyFromWpConfig()
            && (string) \get_option(self::STATE_KEY_MANUAL_PENDING_OPTION, '') === '1'
            && self::stateKey() !== '';
    }

    public static function stateKeyWpConfigSnippet(): ?string
    {
        if (!self::stateKeyNeedsManualInstall()) {
            return null;
        }

        return (new WpConfigStateKeyInstaller())->wpConfigSnippet(self::stateKey());
    }

    public static function wpConfigPath(): ?string
    {
        return (new WpConfigStateKeyInstaller())->locateWpConfigPath();
    }

    private static function clearStateKeyFallbackOptions(): void
    {
        \delete_option(self::STATE_KEY_OPTION);
        \delete_option(self::STATE_KEY_MANUAL_PENDING_OPTION);
    }

    public static function isStateKeyConfigured(): bool
    {
        return self::stateKey() !== '';
    }

    public static function isStateKeyFromWpConfig(): bool
    {
        return self::readConstant(self::STATE_KEY_CONSTANT) !== '';
    }

    /**
     * @return list<string>
     */
    public static function loginButtonOrder(): array
    {
        $raw = (string) \get_option(self::LOGIN_BUTTON_ORDER_OPTION, '');
        if ($raw === '') {
            return self::DEFAULT_LOGIN_BUTTON_ORDER;
        }

        /** @var list<string> $slugs */
        $slugs = \preg_split('/[\s,]+/', \strtolower($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $order = [];
        foreach ($slugs as $slug) {
            if (\preg_match('/^[a-z0-9_-]+$/', $slug) === 1) {
                $order[] = $slug;
            }
        }

        return $order !== [] ? $order : self::DEFAULT_LOGIN_BUTTON_ORDER;
    }

    /**
     * @param list<string> $slugs
     */
    public static function setLoginButtonOrder(array $slugs): void
    {
        $clean = [];
        foreach ($slugs as $slug) {
            $normalized = \strtolower((string) $slug);
            if (\preg_match('/^[a-z0-9_-]+$/', $normalized) === 1) {
                $clean[] = $normalized;
            }
        }

        \update_option(self::LOGIN_BUTTON_ORDER_OPTION, \implode(',', $clean), false);
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

    public static function providerClientIdOptionKey(string $slug): string
    {
        return 'woc_oauth_' . $slug . '_client_id';
    }

    public static function providerClientSecretOptionKey(string $slug): string
    {
        return 'woc_oauth_' . $slug . '_client_secret';
    }

    public static function providerClientId(string $slug): string
    {
        $fromConfig = self::readConstant(self::providerClientIdConstant($slug));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return (string) \get_option(self::providerClientIdOptionKey($slug), '');
    }

    public static function providerClientSecret(string $slug): string
    {
        $fromConfig = self::readConstant(self::providerClientSecretConstant($slug));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return (string) \get_option(self::providerClientSecretOptionKey($slug), '');
    }

    public static function providerCredentialsFromWpConfig(string $slug): bool
    {
        return self::readConstant(self::providerClientIdConstant($slug)) !== ''
            && self::readConstant(self::providerClientSecretConstant($slug)) !== '';
    }

    public static function hasProviderCredentials(string $slug): bool
    {
        return self::providerClientId($slug) !== ''
            && self::providerClientSecret($slug) !== '';
    }

    public static function maskSecret(string $secret): string
    {
        if ($secret === '') {
            return '';
        }

        $length = \strlen($secret);
        if ($length <= 7) {
            return '***';
        }

        return \substr($secret, 0, 4) . '…' . \substr($secret, -3);
    }

    /**
     * Provider is routable when creds, state key, and enable flag are all set.
     */
    public static function isProviderOperational(string $slug): bool
    {
        self::ensureStateKey();

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
     * @return 'missing_creds'|'disabled'|'ready'
     */
    public static function providerAdminStatus(string $slug): string
    {
        self::ensureStateKey();

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