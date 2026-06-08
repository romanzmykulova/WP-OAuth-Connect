<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\AccountLinker;
use WpOAuthConnect\BindPrompt\BindPromptHandler;
use WpOAuthConnect\BindPrompt\BindPromptStore;
use WpOAuthConnect\OAuthProfile;
use WpOAuthConnect\OAuthService;
use WpOAuthConnect\OAuthState;
use WpOAuthConnect\Tests\Unit\Support\FakeProvider;
use WpOAuthConnect\Tests\Unit\Support\FakeProviderRegistry;
use WP_User;

final class OAuthServiceTest extends TestCase
{
    private string $pluginDir;

    protected function setUp(): void
    {
        $this->pluginDir = dirname(__DIR__, 2);
        $GLOBALS['woc_test_options'] = [];
        $GLOBALS['woc_test_filters'] = [];
        $GLOBALS['woc_test_actions'] = [];
        $GLOBALS['woc_test_users'] = [];
        $GLOBALS['woc_test_user_meta'] = [];
        $GLOBALS['woc_test_transients'] = [];
        $GLOBALS['woc_test_redirect_url'] = null;
        $GLOBALS['woc_test_auth_user_id'] = null;
        $_GET = [];
        $_COOKIE = [];
        remove_all_filters('woc_oauth_allow_registration');
        remove_all_filters('woc_oauth_create_user');
        remove_all_filters('woc_oauth_redirect_url');
        remove_all_filters('woc_oauth_state_payload');
    }

    public function test_start_redirects_to_provider_authorize_url(): void
    {
        FakeProviderRegistry::enable('github');
        $service = $this->makeService(new FakeProvider());

        try {
            $service->start('github', ['invite' => 'invite-abc']);
        } catch (\RuntimeException $exception) {
            $this->assertStringStartsWith('redirect:', $exception->getMessage());
            $this->assertStringContainsString('https://github.example/authorize', $exception->getMessage());
            return;
        }

        $this->fail('Expected redirect exception.');
    }

    public function test_callback_logs_in_existing_provider_link(): void
    {
        FakeProviderRegistry::enable('github');
        $user = new WP_User(5);
        $user->user_email = 'dev@example.com';
        $GLOBALS['woc_test_users'][5] = $user;
        $GLOBALS['woc_test_user_meta'][5]['oauth_github_user_id'] = 'gh-123';

        $authenticated = false;
        add_action('woc_oauth_authenticated', static function () use (&$authenticated): void {
            $authenticated = true;
        });

        $service = $this->makeService(new FakeProvider());
        $state   = (new OAuthState())->create('github');
        $_COOKIE[OAuthState::CSRF_COOKIE_NAME] = $state['csrf'];
        $_GET['state'] = $state['state'];
        $_GET['code']  = 'auth-code';

        try {
            $service->callback('github');
        } catch (\RuntimeException $exception) {
            $this->assertStringStartsWith('redirect:', $exception->getMessage());
            $this->assertTrue($authenticated);
            $this->assertSame(5, $GLOBALS['woc_test_auth_user_id']);
            return;
        }

        $this->fail('Expected redirect exception.');
    }

    public function test_callback_denies_registration_by_default(): void
    {
        FakeProviderRegistry::enable('github');
        $rejected = false;
        add_action('woc_oauth_registration_rejected', static function () use (&$rejected): void {
            $rejected = true;
        });

        $service = $this->makeService(new FakeProvider());
        $state   = (new OAuthState())->create('github');
        $_COOKIE[OAuthState::CSRF_COOKIE_NAME] = $state['csrf'];
        $_GET['state'] = $state['state'];
        $_GET['code']  = 'auth-code';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('wp_die:');

        try {
            $service->callback('github');
        } finally {
            $this->assertTrue($rejected);
        }
    }

    public function test_callback_creates_user_when_registration_allowed(): void
    {
        FakeProviderRegistry::enable('github');
        add_filter('woc_oauth_allow_registration', static fn (): bool => true);

        $registered = false;
        add_action('woc_oauth_user_registered', static function () use (&$registered): void {
            $registered = true;
        });

        $service = $this->makeService(new FakeProvider());
        $state   = (new OAuthState())->create('github');
        $_COOKIE[OAuthState::CSRF_COOKIE_NAME] = $state['csrf'];
        $_GET['state'] = $state['state'];
        $_GET['code']  = 'auth-code';

        try {
            $service->callback('github');
        } catch (\RuntimeException $exception) {
            $this->assertStringStartsWith('redirect:', $exception->getMessage());
            $this->assertTrue($registered);
            $this->assertCount(1, $GLOBALS['woc_test_users']);
            $this->assertSame('gh-123', $GLOBALS['woc_test_user_meta'][1]['oauth_github_user_id']);
            return;
        }

        $this->fail('Expected redirect exception.');
    }

    public function test_callback_redirects_to_bind_prompt_on_email_collision(): void
    {
        FakeProviderRegistry::enable('github');
        $existing = new WP_User(9);
        $existing->user_email = 'dev@example.com';
        $GLOBALS['woc_test_users'][9] = $existing;

        $service = $this->makeService(new FakeProvider());
        $state   = (new OAuthState())->create('github');
        $_COOKIE[OAuthState::CSRF_COOKIE_NAME] = $state['csrf'];
        $_GET['state'] = $state['state'];
        $_GET['code']  = 'auth-code';

        try {
            $service->callback('github');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('/oauth/bind?token=', $exception->getMessage());
            $this->assertNotEmpty($GLOBALS['woc_test_transients']);
            return;
        }

        $this->fail('Expected bind redirect.');
    }

    private function makeService(FakeProvider $provider): OAuthService
    {
        $registry = FakeProviderRegistry::withProvider($provider);
        $state    = new OAuthState();
        $linker   = new AccountLinker();
        $store    = new BindPromptStore();
        $handler  = new BindPromptHandler($store, $linker, $state, $this->pluginDir);

        return new OAuthService($registry, $state, $linker, $store, $handler);
    }
}