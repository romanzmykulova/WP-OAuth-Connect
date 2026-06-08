<?php
declare(strict_types=1);

/**
 * Thin adapter for /oauth/{provider}/start, /callback, and /oauth/bind routes.
 */

namespace WpOAuthConnect\Hooks;

use WpOAuthConnect\OAuthService;
use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Plugin;

final class OAuthHooks
{
    public function __construct(
        private readonly OAuthService $oauthService,
    ) {}

    public function handleStart(string $provider): void
    {
        if (!Settings::isProviderOperational($provider) || !Plugin::registry()->has($provider)) {
            $this->sendUnavailable($provider);
            return;
        }

        $context = ['surface' => 'oauth_start'];
        $this->oauthService->start($provider, $context);
    }

    public function handleCallback(string $provider): void
    {
        if (!Settings::isProviderOperational($provider) || !Plugin::registry()->has($provider)) {
            $this->sendUnavailable($provider);
            return;
        }

        $this->oauthService->callback($provider);
    }

    public function handleBind(): void
    {
        $token = isset($_GET['token']) ? \sanitize_text_field((string) wp_unslash($_GET['token'])) : '';
        if ($token === '') {
            \wp_die(
                \esc_html__('Missing bind token.', 'wp-oauth-connect'),
                \esc_html__('OAuth unavailable', 'wp-oauth-connect'),
                ['response' => 400],
            );
        }

        $handler = $this->oauthService->bindHandler();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $handler->handlePost($token);
            return;
        }

        $handler->handleGet($token);
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