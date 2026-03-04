<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Core\JsonRpc;

use GalatanOvidiu\PhpMcpClient\Exception\JsonRpcException;

/**
 * JSON-RPC 2.0 Error object.
 *
 * @since n.e.x.t
 */
class Error
{
    private int $code;
    private string $message;

    /** @var mixed */
    private $data;

    /**
     * Create a new JSON-RPC error.
     *
     * @param int    $code    The error code.
     * @param string $message The error message.
     * @param mixed  $data    Optional additional error data.
     */
    public function __construct(int $code, string $message, $data = null)
    {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }

    /**
     * Get the error code.
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Get the error message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get additional error data.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $error = [
            'code'    => $this->code,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return $error;
    }

    /**
     * Create an error from an associative array.
     *
     * @param array<string, mixed> $data The error data.
     *
     * @throws JsonRpcException When data is invalid.
     */
    public static function createFromArray(array $data): self
    {
        $code = $data['code'] ?? null;

        if (!is_int($code)) {
            throw new JsonRpcException('Invalid error code', JsonRpcException::INVALID_REQUEST);
        }

        $message = $data['message'] ?? null;

        if (!is_string($message)) {
            throw new JsonRpcException('Invalid error message', JsonRpcException::INVALID_REQUEST);
        }

        return new self($code, $message, $data['data'] ?? null);
    }

    /**
     * Create a parse error.
     */
    public static function parseError(string $message = 'Parse error'): self
    {
        return new self(JsonRpcException::PARSE_ERROR, $message);
    }

    /**
     * Create an invalid request error.
     */
    public static function invalidRequest(string $message = 'Invalid Request'): self
    {
        return new self(JsonRpcException::INVALID_REQUEST, $message);
    }

    /**
     * Create a method not found error.
     */
    public static function methodNotFound(string $message = 'Method not found'): self
    {
        return new self(JsonRpcException::METHOD_NOT_FOUND, $message);
    }

    /**
     * Create an invalid params error.
     */
    public static function invalidParams(string $message = 'Invalid params'): self
    {
        return new self(JsonRpcException::INVALID_PARAMS, $message);
    }

    /**
     * Create an internal error.
     */
    public static function internalError(string $message = 'Internal error'): self
    {
        return new self(JsonRpcException::INTERNAL_ERROR, $message);
    }
}
