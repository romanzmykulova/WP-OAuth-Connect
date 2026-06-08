<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Provider\OAuth2Provider;
use WpOAuthConnect\Provider\ProviderDefinition;
use WpOAuthConnect\Support\HttpResponse;
use WpOAuthConnect\Tests\Unit\Support\FakeHttpClient;

final class OAuth2ProviderTest extends TestCase
{
    public function test_explicit_urls_and_claim_map_profile_parse(): void
    {
        $authorizeUrl = 'https://idp.example.com/authorize';
        $tokenUrl     = 'https://idp.example.com/token';
        $profileUrl   = 'https://idp.example.com/me';

        $http = new FakeHttpClient(
            getFixtures: [
                $profileUrl => new HttpResponse(200, (string) json_encode([
                    'uid'      => '42',
                    'mail'     => 'dev@example.com',
                    'verified' => 'true',
                    'full'     => 'Dev User',
                    'photo'    => 'https://cdn.example/p.png',
                ])),
            ],
            postFixtures: [
                $tokenUrl => new HttpResponse(200, (string) json_encode([
                    'access_token' => 'oauth2-token',
                ])),
            ],
        );

        $definition = ProviderDefinition::fromArray([
            'slug'          => 'custom-idp',
            'label'         => 'Sign in with Custom',
            'engine'        => 'oauth2',
            'authorize_url' => $authorizeUrl,
            'token_url'     => $tokenUrl,
            'profile_url'   => $profileUrl,
            'scopes'        => ['profile', 'email'],
            'claim_map'     => [
                'id'             => 'uid',
                'email'          => 'mail',
                'email_verified' => 'verified',
                'name'           => 'full',
                'picture'        => 'photo',
            ],
        ]);

        $provider = new OAuth2Provider($definition, 'cid', 'csecret', $http);

        $url = $provider->authorizeUrl('st', 'https://site.test/oauth/custom-idp/callback');
        $this->assertStringStartsWith($authorizeUrl . '?', $url);

        $token = $provider->exchangeCode('code-1', 'https://site.test/oauth/custom-idp/callback');
        $this->assertSame('oauth2-token', $token);

        $profile = $provider->fetchProfile($token);
        $this->assertSame('custom-idp', $profile->providerSlug);
        $this->assertSame('42', $profile->providerUserId);
        $this->assertSame('dev@example.com', $profile->email);
        $this->assertTrue($profile->emailVerified);
        $this->assertSame('Dev User', $profile->displayName);
    }
}