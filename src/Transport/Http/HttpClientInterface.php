<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Transport\Http;

/**
 * Contract for HTTP client implementations.
 *
 * Provides a minimal, implementation-agnostic interface for HTTP operations
 * required by the MCP HTTP transport. Implementations may use cURL, Guzzle,
 * or any other HTTP library.
 *
 * @since n.e.x.t
 */
interface HttpClientInterface
{
    /**
     * Send a POST request.
     *
     * @param string               $url     The URL to send the request to.
     * @param string               $body    The request body content.
     * @param array<string, string> $headers The request headers.
     *
     * @return HttpResponse The HTTP response.
     *
     * @throws HttpClientException When the request fails.
     */
    public function post(string $url, string $body, array $headers): HttpResponse;

    /**
     * Send a DELETE request.
     *
     * Used for closing MCP sessions cleanly.
     *
     * @param string               $url     The URL to send the request to.
     * @param array<string, string> $headers The request headers.
     *
     * @throws HttpClientException When the request fails.
     */
    public function delete(string $url, array $headers): void;
}
