<?php

declare(strict_types=1);

namespace Ovidiu\McpClient\Core\JsonRpc;

use Ovidiu\McpClient\Core\Exception\JsonRpcException;

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
        $method = $data['method'] ?? null;

        if (!is_string($method)) {
            throw new JsonRpcException('Invalid method', JsonRpcException::INVALID_REQUEST);
        }

        $params = $data['params'] ?? null;

        if ($params !== null && !is_array($params)) {
            throw new JsonRpcException('Invalid params', JsonRpcException::INVALID_PARAMS);
        }

        return new self($method, $params);
    }
}
