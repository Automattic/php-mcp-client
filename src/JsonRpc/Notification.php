<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\JsonRpc;

use Automattic\PhpMcpClient\Exception\JsonRpcException;

/**
 * JSON-RPC 2.0 Notification message.
 *
 * Represents a notification that does not expect a response.
 *
 * @since n.e.x.t
 */
class Notification extends Message
{
    private string $method;

    /** @var array<string, mixed>|null */
    private ?array $params;

    /**
     * Create a new JSON-RPC notification.
     *
     * @param string                    $method The method name.
     * @param array<string, mixed>|null $params Optional parameters.
     */
    public function __construct(string $method, ?array $params = null)
    {
        $this->method = $method;
        $this->params = $params;
    }

    /**
     * Get the method name.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the notification parameters.
     *
     * @return array<string, mixed>|null
     */
    public function getParams(): ?array
    {
        return $this->params;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        $message = [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'method'  => $this->method,
        ];

        if ($this->params !== null) {
            $message['params'] = $this->params;
        }

        return $message;
    }

    /**
     * Create a notification from an associative array.
     *
     * @param array<string, mixed> $data The message data.
     *
     * @throws JsonRpcException When data is invalid.
     */
    public static function createFromArray(array $data): self
    {
        return new self(self::extractMethod($data), self::extractParams($data));
    }
}
