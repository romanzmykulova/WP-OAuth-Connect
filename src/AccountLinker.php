<?php
declare(strict_types=1);

/**
 * Provider-ID ↔ WP-user linking via usermeta. Used by OAuthService and BIND-PROMPT.
 */

namespace WpOAuthConnect;

final class AccountLinker
{
    public function findByProviderId(OAuthProfile $profile): ?\WP_User
    {
        $users = \get_users([
            'meta_key'   => $profile->usermetaKey(),
            'meta_value' => $profile->providerUserId,
            'number'     => 1,
        ]);

        $user = $users[0] ?? null;
        return $user instanceof \WP_User ? $user : null;
    }

    public function findByEmail(string $email): ?\WP_User
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        $user = \get_user_by('email', $normalized);
        return $user instanceof \WP_User ? $user : null;
    }

    public function bind(\WP_User $user, OAuthProfile $profile): void
    {
        \update_user_meta($user->ID, $profile->usermetaKey(), $profile->providerUserId);
    }

    public function createMinimalUser(OAuthProfile $profile): \WP_User
    {
        $userId = \wp_insert_user([
            'user_login'   => $this->uniqueLogin($profile),
            'user_email'   => $profile->email,
            'display_name' => $profile->displayName !== '' ? $profile->displayName : $profile->email,
            'user_pass'    => \wp_generate_password(32, true, true),
            'role'         => \get_option('default_role', 'subscriber'),
        ]);

        if (\is_wp_error($userId)) {
            throw new \RuntimeException('Failed to create OAuth user: ' . $userId->get_error_message());
        }

        $user = \get_user_by('id', (int) $userId);
        if (!$user instanceof \WP_User) {
            throw new \RuntimeException('OAuth user was created but could not be loaded.');
        }

        return $user;
    }

    private function uniqueLogin(OAuthProfile $profile): string
    {
        $base = $profile->rawLogin !== ''
            ? \sanitize_user($profile->rawLogin, true)
            : \sanitize_user((string) strtok($profile->email, '@'), true);

        if ($base === '') {
            $base = $profile->providerSlug . '_' . $profile->providerUserId;
        }

        $candidate = $base;
        $suffix    = 1;
        while (\username_exists($candidate)) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}