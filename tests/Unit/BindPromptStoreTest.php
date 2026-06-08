<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\BindPrompt\BindPromptStore;
use WpOAuthConnect\OAuthProfile;
use WP_User;

final class BindPromptStoreTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['woc_test_transients'] = [];
    }

    public function test_create_peek_and_consume_roundtrip(): void
    {
        $user = new WP_User(4);
        $profile = new OAuthProfile('google', 'g-1', 'g@example.com', true, 'G');
        $store   = new BindPromptStore();

        $token = $store->create($user, $profile, ['invite_token' => 'abc']);
        $peek  = $store->peek($token);

        $this->assertNotNull($peek);
        $this->assertSame(4, $peek['user_id']);
        $this->assertSame('abc', $peek['state_payload']['invite_token']);

        $consumed = $store->consume($token);
        $this->assertNotNull($consumed);
        $this->assertNull($store->peek($token));
    }
}