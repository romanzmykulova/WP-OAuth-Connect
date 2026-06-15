<?php
declare(strict_types=1);

/**
 * Renders and processes the generic BIND-PROMPT page after an email collision.
 */

namespace WpOAuthConnect\BindPrompt;

use WpOAuthConnect\AccountLinker;
use WpOAuthConnect\OAuthState;

final class BindPromptHandler
{
    public function __construct(
        private readonly BindPromptStore $store,
        private readonly AccountLinker $linker,
        private readonly OAuthState $state,
        private readonly string $pluginDir,
    ) {}

    public function handleGet(string $token): void
    {
        $pending = $this->store->peek($token);
        if ($pending === null) {
            $this->renderExpired();
            return;
        }

        $user = \get_user_by('id', $pending['user_id']);
        if (!$user instanceof \WP_User) {
            $this->renderExpired();
            return;
        }

        $message = \apply_filters(
            'woc_oauth_bind_prompt_message',
            \__(
                'An account with this email already exists. Sign in with your password to link your OAuth identity.',
                'wp-oauth-connect',
            ),
            $pending['profile'],
            $user,
        );

        $this->renderTemplate([
            'token'   => $token,
            'message' => (string) $message,
            'email'   => $user->user_email,
            'profile' => $pending['profile'],
        ]);
    }

    public function handlePost(string $token): void
    {
        $pending = $this->store->consume($token);
        if ($pending === null) {
            $this->renderExpired();
            return;
        }

        $user = \get_user_by('id', $pending['user_id']);
        if (!$user instanceof \WP_User) {
            $this->renderExpired();
            return;
        }

        $password = isset($_POST['woc_bind_password']) ? (string) wp_unslash($_POST['woc_bind_password']) : '';
        if ($password === '') {
            $this->renderTemplate([
                'token'   => $token,
                'message' => \__('Password is required.', 'wp-oauth-connect'),
                'email'   => $user->user_email,
                'profile' => $pending['profile'],
                'error'   => true,
            ]);
            return;
        }

        $authenticated = \wp_authenticate($user->user_login, $password);
        if ($authenticated instanceof \WP_Error || (int) $authenticated->ID !== (int) $user->ID) {
            $this->renderTemplate([
                'token'   => $token,
                'message' => \__(
                    'An account with this email already exists. Sign in with your password to link your OAuth identity.',
                    'wp-oauth-connect',
                ),
                'email'   => $user->user_email,
                'profile' => $pending['profile'],
                'error'   => true,
            ]);
            return;
        }

        $this->linker->bind($user, $pending['profile']);
        \do_action('woc_oauth_identity_bound', $user, $pending['profile']);
        $this->authenticate($user);
        $this->state->clearCsrfCookie();

        $redirectUrl = \apply_filters(
            'woc_oauth_redirect_url',
            \home_url('/'),
            $user,
            $pending['profile'],
            $pending['state_payload'],
            'bind',
        );

        \wp_safe_redirect($redirectUrl);
        exit;
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function renderTemplate(array $vars): void
    {
        // Companion plugins (e.g. remotejobs-core) may swap in a branded,
        // theme-aware template via this filter; default stays the minimal
        // self-contained page so the plugin works standalone.
        $template = (string) \apply_filters(
            'woc_oauth_bind_template',
            $this->pluginDir . '/templates/bind-prompt.php',
            $vars,
        );
        if (!is_readable($template)) {
            \wp_die(
                \esc_html__('Bind prompt template is missing.', 'wp-oauth-connect'),
                \esc_html__('OAuth unavailable', 'wp-oauth-connect'),
                ['response' => 500],
            );
        }

        extract($vars, EXTR_SKIP);
        require $template;
        exit;
    }

    private function renderExpired(): void
    {
        \wp_die(
            \esc_html__(
                'This link has expired. Start OAuth sign-in again from the login page.',
                'wp-oauth-connect',
            ),
            \esc_html__('Link expired', 'wp-oauth-connect'),
            ['response' => 410],
        );
    }

    private function authenticate(\WP_User $user): void
    {
        \wp_set_auth_cookie($user->ID, true, \is_ssl());
        \wp_set_current_user($user->ID);
    }
}