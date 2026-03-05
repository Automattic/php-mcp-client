<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Contracts;

use Automattic\PhpMcpClient\Exception\TransportException;

/**
 * Transport layer abstraction for MCP communication.
 *
 * Implementations handle the actual message delivery mechanism (stdio, HTTP, etc.)
 * while the client works with this abstract interface.
 *
 * @since n.e.x.t
 */
interface TransportInterface
{
    /**
     * Establish connection to the MCP server.
     *
     * @throws TransportException When connection cannot be established.
     */
    public function connect(): void;

    /**
     * Close the connection to the MCP server.
     *
     * @throws TransportException When disconnection fails.
     */
    public function disconnect(): void;

    /**
     * Check if transport is currently connected.
     */
    public function isConnected(): bool;

    /**
     * Send a message to the MCP server.
     *
     * @param string $message JSON-encoded message to send.
     *
     * @throws TransportException When message cannot be sent.
     */
    public function send(string $message): void;

    /**
     * Receive a message from the MCP server.
     *
     * This method blocks until a message is available or timeout occurs.
     *
     * @param float|null $timeout Maximum time to wait in seconds. Null for indefinite.
     *
     * @return string|null JSON-encoded message or null on timeout.
     *
     * @throws TransportException When receiving fails.
     */
    public function receive(?float $timeout = null): ?string;
}
