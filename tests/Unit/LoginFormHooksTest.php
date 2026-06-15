<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Hooks\LoginButtonsHooks;
use WpOAuthConnect\Hooks\LoginFormHooks;
use WpOAuthConnect\Plugin;
use WpOAuthConnect\Provider\ProviderRegistry;
use WpOAuthConnect\Tests\Unit\Support\FakeHttpClient;
use WpOAuthConnect\Tests\Unit\Support\FakeProviderRegistry;

final class LoginFormHooksTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['woc_test_filters'] = [];
        $GLOBALS['woc_test_options'] = [];
        $_REQUEST = [];
        if (!defined('OAUTH_STATE_KEY')) {
            define('OAUTH_STATE_KEY', 'test-state-key-32-bytes-minimum!!');
        }
    }

    public function test_renders_operational_provider_buttons_on_native_login_form(): void
    {
        $this->bootButtonsWith(['linkedin', 'github']);

        $html = $this->render();

        $this->assertStringContainsString('woc-oauth-login', $html);
        $this->assertStringContainsString('oauth-btn--linkedin', $html);
        $this->assertStringContainsString('oauth-btn--github', $html);
        $this->assertStringContainsString('/oauth/linkedin/start', $html);
    }

    public function test_renders_nothing_when_no_provider_is_operational(): void
    {
        $this->bootButtonsWith([]);

        $this->assertSame('', $this->render());
    }

    public function test_companion_can_opt_out_via_filter(): void
    {
        $this->bootButtonsWith(['github']);
        add_filter('woc_oauth_render_login_form', static fn (): bool => false);

        $this->assertSame('', $this->render());
    }

    public function test_carries_redirect_to_into_start_url_as_next(): void
    {
        $this->bootButtonsWith(['github']);
        $_REQUEST['redirect_to'] = 'https://example.test/wp-admin/';

        $html = $this->render();

        $this->assertStringContainsString('next=', $html);
    }

    /**
     * @param list<string> $slugs
     */
    private function bootButtonsWith(array $slugs): void
    {
        foreach ($slugs as $slug) {
            FakeProviderRegistry::enable($slug);
        }

        $registry = ProviderRegistry::fromPluginDir(dirname(__DIR__, 2), new FakeHttpClient());
        $ref = new \ReflectionClass(Plugin::class);
        $prop = $ref->getProperty('registry');
        $prop->setAccessible(true);
        $prop->setValue(null, $registry);

        (new LoginButtonsHooks())->register();
    }

    private function render(): string
    {
        ob_start();
        (new LoginFormHooks(dirname(__DIR__, 2) . '/wp-oauth-connect.php'))->renderButtons();
        return (string) ob_get_clean();
    }
}
