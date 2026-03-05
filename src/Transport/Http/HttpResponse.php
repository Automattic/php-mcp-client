<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Transport\Http;

/**
 * Immutable value object representing an HTTP response.
 *
 * Provides access to status code, body, and headers with
 * helper methods for content type detection.
 *
 * @since n.e.x.t
 */
final class HttpResponse
{
    private int $status_code;

    private string $body;

    /**
     * Headers stored with lowercase keys for case-insensitive lookup.
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Original headers with their original case preserved.
     *
     * @var array<string, string>
     */
    private array $original_headers;

    /**
     * Create a new HTTP response.
     *
     * @param int                   $status_code The HTTP status code.
     * @param string                $body        The response body.
     * @param array<string, string> $headers     The response headers.
     */
    public function __construct(int $status_code, string $body, array $headers = [])
    {
        $this->status_code      = $status_code;
        $this->body             = $body;
        $this->original_headers = $headers;
        $this->headers          = $this->normalizeHeaders($headers);
    }

    /**
     * Get the HTTP status code.
     *
     * @return int The status code.
     */
    public function getStatusCode(): int
    {
        return $this->status_code;
    }

    /**
     * Get the response body.
     *
     * @return string The response body.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get all headers with their original case.
     *
     * @return array<string, string> The response headers.
     */
    public function getHeaders(): array
    {
        return $this->original_headers;
    }

    /**
     * Get a header value by name (case-insensitive).
     *
     * @param string $name The header name.
     *
     * @return string|null The header value, or null if not found.
     */
    public function getHeader(string $name): ?string
    {
        $normalized_name = strtolower($name);

        return $this->headers[ $normalized_name ] ?? null;
    }

    /**
     * Check if the response has a JSON content type.
     *
     * Detects 'application/json' content type, including when
     * additional parameters like charset are present.
     *
     * @return bool True if the content type is JSON.
     */
    public function isJson(): bool
    {
        $content_type = $this->getHeader('Content-Type');

        if ($content_type === null) {
            return false;
        }

        // Extract the media type (before any semicolon)
        $media_type = strtolower(trim(explode(';', $content_type)[0]));

        return $media_type === 'application/json';
    }

    /**
     * Normalize headers to lowercase keys for case-insensitive lookup.
     *
     * @param array<string, string> $headers The original headers.
     *
     * @return array<string, string> Headers with lowercase keys.
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[ strtolower($name) ] = $value;
        }

        return $normalized;
    }
}
