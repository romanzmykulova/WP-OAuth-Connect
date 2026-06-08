<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Support\StateKeyInstallResult;
use WpOAuthConnect\Support\WpConfigStateKeyInstaller;

final class WpConfigStateKeyInstallerTest extends TestCase
{
    private string $tempDir;
    private string $configPath;

    protected function setUp(): void
    {
        $this->tempDir    = \sys_get_temp_dir() . '/woc-wpconfig-' . \bin2hex(\random_bytes(4));
        $this->configPath = $this->tempDir . '/wp-config.php';
        \mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        if (\is_file($this->configPath)) {
            \unlink($this->configPath);
        }
        \rmdir($this->tempDir);
    }

    public function test_writes_generated_key_before_stop_editing_marker(): void
    {
        $this->writeSampleConfig();

        $installer = new WpConfigStateKeyInstaller();
        $result    = $installer->installIfMissing('generated-test-key-value', $this->configPath);

        $this->assertTrue($result->writtenToWpConfig());
        $this->assertSame('generated-test-key-value', $result->key);

        $contents = (string) \file_get_contents($this->configPath);
        $this->assertStringContainsString("define( 'OAUTH_STATE_KEY', 'generated-test-key-value' );", $contents);
        $this->assertLessThan(
            \strpos($contents, self::STOP_EDITING_MARKER),
            \strpos($contents, "define( 'OAUTH_STATE_KEY'"),
        );
    }

    public function test_detects_existing_state_key_in_file(): void
    {
        $this->writeSampleConfig(
            "\ndefine( 'OAUTH_STATE_KEY', 'already-there' );\n",
        );

        $installer = new WpConfigStateKeyInstaller();
        $result    = $installer->installIfMissing(null, $this->configPath);

        $this->assertSame(StateKeyInstallResult::STATUS_ALREADY_CONFIGURED, $result->status);
        $this->assertSame('already-there', $result->key);
    }

    public function test_returns_manual_required_when_config_is_not_writable(): void
    {
        $this->writeSampleConfig();
        \chmod($this->configPath, 0444);

        $installer = new WpConfigStateKeyInstaller();
        $result    = $installer->installIfMissing('manual-key', $this->configPath);

        $this->assertTrue($result->needsManualInstall());
        $this->assertSame('manual-key', $result->key);
        $this->assertStringContainsString(
            "define( 'OAUTH_STATE_KEY', 'manual-key' );",
            $installer->wpConfigSnippet('manual-key'),
        );

        \chmod($this->configPath, 0644);
    }

    private function writeSampleConfig(string $extra = ''): void
    {
        $body = "<?php\n"
            . "/* Add any custom values between this line and the \"stop editing\" line. */\n"
            . $extra
            . self::STOP_EDITING_MARKER . "\n"
            . "require_once __DIR__ . '/wp-settings.php';\n";

        \file_put_contents($this->configPath, $body);
    }

    private const STOP_EDITING_MARKER = "/* That's all, stop editing! Happy publishing. */";
}