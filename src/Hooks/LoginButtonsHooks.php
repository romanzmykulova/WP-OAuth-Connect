<?php
declare(strict_types=1);

/**
 * Default woc_oauth_login_buttons filter — only operational providers,
 * order from Settings → OAuth Connect.
 */

namespace WpOAuthConnect\Hooks;

use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Plugin;
use WpOAuthConnect\Provider\Provider;
use WpOAuthConnect\Provider\ProviderRegistry;
use WpOAuthConnect\Support\ProviderIcons;

final class LoginButtonsHooks
{
    public function register(): void
    {
        \add_filter('woc_oauth_login_buttons', [$this, 'defaultButtons'], 10, 2);
    }

    /**
     * @param list<array<string, mixed>> $buttons
     * @param array<string, mixed> $context
     *
     * @return list<array<string, mixed>>
     */
    public function defaultButtons(array $buttons, array $context): array
    {
        if ($buttons !== []) {
            return $this->filterOperationalOnly($buttons);
        }

        $registry = Plugin::registry();
        $seen     = [];

        foreach ($this->orderedSlugs($registry) as $slug) {
            if (!$registry->has($slug) || !Settings::isProviderOperational($slug)) {
                continue;
            }

            $buttons[] = $this->buildButton($registry->get($slug), $context);
            $seen[$slug] = true;
        }

        foreach ($registry->all() as $provider) {
            $slug = $provider->slug();
            if (isset($seen[$slug]) || !Settings::isProviderOperational($slug)) {
                continue;
            }

            $buttons[] = $this->buildButton($provider, $context);
        }

        return $buttons;
    }

    /**
     * @param list<array<string, mixed>> $buttons
     *
     * @return list<array<string, mixed>>
     */
    private function filterOperationalOnly(array $buttons): array
    {
        $registry = Plugin::registry();
        $filtered = [];

        foreach ($buttons as $button) {
            $slug = (string) ($button['provider'] ?? '');
            if ($slug === '' || !$registry->has($slug) || !Settings::isProviderOperational($slug)) {
                continue;
            }

            $button['enabled'] = true;
            $filtered[] = $button;
        }

        return $filtered;
    }

    /**
     * @return list<string>
     */
    private function orderedSlugs(ProviderRegistry $registry): array
    {
        $order = Settings::loginButtonOrder();
        $seen  = [];
        $slugs = [];

        foreach ($order as $slug) {
            if (isset($seen[$slug]) || !$registry->has($slug)) {
                continue;
            }
            $slugs[] = $slug;
            $seen[$slug] = true;
        }

        foreach ($registry->all() as $provider) {
            $slug = $provider->slug();
            if (!isset($seen[$slug])) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function buildButton(Provider $provider, array $context): array
    {
        $slug = $provider->slug();
        $button = [
            'provider'  => $slug,
            'label'     => self::translatedLabel($provider),
            'url'       => \oauth_start_url($slug, $context),
            'css_class' => 'oauth-btn oauth-btn--' . $slug,
            'enabled'   => true,
            'icon_html' => ProviderIcons::for($slug),
        ];

        /** @var array<string, mixed> $filtered */
        $filtered = \apply_filters('woc_oauth_provider_button', $button, $provider);
        return $filtered;
    }

    /**
     * Translatable button labels for the built-in providers. Literal __() calls
     * keep the strings extractable; non-built-in providers (e.g. the admin
     * custom slot) fall back to their definition label.
     */
    private static function translatedLabel(Provider $provider): string
    {
        return match ($provider->slug()) {
            'github'    => \__('Continue with GitHub', 'wp-oauth-connect'),
            'google'    => \__('Continue with Google', 'wp-oauth-connect'),
            'linkedin'  => \__('Continue with LinkedIn', 'wp-oauth-connect'),
            'microsoft' => \__('Continue with Microsoft', 'wp-oauth-connect'),
            default     => $provider->label(),
        };
    }
}