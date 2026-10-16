<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Tests\Unit\Client;

use Automattic\PhpMcpClient\Contracts\TransportInterface;
use Fiber;

/**
 * A transport whose receive() waits like a socket, yielding to the fiber
 * scheduler while no message is available.
 */
final class FiberTransport implements TransportInterface
{
    private bool $connected = false;

    /** @var string[] */
    private array $inbox = [];

    public function deliver(string $message): void
    {
        $this->inbox[] = $message;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function send(string $message): void
    {
    }

    public function receive(?float $timeout = null): ?string
    {
        $until = microtime(true) + ($timeout ?? 0.0);
        while (count($this->inbox) === 0) {
            if (Fiber::getCurrent() === null || microtime(true) >= $until) {
                return null;
            }
            Fiber::suspend();
        }

        return array_shift($this->inbox);
    }
}
