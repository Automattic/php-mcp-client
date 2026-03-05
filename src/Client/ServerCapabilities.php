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
     * Check if server supports completions.
     */
    public function hasCompletions(): bool
    {
        return isset($this->raw['completions']);
    }

    /**
     * Check if tools support list changed notifications.
     */
    public function toolsListChanged(): bool
    {
        return $this->capabilityFlag('tools', 'listChanged');
    }

    /**
     * Check if resources support list changed notifications.
     */
    public function resourcesListChanged(): bool
    {
        return $this->capabilityFlag('resources', 'listChanged');
    }

    /**
     * Check if resources support subscriptions.
     */
    public function resourcesSubscribe(): bool
    {
        return $this->capabilityFlag('resources', 'subscribe');
    }

    /**
     * Check if prompts support list changed notifications.
     */
    public function promptsListChanged(): bool
    {
        return $this->capabilityFlag('prompts', 'listChanged');
    }

    /**
     * Check if a capability has a specific boolean flag enabled.
     *
     * @param string $capability The top-level capability key.
     * @param string $flag       The boolean sub-key to check.
     */
    private function capabilityFlag(string $capability, string $flag): bool
    {
        $cap = $this->raw[$capability] ?? null;

        return is_array($cap) && isset($cap[$flag]) && $cap[$flag] === true;
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
