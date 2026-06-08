<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit\Support;

use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Provider\Provider;
use WpOAuthConnect\Provider\ProviderRegistry;
use WpOAuthConnect\Support\HttpClient;

final class FakeProviderRegistry
{
    public static function withProvider(Provider $provider, ?HttpClient $http = null): ProviderRegistry
    {
        $registry = new ProviderRegistry($http ?? new FakeHttpClient());
        $registry->register($provider);
        return $registry;
    }

    public static function enable(string $slug): void
    {
        if (!defined('OAUTH_STATE_KEY')) {
            define('OAUTH_STATE_KEY', 'test-state-key-32-bytes-minimum!!');
        }

        $idConstant = Settings::providerClientIdConstant($slug);
        $secretConstant = Settings::providerClientSecretConstant($slug);
        if (!defined($idConstant)) {
            define($idConstant, $slug . '-client-id');
        }
        if (!defined($secretConstant)) {
            define($secretConstant, $slug . '-client-secret');
        }

        update_option(Settings::providerEnabledOptionKey($slug), '1');
    }
}