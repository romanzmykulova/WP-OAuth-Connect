<?php
declare(strict_types=1);

/**
 * Normalized HTTP response from HttpClient.
 */

namespace WpOAuthConnect\Support;

final readonly class HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}