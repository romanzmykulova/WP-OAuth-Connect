<?php
declare(strict_types=1);

/**
 * Generates OAUTH_STATE_KEY and writes it into wp-config.php when possible.
 */

namespace WpOAuthConnect\Support;

use WpOAuthConnect\Options\Settings;

final class WpConfigStateKeyInstaller
{
    private const STOP_EDITING_MARKER = "/* That's all, stop editing! Happy publishing. */";

    public function installIfMissing(?string $existingKey = null, ?string $configPath = null): StateKeyInstallResult
    {
        $usingDefaultPath = $configPath === null;
        $configPath ??= $this->locateWpConfigPath();

        if ($usingDefaultPath && Settings::isStateKeyFromWpConfig()) {
            return new StateKeyInstallResult(
                StateKeyInstallResult::STATUS_ALREADY_CONFIGURED,
                Settings::stateKey(),
            );
        }
        if ($configPath === null) {
            return $this->manualRequired($this->resolveKey($existingKey));
        }

        $contents = (string) \file_get_contents($configPath);
        if ($contents === '') {
            return $this->manualRequired($this->resolveKey($existingKey));
        }

        if ($this->fileDeclaresStateKey($contents)) {
            $key = $this->readStateKeyFromFile($contents);
            if ($key !== '' && !\defined(Settings::STATE_KEY_CONSTANT)) {
                \define(Settings::STATE_KEY_CONSTANT, $key);
            }

            return new StateKeyInstallResult(
                StateKeyInstallResult::STATUS_ALREADY_CONFIGURED,
                $key,
            );
        }

        $key = $this->resolveKey($existingKey);
        if (!\is_writable($configPath)) {
            return $this->manualRequired($key);
        }

        $line     = $this->formatDefineLine($key);
        $inserted = $this->insertBeforeStopEditing($contents, $line);
        if ($inserted === null) {
            return $this->manualRequired($key);
        }

        if (!\file_put_contents($configPath, $inserted, LOCK_EX)) {
            return $this->manualRequired($key);
        }

        if (!\defined(Settings::STATE_KEY_CONSTANT)) {
            \define(Settings::STATE_KEY_CONSTANT, $key);
        }

        return new StateKeyInstallResult(StateKeyInstallResult::STATUS_WRITTEN, $key);
    }

    public function wpConfigSnippet(string $key): string
    {
        return $this->formatDefineLine($key);
    }

    public function locateWpConfigPath(): ?string
    {
        if (!\defined('ABSPATH')) {
            return null;
        }

        $path = \rtrim((string) ABSPATH, '/') . '/wp-config.php';

        return \is_readable($path) ? $path : null;
    }

    private function resolveKey(?string $existingKey): string
    {
        if ($existingKey !== null && $existingKey !== '') {
            return $existingKey;
        }

        $stored = (string) \get_option(Settings::STATE_KEY_OPTION, '');
        if ($stored !== '') {
            return $stored;
        }

        return \rtrim(\strtr(\base64_encode(\random_bytes(32)), '+/', '-_'), '=');
    }

    private function manualRequired(string $key): StateKeyInstallResult
    {
        return new StateKeyInstallResult(StateKeyInstallResult::STATUS_MANUAL_REQUIRED, $key);
    }

    private function formatDefineLine(string $key): string
    {
        $escaped = \str_replace("'", "\\'", $key);

        return "define( 'OAUTH_STATE_KEY', '" . $escaped . "' );";
    }

    private function fileDeclaresStateKey(string $contents): bool
    {
        return \preg_match(
            "/define\s*\(\s*['\"]OAUTH_STATE_KEY['\"]\s*,/i",
            $contents,
        ) === 1;
    }

    private function readStateKeyFromFile(string $contents): string
    {
        if (\preg_match(
            "/define\s*\(\s*['\"]OAUTH_STATE_KEY['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/i",
            $contents,
            $matches,
        ) !== 1) {
            return '';
        }

        return \str_replace("\\'", "'", (string) $matches[1]);
    }

    private function insertBeforeStopEditing(string $contents, string $defineLine): ?string
    {
        $markerPos = \strpos($contents, self::STOP_EDITING_MARKER);
        if ($markerPos === false) {
            return null;
        }

        $block = "\n/** OAuth state signing key (wp-oauth-connect). */\n"
            . $defineLine . "\n";

        return \substr($contents, 0, $markerPos) . $block . \substr($contents, $markerPos);
    }
}