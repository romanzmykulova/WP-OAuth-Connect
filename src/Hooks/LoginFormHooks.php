<?php
declare(strict_types=1);

/**
 * Renders OAuth provider buttons on the native wp-login.php form so a standalone
 * install works out of the box — no companion plugin or theme code required.
 *
 * It hooks the login-page RENDER events (login_form / login_enqueue_scripts)
 * instead of detecting the login URL, so it stays compatible when the login
 * path is relocated by WPS Hide Login, a custom slug, or the server's
 * login-rename mu-plugin: those serve the genuine wp-login.php, which still
 * fires these hooks wherever it is mounted.
 */

namespace WpOAuthConnect\Hooks;

final class LoginFormHooks
{
    private const STYLE_HANDLE = 'woc-oauth-login';

    public function __construct(private readonly string $pluginFile) {}

    public function register(): void
    {
        \add_action('login_enqueue_scripts', [$this, 'enqueueStyles']);
        \add_action('login_form', [$this, 'renderButtons']);
    }

    public function enqueueStyles(): void
    {
        if (!$this->shouldRender() || $this->buttons([]) === []) {
            return;
        }

        \wp_enqueue_style(
            self::STYLE_HANDLE,
            \plugins_url('assets/css/login.css', $this->pluginFile),
            [],
            '0.1.0',
        );
    }

    public function renderButtons(): void
    {
        if (!$this->shouldRender()) {
            return;
        }

        $buttons = $this->buttons($this->context());
        if ($buttons === []) {
            return;
        }

        echo '<div class="woc-oauth-login">';
        echo '<div class="woc-oauth-login__divider"><span>'
            . \esc_html__('or', 'wp-oauth-connect')
            . '</span></div>';

        foreach ($buttons as $button) {
            $this->renderButton($button);
        }

        echo '</div>';
    }

    /**
     * @param array<string, mixed> $button
     */
    private function renderButton(array $button): void
    {
        $url   = isset($button['url']) ? (string) $button['url'] : '';
        $label = isset($button['label']) ? (string) $button['label'] : '';
        if ($url === '') {
            return;
        }

        $cssClass = isset($button['css_class']) && $button['css_class'] !== ''
            ? (string) $button['css_class']
            : 'oauth-btn';

        // icon_html is a trusted descriptor field, sanitised at its source
        // (provider preset / admin-sanitised custom icon), so it is emitted as-is.
        $iconHtml = isset($button['icon_html']) ? (string) $button['icon_html'] : '';

        printf(
            '<a class="%1$s" href="%2$s">%3$s<span class="oauth-btn__label">%4$s</span></a>',
            \esc_attr($cssClass),
            \esc_url($url),
            $iconHtml,
            \esc_html($label),
        );
    }

    /**
     * Companions that render their own OAuth UI opt out by returning false.
     */
    private function shouldRender(): bool
    {
        return (bool) \apply_filters('woc_oauth_render_login_form', true);
    }

    /**
     * Carry wp-login.php's redirect_to through as the same-host post-auth next.
     *
     * @return array<string, mixed>
     */
    private function context(): array
    {
        $context = ['surface' => 'wp_login'];

        if (isset($_REQUEST['redirect_to'])) {
            $redirectTo = \esc_url_raw((string) \wp_unslash($_REQUEST['redirect_to']));
            if ($redirectTo !== '') {
                $context['next'] = $redirectTo;
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<array<string, mixed>>
     */
    private function buttons(array $context): array
    {
        /** @var list<array<string, mixed>> $buttons */
        $buttons = (array) \apply_filters('woc_oauth_login_buttons', [], $context);
        return $buttons;
    }
}
