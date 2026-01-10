<?php

declare(strict_types=1);

namespace Ovidiu\McpClient\Core\Transport;

use Ovidiu\McpClient\Core\Contracts\TransportInterface;

/**
 * Base abstract transport implementation.
 *
 * Provides common functionality for all transport implementations.
 *
 * @since n.e.x.t
 */
abstract class AbstractTransport implements TransportInterface
{
    protected bool $connected = false;

    /**
     * {@inheritDoc}
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }
}
