<?php
declare(strict_types=1);

/**
 * Enforces native-login toggle on plugin-owned member auth endpoints.
 */

namespace WpOAuthConnect\Hooks;

use WpOAuthConnect\Options\Settings;

final class NativeLoginHooks
{
    public function register(): void
    {
        \add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        \register_rest_route(
            'wp-oauth-connect/v1',
            '/native-login',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleNativeLogin'],
                'permission_callback' => [$this, 'nativeLoginPermission'],
            ],
        );
    }

    public function nativeLoginPermission(): bool
    {
        return Settings::isNativeLoginEnabled();
    }

    public function handleNativeLogin(): \WP_REST_Response|\WP_Error
    {
        return new \WP_Error(
            'woc_native_login_not_implemented',
            \__(
                'Native login is handled by the companion application plugin.',
                'wp-oauth-connect',
            ),
            ['status' => 501],
        );
    }
}