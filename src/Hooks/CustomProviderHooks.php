<?php
declare(strict_types=1);

/**
 * Registers the admin-configured custom OIDC provider and button styling.
 */

namespace WpOAuthConnect\Hooks;

use WpOAuthConnect\Options\CustomProviderSettings;
use WpOAuthConnect\Provider\Provider;

final class CustomProviderHooks
{
    public function register(): void
    {
        \add_filter('woc_oauth_provider_definitions', [$this, 'registerDefinition'], 10, 1);
        \add_filter('woc_oauth_provider_button', [$this, 'styleButton'], 10, 2);
    }

    /**
     * @param list<array<string, mixed>> $definitions
     *
     * @return list<array<string, mixed>>
     */
    public function registerDefinition(array $definitions): array
    {
        if (!CustomProviderSettings::isDefined()) {
            return $definitions;
        }

        $definitions[] = CustomProviderSettings::definitionArray();

        return $definitions;
    }

    /**
     * @param array<string, mixed> $button
     *
     * @return array<string, mixed>
     */
    public function styleButton(array $button, Provider $provider): array
    {
        if ($provider->slug() !== CustomProviderSettings::SLUG) {
            return $button;
        }

        $label = CustomProviderSettings::label();
        if ($label !== '') {
            $button['label'] = $label;
        }

        $iconHtml = CustomProviderSettings::buttonIconHtml();
        if ($iconHtml !== '') {
            $button['icon_html'] = $iconHtml;
        }

        return $button;
    }
}