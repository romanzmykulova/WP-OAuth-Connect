<?php
declare(strict_types=1);

/**
 * Minimal WordPress shims for unit tests. function_exists-guarded so
 * integration suites can re-bind without collision.
 *
 * Control globals:
 *   $GLOBALS['woc_test_options']  — get_option store
 *   $GLOBALS['woc_test_filters']   — tag => list<callable>
 *   $GLOBALS['woc_test_actions']   — tag => list<callable>
 */

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