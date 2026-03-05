<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Exception;

/**
 * Exception for JSON-RPC protocol errors.
 *
 * @since n.e.x.t
 */
class JsonRpcException extends McpException
{
    public const PARSE_ERROR      = -32700;
    public const INVALID_REQUEST  = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS   = -32602;
    public const INTERNAL_ERROR   = -32603;

    /** @var mixed */
    protected $error_data;

    /**
     * Create a new JSON-RPC exception.
     *
     * @param string $message    Error message.
     * @param int    $error_code JSON-RPC error code.
     * @param mixed  $error_data Additional error data.
     */
    public function __construct(string $message, int $error_code = self::INTERNAL_ERROR, $error_data = null)
    {
        parent::__construct($message, $error_code);
        $this->error_data = $error_data;
    }

    /**
     * Get the JSON-RPC error code.
     */
    public function getErrorCode(): int
    {
        return $this->getCode();
    }

    /**
     * Get additional error data.
     *
     * @return mixed
     */
    public function getErrorData()
    {
        return $this->error_data;
    }
}
