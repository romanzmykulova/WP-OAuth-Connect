<?php
declare(strict_types=1);

/**
 * WordPress wp_remote_* backed HttpClient for production requests.
 */

namespace WpOAuthConnect\Support;

final class WpHttpClient implements HttpClient
{
    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): HttpResponse
    {
        $response = \wp_remote_get($url, ['headers' => $headers, 'timeout' => 15]);
        return $this->normalize($response);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $body
     */
    public function post(string $url, array $headers, array $body): HttpResponse
    {
        $response = \wp_remote_post($url, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 15,
        ]);
        return $this->normalize($response);
    }

    /**
     * @param array<string, mixed>|\WP_Error $response
     */
    private function normalize(array|\WP_Error $response): HttpResponse
    {
        if ($response instanceof \WP_Error) {
            return new HttpResponse(0, $response->get_error_message());
        }

        $status  = (int) \wp_remote_retrieve_response_code($response);
        $body    = (string) \wp_remote_retrieve_body($response);
        $headers = [];

        $rawHeaders = \wp_remote_retrieve_headers($response);
        if ($rawHeaders instanceof \Requests_Utility_CaseInsensitiveDictionary) {
            foreach ($rawHeaders->getAll() as $name => $value) {
                $headers[(string) $name] = is_array($value) ? implode(', ', $value) : (string) $value;
            }
        }

        return new HttpResponse($status, $body, $headers);
    }
}