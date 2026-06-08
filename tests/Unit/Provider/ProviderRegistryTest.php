<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Provider\OidcProvider;
use WpOAuthConnect\Provider\ProviderRegistry;
use WpOAuthConnect\Tests\Unit\Support\FakeHttpClient;

final class ProviderRegistryTest extends TestCase
{
    private string $pluginDir;

    protected function setUp(): void
    {
        $this->pluginDir = dirname(__DIR__, 3);
        $GLOBALS['woc_test_options'] = [];
        $GLOBALS['woc_test_filters'] = [];
        $GLOBALS['woc_test_actions'] = [];
        remove_all_filters('woc_oauth_provider_definitions');
        remove_all_filters('woc_oauth_providers');
        remove_all_filters('woc_oauth_provider_credentials');
        remove_all_filters('woc_oauth_provider_enabled');
    }

    public function test_loads_bundled_presets(): void
    {
        $registry = ProviderRegistry::fromPluginDir($this->pluginDir, new FakeHttpClient());
        $slugs    = array_map(static fn ($provider) => $provider->slug(), $registry->all());

        $this->assertContains('linkedin', $slugs);
        $this->assertContains('google', $slugs);
        $this->assertContains('github', $slugs);
        $this->assertContains('microsoft', $slugs);
    }

    public function test_custom_oidc_definition_via_filter_without_new_class(): void
    {
        add_filter(
            'woc_oauth_provider_definitions',
            static function (array $definitions): array {
                $definitions[] = [
                    'slug'   => 'okta-acme',
                    'label'  => 'Sign in with Acme SSO',
                    'engine' => 'oidc',
                    'issuer' => 'https://acme.okta.com/oauth2/default',
                    'scopes' => ['openid', 'email', 'profile'],
                ];
                return $definitions;
            },
        );

        $registry = ProviderRegistry::fromPluginDir($this->pluginDir, new FakeHttpClient());
        $this->assertTrue($registry->has('okta-acme'));
        $this->assertInstanceOf(OidcProvider::class, $registry->get('okta-acme'));
    }

    public function test_duplicate_slug_in_filter_replacement_is_rejected(): void
    {
        add_filter(
            'woc_oauth_provider_definitions',
            static function (array $definitions): array {
                $definitions[] = $definitions[0];
                return $definitions;
            },
        );

        $this->expectException(\InvalidArgumentException::class);
        ProviderRegistry::fromPluginDir($this->pluginDir, new FakeHttpClient());
    }

    public function test_enabled_requires_credentials_state_key_and_option(): void
    {
        if (!defined('OAUTH_STATE_KEY')) {
            define('OAUTH_STATE_KEY', 'registry-test-key');
        }
        if (!defined('OAUTH_GITHUB_CLIENT_ID')) {
            define('OAUTH_GITHUB_CLIENT_ID', 'gh-id');
        }
        if (!defined('OAUTH_GITHUB_CLIENT_SECRET')) {
            define('OAUTH_GITHUB_CLIENT_SECRET', 'gh-secret');
        }

        $registry = ProviderRegistry::fromPluginDir($this->pluginDir, new FakeHttpClient());

        $this->assertFalse($registry->enabled('github'));

        update_option(Settings::providerEnabledOptionKey('github'), '1');
        $this->assertTrue($registry->enabled('github'));
    }

    public function test_woc_oauth_init_allows_class_registration(): void
    {
        $captured = false;
        add_action('woc_oauth_init', static function (ProviderRegistry $registry) use (&$captured): void {
            $captured = $registry->has('github');
        });

        ProviderRegistry::fromPluginDir($this->pluginDir, new FakeHttpClient());
        $this->assertTrue($captured);
    }
}