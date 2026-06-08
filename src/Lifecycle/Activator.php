<?php
declare(strict_types=1);

/**
 * Runs on plugin activation. Validates permalinks, applies migrations,
 * registers rewrite rules, and flushes.
 */

namespace WpOAuthConnect\Lifecycle;

use WpOAuthConnect\Hooks\RoutingHooks;
use WpOAuthConnect\Migrations\Migrator;

final class Activator
{
    public static function run(): void
    {
        if ((string) \get_option('permalink_structure') === '') {
            throw new \RuntimeException(
                'wp-oauth-connect requires pretty permalinks; visit Settings → Permalinks first.'
            );
        }

        Migrator::run();
        RoutingHooks::registerRewriteRules();
        \flush_rewrite_rules();
    }
}