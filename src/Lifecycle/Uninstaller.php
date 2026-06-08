<?php
declare(strict_types=1);

/**
 * Runs when the plugin is deleted. Removes plugin options unless a
 * companion filter requests full usermeta purge.
 */

namespace WpOAuthConnect\Lifecycle;

use WpOAuthConnect\Options\Settings;

final class Uninstaller
{
    public static function run(): void
    {
        \delete_option(Settings::SCHEMA_VERSION_OPTION);
        \delete_option(Settings::NATIVE_LOGIN_ENABLED_OPTION);

        foreach (Settings::builtinProviderSlugs() as $slug) {
            \delete_option(Settings::providerEnabledOptionKey($slug));
        }
    }
}