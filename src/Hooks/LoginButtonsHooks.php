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
            'label'     => $provider->label(),
            'url'       => \oauth_start_url($slug, $context),
            'css_class' => 'oauth-btn oauth-btn--' . $slug,
            'enabled'   => true,
            'icon_html' => '',
        ];

        /** @var array<string, mixed> $filtered */
        $filtered = \apply_filters('woc_oauth_provider_button', $button, $provider);
        return $filtered;
    }
}