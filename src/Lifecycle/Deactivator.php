<?php
declare(strict_types=1);

/**
 * Runs on plugin deactivation. Reversible only — no data destruction.
 */

namespace WpOAuthConnect\Lifecycle;

final class Deactivator
{
    public static function run(): void
    {
        \flush_rewrite_rules();
    }
}