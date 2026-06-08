<?php
declare(strict_types=1);

/**
 * Schema-version-gated migration runner. Idempotent — safe on every boot.
 */

namespace WpOAuthConnect\Migrations;

use WpOAuthConnect\Options\Settings;

final class Migrator
{
    public static function run(): void
    {
        global $wpdb;
        if (!($wpdb instanceof \wpdb)) {
            return;
        }

        $currentVersion = Settings::schemaVersion();
        $pending        = self::pendingMigrations($currentVersion);
        if ($pending === []) {
            return;
        }

        $highestApplied = $currentVersion;
        foreach ($pending as $version => $className) {
            /** @var object{up: callable} $migration */
            $migration = new $className();
            $migration->up($wpdb);
            $highestApplied = max($highestApplied, $version);
        }

        Settings::setSchemaVersion($highestApplied);
    }

    /**
     * @return array<int, class-string>
     */
    private static function pendingMigrations(int $currentVersion): array
    {
        $discovered = [];
        foreach (glob(__DIR__ . '/Migration_*.php') ?: [] as $file) {
            if (!preg_match('/Migration_(\d{4})_/', basename($file), $matches)) {
                continue;
            }
            $version   = (int) $matches[1];
            $className = 'WpOAuthConnect\\Migrations\\Migration_' . $matches[1] . '_' . self::classSuffix($file);
            if ($version > $currentVersion && class_exists($className)) {
                $discovered[$version] = $className;
            }
        }

        \ksort($discovered);
        return $discovered;
    }

    private static function classSuffix(string $file): string
    {
        $base = basename($file, '.php');
        return (string) preg_replace('/^Migration_\d{4}_/', '', $base);
    }
}