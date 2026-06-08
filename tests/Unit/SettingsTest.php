<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Support\WpConfigStateKeyInstaller;

final class SettingsTest extends TestCase
{
    public function test_provider_credentials_fall_back_to_admin_options(): void
    {
        $GLOBALS['woc_test_options'] = [];
        $slug = 'microsoft';
        \update_option(Settings::providerClientIdOptionKey($slug), 'ms-admin-id');
        \update_option(Settings::providerClientSecretOptionKey($slug), 'ms-admin-secret');

        if (Settings::providerCredentialsFromWpConfig($slug)) {
            $this->markTestSkipped('Provider credentials already defined in wp-config for this process.');
        }

        $this->assertTrue(Settings::hasProviderCredentials($slug));
        $this->assertSame('ms-admin-id', Settings::providerClientId($slug));
        $this->assertSame('ms-admin-secret', Settings::providerClientSecret($slug));
    }

    public function test_login_button_order_parses_comma_separated_slugs(): void
    {
        $GLOBALS['woc_test_options'] = [];
        \update_option(Settings::LOGIN_BUTTON_ORDER_OPTION, 'github, linkedin, google');

        $this->assertSame(['github', 'linkedin', 'google'], Settings::loginButtonOrder());
    }

    public function test_wp_config_constant_takes_precedence_over_stored_option(): void
    {
        $GLOBALS['woc_test_options'] = [];

        if (\defined('OAUTH_STATE_KEY')) {
            $expected = (string) \constant('OAUTH_STATE_KEY');
        } else {
            \define('OAUTH_STATE_KEY', 'pinned-from-wp-config');
            $expected = 'pinned-from-wp-config';
        }

        \update_option(Settings::STATE_KEY_OPTION, 'stored-option-key');

        $this->assertSame($expected, Settings::stateKey());
        $this->assertTrue(Settings::isStateKeyFromWpConfig());
    }

    public function test_manual_snippet_is_exposed_when_installer_cannot_write(): void
    {
        $GLOBALS['woc_test_options'] = [];
        $tempDir                       = \sys_get_temp_dir() . '/woc-settings-' . \bin2hex(\random_bytes(4));
        $configPath                    = $tempDir . '/wp-config.php';
        \mkdir($tempDir, 0775, true);
        \file_put_contents(
            $configPath,
            "<?php\n/* That's all, stop editing! Happy publishing. */\n",
        );
        \chmod($configPath, 0444);

        $installer = new WpConfigStateKeyInstaller();
        $result    = $installer->installIfMissing('manual-key', $configPath);

        \update_option(Settings::STATE_KEY_OPTION, $result->key);
        \update_option(Settings::STATE_KEY_MANUAL_PENDING_OPTION, '1');

        $this->assertTrue($result->needsManualInstall());
        $this->assertSame(
            "define( 'OAUTH_STATE_KEY', 'manual-key' );",
            $installer->wpConfigSnippet('manual-key'),
        );

        \chmod($configPath, 0644);
        \unlink($configPath);
        \rmdir($tempDir);
    }
}