<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpOAuthConnect\AccountLinker;
use WpOAuthConnect\OAuthProfile;
use WP_User;

final class AccountLinkerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['woc_test_users'] = [];
        $GLOBALS['woc_test_user_meta'] = [];
    }

    public function test_find_by_provider_id_returns_linked_user(): void
    {
        $user = new WP_User(7);
        $user->user_email = 'member@example.com';
        $GLOBALS['woc_test_users'][7] = $user;
        $GLOBALS['woc_test_user_meta'][7]['oauth_github_user_id'] = 'gh-99';

        $profile = new OAuthProfile('github', 'gh-99', 'member@example.com', true, 'Member');
        $linker  = new AccountLinker();

        $found = $linker->findByProviderId($profile);
        $this->assertInstanceOf(WP_User::class, $found);
        $this->assertSame(7, $found->ID);
    }

    public function test_bind_writes_provider_usermeta(): void
    {
        $user = new WP_User(3);
        $GLOBALS['woc_test_users'][3] = $user;

        $profile = new OAuthProfile('google', 'sub-1', 'g@example.com', true, 'G');
        (new AccountLinker())->bind($user, $profile);

        $this->assertSame('sub-1', $GLOBALS['woc_test_user_meta'][3]['oauth_google_user_id']);
    }

    public function test_create_minimal_user_inserts_wp_user(): void
    {
        $profile = new OAuthProfile('linkedin', 'li-1', 'li@example.com', true, 'Linked Member', rawLogin: 'linkedmember');
        $user    = (new AccountLinker())->createMinimalUser($profile);

        $this->assertSame('li@example.com', $user->user_email);
        $this->assertSame('Linked Member', $user->display_name);
        $this->assertCount(1, $GLOBALS['woc_test_users']);
    }
}