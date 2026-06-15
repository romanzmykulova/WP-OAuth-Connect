<?php
declare(strict_types=1);

/**
 * Minimal WordPress shims for unit tests. function_exists-guarded so
 * integration suites can re-bind without collision.
 *
 * Control globals:
 *   $GLOBALS['woc_test_options']     — get_option store
 *   $GLOBALS['woc_test_filters']     — tag => list<callable>
 *   $GLOBALS['woc_test_actions']     — tag => list<callable>
 *   $GLOBALS['woc_test_users']        — user id => WP_User
 *   $GLOBALS['woc_test_user_meta']    — user id => meta_key => value
 *   $GLOBALS['woc_test_transients']   — transient key => value
 *   $GLOBALS['woc_test_redirect_url'] — last wp_safe_redirect target
 */

if (!class_exists('WP_User')) {
    class WP_User
    {
        public int $ID = 0;
        public string $user_login = '';
        public string $user_email = '';
        public string $display_name = '';

        public function __construct(int $id = 0)
        {
            $this->ID = $id;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            public string $code = '',
            public string $message = '',
            public mixed $data = null,
        ) {}
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const CREATABLE = 'POST';
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['woc_test_options'][$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, mixed $value, mixed $autoload = null): bool
    {
        $GLOBALS['woc_test_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        unset($GLOBALS['woc_test_options'][$option]);
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
    {
        foreach ($GLOBALS['woc_test_filters'][$tag] ?? [] as $callback) {
            $value = $callback($value, ...$args);
        }
        return $value;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): true
    {
        $GLOBALS['woc_test_filters'][$tag][] = $callback;
        return true;
    }
}

if (!function_exists('remove_all_filters')) {
    function remove_all_filters(string $tag, int $priority = 10): true
    {
        unset($GLOBALS['woc_test_filters'][$tag]);
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $tag, mixed ...$args): void
    {
        foreach ($GLOBALS['woc_test_actions'][$tag] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}

if (!function_exists('add_action')) {
    function add_action(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): true
    {
        $GLOBALS['woc_test_actions'][$tag][] = $callback;
        return true;
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl(): bool
    {
        return (bool) ($GLOBALS['woc_test_is_ssl'] ?? false);
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        $base = $GLOBALS['woc_test_home_url'] ?? 'https://remotejobs.team';
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1): mixed
    {
        return parse_url($url, $component);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return $url;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        return $value;
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect(string $url, int $status = 302): never
    {
        $GLOBALS['woc_test_redirect_url'] = $url;
        throw new RuntimeException('redirect:' . $url);
    }
}

if (!function_exists('wp_redirect')) {
    function wp_redirect(string $url, int $status = 302): never
    {
        $GLOBALS['woc_test_redirect_url'] = $url;
        throw new RuntimeException('redirect:' . $url);
    }
}

if (!function_exists('wp_die')) {
    function wp_die(string|WP_Error $message = '', string $title = '', array|string $args = []): never
    {
        $text = is_string($message) ? $message : $message->message;
        throw new RuntimeException('wp_die:' . $text);
    }
}

if (!function_exists('get_users')) {
    function get_users(array $args = []): array
    {
        $metaKey   = $args['meta_key'] ?? '';
        $metaValue = $args['meta_value'] ?? '';
        $matches   = [];

        foreach ($GLOBALS['woc_test_users'] ?? [] as $user) {
            if ($metaKey === '') {
                $matches[] = $user;
                continue;
            }

            $stored = $GLOBALS['woc_test_user_meta'][$user->ID][$metaKey] ?? null;
            if ((string) $stored === (string) $metaValue) {
                $matches[] = $user;
            }
        }

        $number = isset($args['number']) ? (int) $args['number'] : 0;
        if ($number > 0) {
            return array_slice($matches, 0, $number);
        }

        return $matches;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by(string $field, mixed $value): WP_User|false
    {
        foreach ($GLOBALS['woc_test_users'] ?? [] as $user) {
            if ($field === 'id' && (int) $user->ID === (int) $value) {
                return $user;
            }
            if ($field === 'email' && strcasecmp($user->user_email, (string) $value) === 0) {
                return $user;
            }
            if ($field === 'login' && strcasecmp($user->user_login, (string) $value) === 0) {
                return $user;
            }
        }

        return false;
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta(int $userId, string $key, mixed $value): bool
    {
        $GLOBALS['woc_test_user_meta'][$userId][$key] = $value;
        return true;
    }
}

if (!function_exists('username_exists')) {
    function username_exists(string $login): int|false
    {
        foreach ($GLOBALS['woc_test_users'] ?? [] as $user) {
            if (strcasecmp($user->user_login, $login) === 0) {
                return $user->ID;
            }
        }

        return false;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special = true, bool $extra = false): string
    {
        return 'generated-password-' . $length;
    }
}

if (!function_exists('wp_insert_user')) {
    function wp_insert_user(array $userdata): int|WP_Error
    {
        $nextId = count($GLOBALS['woc_test_users'] ?? []) + 1;
        $user   = new WP_User($nextId);
        $user->user_login   = (string) ($userdata['user_login'] ?? 'user' . $nextId);
        $user->user_email   = (string) ($userdata['user_email'] ?? '');
        $user->display_name = (string) ($userdata['display_name'] ?? $user->user_login);
        $GLOBALS['woc_test_users'][$nextId] = $user;
        return $nextId;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_set_auth_cookie')) {
    function wp_set_auth_cookie(int $userId, bool $remember = false, bool $secure = false): void
    {
        $GLOBALS['woc_test_auth_user_id'] = $userId;
    }
}

if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user(int $userId): WP_User
    {
        $user = get_user_by('id', $userId);
        $GLOBALS['woc_test_current_user_id'] = $userId;
        return $user instanceof WP_User ? $user : new WP_User($userId);
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['woc_test_transients'][$transient] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $transient): mixed
    {
        return $GLOBALS['woc_test_transients'][$transient] ?? false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool
    {
        unset($GLOBALS['woc_test_transients'][$transient]);
        return true;
    }
}

if (!function_exists('wp_authenticate')) {
    function wp_authenticate(string $username, string $password): WP_User|WP_Error
    {
        $expected = $GLOBALS['woc_test_passwords'][$username] ?? null;
        if ($expected === null || $expected !== $password) {
            return new WP_Error('invalid', 'Invalid credentials.');
        }

        $user = get_user_by('login', $username);
        return $user instanceof WP_User ? $user : new WP_Error('invalid', 'Invalid credentials.');
    }
}

if (!function_exists('__')) {
    function __(string $text, ?string $domain = null): string
    {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return htmlspecialchars(filter_var($url, FILTER_SANITIZE_URL) ?: '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses(string $string, array $allowedHtml): string
    {
        return strip_tags($string, '<span><svg><path><g><circle><rect><img>');
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null): string
    {
        return $length === null ? substr($string, $start) : substr($string, $start, $length);
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, ?string $domain = null): string
    {
        return esc_html(__($text, $domain));
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user(string $username, bool $strict = false): string
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $username) ?? '');
    }
}