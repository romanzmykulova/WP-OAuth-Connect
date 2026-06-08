<?php
declare(strict_types=1);

/**
 * Admin-configured custom OIDC provider (single slot, slug `custom`).
 */

namespace WpOAuthConnect\Options;

final class CustomProviderSettings
{
    public const SLUG = 'custom';

    public const LABEL_OPTION = 'woc_oauth_custom_label';

    public const ICON_TEXT_OPTION = 'woc_oauth_custom_icon_text';

    public const ICON_HTML_OPTION = 'woc_oauth_custom_icon_html';

    public const ISSUER_OPTION = 'woc_oauth_custom_issuer';

    public const SCOPES_OPTION = 'woc_oauth_custom_scopes';

    public const DEFAULT_SCOPES = 'openid,email,profile';

    public static function label(): string
    {
        return \trim((string) \get_option(self::LABEL_OPTION, ''));
    }

    public static function iconText(): string
    {
        return \trim((string) \get_option(self::ICON_TEXT_OPTION, ''));
    }

    public static function iconHtmlRaw(): string
    {
        return \trim((string) \get_option(self::ICON_HTML_OPTION, ''));
    }

    public static function issuer(): string
    {
        return \trim((string) \get_option(self::ISSUER_OPTION, ''));
    }

    /**
     * @return list<string>
     */
    public static function scopes(): array
    {
        $raw = (string) \get_option(self::SCOPES_OPTION, '');
        if ($raw === '') {
            $raw = self::DEFAULT_SCOPES;
        }

        /** @var list<string> $parts */
        $parts = \preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $parts !== [] ? $parts : ['openid', 'email', 'profile'];
    }

    public static function isDefined(): bool
    {
        return self::label() !== '' && self::issuer() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function definitionArray(): array
    {
        return [
            'slug'   => self::SLUG,
            'label'  => self::label(),
            'engine' => 'oidc',
            'issuer' => self::issuer(),
            'scopes' => self::scopes(),
        ];
    }

    public static function buttonIconHtml(): string
    {
        $storedHtml = self::iconHtmlRaw();
        if ($storedHtml !== '') {
            return self::sanitizeIconHtml($storedHtml);
        }

        $iconText = self::iconText();
        if ($iconText === '') {
            return '';
        }

        return '<span class="oauth-btn__icon" aria-hidden="true">'
            . \esc_html(\mb_substr($iconText, 0, 3))
            . '</span>';
    }

    public static function sanitizeIconHtml(string $html): string
    {
        $allowed = [
            'span' => ['class' => true, 'aria-hidden' => true, 'role' => true],
            'svg'  => [
                'class' => true, 'width' => true, 'height' => true, 'viewBox' => true,
                'fill' => true, 'xmlns' => true, 'role' => true, 'aria-hidden' => true,
            ],
            'path' => ['d' => true, 'fill' => true],
            'g'    => ['fill' => true],
            'circle' => ['cx' => true, 'cy' => true, 'r' => true, 'fill' => true],
            'rect' => ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'fill' => true, 'rx' => true],
            'img'  => ['src' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true],
        ];

        return (string) \wp_kses($html, $allowed);
    }
}