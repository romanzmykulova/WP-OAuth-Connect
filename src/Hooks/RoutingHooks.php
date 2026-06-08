<?php
declare(strict_types=1);

/**
 * Registers provider-agnostic OAuth rewrite rules and dispatches template_redirect.
 */

namespace WpOAuthConnect\Hooks;

final class RoutingHooks
{
    public function __construct(
        private readonly OAuthHooks $oauthHooks,
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
        \add_rewrite_rule(
            '^oauth/bind/?$',
            'index.php?woc_oauth_action=bind',
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

        if ($action === '') {
            return;
        }

        if ($action === 'bind') {
            $this->oauthHooks->handleBind();
            return;
        }

        if ($provider === '') {
            return;
        }

        if (!preg_match('/^[a-z0-9_-]{2,32}$/', $provider)) {
            $this->sendNotFound();
            return;
        }

        if ($action === 'start') {
            $this->oauthHooks->handleStart($provider);
            return;
        }

        if ($action === 'callback') {
            $this->oauthHooks->handleCallback($provider);
            return;
        }

        $this->sendNotFound();
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