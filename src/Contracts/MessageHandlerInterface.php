<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Contracts;

/**
 * Handler for incoming MCP server messages.
 *
 * Implementations process server-initiated requests and notifications.
 *
 * @since n.e.x.t
 */
interface MessageHandlerInterface
{
    /**
     * Handle an incoming server request.
     *
     * @param string               $method The JSON-RPC method name.
     * @param array<string, mixed> $params The request parameters.
     *
     * @return mixed The response result to send back.
     */
    public function handleRequest(string $method, array $params);

    /**
     * Handle an incoming server notification.
     *
     * @param string               $method The JSON-RPC method name.
     * @param array<string, mixed> $params The notification parameters.
     */
    public function handleNotification(string $method, array $params): void;

    /**
     * Check if this handler supports a given method.
     *
     * @param string $method The JSON-RPC method name.
     */
    public function supports(string $method): bool;
}
