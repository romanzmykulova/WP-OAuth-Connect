<?php
declare(strict_types=1);

/**
 * Bootstrap entry point. Wires the hook layer once WordPress has loaded
 * all plugins. Domain classes never call add_action themselves.
 */

namespace WpOAuthConnect;

use WpOAuthConnect\Hooks\AdminHooks;
use WpOAuthConnect\Hooks\RoutingHooks;
use WpOAuthConnect\Migrations\Migrator;
use WpOAuthConnect\Provider\ProviderRegistry;

final class Plugin
{
    private static ?ProviderRegistry $registry = null;

    public static function boot(string $pluginFile): void
    {
        self::$registry = ProviderRegistry::fromPluginDir(\plugin_dir_path($pluginFile));

        (new AdminHooks())->register();
        (new RoutingHooks($pluginFile))->register();

        \add_action('init', [Migrator::class, 'run'], 5);
    }

    public static function registry(): ProviderRegistry
    {
        if (self::$registry === null) {
            throw new \RuntimeException('ProviderRegistry is not bootstrapped.');
        }
        return self::$registry;
    }
}