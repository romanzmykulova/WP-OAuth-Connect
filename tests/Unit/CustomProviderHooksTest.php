<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Hooks\CustomProviderHooks;
use WpOAuthConnect\Options\CustomProviderSettings;
use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Provider\OidcProvider;
use WpOAuthConnect\Provider\ProviderRegistry;
use WpOAuthConnect\Tests\Unit\Support\FakeHttpClient;

final class CustomProviderHooksTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['woc_test_options'] = [];
        $GLOBALS['woc_test_filters'] = [];
        remove_all_filters('woc_oauth_provider_definitions');
        remove_all_filters('woc_oauth_provider_button');
    }

    public function test_registers_custom_oidc_definition_when_configured(): void
    {
        update_option(CustomProviderSettings::LABEL_OPTION, 'Continue with Acme');
        update_option(CustomProviderSettings::ISSUER_OPTION, 'https://acme.okta.com/oauth2/default');

        (new CustomProviderHooks())->register();
        $registry = ProviderRegistry::fromPluginDir(dirname(__DIR__, 2), new FakeHttpClient());

        $this->assertTrue($registry->has('custom'));
        $this->assertInstanceOf(OidcProvider::class, $registry->get('custom'));
        $this->assertSame('Continue with Acme', $registry->get('custom')->label());
    }

    public function test_styles_button_with_icon_text_and_label(): void
    {
        update_option(CustomProviderSettings::LABEL_OPTION, 'Sign in with Acme');
        update_option(CustomProviderSettings::ICON_TEXT_OPTION, 'A');
        update_option(CustomProviderSettings::ISSUER_OPTION, 'https://acme.okta.com/oauth2/default');
        update_option(Settings::providerClientIdOptionKey('custom'), 'id');
        update_option(Settings::providerClientSecretOptionKey('custom'), 'secret');
        update_option(Settings::providerEnabledOptionKey('custom'), '1');

        if (!defined('OAUTH_STATE_KEY')) {
            define('OAUTH_STATE_KEY', 'test-state-key-32-bytes-minimum!!');
        }

        (new CustomProviderHooks())->register();
        $registry = ProviderRegistry::fromPluginDir(dirname(__DIR__, 2), new FakeHttpClient());
        $provider = $registry->get('custom');

        $button = apply_filters(
            'woc_oauth_provider_button',
            [
                'provider'  => 'custom',
                'label'     => $provider->label(),
                'enabled'   => true,
                'icon_html' => '',
            ],
            $provider,
        );

        $this->assertSame('Sign in with Acme', $button['label']);
        $this->assertStringContainsString('oauth-btn__icon', $button['icon_html']);
        $this->assertStringContainsString('A', $button['icon_html']);
    }
}