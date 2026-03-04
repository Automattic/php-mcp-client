<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Client;

/**
 * Represents the capabilities advertised by an MCP server.
 *
 * @since n.e.x.t
 */
class ServerCapabilities
{
    /** @var array<string, mixed> */
    private array $raw;

    /**
     * Create from raw capabilities array.
     *
     * @param array<string, mixed> $capabilities The raw capabilities from initialization.
     */
    public function __construct(array $capabilities)
    {
        $this->raw = $capabilities;
    }

    /**
     * Check if server supports tools.
     */
    public function hasTools(): bool
    {
        return isset($this->raw['tools']);
    }

    /**
     * Check if server supports resources.
     */
    public function hasResources(): bool
    {
        return isset($this->raw['resources']);
    }

    /**
     * Check if server supports prompts.
     */
    public function hasPrompts(): bool
    {
        return isset($this->raw['prompts']);
    }

    /**
     * Check if server supports logging.
     */
    public function hasLogging(): bool
    {
        return isset($this->raw['logging']);
    }

    /**
     * Check if tools support list changed notifications.
     */
    public function toolsListChanged(): bool
    {
        $tools = $this->raw['tools'] ?? null;

        return is_array($tools) && isset($tools['listChanged']) && $tools['listChanged'] === true;
    }

    /**
     * Check if resources support list changed notifications.
     */
    public function resourcesListChanged(): bool
    {
        $resources = $this->raw['resources'] ?? null;

        return is_array($resources) && isset($resources['listChanged']) && $resources['listChanged'] === true;
    }

    /**
     * Check if resources support subscriptions.
     */
    public function resourcesSubscribe(): bool
    {
        $resources = $this->raw['resources'] ?? null;

        return is_array($resources) && isset($resources['subscribe']) && $resources['subscribe'] === true;
    }

    /**
     * Check if prompts support list changed notifications.
     */
    public function promptsListChanged(): bool
    {
        $prompts = $this->raw['prompts'] ?? null;

        return is_array($prompts) && isset($prompts['listChanged']) && $prompts['listChanged'] === true;
    }

    /**
     * Get experimental capabilities.
     *
     * @return array<string, mixed>
     */
    public function getExperimental(): array
    {
        $experimental = $this->raw['experimental'] ?? [];

        return is_array($experimental) ? $experimental : [];
    }

    /**
     * Get raw capabilities array.
     *
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}
