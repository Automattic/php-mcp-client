<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Contracts;

/**
 * Handler for server-initiated roots/list requests.
 *
 * Implementations return the list of root URIs that this client exposes
 * to MCP servers. Each root is an associative array with a required 'uri'
 * key and an optional 'name' key.
 *
 * @since n.e.x.t
 */
interface RootsHandlerInterface
{
    /**
     * Handle a roots/list request from the server.
     *
     * @return array<int, array{uri: string, name?: string}> The list of roots.
     */
    public function handleListRoots(): array;
}
