<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\Exceptions\OAuthStateExpired;
use WpOAuthConnect\Exceptions\OAuthStateInvalid;
use WpOAuthConnect\OAuthState;

final class OAuthStateTest extends TestCase
{
    private const STATE_KEY = 'test-state-key-32-bytes-minimum!!';

    protected function setUp(): void
    {
        if (!defined('OAUTH_STATE_KEY')) {
            define('OAUTH_STATE_KEY', self::STATE_KEY);
        }
        $_COOKIE = [];
        remove_all_filters('woc_oauth_state_payload');
    }

    public function test_create_and_verify_roundtrip_with_invite_extension(): void
    {
        $stateService = new OAuthState();
        $created      = $stateService->create('google', ['invite_token' => 'abc123']);

        $_COOKIE[OAuthState::CSRF_COOKIE_NAME] = $created['csrf'];
        $payload = $stateService->verify($created['state'], 'google');

        $this->assertSame('abc123', $payload['invite_token']);
        $this->assertSame('google', $payload['provider']);
    }

    public function test_filter_merges_site_payload_before_signing(): void
    {
        add_filter(
            'woc_oauth_state_payload',
            static function (array $payload): array {
                $payload['required_email'] = 'member@example.com';
                return $payload;
            },
        );

        $stateService = new OAuthState();
        $created      = $stateService->create('linkedin', [], ['surface' => 'join']);
        $_COOKIE[OAuthState::CSRF_COOKIE_NAME] = $created['csrf'];

        $payload = $stateService->verify($created['state'], 'linkedin');
        $this->assertSame('member@example.com', $payload['required_email']);
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $stateService = new OAuthState();
        $created      = $stateService->create('github');
        $_COOKIE[OAuthState::CSRF_COOKIE_NAME] = $created['csrf'];

        $this->expectException(OAuthStateInvalid::class);
        $stateService->verify($created['state'] . 'x', 'github');
    }

    public function test_expired_state_is_rejected(): void
    {
        $stateService = new OAuthState();
        $created      = $stateService->create('google');
        $_COOKIE[OAuthState::CSRF_COOKIE_NAME] = $created['csrf'];

        $parts = explode('.', $created['state'], 2);
        $json  = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/') . '=='), true);
        $this->assertIsArray($json);
        $json['issued_at'] = time() - OAuthState::TTL_SECONDS - 1;
        $encoded = rtrim(strtr(base64_encode((string) json_encode($json)), '+/', '-_'), '=');
        $sig     = rtrim(strtr(base64_encode(hash_hmac('sha256', $encoded, self::STATE_KEY, true)), '+/', '-_'), '=');
        $stale   = $encoded . '.' . $sig;

        $this->expectException(OAuthStateExpired::class);
        $stateService->verify($stale, 'google');
    }

    public function test_csrf_cookie_mismatch_is_rejected(): void
    {
        $stateService = new OAuthState();
        $created      = $stateService->create('google');
        $_COOKIE[OAuthState::CSRF_COOKIE_NAME] = 'wrong-csrf';

        $this->expectException(OAuthStateInvalid::class);
        $stateService->verify($created['state'], 'google');
    }
}