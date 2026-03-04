<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Transport\Http;

use GalatanOvidiu\PhpMcpClient\Exception\TransportException;

/**
 * Exception thrown when HTTP client operations fail.
 *
 * Extends TransportException as HTTP errors are a specific type
 * of transport layer failure.
 *
 * @since n.e.x.t
 */
class HttpClientException extends TransportException
{
    /**
     * The HTTP status code, if available.
     */
    private ?int $status_code;

    /**
     * Create a new HTTP client exception.
     *
     * @param string          $message     The exception message.
     * @param int|null        $status_code The HTTP status code, if applicable.
     * @param int             $code        The exception code.
     * @param \Throwable|null $previous    The previous throwable for chaining.
     */
    public function __construct(
        string $message,
        ?int $status_code = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->status_code = $status_code;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int|null The HTTP status code, or null if not applicable.
     */
    public function getStatusCode(): ?int
    {
        return $this->status_code;
    }

    /**
     * Create an exception for a connection failure.
     *
     * @param string          $url      The URL that could not be reached.
     * @param \Throwable|null $previous The previous throwable for chaining.
     *
     * @return self
     */
    public static function connectionFailed(string $url, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Failed to connect to %s', $url),
            null,
            0,
            $previous
        );
    }

    /**
     * Create an exception for an unexpected HTTP status.
     *
     * @param int    $status_code The unexpected status code.
     * @param string $body        The response body for context.
     *
     * @return self
     */
    public static function unexpectedStatus(int $status_code, string $body = ''): self
    {
        $message = sprintf('Unexpected HTTP status code: %d', $status_code);

        if ($body !== '') {
            $message .= sprintf(' - %s', substr($body, 0, 200));
        }

        return new self($message, $status_code);
    }

    /**
     * Create an exception for a cURL error.
     *
     * @param int    $error_code    The cURL error code.
     * @param string $error_message The cURL error message.
     *
     * @return self
     */
    public static function curlError(int $error_code, string $error_message): self
    {
        return new self(
            sprintf('cURL error %d: %s', $error_code, $error_message),
            null,
            $error_code
        );
    }
}
