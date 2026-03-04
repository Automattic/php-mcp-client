<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\JsonRpc;

use GalatanOvidiu\PhpMcpClient\Exception\JsonRpcException;

/**
 * JSON-RPC 2.0 Response message.
 *
 * Represents a response to a request, containing either a result or an error.
 *
 * @since n.e.x.t
 */
class Response extends Message
{
    /**
     * @var string|int|null
     */
    private $id;

    /** @var mixed */
    private $result;

    private ?Error $error;

    /**
     * Create a new JSON-RPC response.
     *
     * @param string|int|null $id     The request ID this responds to.
     * @param mixed           $result The result value (mutually exclusive with error).
     * @param Error|null      $error  The error (mutually exclusive with result).
     */
    public function __construct($id, $result = null, ?Error $error = null)
    {
        $this->id     = $id;
        $this->result = $result;
        $this->error  = $error;
    }

    /**
     * Create a success response.
     *
     * @param string|int $id     The request ID.
     * @param mixed      $result The result value.
     *
     * @return self
     */
    public static function success($id, $result): self
    {
        return new self($id, $result, null);
    }

    /**
     * Create an error response.
     *
     * @param string|int|null $id    The request ID (null for parse errors).
     * @param Error           $error The error.
     *
     * @return self
     */
    public static function error($id, Error $error): self
    {
        return new self($id, null, $error);
    }

    /**
     * Get the response ID.
     *
     * @return string|int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the result value.
     *
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Get the error.
     */
    public function getError(): ?Error
    {
        return $this->error;
    }

    /**
     * Check if this is an error response.
     */
    public function isError(): bool
    {
        return $this->error !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        $message = [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id'      => $this->id,
        ];

        if ($this->error !== null) {
            $message['error'] = $this->error->toArray();
        } else {
            $message['result'] = $this->result;
        }

        return $message;
    }

    /**
     * Create a response from an associative array.
     *
     * @param array<string, mixed> $data The message data.
     *
     * @throws JsonRpcException When data is invalid.
     */
    public static function createFromArray(array $data): self
    {
        $id = $data['id'] ?? null;

        if ($id !== null && !is_string($id) && !is_int($id)) {
            throw new JsonRpcException('Invalid response ID', JsonRpcException::INVALID_REQUEST);
        }

        $has_result = array_key_exists('result', $data);
        $has_error  = array_key_exists('error', $data);

        if ($has_result && $has_error) {
            throw new JsonRpcException(
                'Invalid response: contains both result and error fields',
                JsonRpcException::INVALID_REQUEST
            );
        }

        if ($has_error) {
            $error_data = $data['error'];

            if (!is_array($error_data)) {
                throw new JsonRpcException('Invalid error format', JsonRpcException::INVALID_REQUEST);
            }

            $error = Error::createFromArray($error_data);

            return new self($id, null, $error);
        }

        return new self($id, $data['result'] ?? null, null);
    }
}
