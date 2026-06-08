<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Hooks\LoginButtonsHooks;
use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Plugin;
use WpOAuthConnect\Provider\ProviderRegistry;
use WpOAuthConnect\Tests\Unit\Support\FakeHttpClient;
use WpOAuthConnect\Tests\Unit\Support\FakeProviderRegistry;

final class LoginButtonsHooksTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['woc_test_filters'] = [];
        $GLOBALS['woc_test_options'] = [];
        if (!defined('OAUTH_STATE_KEY')) {
            define('OAUTH_STATE_KEY', 'test-state-key-32-bytes-minimum!!');
        }
    }

    public function test_default_buttons_follow_linkedin_google_github_order(): void
    {
        foreach (['linkedin', 'google', 'github'] as $slug) {
            FakeProviderRegistry::enable($slug);
        }

        $registry = ProviderRegistry::fromPluginDir(dirname(__DIR__, 2), new FakeHttpClient());
        $this->setPluginRegistry($registry);

        (new LoginButtonsHooks())->register();
        $buttons = apply_filters('woc_oauth_login_buttons', [], ['surface' => 'login']);

        $this->assertCount(3, $buttons);
        $this->assertSame('linkedin', $buttons[0]['provider']);
        $this->assertSame('google', $buttons[1]['provider']);
        $this->assertSame('github', $buttons[2]['provider']);
        $this->assertTrue($buttons[0]['enabled']);
        $this->assertStringContainsString('/oauth/linkedin/start', $buttons[0]['url']);
    }

    public function test_omits_providers_without_credentials_or_enable_flag(): void
    {
        FakeProviderRegistry::enable('github');

        $registry = ProviderRegistry::fromPluginDir(dirname(__DIR__, 2), new FakeHttpClient());
        $this->setPluginRegistry($registry);

        (new LoginButtonsHooks())->register();
        $buttons = apply_filters('woc_oauth_login_buttons', [], ['surface' => 'login']);

        $this->assertCount(1, $buttons);
        $this->assertSame('github', $buttons[0]['provider']);
    }

    public function test_respects_admin_configured_button_order(): void
    {
        foreach (['linkedin', 'google', 'github'] as $slug) {
            FakeProviderRegistry::enable($slug);
        }
        update_option(Settings::LOGIN_BUTTON_ORDER_OPTION, 'github,google,linkedin');

        $registry = ProviderRegistry::fromPluginDir(dirname(__DIR__, 2), new FakeHttpClient());
        $this->setPluginRegistry($registry);

        (new LoginButtonsHooks())->register();
        $buttons = apply_filters('woc_oauth_login_buttons', [], ['surface' => 'login']);

        $this->assertSame(['github', 'google', 'linkedin'], array_column($buttons, 'provider'));
    }

    private function setPluginRegistry(ProviderRegistry $registry): void
    {
        $ref = new \ReflectionClass(Plugin::class);
        $prop = $ref->getProperty('registry');
        $prop->setAccessible(true);
        $prop->setValue(null, $registry);
    }
}