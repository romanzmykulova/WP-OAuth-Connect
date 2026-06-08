<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Hooks\NativeLoginHooks;
use WpOAuthConnect\Options\Settings;

final class NativeLoginHooksTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['woc_test_options'] = [];
    }

    public function test_native_login_permission_denied_when_toggle_off(): void
    {
        update_option(Settings::NATIVE_LOGIN_ENABLED_OPTION, '0');
        $hooks = new NativeLoginHooks();
        $this->assertFalse($hooks->nativeLoginPermission());
    }

    public function test_native_login_permission_allowed_when_toggle_on(): void
    {
        update_option(Settings::NATIVE_LOGIN_ENABLED_OPTION, '1');
        $hooks = new NativeLoginHooks();
        $this->assertTrue($hooks->nativeLoginPermission());
    }
}