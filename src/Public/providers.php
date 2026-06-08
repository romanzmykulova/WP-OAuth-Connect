<?php
declare(strict_types=1);

/**
 * Public read-only helpers for companion plugins and themes.
 */

use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Plugin;

if (!function_exists('oauth_providers')) {
    /**
     * @return list<array{slug: string, label: string, enabled: bool}>
     */
    function oauth_providers(): array
    {
        $providers = [];
        foreach (Plugin::registry()->all() as $provider) {
            $slug = $provider->slug();
            $providers[] = [
                'slug'    => $slug,
                'label'   => $provider->label(),
                'enabled' => Settings::isProviderOperational($slug),
            ];
        }

        return $providers;
    }
}