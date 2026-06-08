<?php
declare(strict_types=1);

/**
 * Bootstrap entry point. Wires the hook layer once WordPress has loaded
 * all plugins. Domain classes never call add_action themselves.
 */

namespace WpOAuthConnect;

use WpOAuthConnect\Migrations\Migrator;

final class Plugin
{
    public static function boot(string $pluginFile): void
    {
        \add_action('init', [Migrator::class, 'run'], 5);
    }
}