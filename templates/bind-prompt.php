<?php
declare(strict_types=1);

/**
 * Minimal BIND-PROMPT template — companions may override via template_include.
 *
 * @var string $token
 * @var string $message
 * @var string $email
 * @var WpOAuthConnect\OAuthProfile $profile
 * @var bool $error
 */

if (!defined('ABSPATH')) {
    exit;
}

$error = $error ?? false;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html__('Link OAuth account', 'wp-oauth-connect'); ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 28rem; line-height: 1.5; }
        .woc-bind { border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; }
        .woc-bind__error { color: #b91c1c; margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.25rem; font-weight: 600; }
        input[type="password"] { width: 100%; padding: 0.5rem; margin-bottom: 1rem; box-sizing: border-box; }
        button { padding: 0.5rem 1rem; cursor: pointer; }
    </style>
</head>
<body>
    <main class="woc-bind">
        <h1><?php echo esc_html__('Confirm your account', 'wp-oauth-connect'); ?></h1>
        <?php if ($error) : ?>
            <p class="woc-bind__error"><?php echo esc_html($message); ?></p>
        <?php else : ?>
            <p><?php echo esc_html($message); ?></p>
        <?php endif; ?>
        <p>
            <?php
            printf(
                /* translators: %s: email address */
                esc_html__('Sign in as %s to link your %s account.', 'wp-oauth-connect'),
                esc_html($email),
                esc_html(ucfirst($profile->providerSlug)),
            );
            ?>
        </p>
        <form method="post" action="<?php echo esc_url(home_url('/oauth/bind?token=' . rawurlencode($token))); ?>">
            <label for="woc_bind_password"><?php echo esc_html__('Password', 'wp-oauth-connect'); ?></label>
            <input type="password" id="woc_bind_password" name="woc_bind_password" required autocomplete="current-password">
            <button type="submit"><?php echo esc_html__('Link account', 'wp-oauth-connect'); ?></button>
        </form>
    </main>
</body>
</html>