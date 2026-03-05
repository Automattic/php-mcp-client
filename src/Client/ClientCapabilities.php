<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Client;

/**
 * MCP client capabilities configuration.
 *
 * Defines what features this client supports and can offer to servers.
 *
 * @since n.e.x.t
 */
class ClientCapabilities
{
    private bool $sampling   = false;
    private bool $roots      = false;
    private bool $elicitation = false;

    /** @var array<string, mixed> */
    private array $experimental = [];

    /**
     * Enable sampling capability.
     *
     * Allows servers to request LLM completions through this client.
     */
    public function withSampling(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->sampling = $enabled;
        return $clone;
    }

    /**
     * Enable roots capability.
     *
     * Allows servers to query the client for filesystem or URI boundaries.
     */
    public function withRoots(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->roots = $enabled;
        return $clone;
    }

    /**
     * Enable elicitation capability.
     *
     * Allows servers to request additional user information.
     */
    public function withElicitation(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->elicitation = $enabled;
        return $clone;
    }

    /**
     * Add experimental capabilities.
     *
     * @param array<string, mixed> $capabilities The experimental capabilities.
     */
    public function withExperimental(array $capabilities): self
    {
        $clone = clone $this;
        $clone->experimental = $capabilities;
        return $clone;
    }

    /**
     * Check if sampling is enabled.
     */
    public function hasSampling(): bool
    {
        return $this->sampling;
    }

    /**
     * Check if roots is enabled.
     */
    public function hasRoots(): bool
    {
        return $this->roots;
    }

    /**
     * Check if elicitation is enabled.
     */
    public function hasElicitation(): bool
    {
        return $this->elicitation;
    }

    /**
     * Convert to object for protocol messages.
     *
     * Returns stdClass to ensure JSON encoding produces {} for empty capabilities.
     *
     * @return \stdClass
     */
    public function toObject(): \stdClass
    {
        $capabilities = new \stdClass();

        if ($this->sampling) {
            $capabilities->sampling = new \stdClass();
        }

        if ($this->roots) {
            $capabilities->roots = (object) ['listChanged' => true];
        }

        if ($this->elicitation) {
            $capabilities->elicitation = new \stdClass();
        }

        if (!empty($this->experimental)) {
            $capabilities->experimental = (object) $this->experimental;
        }

        return $capabilities;
    }
}
