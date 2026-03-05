<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Tests\Unit\Transport\Http;

use Automattic\PhpMcpClient\Transport\Http\HttpClientException;
use Automattic\PhpMcpClient\Transport\Http\HttpClientInterface;
use Automattic\PhpMcpClient\Transport\Http\HttpResponse;

/**
 * Mock HTTP client for testing HttpTransport without network calls.
 *
 * Provides ability to:
 * - Queue responses for sequential return
 * - Record all requests for assertions
 * - Simulate transport exceptions
 *
 * @since n.e.x.t
 */
final class MockHttpClient implements HttpClientInterface
{
    /**
     * Queued responses to return from post() calls.
     *
     * @var HttpResponse[]
     */
    private array $queued_responses = [];

    /**
     * Exception to throw on next request.
     */
    private ?HttpClientException $exception_to_throw = null;

    /**
     * Recorded requests for verification.
     *
     * @var array<int, array{method: string, url: string, body: string, headers: array<string, string>}>
     */
    private array $recorded_requests = [];

    /**
     * Queue a response to be returned from the next post() call.
     *
     * Responses are returned in FIFO order.
     *
     * @param HttpResponse $response The response to queue.
     *
     * @return self For method chaining.
     */
    public function queueResponse(HttpResponse $response): self
    {
        $this->queued_responses[] = $response;

        return $this;
    }

    /**
     * Queue multiple responses at once.
     *
     * @param HttpResponse ...$responses The responses to queue.
     *
     * @return self For method chaining.
     */
    public function queueResponses(HttpResponse ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queued_responses[] = $response;
        }

        return $this;
    }

    /**
     * Configure the mock to throw an exception on the next request.
     *
     * @param HttpClientException $exception The exception to throw.
     *
     * @return self For method chaining.
     */
    public function throwOnNextRequest(HttpClientException $exception): self
    {
        $this->exception_to_throw = $exception;

        return $this;
    }

    /**
     * Get all recorded requests.
     *
     * @return array<int, array{method: string, url: string, body: string, headers: array<string, string>}>
     */
    public function getRequests(): array
    {
        return $this->recorded_requests;
    }

    /**
     * Get the last recorded request.
     *
     * @return array{method: string, url: string, body: string, headers: array<string, string>}|null
     */
    public function getLastRequest(): ?array
    {
        if (count($this->recorded_requests) === 0) {
            return null;
        }

        return $this->recorded_requests[ count($this->recorded_requests) - 1 ];
    }

    /**
     * Get the number of requests made.
     *
     * @return int The request count.
     */
    public function getRequestCount(): int
    {
        return count($this->recorded_requests);
    }

    /**
     * Clear all recorded requests.
     *
     * @return self For method chaining.
     */
    public function clearRequests(): self
    {
        $this->recorded_requests = [];

        return $this;
    }

    /**
     * Reset the mock to its initial state.
     *
     * Clears queued responses, recorded requests, and exception configuration.
     *
     * @return self For method chaining.
     */
    public function reset(): self
    {
        $this->queued_responses   = [];
        $this->recorded_requests  = [];
        $this->exception_to_throw = null;

        return $this;
    }

    /**
     * Check if there are queued responses remaining.
     *
     * @return bool True if responses are queued.
     */
    public function hasQueuedResponses(): bool
    {
        return count($this->queued_responses) > 0;
    }

    /**
     * {@inheritDoc}
     *
     * @throws HttpClientException When configured to throw or when no responses are queued.
     */
    public function post(string $url, string $body, array $headers): HttpResponse
    {
        $this->recordRequest('POST', $url, $body, $headers);

        if ($this->exception_to_throw !== null) {
            $exception                = $this->exception_to_throw;
            $this->exception_to_throw = null;
            throw $exception;
        }

        if (count($this->queued_responses) === 0) {
            throw new HttpClientException(
                'MockHttpClient: No responses queued. Call queueResponse() before making requests.'
            );
        }

        return array_shift($this->queued_responses);
    }

    /**
     * {@inheritDoc}
     *
     * @throws HttpClientException When configured to throw.
     */
    public function delete(string $url, array $headers): void
    {
        $this->recordRequest('DELETE', $url, '', $headers);

        if ($this->exception_to_throw !== null) {
            $exception                = $this->exception_to_throw;
            $this->exception_to_throw = null;
            throw $exception;
        }
    }

    /**
     * Record a request for later verification.
     *
     * @param string               $method  The HTTP method.
     * @param string               $url     The request URL.
     * @param string               $body    The request body.
     * @param array<string, string> $headers The request headers.
     */
    private function recordRequest(string $method, string $url, string $body, array $headers): void
    {
        $this->recorded_requests[] = [
            'method'  => $method,
            'url'     => $url,
            'body'    => $body,
            'headers' => $headers,
        ];
    }
}
