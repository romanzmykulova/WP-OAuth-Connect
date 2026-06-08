<?php
declare(strict_types=1);

/**
 * Runs when the plugin is deleted. Removes plugin options unless a
 * companion filter requests full usermeta purge.
 */

namespace WpOAuthConnect\Lifecycle;

final class Uninstaller
{
    public static function run(): void
    {
        // Option cleanup is wired once Settings ships.
    }
}