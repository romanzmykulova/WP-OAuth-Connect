<?php
declare(strict_types=1);

/**
 * HMAC-signed OAuth state: CSRF protection + extensible signed payload.
 */

namespace WpOAuthConnect;

use WpOAuthConnect\Exceptions\OAuthStateExpired;
use WpOAuthConnect\Exceptions\OAuthStateInvalid;
use WpOAuthConnect\Options\Settings;

final class OAuthState
{
    public const TTL_SECONDS = 600;

    public const CSRF_COOKIE_NAME = 'woc_oauth_csrf';

    /**
     * @param array<string, mixed> $extensions Site-specific keys merged before signing.
     * @param array<string, mixed> $context    Passed to woc_oauth_state_payload.
     *
     * @return array{state: string, csrf: string}
     */
    public function create(string $provider, array $extensions = [], array $context = []): array
    {
        $csrf = bin2hex(random_bytes(16));
        $payload = [
            'csrf'       => $csrf,
            'issued_at'  => time(),
            'provider'   => $provider,
        ];

        foreach ($extensions as $key => $value) {
            $payload[(string) $key] = $value;
        }

        $payload = \apply_filters('woc_oauth_state_payload', $payload, $provider, $context);

        return [
            'state' => $this->sign($payload),
            'csrf'  => $csrf,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $state, string $expectedProvider): array
    {
        $key = Settings::stateKey();
        if ($key === '') {
            throw new OAuthStateInvalid('OAUTH_STATE_KEY is not configured.');
        }

        $parts = explode('.', $state, 2);
        if (count($parts) !== 2) {
            throw new OAuthStateInvalid('Malformed state token.');
        }

        [$encodedPayload, $signature] = $parts;
        $expectedSignature = $this->hmac($encodedPayload, $key);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new OAuthStateInvalid('State signature mismatch.');
        }

        $json = $this->base64UrlDecode($encodedPayload);
        if ($json === false) {
            throw new OAuthStateInvalid('State payload is not valid base64url.');
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            throw new OAuthStateInvalid('State payload is not valid JSON.');
        }

        $issuedAt = isset($payload['issued_at']) ? (int) $payload['issued_at'] : 0;
        if ($issuedAt <= 0 || (time() - $issuedAt) > self::TTL_SECONDS) {
            throw new OAuthStateExpired('State token expired.');
        }

        $provider = isset($payload['provider']) ? (string) $payload['provider'] : '';
        if ($provider !== $expectedProvider) {
            throw new OAuthStateInvalid('State provider mismatch.');
        }

        $csrf = isset($payload['csrf']) ? (string) $payload['csrf'] : '';
        $cookieCsrf = isset($_COOKIE[self::CSRF_COOKIE_NAME])
            ? (string) $_COOKIE[self::CSRF_COOKIE_NAME]
            : '';

        if ($csrf === '' || $cookieCsrf === '' || !hash_equals($csrf, $cookieCsrf)) {
            throw new OAuthStateInvalid('CSRF validation failed.');
        }

        return $payload;
    }

    public function setCsrfCookie(string $csrf): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(
            self::CSRF_COOKIE_NAME,
            $csrf,
            [
                'expires'  => time() + self::TTL_SECONDS,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ],
        );
    }

    public function clearCsrfCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(
            self::CSRF_COOKIE_NAME,
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sign(array $payload): string
    {
        $key = Settings::stateKey();
        if ($key === '') {
            throw new OAuthStateInvalid('OAUTH_STATE_KEY is not configured.');
        }

        $encoded = $this->base64UrlEncode((string) json_encode($payload, JSON_THROW_ON_ERROR));
        return $encoded . '.' . $this->hmac($encoded, $key);
    }

    private function hmac(string $encodedPayload, string $key): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $key, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string|false
    {
        $padded = strtr($data, '-_', '+/');
        $mod    = strlen($padded) % 4;
        if ($mod > 0) {
            $padded .= str_repeat('=', 4 - $mod);
        }
        return base64_decode($padded, true);
    }
}