<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Tests\Unit\Client;

use Automattic\PhpMcpClient\Contracts\TransportInterface;

/**
 * Mock transport for testing McpClient without a real MCP server.
 *
 * Provides ability to:
 * - Track connection state
 * - Store sent messages for assertions
 * - Queue responses for scripting server behavior
 *
 * @since n.e.x.t
 */
final class MockTransport implements TransportInterface
{
    private bool $connected = false;

    /**
     * Queued responses to return from receive() calls.
     *
     * @var string[]
     */
    private array $response_queue = [];

    /**
     * All messages sent through this transport.
     *
     * @var string[]
     */
    private array $sent_messages = [];

    /**
     * Exception to throw on a future send() call, if set.
     */
    private ?\Throwable $send_exception = null;

    /**
     * Number of send() calls to allow before throwing the exception.
     */
    private int $send_exception_after = 0;

    /**
     * Set an exception to be thrown on a future send() call.
     *
     * The exception is consumed after being thrown (one-shot).
     * Use $after to skip N send() calls before throwing. Default is 0 (next send).
     *
     * @param \Throwable $exception The exception to throw.
     * @param int        $after     Number of send() calls to allow before throwing.
     */
    public function throwOnNextSend(\Throwable $exception, int $after = 0): void
    {
        $this->send_exception       = $exception;
        $this->send_exception_after = $after;
    }

    /**
     * Queue a JSON response to be returned by receive().
     *
     * Responses are returned in FIFO order.
     *
     * @param string $json The JSON response string.
     */
    public function queueResponse(string $json): void
    {
        $this->response_queue[] = $json;
    }

    /**
     * Get the last message sent through this transport.
     *
     * @return string|null The last sent message, or null if none.
     */
    public function getLastSentMessage(): ?string
    {
        if (count($this->sent_messages) === 0) {
            return null;
        }

        return $this->sent_messages[count($this->sent_messages) - 1];
    }

    /**
     * Get all messages sent through this transport.
     *
     * @return string[]
     */
    public function getSentMessages(): array
    {
        return $this->sent_messages;
    }

    /**
     * {@inheritDoc}
     */
    public function connect(): void
    {
        $this->connected = true;
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect(): void
    {
        $this->connected = false;
    }

    /**
     * {@inheritDoc}
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * {@inheritDoc}
     */
    public function send(string $message): void
    {
        if ($this->send_exception !== null) {
            if ($this->send_exception_after <= 0) {
                $exception            = $this->send_exception;
                $this->send_exception = null;
                throw $exception;
            }

            --$this->send_exception_after;
        }

        $this->sent_messages[] = $message;
    }

    /**
     * {@inheritDoc}
     */
    public function receive(?float $timeout = null): ?string
    {
        if (count($this->response_queue) === 0) {
            return null;
        }

        return array_shift($this->response_queue);
    }
}
