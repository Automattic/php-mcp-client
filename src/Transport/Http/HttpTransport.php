<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Transport\Http;

use GalatanOvidiu\PhpMcpClient\Exception\TransportException;
use GalatanOvidiu\PhpMcpClient\Transport\AbstractTransport;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP transport implementation for MCP.
 *
 * Communicates with an MCP server over HTTP using JSON-RPC messages.
 * Manages session state via the `Mcp-Session-Id` header.
 *
 * @since n.e.x.t
 */
class HttpTransport extends AbstractTransport
{
    /**
     * The MCP server endpoint URL.
     */
    private string $endpoint_url;

    /**
     * HTTP client for making requests.
     */
    private HttpClientInterface $http_client;

    /**
     * PSR-3 logger for debugging.
     */
    private LoggerInterface $logger;

    /**
     * Session ID from the server.
     */
    private ?string $session_id = null;

    /**
     * Buffered response from last send() call.
     */
    private ?string $buffered_response = null;

    /**
     * Custom headers to include in all requests.
     *
     * @var array<string, string>
     */
    private array $custom_headers = [];

    /**
     * Create a new HTTP transport.
     *
     * @param string                   $endpoint_url   The MCP server endpoint URL.
     * @param HttpClientInterface|null $http_client    HTTP client (optional, defaults to CurlHttpClient).
     * @param LoggerInterface|null     $logger         PSR-3 logger for debugging (optional).
     * @param array<string, string>    $custom_headers Custom headers to include in all requests (e.g., Authorization).
     */
    public function __construct(
        string $endpoint_url,
        ?HttpClientInterface $http_client = null,
        ?LoggerInterface $logger = null,
        array $custom_headers = []
    ) {
        $this->endpoint_url   = $endpoint_url;
        $this->http_client    = $http_client ?? new CurlHttpClient();
        $this->logger         = $logger ?? new NullLogger();
        $this->custom_headers = $custom_headers;
    }

    /**
     * {@inheritDoc}
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $this->validateUrl($this->endpoint_url);

        $this->logger->debug('HTTP transport connected', [ 'endpoint' => $this->endpoint_url ]);

        $this->connected = true;
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect(): void
    {
        if (! $this->connected) {
            return;
        }

        // Send DELETE to close session if we have a session ID
        if ($this->session_id !== null) {
            $this->closeSession();
        }

        $this->session_id        = null;
        $this->buffered_response = null;
        $this->connected         = false;

        $this->logger->debug('HTTP transport disconnected');
    }

    /**
     * {@inheritDoc}
     */
    public function send(string $message): void
    {
        if (! $this->connected) {
            throw new TransportException('Transport is not connected');
        }

        $headers = $this->buildRequestHeaders();

        $this->logger->debug('Sending HTTP request', [
            'endpoint'   => $this->endpoint_url,
            'session_id' => $this->session_id,
            'message'    => $message,
        ]);

        try {
            $response = $this->http_client->post($this->endpoint_url, $message, $headers);
        } catch (HttpClientException $e) {
            $this->logger->error('HTTP request failed', [
                'endpoint' => $this->endpoint_url,
                'error'    => $e->getMessage(),
            ]);
            throw new TransportException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }

        $this->handleResponse($response);
    }

    /**
     * {@inheritDoc}
     */
    public function receive(?float $timeout = null): ?string
    {
        if (! $this->connected) {
            throw new TransportException('Transport is not connected');
        }

        if ($this->buffered_response === null) {
            return null;
        }

        $response                = $this->buffered_response;
        $this->buffered_response = null;

        return $response;
    }

    /**
     * Get the current session ID.
     *
     * @return string|null The session ID, or null if not set.
     */
    public function getSessionId(): ?string
    {
        return $this->session_id;
    }

    /**
     * Validate that a URL is properly formatted.
     *
     * @param string $url The URL to validate.
     *
     * @throws TransportException When the URL is invalid.
     */
    private function validateUrl(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            throw new TransportException('Invalid URL: unable to parse');
        }

        if (! isset($parsed['scheme'])) {
            throw new TransportException('Invalid URL: missing scheme');
        }

        if (! in_array($parsed['scheme'], [ 'http', 'https' ], true)) {
            throw new TransportException('Invalid URL: scheme must be http or https');
        }

        if (! isset($parsed['host'])) {
            throw new TransportException('Invalid URL: missing host');
        }
    }

    /**
     * Build the request headers.
     *
     * @return array<string, string> The request headers.
     */
    private function buildRequestHeaders(): array
    {
        $headers = array_merge($this->custom_headers, [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ]);

        if ($this->session_id !== null) {
            $headers['Mcp-Session-Id'] = $this->session_id;
        }

        return $headers;
    }

    /**
     * Handle the HTTP response.
     *
     * @param HttpResponse $response The HTTP response.
     *
     * @throws TransportException When the response indicates an error.
     */
    private function handleResponse(HttpResponse $response): void
    {
        $status_code = $response->getStatusCode();

        // Extract session ID if present
        $session_id = $response->getHeader('Mcp-Session-Id');

        if ($session_id !== null) {
            $this->session_id = $session_id;
            $this->logger->debug('Session ID received', [ 'session_id' => $session_id ]);
        }

        // Handle error status codes
        if ($status_code === 404 && $this->session_id !== null) {
            $this->session_id = null;
            throw new TransportException('Session expired or not found');
        }

        if ($status_code >= 400) {
            throw new TransportException(sprintf(
                'HTTP error %d: %s',
                $status_code,
                $this->truncateBody($response->getBody())
            ));
        }

        // Buffer the response body for receive()
        $body = $response->getBody();

        if ($body !== '') {
            $this->buffered_response = $body;
            $this->logger->debug('Response buffered', [ 'body' => $body ]);
        }
    }

    /**
     * Truncate response body for error messages.
     *
     * @param string $body The response body.
     *
     * @return string The truncated body.
     */
    private function truncateBody(string $body): string
    {
        $max_length = 200;

        if (strlen($body) <= $max_length) {
            return $body;
        }

        return substr($body, 0, $max_length) . '...';
    }

    /**
     * Close the MCP session via DELETE request.
     *
     * Errors are logged but not thrown to allow clean disconnection.
     */
    private function closeSession(): void
    {
        // This method is only called when session_id is not null
        if ($this->session_id === null) {
            return;
        }

        $headers = array_merge($this->custom_headers, [
            'Mcp-Session-Id' => $this->session_id,
        ]);

        $this->logger->debug('Closing session', [
            'endpoint'   => $this->endpoint_url,
            'session_id' => $this->session_id,
        ]);

        try {
            $this->http_client->delete($this->endpoint_url, $headers);
        } catch (HttpClientException $e) {
            // Log but don't throw - disconnection should succeed even if DELETE fails
            $this->logger->warning('Failed to close session via DELETE', [
                'endpoint'   => $this->endpoint_url,
                'session_id' => $this->session_id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Destructor ensures cleanup.
     */
    public function __destruct()
    {
        if ($this->connected) {
            $this->disconnect();
        }
    }
}
