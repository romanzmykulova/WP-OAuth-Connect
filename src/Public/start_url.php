<?php
declare(strict_types=1);

/**
 * Builds OAuth start URLs with optional invite/next context query params.
 */

if (!function_exists('oauth_start_url')) {
    /**
     * @param array<string, mixed> $context
     */
    function oauth_start_url(string $provider, array $context = []): string
    {
        $url = \home_url('/oauth/' . rawurlencode($provider) . '/start');
        $query = [];

        if (isset($context['invite']) && is_string($context['invite']) && $context['invite'] !== '') {
            $query['invite'] = $context['invite'];
        }

        if (isset($context['next']) && is_string($context['next']) && $context['next'] !== '') {
            $query['next'] = $context['next'];
        }

        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}