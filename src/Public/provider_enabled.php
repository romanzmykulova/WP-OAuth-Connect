<?php
declare(strict_types=1);

/**
 * Single-provider operational check for login/join template rendering.
 */

use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Plugin;

if (!function_exists('oauth_provider_enabled')) {
    function oauth_provider_enabled(string $provider): bool
    {
        return Plugin::registry()->has($provider) && Settings::isProviderOperational($provider);
    }
}