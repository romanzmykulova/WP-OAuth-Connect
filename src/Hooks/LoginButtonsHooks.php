<?php
declare(strict_types=1);

/**
 * Default woc_oauth_login_buttons filter — LinkedIn → Google → GitHub order.
 */

namespace WpOAuthConnect\Hooks;

use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Plugin;
use WpOAuthConnect\Provider\Provider;

final class LoginButtonsHooks
{
    /** @var list<string> */
    private const DEFAULT_ORDER = [
        'linkedin',
        'google',
        'github',
    ];

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
            return $buttons;
        }

        $registry = Plugin::registry();
        $seen     = [];

        foreach (self::DEFAULT_ORDER as $slug) {
            if (!$registry->has($slug)) {
                continue;
            }

            $buttons[] = $this->buildButton($registry->get($slug), $context);
            $seen[$slug] = true;
        }

        foreach ($registry->all() as $provider) {
            $slug = $provider->slug();
            if (isset($seen[$slug])) {
                continue;
            }

            $buttons[] = $this->buildButton($provider, $context);
        }

        return $buttons;
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
            'enabled'   => Settings::isProviderOperational($slug),
            'icon_html' => '',
        ];

        /** @var array<string, mixed> $filtered */
        $filtered = \apply_filters('woc_oauth_provider_button', $button, $provider);
        return $filtered;
    }
}