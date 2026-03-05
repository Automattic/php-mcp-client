<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\JsonRpc;

use Automattic\PhpMcpClient\Exception\JsonRpcException;

/**
 * JSON-RPC 2.0 Request message.
 *
 * Represents a request that expects a response from the server.
 *
 * @since n.e.x.t
 */
class Request extends Message
{
    /**
     * @var string|int
     */
    private $id;

    private string $method;

    /** @var array<string, mixed>|null */
    private ?array $params;

    /**
     * Create a new JSON-RPC request.
     *
     * @param string|int                $id     The request ID.
     * @param string                    $method The method name.
     * @param array<string, mixed>|null $params Optional parameters.
     */
    public function __construct($id, string $method, ?array $params = null)
    {
        $this->id     = $id;
        $this->method = $method;
        $this->params = $params;
    }

    /**
     * Get the request ID.
     *
     * @return string|int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the method name.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the request parameters.
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
            'id'      => $this->id,
            'method'  => $this->method,
        ];

        if ($this->params !== null) {
            $message['params'] = $this->params;
        }

        return $message;
    }

    /**
     * Create a request from an associative array.
     *
     * @param array<string, mixed> $data The message data.
     *
     * @throws JsonRpcException When data is invalid.
     */
    public static function createFromArray(array $data): self
    {
        $id = $data['id'] ?? null;

        if (!is_string($id) && !is_int($id)) {
            throw new JsonRpcException('Invalid request ID', JsonRpcException::INVALID_REQUEST);
        }

        return new self($id, self::extractMethod($data), self::extractParams($data));
    }
}
