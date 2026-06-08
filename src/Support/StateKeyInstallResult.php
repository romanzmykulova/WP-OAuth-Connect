<?php
declare(strict_types=1);

/**
 * Outcome of attempting to place OAUTH_STATE_KEY in wp-config.php.
 */

namespace WpOAuthConnect\Support;

final class StateKeyInstallResult
{
    public const STATUS_ALREADY_CONFIGURED = 'already_configured';
    public const STATUS_WRITTEN            = 'written';
    public const STATUS_MANUAL_REQUIRED    = 'manual_required';

    public function __construct(
        public readonly string $status,
        public readonly string $key,
    ) {}

    public function writtenToWpConfig(): bool
    {
        return $this->status === self::STATUS_WRITTEN;
    }

    public function needsManualInstall(): bool
    {
        return $this->status === self::STATUS_MANUAL_REQUIRED;
    }
}