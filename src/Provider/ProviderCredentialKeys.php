<?php
declare(strict_types=1);

/**
 * wp-config constant names for a provider slug.
 */

namespace WpOAuthConnect\Provider;

final readonly class ProviderCredentialKeys
{
    public function __construct(
        public string $clientIdConstant,
        public string $clientSecretConstant,
    ) {}
}