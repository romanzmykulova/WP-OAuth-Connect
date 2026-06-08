<?php
declare(strict_types=1);

/**
 * Admin settings page: per-provider credentials, enable toggles,
 * native-login mode, and state-key status.
 */

namespace WpOAuthConnect\Hooks;

use WpOAuthConnect\Options\CustomProviderSettings;
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

            \register_setting(
                'wp_oauth_connect',
                Settings::providerClientIdOptionKey($slug),
                [
                    'type'              => 'string',
                    'sanitize_callback' => static function (mixed $value) use ($slug): string {
                        if (Settings::providerCredentialsFromWpConfig($slug)) {
                            return (string) \get_option(Settings::providerClientIdOptionKey($slug), '');
                        }

                        return \sanitize_text_field((string) $value);
                    },
                    'default'           => '',
                ],
            );

            \register_setting(
                'wp_oauth_connect',
                Settings::providerClientSecretOptionKey($slug),
                [
                    'type'              => 'string',
                    'sanitize_callback' => static function (mixed $value) use ($slug): string {
                        if (Settings::providerCredentialsFromWpConfig($slug)) {
                            return (string) \get_option(Settings::providerClientSecretOptionKey($slug), '');
                        }

                        $incoming = \sanitize_text_field((string) $value);
                        if ($incoming === '') {
                            return (string) \get_option(Settings::providerClientSecretOptionKey($slug), '');
                        }

                        return $incoming;
                    },
                    'default'           => '',
                ],
            );
        }

        $this->registerProviderCredentialSettings(CustomProviderSettings::SLUG);

        \register_setting(
            'wp_oauth_connect',
            CustomProviderSettings::LABEL_OPTION,
            [
                'type'              => 'string',
                'sanitize_callback' => static fn (mixed $value): string => \sanitize_text_field((string) $value),
                'default'           => '',
            ],
        );

        \register_setting(
            'wp_oauth_connect',
            CustomProviderSettings::ICON_TEXT_OPTION,
            [
                'type'              => 'string',
                'sanitize_callback' => static function (mixed $value): string {
                    $text = \sanitize_text_field((string) $value);
                    return \mb_substr($text, 0, 3);
                },
                'default'           => '',
            ],
        );

        \register_setting(
            'wp_oauth_connect',
            CustomProviderSettings::ICON_HTML_OPTION,
            [
                'type'              => 'string',
                'sanitize_callback' => static fn (mixed $value): string => CustomProviderSettings::sanitizeIconHtml((string) $value),
                'default'           => '',
            ],
        );

        \register_setting(
            'wp_oauth_connect',
            CustomProviderSettings::ISSUER_OPTION,
            [
                'type'              => 'string',
                'sanitize_callback' => static fn (mixed $value): string => \esc_url_raw(\trim((string) $value)),
                'default'           => '',
            ],
        );

        \register_setting(
            'wp_oauth_connect',
            CustomProviderSettings::SCOPES_OPTION,
            [
                'type'              => 'string',
                'sanitize_callback' => static fn (mixed $value): string => \sanitize_text_field((string) $value),
                'default'           => CustomProviderSettings::DEFAULT_SCOPES,
            ],
        );

        \register_setting(
            'wp_oauth_connect',
            Settings::NATIVE_LOGIN_ENABLED_OPTION,
            [
                'type'              => 'string',
                'sanitize_callback' => static fn (mixed $value): string => $value === '1' ? '1' : '0',
                'default'           => '1',
            ],
        );

        \register_setting(
            'wp_oauth_connect',
            Settings::LOGIN_BUTTON_ORDER_OPTION,
            [
                'type'              => 'string',
                'sanitize_callback' => static function (mixed $value): string {
                    $raw = \sanitize_text_field((string) $value);
                    if ($raw === '') {
                        return \implode(',', Settings::DEFAULT_LOGIN_BUTTON_ORDER);
                    }

                    /** @var list<string> $slugs */
                    $slugs = \preg_split('/[\s,]+/', \strtolower($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $clean = [];
                    foreach ($slugs as $slug) {
                        if (\preg_match('/^[a-z0-9_-]+$/', $slug) === 1) {
                            $clean[] = $slug;
                        }
                    }

                    return $clean !== [] ? \implode(',', $clean) : \implode(',', Settings::DEFAULT_LOGIN_BUTTON_ORDER);
                },
                'default'           => \implode(',', Settings::DEFAULT_LOGIN_BUTTON_ORDER),
            ],
        );
    }

    public function renderPage(): void
    {
        if (!\current_user_can('manage_options')) {
            return;
        }

        Settings::ensureStateKey();
        $stateFromConfig = Settings::isStateKeyFromWpConfig();
        $manualSnippet   = Settings::stateKeyWpConfigSnippet();
        $wpConfigPath    = Settings::wpConfigPath();
        $buttonOrder     = \implode(', ', Settings::loginButtonOrder());
        ?>
        <div class="wrap">
            <h1><?php echo \esc_html__('OAuth Connect', 'wp-oauth-connect'); ?></h1>

            <?php if ($stateFromConfig) : ?>
                <div class="notice notice-success">
                    <p>
                        <?php
                        echo \esc_html__(
                            'OAUTH_STATE_KEY is ready. Enter each provider\'s Client ID and Client Secret below (from your LinkedIn / Google / GitHub developer app), then enable the provider.',
                            'wp-oauth-connect',
                        );
                        ?>
                    </p>
                </div>
            <?php elseif ($manualSnippet !== null) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        echo \esc_html__(
                            'Could not write OAUTH_STATE_KEY to wp-config.php automatically. Paste the following line into wp-config.php before the "That\'s all, stop editing!" comment, then reload this page.',
                            'wp-oauth-connect',
                        );
                        ?>
                    </p>
                    <?php if ($wpConfigPath !== null) : ?>
                        <p>
                            <strong><?php echo \esc_html__('File:', 'wp-oauth-connect'); ?></strong>
                            <code><?php echo \esc_html($wpConfigPath); ?></code>
                        </p>
                    <?php endif; ?>
                    <pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:960px;overflow:auto;"><code><?php echo \esc_html($manualSnippet); ?></code></pre>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php \settings_fields('wp_oauth_connect'); ?>

                <h2><?php echo \esc_html__('Providers', 'wp-oauth-connect'); ?></h2>
                <p class="description" style="max-width:960px;">
                    <?php
                    echo \esc_html__(
                        'The state signing key is separate from provider credentials. Each provider needs an OAuth app registered with the callback URL shown below.',
                        'wp-oauth-connect',
                    );
                    ?>
                </p>

                <?php foreach (Settings::builtinProviderSlugs() as $slug) :
                    $status           = Settings::providerAdminStatus($slug);
                    $statusLabel      = $this->statusLabel($status);
                    $fromWpConfig     = Settings::providerCredentialsFromWpConfig($slug);
                    $clientId         = Settings::providerClientId($slug);
                    $clientSecret     = Settings::providerClientSecret($slug);
                    $canEnable        = Settings::hasProviderCredentials($slug);
                    $idOptionKey      = Settings::providerClientIdOptionKey($slug);
                    $secretOptionKey    = Settings::providerClientSecretOptionKey($slug);
                    $enabledOptionKey = Settings::providerEnabledOptionKey($slug);
                    ?>
                    <div style="max-width:960px;margin:1.5em 0;padding:1em 1.25em;background:#fff;border:1px solid #c3c4c7;border-radius:4px;">
                        <h3 style="margin-top:0;">
                            <?php echo \esc_html(ucfirst($slug)); ?>
                            <span style="font-size:12px;font-weight:400;color:#646970;">— <?php echo \esc_html($statusLabel); ?></span>
                        </h3>
                        <p style="margin:0 0 1em;">
                            <strong><?php echo \esc_html__('Callback URL:', 'wp-oauth-connect'); ?></strong>
                            <code><?php echo \esc_html(\home_url('/oauth/' . $slug . '/callback')); ?></code>
                        </p>

                        <?php if ($fromWpConfig) : ?>
                            <p class="description">
                                <?php
                                echo \esc_html__(
                                    'Credentials are locked via wp-config.php constants for this provider.',
                                    'wp-oauth-connect',
                                );
                                ?>
                            </p>
                            <p>
                                <code><?php echo \esc_html(Settings::providerClientIdConstant($slug)); ?></code>
                                <?php if ($clientId !== '') : ?>
                                    — <?php echo \esc_html(Settings::maskSecret($clientId)); ?>
                                <?php endif; ?>
                                <br>
                                <code><?php echo \esc_html(Settings::providerClientSecretConstant($slug)); ?></code>
                                <?php if ($clientSecret !== '') : ?>
                                    — <?php echo \esc_html(Settings::maskSecret($clientSecret)); ?>
                                <?php endif; ?>
                            </p>
                        <?php else : ?>
                            <table class="form-table" role="presentation" style="margin-top:0;">
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo \esc_attr($idOptionKey); ?>">
                                            <?php echo \esc_html__('Client ID', 'wp-oauth-connect'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            class="regular-text"
                                            id="<?php echo \esc_attr($idOptionKey); ?>"
                                            name="<?php echo \esc_attr($idOptionKey); ?>"
                                            value="<?php echo \esc_attr($clientId); ?>"
                                            autocomplete="off"
                                        />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo \esc_attr($secretOptionKey); ?>">
                                            <?php echo \esc_html__('Client Secret', 'wp-oauth-connect'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="password"
                                            class="regular-text"
                                            id="<?php echo \esc_attr($secretOptionKey); ?>"
                                            name="<?php echo \esc_attr($secretOptionKey); ?>"
                                            value=""
                                            placeholder="<?php echo \esc_attr($clientSecret !== '' ? Settings::maskSecret($clientSecret) : ''); ?>"
                                            autocomplete="new-password"
                                        />
                                        <?php if ($clientSecret !== '') : ?>
                                            <p class="description">
                                                <?php
                                                echo \esc_html__(
                                                    'Leave blank to keep the current secret.',
                                                    'wp-oauth-connect',
                                                );
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        <?php endif; ?>

                        <label>
                            <input
                                type="checkbox"
                                name="<?php echo \esc_attr($enabledOptionKey); ?>"
                                value="1"
                                <?php \checked(Settings::isProviderEnabled($slug)); ?>
                                <?php \disabled(!$canEnable); ?>
                            />
                            <?php echo \esc_html__('Enable this provider on login and join pages', 'wp-oauth-connect'); ?>
                        </label>
                        <?php if (!$canEnable) : ?>
                            <p class="description" style="margin:.5em 0 0;">
                                <?php
                                echo \esc_html__(
                                    'Save a Client ID and Client Secret first — the enable checkbox unlocks after that.',
                                    'wp-oauth-connect',
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php $this->renderCustomProviderSection(); ?>

                <h2 style="margin-top: 2em;"><?php echo \esc_html__('Login button order', 'wp-oauth-connect'); ?></h2>
                <p>
                    <label for="woc-login-button-order">
                        <?php echo \esc_html__('Provider slugs (comma-separated)', 'wp-oauth-connect'); ?>
                    </label>
                </p>
                <input
                    type="text"
                    class="regular-text"
                    id="woc-login-button-order"
                    name="<?php echo \esc_attr(Settings::LOGIN_BUTTON_ORDER_OPTION); ?>"
                    value="<?php echo \esc_attr($buttonOrder); ?>"
                    placeholder="linkedin, google, github, custom"
                />
                <p class="description">
                    <?php
                    echo \esc_html__(
                        'Controls the order of OAuth buttons on login and join pages. Use slug custom for the custom provider below. Only operational providers appear.',
                        'wp-oauth-connect',
                    );
                    ?>
                </p>

                <h2 style="margin-top: 2em;"><?php echo \esc_html__('Login mode', 'wp-oauth-connect'); ?></h2>
                <fieldset>
                    <label>
                        <input
                            type="checkbox"
                            name="<?php echo \esc_attr(Settings::NATIVE_LOGIN_ENABLED_OPTION); ?>"
                            value="1"
                            <?php \checked(Settings::isNativeLoginEnabled()); ?>
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
        </div>
        <?php
    }

    private function registerProviderCredentialSettings(string $slug): void
    {
        \register_setting(
            'wp_oauth_connect',
            Settings::providerEnabledOptionKey($slug),
            [
                'type'              => 'string',
                'sanitize_callback' => static fn (mixed $value): string => $value === '1' ? '1' : '0',
                'default'           => '0',
            ],
        );

        \register_setting(
            'wp_oauth_connect',
            Settings::providerClientIdOptionKey($slug),
            [
                'type'              => 'string',
                'sanitize_callback' => static function (mixed $value) use ($slug): string {
                    if (Settings::providerCredentialsFromWpConfig($slug)) {
                        return (string) \get_option(Settings::providerClientIdOptionKey($slug), '');
                    }

                    return \sanitize_text_field((string) $value);
                },
                'default'           => '',
            ],
        );

        \register_setting(
            'wp_oauth_connect',
            Settings::providerClientSecretOptionKey($slug),
            [
                'type'              => 'string',
                'sanitize_callback' => static function (mixed $value) use ($slug): string {
                    if (Settings::providerCredentialsFromWpConfig($slug)) {
                        return (string) \get_option(Settings::providerClientSecretOptionKey($slug), '');
                    }

                    $incoming = \sanitize_text_field((string) $value);
                    if ($incoming === '') {
                        return (string) \get_option(Settings::providerClientSecretOptionKey($slug), '');
                    }

                    return $incoming;
                },
                'default'           => '',
            ],
        );
    }

    private function renderCustomProviderSection(): void
    {
        $slug             = CustomProviderSettings::SLUG;
        $status           = Settings::providerAdminStatus($slug);
        $statusLabel      = $this->statusLabel($status);
        $clientId         = Settings::providerClientId($slug);
        $clientSecret     = Settings::providerClientSecret($slug);
        $canEnable        = Settings::hasProviderCredentials($slug) && CustomProviderSettings::isDefined();
        $idOptionKey      = Settings::providerClientIdOptionKey($slug);
        $secretOptionKey  = Settings::providerClientSecretOptionKey($slug);
        $enabledOptionKey = Settings::providerEnabledOptionKey($slug);
        ?>
        <h2 style="margin-top:2em;"><?php echo \esc_html__('Custom provider (OIDC)', 'wp-oauth-connect'); ?></h2>
        <p class="description" style="max-width:960px;">
            <?php
            echo \esc_html__(
                'One extra OIDC identity provider (Okta, Auth0, Keycloak, corporate SSO). Slug is always custom — include custom in the button order field above.',
                'wp-oauth-connect',
            );
            ?>
        </p>
        <div style="max-width:960px;margin:1em 0;padding:1em 1.25em;background:#fff;border:1px solid #c3c4c7;border-radius:4px;">
            <h3 style="margin-top:0;">
                <?php echo \esc_html__('Custom', 'wp-oauth-connect'); ?>
                <span style="font-size:12px;font-weight:400;color:#646970;">— <?php echo \esc_html($statusLabel); ?></span>
            </h3>
            <p style="margin:0 0 1em;">
                <strong><?php echo \esc_html__('Callback URL:', 'wp-oauth-connect'); ?></strong>
                <code><?php echo \esc_html(\home_url('/oauth/custom/callback')); ?></code>
            </p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="woc-custom-label"><?php echo \esc_html__('Button label', 'wp-oauth-connect'); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            class="regular-text"
                            id="woc-custom-label"
                            name="<?php echo \esc_attr(CustomProviderSettings::LABEL_OPTION); ?>"
                            value="<?php echo \esc_attr(CustomProviderSettings::label()); ?>"
                            placeholder="<?php echo \esc_attr__('Continue with Acme SSO', 'wp-oauth-connect'); ?>"
                        />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woc-custom-icon-text"><?php echo \esc_html__('Icon text', 'wp-oauth-connect'); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            class="small-text"
                            id="woc-custom-icon-text"
                            name="<?php echo \esc_attr(CustomProviderSettings::ICON_TEXT_OPTION); ?>"
                            value="<?php echo \esc_attr(CustomProviderSettings::iconText()); ?>"
                            maxlength="3"
                            placeholder="A"
                        />
                        <p class="description">
                            <?php echo \esc_html__('Short text or emoji shown in the button icon circle (max 3 characters).', 'wp-oauth-connect'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woc-custom-icon-html"><?php echo \esc_html__('Icon HTML (optional)', 'wp-oauth-connect'); ?></label>
                    </th>
                    <td>
                        <textarea
                            class="large-text code"
                            rows="4"
                            id="woc-custom-icon-html"
                            name="<?php echo \esc_attr(CustomProviderSettings::ICON_HTML_OPTION); ?>"
                            placeholder="&lt;svg ...&gt;..."
                        ><?php echo \esc_textarea(CustomProviderSettings::iconHtmlRaw()); ?></textarea>
                        <p class="description">
                            <?php
                            echo \esc_html__(
                                'Inline SVG or small HTML for the icon. Overrides icon text when set. Allowed tags: span, svg, path, img.',
                                'wp-oauth-connect',
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woc-custom-issuer"><?php echo \esc_html__('OIDC issuer URL', 'wp-oauth-connect'); ?></label>
                    </th>
                    <td>
                        <input
                            type="url"
                            class="regular-text"
                            id="woc-custom-issuer"
                            name="<?php echo \esc_attr(CustomProviderSettings::ISSUER_OPTION); ?>"
                            value="<?php echo \esc_attr(CustomProviderSettings::issuer()); ?>"
                            placeholder="https://your-idp.example.com/oauth2/default"
                        />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woc-custom-scopes"><?php echo \esc_html__('Scopes', 'wp-oauth-connect'); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            class="regular-text"
                            id="woc-custom-scopes"
                            name="<?php echo \esc_attr(CustomProviderSettings::SCOPES_OPTION); ?>"
                            value="<?php echo \esc_attr((string) \get_option(CustomProviderSettings::SCOPES_OPTION, CustomProviderSettings::DEFAULT_SCOPES)); ?>"
                        />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="<?php echo \esc_attr($idOptionKey); ?>"><?php echo \esc_html__('Client ID', 'wp-oauth-connect'); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            class="regular-text"
                            id="<?php echo \esc_attr($idOptionKey); ?>"
                            name="<?php echo \esc_attr($idOptionKey); ?>"
                            value="<?php echo \esc_attr($clientId); ?>"
                            autocomplete="off"
                        />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="<?php echo \esc_attr($secretOptionKey); ?>"><?php echo \esc_html__('Client Secret', 'wp-oauth-connect'); ?></label>
                    </th>
                    <td>
                        <input
                            type="password"
                            class="regular-text"
                            id="<?php echo \esc_attr($secretOptionKey); ?>"
                            name="<?php echo \esc_attr($secretOptionKey); ?>"
                            value=""
                            placeholder="<?php echo \esc_attr($clientSecret !== '' ? Settings::maskSecret($clientSecret) : ''); ?>"
                            autocomplete="new-password"
                        />
                    </td>
                </tr>
            </table>
            <label>
                <input
                    type="checkbox"
                    name="<?php echo \esc_attr($enabledOptionKey); ?>"
                    value="1"
                    <?php \checked(Settings::isProviderEnabled($slug)); ?>
                    <?php \disabled(!$canEnable); ?>
                />
                <?php echo \esc_html__('Enable custom provider on login and join pages', 'wp-oauth-connect'); ?>
            </label>
            <?php if (!$canEnable) : ?>
                <p class="description" style="margin:.5em 0 0;">
                    <?php
                    echo \esc_html__(
                        'Fill in button label, OIDC issuer URL, Client ID, and Client Secret — then save before enabling.',
                        'wp-oauth-connect',
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'missing_creds'     => __('Credentials not configured', 'wp-oauth-connect'),
            'disabled'          => __('Configured, disabled', 'wp-oauth-connect'),
            'ready'             => __('Ready', 'wp-oauth-connect'),
            default             => __('Unknown', 'wp-oauth-connect'),
        };
    }
}