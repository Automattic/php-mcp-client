<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\JsonRpc;

use Automattic\PhpMcpClient\Exception\JsonRpcException;

/**
 * Base class for JSON-RPC 2.0 messages.
 *
 * @since n.e.x.t
 */
abstract class Message
{
    public const JSON_RPC_VERSION = '2.0';

    /**
     * Convert the message to an associative array.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Encode the message as JSON.
     *
     * @throws JsonRpcException When encoding fails.
     */
    public function toJson(): string
    {
        try {
            return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new JsonRpcException(
                'Failed to encode JSON-RPC message: ' . $e->getMessage(),
                JsonRpcException::INTERNAL_ERROR
            );
        }
    }

    /**
     * Parse a JSON string into a message object.
     *
     * @param string $json The JSON string to parse.
     *
     * @return Request|Notification|Response The parsed message.
     *
     * @throws JsonRpcException When parsing fails or message is invalid.
     */
    public static function fromJson(string $json)
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonRpcException('Parse error: ' . $e->getMessage(), JsonRpcException::PARSE_ERROR);
        }

        if (!is_array($data)) {
            throw new JsonRpcException('Invalid JSON-RPC message format', JsonRpcException::INVALID_REQUEST);
        }

        return self::fromArray($data);
    }

    /**
     * Extract and validate a method string from message data.
     *
     * @param array<string, mixed> $data The message data.
     *
     * @return string The validated method name.
     *
     * @throws JsonRpcException When method is missing or invalid.
     */
    protected static function extractMethod(array $data): string
    {
        $method = $data['method'] ?? null;

        if (!is_string($method)) {
            throw new JsonRpcException('Invalid method', JsonRpcException::INVALID_REQUEST);
        }

        return $method;
    }

    /**
     * Extract and validate optional params from message data.
     *
     * @param array<string, mixed> $data The message data.
     *
     * @return array<string, mixed>|null The validated params, or null.
     *
     * @throws JsonRpcException When params are present but not an array.
     */
    protected static function extractParams(array $data): ?array
    {
        $params = $data['params'] ?? null;

        if ($params !== null && !is_array($params)) {
            throw new JsonRpcException('Invalid params', JsonRpcException::INVALID_PARAMS);
        }

        return $params;
    }

    /**
     * Create a message from an associative array.
     *
     * @param array<string, mixed> $data The message data.
     *
     * @return Request|Notification|Response The parsed message.
     *
     * @throws JsonRpcException When message is invalid.
     */
    public static function fromArray(array $data)
    {
        $jsonrpc = $data['jsonrpc'] ?? null;

        if ($jsonrpc !== self::JSON_RPC_VERSION) {
            throw new JsonRpcException('Invalid JSON-RPC version', JsonRpcException::INVALID_REQUEST);
        }

        // Response has result or error
        if (array_key_exists('result', $data) || array_key_exists('error', $data)) {
            return Response::createFromArray($data);
        }

        // Request or Notification has method
        if (!isset($data['method']) || !is_string($data['method'])) {
            throw new JsonRpcException('Missing or invalid method', JsonRpcException::INVALID_REQUEST);
        }

        // Request has id, Notification does not
        if (array_key_exists('id', $data)) {
            return Request::createFromArray($data);
        }

        return Notification::createFromArray($data);
    }
}
