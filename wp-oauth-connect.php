<?php
declare(strict_types=1);

/**
 * Plugin Name:       WP OAuth Connect
 * Plugin URI:        https://remotejobs.team
 * Description:       Generic OAuth transport + identity-linking layer — providers, routes, CSRF state, account linking, hook API.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.3
 * Author:            RemoteJobs.team
 * Author URI:        https://remotejobs.team
 * License:           Proprietary
 * Text Domain:       wp-oauth-connect
 * Domain Path:       /languages
 *
 * Bootstrap only: autoload, lifecycle hooks, plugins_loaded boot.
 * Domain logic lives in src/ — see docs/oauth-plugin-plan.md.
 */

if (!defined('ABSPATH')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>WP OAuth Connect:</strong> '
            . '<code>vendor/autoload.php</code> is missing. '
            . 'Run <code>composer install</code> in <code>wp-content/plugins/wp-oauth-connect/</code>.'
            . '</p></div>';
    });
    return;
}

require_once $autoload;

register_activation_hook(__FILE__,   [\WpOAuthConnect\Lifecycle\Activator::class,   'run']);
register_deactivation_hook(__FILE__, [\WpOAuthConnect\Lifecycle\Deactivator::class, 'run']);

add_action('plugins_loaded', static function (): void {
    \WpOAuthConnect\Plugin::boot(__FILE__);
});