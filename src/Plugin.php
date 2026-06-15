<?php
declare(strict_types=1);

/**
 * Bootstrap entry point. Wires the hook layer once WordPress has loaded
 * all plugins. Domain classes never call add_action themselves.
 */

namespace WpOAuthConnect;

use WpOAuthConnect\BindPrompt\BindPromptHandler;
use WpOAuthConnect\BindPrompt\BindPromptStore;
use WpOAuthConnect\Hooks\AdminHooks;
use WpOAuthConnect\Hooks\CustomProviderHooks;
use WpOAuthConnect\Hooks\LoginButtonsHooks;
use WpOAuthConnect\Hooks\LoginFormHooks;
use WpOAuthConnect\Hooks\NativeLoginHooks;
use WpOAuthConnect\Hooks\OAuthHooks;
use WpOAuthConnect\Hooks\RoutingHooks;
use WpOAuthConnect\Migrations\Migrator;
use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Provider\ProviderRegistry;

final class Plugin
{
    private static ?ProviderRegistry $registry = null;

    public static function boot(string $pluginFile): void
    {
        Settings::ensureStateKey();

        self::$registry = ProviderRegistry::fromPluginDir(\plugin_dir_path($pluginFile));

        $pluginDir = \plugin_dir_path($pluginFile);
        $state     = new OAuthState();
        $linker    = new AccountLinker();
        $bindStore = new BindPromptStore();
        $bindHandler = new BindPromptHandler($bindStore, $linker, $state, $pluginDir);
        $oauthService = new OAuthService(self::$registry, $state, $linker, $bindStore, $bindHandler);
        $oauthHooks   = new OAuthHooks($oauthService);

        (new AdminHooks())->register();
        (new CustomProviderHooks())->register();
        (new RoutingHooks($oauthHooks))->register();
        (new LoginButtonsHooks())->register();
        (new LoginFormHooks($pluginFile))->register();
        (new NativeLoginHooks())->register();

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