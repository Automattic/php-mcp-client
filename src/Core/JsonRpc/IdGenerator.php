<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Core\JsonRpc;

/**
 * Generates unique request IDs for JSON-RPC messages.
 *
 * @since n.e.x.t
 */
class IdGenerator
{
    private int $counter = 0;

    /**
     * Generate the next unique ID.
     */
    public function next(): int
    {
        return ++$this->counter;
    }

    /**
     * Reset the counter.
     */
    public function reset(): void
    {
        $this->counter = 0;
    }
}
