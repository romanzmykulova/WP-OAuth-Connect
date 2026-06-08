<?php
declare(strict_types=1);

/**
 * Injectable HTTP boundary for provider token/profile fetches.
 */

namespace WpOAuthConnect\Support;

interface HttpClient
{
    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): HttpResponse;

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $body
     */
    public function post(string $url, array $headers, array $body): HttpResponse;
}