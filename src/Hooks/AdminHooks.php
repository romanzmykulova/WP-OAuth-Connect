<?php
declare(strict_types=1);

/**
 * Admin settings page: per-provider status badges, enable toggles,
 * native-login mode, and wp-config constant hints.
 */

namespace WpOAuthConnect\Hooks;

use WpOAuthConnect\Options\Settings;

final class AdminHooks
{
    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerMenu(): void
    {
        \add_options_page(
            __('OAuth Connect', 'wp-oauth-connect'),
            __('OAuth Connect', 'wp-oauth-connect'),
            'manage_options',
            'wp-oauth-connect',
            [$this, 'renderPage'],
        );
    }

    public function registerSettings(): void
    {
        foreach (Settings::builtinProviderSlugs() as $slug) {
            \register_setting(
                'wp_oauth_connect',
                Settings::providerEnabledOptionKey($slug),
                [
                    'type'              => 'string',
                    'sanitize_callback' => static fn (mixed $value): string => $value === '1' ? '1' : '0',
                    'default'           => '0',
                ],
            );
        }

        \register_setting(
            'wp_oauth_connect',
            Settings::NATIVE_LOGIN_ENABLED_OPTION,
            [
                'type'              => 'string',
                'sanitize_callback' => static fn (mixed $value): string => $value === '1' ? '1' : '0',
                'default'           => '1',
            ],
        );
    }

    public function renderPage(): void
    {
        if (!\current_user_can('manage_options')) {
            return;
        }

        $stateConfigured = Settings::isStateKeyConfigured();
        ?>
        <div class="wrap">
            <h1><?php echo \esc_html__('OAuth Connect', 'wp-oauth-connect'); ?></h1>

            <?php if (!$stateConfigured) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        echo \esc_html__(
                            'Define OAUTH_STATE_KEY in wp-config.php before enabling providers.',
                            'wp-oauth-connect',
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php \settings_fields('wp_oauth_connect'); ?>

                <h2><?php echo \esc_html__('Providers', 'wp-oauth-connect'); ?></h2>
                <table class="widefat striped" style="max-width: 960px;">
                    <thead>
                        <tr>
                            <th><?php echo \esc_html__('Provider', 'wp-oauth-connect'); ?></th>
                            <th><?php echo \esc_html__('Status', 'wp-oauth-connect'); ?></th>
                            <th><?php echo \esc_html__('wp-config constants', 'wp-oauth-connect'); ?></th>
                            <th><?php echo \esc_html__('Enabled', 'wp-oauth-connect'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (Settings::builtinProviderSlugs() as $slug) : ?>
                            <?php
                            $status      = Settings::providerAdminStatus($slug);
                            $statusLabel = $this->statusLabel($status);
                            $idConstant  = Settings::providerClientIdConstant($slug);
                            $secretConst = Settings::providerClientSecretConstant($slug);
                            $optionKey   = Settings::providerEnabledOptionKey($slug);
                            $canEnable   = $status !== 'missing_creds' && $status !== 'missing_state_key';
                            ?>
                            <tr>
                                <td><strong><?php echo \esc_html(ucfirst($slug)); ?></strong></td>
                                <td><?php echo \esc_html($statusLabel); ?></td>
                                <td>
                                    <code><?php echo \esc_html($idConstant); ?></code>,
                                    <code><?php echo \esc_html($secretConst); ?></code>
                                </td>
                                <td>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="<?php echo \esc_attr($optionKey); ?>"
                                            value="1"
                                            <?php checked(Settings::isProviderEnabled($slug)); ?>
                                            <?php disabled(!$canEnable); ?>
                                        />
                                        <?php echo \esc_html__('Enable', 'wp-oauth-connect'); ?>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="margin-top: 2em;"><?php echo \esc_html__('Login mode', 'wp-oauth-connect'); ?></h2>
                <fieldset>
                    <label>
                        <input
                            type="checkbox"
                            name="<?php echo \esc_attr(Settings::NATIVE_LOGIN_ENABLED_OPTION); ?>"
                            value="1"
                            <?php checked(Settings::isNativeLoginEnabled()); ?>
                        />
                        <?php
                        echo \esc_html__(
                            'Allow native login (email/password and magic link) alongside OAuth',
                            'wp-oauth-connect',
                        );
                        ?>
                    </label>
                    <p class="description">
                        <?php
                        echo \esc_html__(
                            'Companion plugins may hide their native login forms when this is off.',
                            'wp-oauth-connect',
                        );
                        ?>
                    </p>
                </fieldset>

                <?php \submit_button(); ?>
            </form>

            <h2 style="margin-top: 2em;"><?php echo \esc_html__('Callback URLs', 'wp-oauth-connect'); ?></h2>
            <p class="description">
                <?php echo \esc_html__('Register these redirect URIs with each OAuth provider:', 'wp-oauth-connect'); ?>
            </p>
            <ul>
                <?php foreach (Settings::builtinProviderSlugs() as $slug) : ?>
                    <li>
                        <strong><?php echo \esc_html(ucfirst($slug)); ?>:</strong>
                        <code><?php echo \esc_html(\home_url('/oauth/' . $slug . '/callback')); ?></code>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'missing_state_key' => __('Missing OAUTH_STATE_KEY', 'wp-oauth-connect'),
            'missing_creds'     => __('Credentials not configured', 'wp-oauth-connect'),
            'disabled'          => __('Configured, disabled', 'wp-oauth-connect'),
            'ready'             => __('Ready', 'wp-oauth-connect'),
            default             => __('Unknown', 'wp-oauth-connect'),
        };
    }
}