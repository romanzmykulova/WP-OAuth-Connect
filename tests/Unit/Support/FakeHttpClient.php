<?php
declare(strict_types=1);

namespace WpOAuthConnect\Tests\Unit\Support;

use PHPUnit\Framework\Assert;
use WpOAuthConnect\Support\HttpClient;
use WpOAuthConnect\Support\HttpResponse;

final class FakeHttpClient implements HttpClient
{
    /**
     * @param array<string, HttpResponse> $getFixtures
     * @param array<string, HttpResponse> $postFixtures
     */
    public function __construct(
        private readonly array $getFixtures = [],
        private readonly array $postFixtures = [],
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): HttpResponse
    {
        if (!isset($this->getFixtures[$url])) {
            Assert::fail(sprintf(
                'FakeHttpClient: no GET fixture for %s (have: %s)',
                $url,
                implode(', ', array_keys($this->getFixtures)) ?: '(none)',
            ));
        }
        return $this->getFixtures[$url];
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $body
     */
    public function post(string $url, array $headers, array $body): HttpResponse
    {
        if (!isset($this->postFixtures[$url])) {
            Assert::fail(sprintf(
                'FakeHttpClient: no POST fixture for %s (have: %s)',
                $url,
                implode(', ', array_keys($this->postFixtures)) ?: '(none)',
            ));
        }
        return $this->postFixtures[$url];
    }
}