<?php
declare(strict_types=1);

/**
 * WordPress invokes this file when the plugin is deleted from the admin
 * Plugins screen. Delegates to Lifecycle\Uninstaller.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    \WpOAuthConnect\Lifecycle\Uninstaller::run();
}