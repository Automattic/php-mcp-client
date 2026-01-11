<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Core\Client;

/**
 * Information about the connected MCP server.
 *
 * @since n.e.x.t
 */
class ServerInfo
{
    private string $name;
    private string $version;
    private string $protocol_version;
    private ServerCapabilities $capabilities;

    /**
     * Create server info from initialization response.
     *
     * @param string             $name             Server name.
     * @param string             $version          Server version.
     * @param string             $protocol_version MCP protocol version.
     * @param ServerCapabilities $capabilities     Server capabilities.
     */
    public function __construct(
        string $name,
        string $version,
        string $protocol_version,
        ServerCapabilities $capabilities
    ) {
        $this->name             = $name;
        $this->version          = $version;
        $this->protocol_version = $protocol_version;
        $this->capabilities     = $capabilities;
    }

    /**
     * Get the server name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the server version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get the MCP protocol version.
     */
    public function getProtocolVersion(): string
    {
        return $this->protocol_version;
    }

    /**
     * Get the server capabilities.
     */
    public function getCapabilities(): ServerCapabilities
    {
        return $this->capabilities;
    }
}
