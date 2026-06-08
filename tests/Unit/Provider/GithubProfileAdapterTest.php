<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Provider\Adapter\GithubProfileAdapter;
use WpOAuthConnect\Provider\ProviderDefinition;
use WpOAuthConnect\Support\HttpResponse;
use WpOAuthConnect\Tests\Unit\Support\FakeHttpClient;

final class GithubProfileAdapterTest extends TestCase
{
    public function test_primary_verified_email_is_selected(): void
    {
        $adapter = new GithubProfileAdapter();
        $http    = new FakeHttpClient(getFixtures: [
            'https://api.github.com/user' => new HttpResponse(200, (string) json_encode([
                'id'         => 99,
                'login'      => 'octocat',
                'name'       => 'The Octocat',
                'avatar_url' => 'https://avatars.example/octocat',
                'email'      => null,
            ])),
            'https://api.github.com/user/emails' => new HttpResponse(200, (string) json_encode([
                ['email' => 'noreply@users.noreply.github.com', 'primary' => true, 'verified' => false],
                ['email' => 'real@example.com', 'primary' => true, 'verified' => true],
            ])),
        ]);

        $profile = $adapter->fetchProfile(
            $http,
            'gh-token',
            ProviderDefinition::fromArray([
                'slug'   => 'github',
                'label'  => 'GitHub',
                'engine' => 'oauth2',
                'scopes' => ['read:user', 'user:email'],
            ]),
        );

        $this->assertSame('99', $profile->providerUserId);
        $this->assertSame('real@example.com', $profile->email);
        $this->assertTrue($profile->emailVerified);
        $this->assertSame('octocat', $profile->rawLogin);
    }

    public function test_noreply_fallback_marks_email_unverified(): void
    {
        $adapter = new GithubProfileAdapter();
        $http    = new FakeHttpClient(getFixtures: [
            'https://api.github.com/user' => new HttpResponse(200, (string) json_encode([
                'id'    => 7,
                'login' => 'ghost',
                'name'  => '',
                'email' => 'ghost@users.noreply.github.com',
            ])),
            'https://api.github.com/user/emails' => new HttpResponse(200, (string) json_encode([
                ['email' => 'ghost@users.noreply.github.com', 'primary' => true, 'verified' => false],
            ])),
        ]);

        $profile = $adapter->fetchProfile(
            $http,
            'gh-token',
            ProviderDefinition::fromArray([
                'slug'   => 'github',
                'label'  => 'GitHub',
                'engine' => 'oauth2',
                'scopes' => ['read:user', 'user:email'],
            ]),
        );

        $this->assertSame('ghost@users.noreply.github.com', $profile->email);
        $this->assertFalse($profile->emailVerified);
    }
}