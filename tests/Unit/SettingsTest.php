<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Options\Settings;

final class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['woc_test_options'] = [];
    }

    public function test_auto_generates_state_key_when_wp_config_constant_missing(): void
    {
        if (\defined('OAUTH_STATE_KEY')) {
            $this->markTestSkipped('OAUTH_STATE_KEY already defined in this PHP process.');
        }

        Settings::ensureStateKey();

        $this->assertTrue(Settings::isStateKeyConfigured());
        $this->assertFalse(Settings::isStateKeyFromWpConfig());
        $this->assertNotSame('', (string) get_option(Settings::STATE_KEY_OPTION, ''));
    }

    public function test_wp_config_constant_takes_precedence_over_stored_option(): void
    {
        if (\defined('OAUTH_STATE_KEY')) {
            $expected = (string) \constant('OAUTH_STATE_KEY');
        } else {
            \define('OAUTH_STATE_KEY', 'pinned-from-wp-config');
            $expected = 'pinned-from-wp-config';
        }

        update_option(Settings::STATE_KEY_OPTION, 'stored-option-key');

        $this->assertSame($expected, Settings::stateKey());
        $this->assertTrue(Settings::isStateKeyFromWpConfig());
    }

    public function test_login_button_order_parses_comma_separated_slugs(): void
    {
        update_option(Settings::LOGIN_BUTTON_ORDER_OPTION, 'github, linkedin, google');

        $this->assertSame(['github', 'linkedin', 'google'], Settings::loginButtonOrder());
    }
}