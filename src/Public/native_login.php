<?php
declare(strict_types=1);

/**
 * Native login toggle — companions hide password/magic-link UI when false.
 */

use WpOAuthConnect\Options\Settings;

if (!function_exists('oauth_native_login_enabled')) {
    function oauth_native_login_enabled(): bool
    {
        return Settings::isNativeLoginEnabled();
    }
}