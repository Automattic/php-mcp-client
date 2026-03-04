<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Client;

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
    private ?string $instructions;

    /**
     * Create server info from initialization response.
     *
     * @param string             $name             Server name.
     * @param string             $version          Server version.
     * @param string             $protocol_version MCP protocol version.
     * @param ServerCapabilities $capabilities     Server capabilities.
     * @param string|null        $instructions     Optional server instructions for the client.
     */
    public function __construct(
        string $name,
        string $version,
        string $protocol_version,
        ServerCapabilities $capabilities,
        ?string $instructions = null
    ) {
        $this->name             = $name;
        $this->version          = $version;
        $this->protocol_version = $protocol_version;
        $this->capabilities     = $capabilities;
        $this->instructions     = $instructions;
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

    /**
     * Get the server instructions for the client.
     *
     * Instructions describe how the client should interact with this server.
     * Returns null when the server does not provide instructions.
     */
    public function getInstructions(): ?string
    {
        return $this->instructions;
    }
}
