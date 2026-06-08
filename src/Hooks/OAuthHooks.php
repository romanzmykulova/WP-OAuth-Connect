<?php
declare(strict_types=1);

/**
 * Thin adapter for /oauth/{provider}/start and /callback routes.
 * Full OAuthService orchestration lands in OAUTH-P.3.
 */

namespace WpOAuthConnect\Hooks;

use WpOAuthConnect\Options\Settings;

final class OAuthHooks
{
    public function __construct(
        private readonly string $pluginFile,
    ) {}

    public function handleStart(string $provider): void
    {
        if (!Settings::isProviderOperational($provider)) {
            $this->sendUnavailable($provider);
            return;
        }

        // OAUTH-P.3: OAuthService::start() — authorize redirect + CSRF cookie.
        \wp_die(
            \esc_html__(
                'OAuth start handler is not wired yet. Enable this provider after OAUTH-P.3 ships.',
                'wp-oauth-connect',
            ),
            \esc_html__('OAuth unavailable', 'wp-oauth-connect'),
            ['response' => 503],
        );
    }

    public function handleCallback(string $provider): void
    {
        if (!Settings::isProviderOperational($provider)) {
            $this->sendUnavailable($provider);
            return;
        }

        // OAUTH-P.3: OAuthService::callback() — verify state, exchange code, hooks.
        \wp_die(
            \esc_html__(
                'OAuth callback handler is not wired yet. Enable this provider after OAUTH-P.3 ships.',
                'wp-oauth-connect',
            ),
            \esc_html__('OAuth unavailable', 'wp-oauth-connect'),
            ['response' => 503],
        );
    }

    private function sendUnavailable(string $provider): void
    {
        \wp_die(
            \sprintf(
                /* translators: %s: provider slug */
                \esc_html__('OAuth provider "%s" is not enabled or configured.', 'wp-oauth-connect'),
                \esc_html($provider),
            ),
            \esc_html__('OAuth unavailable', 'wp-oauth-connect'),
            ['response' => 404],
        );
    }
}