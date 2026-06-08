<?php
declare(strict_types=1);

/**
 * OAuth start + callback orchestration — state, code exchange, decision tree, hooks.
 */

namespace WpOAuthConnect;

use WpOAuthConnect\BindPrompt\BindPromptHandler;
use WpOAuthConnect\BindPrompt\BindPromptStore;
use WpOAuthConnect\Exceptions\OAuthStateExpired;
use WpOAuthConnect\Exceptions\OAuthStateInvalid;
use WpOAuthConnect\Provider\Provider;
use WpOAuthConnect\Provider\ProviderRegistry;

final class OAuthService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly OAuthState $state,
        private readonly AccountLinker $linker,
        private readonly BindPromptStore $bindStore,
        private readonly BindPromptHandler $bindHandler,
    ) {}

    /**
     * @param array<string, mixed> $context Passed to woc_oauth_state_payload.
     */
    public function start(string $providerSlug, array $context = []): never
    {
        $provider = $this->registry->get($providerSlug);
        $extensions = $this->buildStateExtensions($context);

        $created = $this->state->create($providerSlug, $extensions, $context);
        $this->state->setCsrfCookie($created['csrf']);

        $redirectUri = $this->callbackUrl($providerSlug);
        $authorizeUrl = $provider->authorizeUrl($created['state'], $redirectUri);

        \wp_safe_redirect($authorizeUrl);
        exit;
    }

    public function callback(string $providerSlug): never
    {
        $provider = $this->registry->get($providerSlug);
        $state    = isset($_GET['state']) ? (string) wp_unslash($_GET['state']) : '';
        $code     = isset($_GET['code']) ? (string) wp_unslash($_GET['code']) : '';

        if ($state === '' || $code === '') {
            $this->reject('missing_parameters');
        }

        try {
            $payload = $this->state->verify($state, $providerSlug);
        } catch (OAuthStateExpired) {
            $this->reject('state_expired');
        } catch (OAuthStateInvalid) {
            $this->reject('invalid_state');
        }

        $redirectUri = $this->callbackUrl($providerSlug);
        $accessToken = $provider->exchangeCode($code, $redirectUri);
        $profile     = $provider->fetchProfile($accessToken);

        $existingByProvider = $this->linker->findByProviderId($profile);
        if ($existingByProvider !== null) {
            $this->completeLogin($existingByProvider, $profile, $payload);
        }

        if ($profile->email !== '') {
            $existingByEmail = $this->linker->findByEmail($profile->email);
            if ($existingByEmail !== null) {
                $this->startBindPrompt($existingByEmail, $profile, $payload);
            }
        }

        $allowed = (bool) \apply_filters('woc_oauth_allow_registration', false, $profile, $payload);
        if (!$allowed) {
            \do_action('woc_oauth_registration_rejected', $profile, $payload, 'not_allowed');
            $this->reject('registration_denied');
        }

        /** @var \WP_User|null $user */
        $user = \apply_filters('woc_oauth_create_user', null, $profile, $payload);
        if ($user === null) {
            $user = $this->linker->createMinimalUser($profile);
        }

        if (!$user instanceof \WP_User) {
            $this->reject('user_creation_failed');
        }

        $this->linker->bind($user, $profile);
        \do_action('woc_oauth_user_registered', $user, $profile, $payload);
        $this->completeLogin($user, $profile, $payload, 'signup');
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function buildStateExtensions(array $context): array
    {
        $extensions = [];

        if (isset($context['invite']) && is_string($context['invite']) && $context['invite'] !== '') {
            $extensions['invite_token'] = $context['invite'];
        }

        if (isset($_GET['invite'])) {
            $invite = \sanitize_text_field((string) wp_unslash($_GET['invite']));
            if ($invite !== '') {
                $extensions['invite_token'] = $invite;
            }
        }

        if (isset($context['next']) && is_string($context['next']) && $context['next'] !== '') {
            $extensions['next'] = $context['next'];
        }

        if (isset($_GET['next'])) {
            $next = \esc_url_raw((string) wp_unslash($_GET['next']));
            if ($next !== '' && $this->isSameHost($next)) {
                $extensions['next'] = $next;
            }
        }

        return $extensions;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function completeLogin(
        \WP_User $user,
        OAuthProfile $profile,
        array $payload,
        string $flow = 'login',
    ): never {
        $this->authenticate($user);
        \do_action('woc_oauth_authenticated', $user, $profile, $payload);
        $this->redirect($user, $profile, $payload, $flow);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function startBindPrompt(\WP_User $existingUser, OAuthProfile $profile, array $payload): never
    {
        $token = $this->bindStore->create($existingUser, $profile, $payload);
        $this->state->clearCsrfCookie();

        \wp_safe_redirect(\home_url('/oauth/bind?token=' . rawurlencode($token)));
        exit;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function redirect(\WP_User $user, OAuthProfile $profile, array $payload, string $flow): never
    {
        $next = isset($payload['next']) ? (string) $payload['next'] : '';
        $url  = $next !== '' && $this->isSameHost($next) ? $next : \home_url('/');

        $url = (string) \apply_filters('woc_oauth_redirect_url', $url, $user, $profile, $payload, $flow);
        $this->state->clearCsrfCookie();

        \wp_safe_redirect($url);
        exit;
    }

    private function authenticate(\WP_User $user): void
    {
        \wp_set_auth_cookie($user->ID, true, \is_ssl());
        \wp_set_current_user($user->ID);
    }

    private function callbackUrl(string $providerSlug): string
    {
        return \home_url('/oauth/' . $providerSlug . '/callback');
    }

    private function isSameHost(string $url): bool
    {
        $targetHost = (string) wp_parse_url($url, PHP_URL_HOST);
        $siteHost   = (string) wp_parse_url(\home_url('/'), PHP_URL_HOST);

        return $targetHost !== '' && $siteHost !== '' && strcasecmp($targetHost, $siteHost) === 0;
    }

    private function reject(string $reason): never
    {
        $message = match ($reason) {
            'missing_parameters'  => \__('OAuth sign-in could not be completed. Missing authorization data.', 'wp-oauth-connect'),
            'state_expired'       => \__('OAuth sign-in expired. Please try again.', 'wp-oauth-connect'),
            'invalid_state'       => \__('OAuth sign-in could not be verified. Please try again.', 'wp-oauth-connect'),
            'registration_denied' => \__(
                'This OAuth account is not linked to a member account and sign-up is not allowed.',
                'wp-oauth-connect',
            ),
            'user_creation_failed'=> \__('Account creation failed. Please contact support.', 'wp-oauth-connect'),
            default               => \__('OAuth sign-in failed. Please try again.', 'wp-oauth-connect'),
        };

        $message = (string) \apply_filters('woc_oauth_reject_message', $message, $reason);

        \wp_die(
            \esc_html($message),
            \esc_html__('OAuth sign-in failed', 'wp-oauth-connect'),
            ['response' => 403],
        );
    }

    public function bindHandler(): BindPromptHandler
    {
        return $this->bindHandler;
    }
}