<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Exceptions\OAuthEmailNotVerified;
use WpOAuthConnect\Provider\OidcProvider;
use WpOAuthConnect\Provider\ProviderDefinition;
use WpOAuthConnect\Support\HttpResponse;
use WpOAuthConnect\Tests\Unit\Support\FakeHttpClient;

final class OidcProviderTest extends TestCase
{
    private const DISCOVERY_URL = 'https://accounts.google.com/.well-known/openid-configuration';
    private const AUTH_URL      = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL     = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL  = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function test_discovery_authorize_token_and_profile_happy_path(): void
    {
        $http = new FakeHttpClient(
            getFixtures: [
                self::DISCOVERY_URL => new HttpResponse(200, (string) json_encode([
                    'authorization_endpoint' => self::AUTH_URL,
                    'token_endpoint'         => self::TOKEN_URL,
                    'userinfo_endpoint'      => self::USERINFO_URL,
                ])),
                self::USERINFO_URL => new HttpResponse(200, (string) json_encode([
                    'sub'             => 'google-sub-1',
                    'email'           => 'Member@Example.com',
                    'email_verified'  => true,
                    'name'            => 'Alex Member',
                    'picture'         => 'https://cdn.example/avatar.jpg',
                ])),
            ],
            postFixtures: [
                self::TOKEN_URL => new HttpResponse(200, (string) json_encode([
                    'access_token' => 'at-google',
                    'token_type'   => 'Bearer',
                ])),
            ],
        );

        $provider = new OidcProvider(
            $this->googleDefinition(),
            'client-id',
            'client-secret',
            $http,
        );

        $authorize = $provider->authorizeUrl('state-xyz', 'https://remotejobs.team/oauth/google/callback');
        $this->assertStringStartsWith(self::AUTH_URL . '?', $authorize);
        $this->assertStringContainsString('client_id=client-id', $authorize);
        $this->assertStringContainsString('state=state-xyz', $authorize);
        $this->assertStringContainsString('scope=openid', $authorize);

        $token = $provider->exchangeCode('auth-code', 'https://remotejobs.team/oauth/google/callback');
        $this->assertSame('at-google', $token);

        $profile = $provider->fetchProfile($token);
        $this->assertSame('google', $profile->providerSlug);
        $this->assertSame('google-sub-1', $profile->providerUserId);
        $this->assertSame('member@example.com', $profile->email);
        $this->assertTrue($profile->emailVerified);
        $this->assertSame('Alex Member', $profile->displayName);
    }

    public function test_unverified_email_is_rejected(): void
    {
        $http = new FakeHttpClient(
            getFixtures: [
                self::DISCOVERY_URL => new HttpResponse(200, (string) json_encode([
                    'authorization_endpoint' => self::AUTH_URL,
                    'token_endpoint'         => self::TOKEN_URL,
                    'userinfo_endpoint'      => self::USERINFO_URL,
                ])),
                self::USERINFO_URL => new HttpResponse(200, (string) json_encode([
                    'sub'            => 'google-sub-2',
                    'email'          => 'unverified@example.com',
                    'email_verified' => false,
                    'name'           => 'Unverified',
                ])),
            ],
        );

        $provider = new OidcProvider($this->googleDefinition(), 'id', 'secret', $http);

        $this->expectException(OAuthEmailNotVerified::class);
        $provider->fetchProfile('token');
    }

    private function googleDefinition(): ProviderDefinition
    {
        return ProviderDefinition::fromArray([
            'slug'   => 'google',
            'label'  => 'Continue with Google',
            'engine' => 'oidc',
            'issuer' => 'https://accounts.google.com',
            'scopes' => ['openid', 'email', 'profile'],
        ]);
    }
}