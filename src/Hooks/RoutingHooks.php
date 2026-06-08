<?php
declare(strict_types=1);

/**
 * Registers provider-agnostic /oauth/{slug}/start|callback rewrite rules
 * and dispatches template_redirect to OAuthHooks.
 */

namespace WpOAuthConnect\Hooks;

final class RoutingHooks
{
    public function __construct(
        private readonly string $pluginFile,
        private readonly ?OAuthHooks $oauthHooks = null,
    ) {}

    public function register(): void
    {
        \add_action('init', [self::class, 'registerRewriteRules']);
        \add_filter('query_vars', [$this, 'registerQueryVars']);
        \add_action('template_redirect', [$this, 'dispatchOAuthRoute'], 5);
    }

    public static function registerRewriteRules(): void
    {
        \add_rewrite_rule(
            '^oauth/([a-z0-9_-]+)/start/?$',
            'index.php?woc_oauth_provider=$matches[1]&woc_oauth_action=start',
            'top',
        );
        \add_rewrite_rule(
            '^oauth/([a-z0-9_-]+)/callback/?$',
            'index.php?woc_oauth_provider=$matches[1]&woc_oauth_action=callback',
            'top',
        );
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'woc_oauth_provider';
        $vars[] = 'woc_oauth_action';
        return $vars;
    }

    public function dispatchOAuthRoute(): void
    {
        $provider = (string) \get_query_var('woc_oauth_provider');
        $action   = (string) \get_query_var('woc_oauth_action');

        if ($provider === '' || $action === '') {
            return;
        }

        if (!preg_match('/^[a-z0-9_-]{2,32}$/', $provider)) {
            $this->sendNotFound();
            return;
        }

        if ($action !== 'start' && $action !== 'callback') {
            $this->sendNotFound();
            return;
        }

        $handler = $this->oauthHooks ?? new OAuthHooks($this->pluginFile);
        if ($action === 'start') {
            $handler->handleStart($provider);
            return;
        }

        $handler->handleCallback($provider);
    }

    private function sendNotFound(): void
    {
        global $wp_query;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }
        \status_header(404);
        \nocache_headers();
    }
}