<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Client;

use GalatanOvidiu\PhpMcpClient\Contracts\MessageHandlerInterface;
use GalatanOvidiu\PhpMcpClient\Contracts\RootsHandlerInterface;
use GalatanOvidiu\PhpMcpClient\Contracts\TransportInterface;
use GalatanOvidiu\PhpMcpClient\Exception\CapabilityException;
use GalatanOvidiu\PhpMcpClient\Exception\ConnectionException;
use GalatanOvidiu\PhpMcpClient\Exception\JsonRpcException;
use GalatanOvidiu\PhpMcpClient\Exception\McpException;
use GalatanOvidiu\PhpMcpClient\Exception\TimeoutException;
use GalatanOvidiu\PhpMcpClient\Exception\TransportException;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Error;
use GalatanOvidiu\PhpMcpClient\JsonRpc\IdGenerator;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Message;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Notification;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Request;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use Throwable;
use WP\McpSchema\Server\Logging\Enum\LoggingLevel;

/**
 * MCP Client for communicating with MCP servers.
 *
 * Handles the MCP protocol lifecycle including initialization, capability
 * negotiation, and method invocation.
 *
 * @since n.e.x.t
 */
class McpClient
{
    public const PROTOCOL_VERSION = '2025-11-25';

    /**
     * Map of JSON-RPC method names to their required response keys.
     *
     * @var array<string, string>
     */
    private const RESPONSE_REQUIRED_KEYS = [
        'tools/list'               => 'tools',
        'tools/call'               => 'content',
        'resources/list'           => 'resources',
        'resources/read'           => 'contents',
        'resources/templates/list' => 'resourceTemplates',
        'prompts/list'             => 'prompts',
        'prompts/get'              => 'messages',
    ];

    private TransportInterface $transport;
    private ClientCapabilities $capabilities;
    private LoggerInterface $logger;
    private IdGenerator $id_generator;

    private ?ServerInfo $server_info = null;
    private bool $initialized = false;

    /** @var array<MessageHandlerInterface> */
    private array $message_handlers = [];

    private ?RootsHandlerInterface $roots_handler = null;

    private string $client_name;
    private string $client_version;
    private ?string $client_description;
    private ?string $client_title;
    private ?string $client_website_url;

    /**
     * Create a new MCP client.
     *
     * @param TransportInterface   $transport          The transport to use.
     * @param ClientCapabilities   $capabilities       The client capabilities.
     * @param string               $client_name        The client application name.
     * @param string               $client_version     The client application version.
     * @param string|null          $client_description Optional client description.
     * @param string|null          $client_title       Optional human-readable client title.
     * @param string|null          $client_website_url Optional client website URL.
     * @param LoggerInterface|null $logger             Optional logger.
     */
    public function __construct(
        TransportInterface $transport,
        ClientCapabilities $capabilities,
        string $client_name = 'php-mcp-client',
        string $client_version = '1.0.0',
        ?string $client_description = null,
        ?string $client_title = null,
        ?string $client_website_url = null,
        ?LoggerInterface $logger = null
    ) {
        $this->transport          = $transport;
        $this->capabilities       = $capabilities;
        $this->client_name        = $client_name;
        $this->client_version     = $client_version;
        $this->client_description = $client_description;
        $this->client_title       = $client_title;
        $this->client_website_url = $client_website_url;
        $this->logger             = $logger ?? new NullLogger();
        $this->id_generator       = new IdGenerator();
    }

    /**
     * Add a message handler for server-initiated requests/notifications.
     */
    public function addMessageHandler(MessageHandlerInterface $handler): void
    {
        $this->message_handlers[] = $handler;
    }

    /**
     * Set the handler for server-initiated roots/list requests.
     *
     * When a server sends a roots/list request, the registered handler will be
     * called to provide the list of roots. Without a handler, an empty roots
     * array is returned as the default response.
     *
     * @param RootsHandlerInterface $handler The roots handler.
     */
    public function setRootsHandler(RootsHandlerInterface $handler): void
    {
        $this->roots_handler = $handler;
    }

    /**
     * Connect to the MCP server and perform initialization.
     *
     * @param float $timeout Timeout for initialization in seconds.
     *
     * @throws ConnectionException When connection fails.
     * @throws McpException When initialization fails.
     * @throws TransportException When transport operations fail.
     * @throws TimeoutException When initialization times out.
     * @throws JsonRpcException When server returns an error during initialization.
     * @throws Throwable When any error occurs during initialization (rethrown after cleanup).
     */
    public function connect(float $timeout = 30.0): void
    {
        if ($this->initialized) {
            return;
        }

        $this->logger->debug('Connecting to MCP server');

        $this->transport->connect();

        try {
            $this->initialize($timeout);
        } catch (Throwable $e) {
            $this->transport->disconnect();
            throw $e;
        }
    }

    /**
     * Disconnect from the MCP server.
     *
     * @throws TransportException When transport disconnection fails.
     */
    public function disconnect(): void
    {
        if (! $this->initialized) {
            return;
        }

        $this->logger->debug('Disconnecting from MCP server');

        $this->initialized = false;
        $this->server_info = null;
        $this->transport->disconnect();
    }

    /**
     * Check if client is connected and initialized.
     */
    public function isConnected(): bool
    {
        return $this->initialized && $this->transport->isConnected();
    }

    /**
     * Get the connected server info.
     *
     * @throws McpException When not connected.
     */
    public function getServerInfo(): ServerInfo
    {
        if ($this->server_info === null) {
            throw new McpException('Not connected to an MCP server');
        }

        return $this->server_info;
    }

    /**
     * List available tools from the server.
     *
     * @param string|null $cursor Optional pagination cursor.
     * @param float $timeout Request timeout in seconds.
     *
     * @return array<string, mixed> The list of tools.
     *
     * @throws McpException When the request fails.
     */
    public function listTools(?string $cursor = null, float $timeout = 30.0): array
    {
        return $this->paginatedList('tools/list', 'tools', $cursor, $timeout);
    }

    /**
     * Call a tool on the server.
     *
     * @param string               $name      The tool name.
     * @param array<string, mixed> $arguments The tool arguments.
     * @param float                $timeout   Request timeout in seconds.
     * @param array<string, mixed>|null $meta Optional protocol-level metadata (e.g. progressToken).
     *
     * @return array<string, mixed> The tool result.
     *
     * @throws McpException When the request fails.
     */
    public function callTool(
        string $name,
        array $arguments = [],
        float $timeout = 60.0,
        ?array $meta = null
    ): array {
        $this->ensureConnected();
        $this->ensureCapability('tools', 'tools/call');

        $result = $this->requestArray('tools/call', [
            'name'      => $name,
            'arguments' => $this->normalizeArguments($arguments),
        ], $timeout, $meta);
        $this->validateResponse('tools/call', $result);

        return $result;
    }

    /**
     * List available resources from the server.
     *
     * @param string|null $cursor Optional pagination cursor.
     * @param float $timeout Request timeout in seconds.
     *
     * @return array<string, mixed> The list of resources.
     *
     * @throws McpException When the request fails.
     */
    public function listResources(?string $cursor = null, float $timeout = 30.0): array
    {
        return $this->paginatedList('resources/list', 'resources', $cursor, $timeout);
    }

    /**
     * Read a resource from the server.
     *
     * @param string               $uri     The resource URI.
     * @param float                $timeout Request timeout in seconds.
     * @param array<string, mixed>|null $meta Optional protocol-level metadata (e.g. progressToken).
     *
     * @return array<string, mixed> The resource content.
     *
     * @throws McpException When the request fails.
     */
    public function readResource(string $uri, float $timeout = 30.0, ?array $meta = null): array
    {
        $this->ensureConnected();
        $this->ensureCapability('resources', 'resources/read');

        $result = $this->requestArray('resources/read', [
            'uri' => $uri,
        ], $timeout, $meta);
        $this->validateResponse('resources/read', $result);

        return $result;
    }

    /**
     * List available resource templates from the server.
     *
     * @param string|null $cursor Optional pagination cursor.
     * @param float $timeout Request timeout in seconds.
     *
     * @return array<string, mixed> The list of resource templates.
     *
     * @throws McpException When the request fails.
     * @throws CapabilityException When server does not support resources.
     */
    public function listResourceTemplates(?string $cursor = null, float $timeout = 30.0): array
    {
        return $this->paginatedList('resources/templates/list', 'resources', $cursor, $timeout);
    }

    /**
     * List available prompts from the server.
     *
     * @param string|null $cursor Optional pagination cursor.
     * @param float $timeout Request timeout in seconds.
     *
     * @return array<string, mixed> The list of prompts.
     *
     * @throws McpException When the request fails.
     */
    public function listPrompts(?string $cursor = null, float $timeout = 30.0): array
    {
        return $this->paginatedList('prompts/list', 'prompts', $cursor, $timeout);
    }

    /**
     * Get a prompt from the server.
     *
     * @param string               $name      The prompt name.
     * @param array<string, mixed> $arguments The prompt arguments.
     * @param float                $timeout   Request timeout in seconds.
     * @param array<string, mixed>|null $meta Optional protocol-level metadata (e.g. progressToken).
     *
     * @return array<string, mixed> The prompt result.
     *
     * @throws McpException When the request fails.
     */
    public function getPrompt(
        string $name,
        array $arguments = [],
        float $timeout = 30.0,
        ?array $meta = null
    ): array {
        $this->ensureConnected();
        $this->ensureCapability('prompts', 'prompts/get');

        $result = $this->requestArray('prompts/get', [
            'name'      => $name,
            'arguments' => $this->normalizeArguments($arguments),
        ], $timeout, $meta);
        $this->validateResponse('prompts/get', $result);

        return $result;
    }

    /**
     * Send a ping to the server.
     *
     * @param float $timeout Request timeout in seconds.
     *
     * @throws McpException When the request fails.
     */
    public function ping(float $timeout = 5.0): void
    {
        $this->request('ping', [], $timeout);
    }

    /**
     * Set the logging level on the server.
     *
     * @param string $level   The logging level (one of LoggingLevel::values()).
     * @param float  $timeout Request timeout in seconds.
     *
     * @throws McpException When the level is invalid or the request fails.
     * @throws CapabilityException When the server does not support logging.
     */
    public function setLoggingLevel(string $level, float $timeout = 30.0): void
    {
        $valid_levels = LoggingLevel::values();

        if (!in_array($level, $valid_levels, true)) {
            throw new McpException(
                "Invalid logging level '{$level}'. Valid levels: " . implode(', ', $valid_levels)
            );
        }

        $this->ensureConnected();
        $this->ensureCapability('logging', 'logging/setLevel');

        $this->request('logging/setLevel', ['level' => $level], $timeout);
    }

    /**
     * Send a cancellation notification for an in-flight request.
     *
     * Sends a `notifications/cancelled` notification to the server, indicating
     * the client is no longer interested in the response for the given request.
     *
     * @param int|string  $request_id The ID of the request to cancel.
     * @param string|null $reason     Optional human-readable cancellation reason.
     *
     * @throws McpException When sending fails.
     *
     * @since n.e.x.t
     */
    public function sendCancellation($request_id, ?string $reason = null): void
    {
        $params = ['requestId' => $request_id];

        if ($reason !== null) {
            $params['reason'] = $reason;
        }

        $this->notify('notifications/cancelled', $params);
    }

    /**
     * Send a notification to the server.
     *
     * @param string $method The method name.
     * @param array<string, mixed> $params The notification parameters.
     *
     * @throws McpException When sending fails.
     */
    public function notify(string $method, array $params = []): void
    {
        $this->ensureConnected();

        $notification = new Notification($method, empty($params) ? null : $params);

        $this->logger->debug('Sending notification', [ 'method' => $method ]);

        $this->transport->send($notification->toJson());
    }

    /**
     * Send a request and wait for response, expecting an array result.
     *
     * @param string              $method  The method name.
     * @param array<string, mixed> $params The request parameters.
     * @param float               $timeout Request timeout in seconds.
     * @param array<string, mixed>|null $meta Optional protocol-level metadata (e.g. progressToken).
     *
     * @return array<string, mixed> The response result.
     *
     * @throws McpException When the request fails or result is not an array.
     */
    public function requestArray(
        string $method,
        array $params = [],
        float $timeout = 30.0,
        ?array $meta = null
    ): array {
        $result = $this->request($method, $params, $timeout, $meta);

        if (! is_array($result)) {
            throw new McpException("Expected array response for $method, got " . gettype($result));
        }

        return $result;
    }

    /**
     * Send a request and wait for response.
     *
     * @param string              $method  The method name.
     * @param array<string, mixed> $params The request parameters.
     * @param float               $timeout Request timeout in seconds.
     * @param array<string, mixed>|null $meta Optional protocol-level metadata (e.g. progressToken).
     *
     * @return mixed The response result.
     *
     * @throws McpException When the request fails.
     */
    public function request(string $method, array $params = [], float $timeout = 30.0, ?array $meta = null)
    {
        $this->ensureConnected();

        if (! empty($meta)) {
            $params['_meta'] = $meta;
        }

        $id      = $this->id_generator->next();
        $request = new Request($id, $method, empty($params) ? null : $params);

        $this->logger->debug('Sending request', [ 'id' => $id, 'method' => $method ]);

        $this->transport->send($request->toJson());

        return $this->waitForResponse($id, $timeout);
    }

    /**
     * Process any pending incoming messages.
     *
     * Call this periodically to handle server-initiated requests and notifications.
     *
     * @param float $timeout How long to wait for messages.
     *
     * @throws ConnectionException When client is not connected.
     * @throws TransportException When receiving messages fails.
     */
    public function processMessages(float $timeout = 0.1): void
    {
        $this->ensureConnected();

        $message_json = $this->transport->receive($timeout);

        if ($message_json !== null) {
            $this->handleIncomingMessage($message_json);
        }
    }

    /**
     * Perform MCP initialization handshake.
     *
     * @param float $timeout Timeout in seconds.
     *
     * @throws McpException When initialization fails.
     */
    private function initialize(float $timeout): void
    {
        $id = $this->id_generator->next();

        $client_info = [
            'name'    => $this->client_name,
            'version' => $this->client_version,
        ];

        if ($this->client_description !== null) {
            $client_info['description'] = $this->client_description;
        }

        if ($this->client_title !== null) {
            $client_info['title'] = $this->client_title;
        }

        if ($this->client_website_url !== null) {
            $client_info['websiteUrl'] = $this->client_website_url;
        }

        $init_request = new Request($id, 'initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => $this->capabilities->toObject(),
            'clientInfo'      => $client_info,
        ]);

        $this->logger->debug('Sending initialize request');

        $this->transport->send($init_request->toJson());

        $result = $this->waitForResponse($id, $timeout);

        if (! is_array($result)) {
            throw new McpException('Invalid initialize response');
        }

        if (!isset($result['protocolVersion'])) {
            throw new McpException('Server did not return a protocolVersion in initialize response');
        }

        $protocol_version = $result['protocolVersion'];

        if ($protocol_version !== self::PROTOCOL_VERSION) {
            throw new McpException(sprintf(
                'Protocol version mismatch: client supports %s, server returned %s',
                self::PROTOCOL_VERSION,
                $protocol_version
            ));
        }

        $server_info_data = $result['serverInfo'] ?? [];
        $capabilities     = $result['capabilities'] ?? [];
        $instructions     = $result['instructions'] ?? null;

        $this->server_info = new ServerInfo(
            $server_info_data['name'] ?? 'Unknown',
            $server_info_data['version'] ?? '0.0.0',
            $protocol_version,
            new ServerCapabilities($capabilities),
            $instructions,
            $server_info_data['description'] ?? null,
            $server_info_data['title'] ?? null,
            $server_info_data['websiteUrl'] ?? null
        );

        $this->logger->info('MCP initialized', [
            'server'   => $this->server_info->getName(),
            'version'  => $this->server_info->getVersion(),
            'protocol' => $protocol_version,
        ]);

        // Send initialized notification
        $this->transport->send(( new Notification('notifications/initialized') )->toJson());

        $this->initialized = true;
    }

    /**
     * Wait for a response to a specific request.
     *
     * @param int|string $request_id The request ID to wait for.
     * @param float $timeout Timeout in seconds.
     *
     * @return mixed The response result.
     *
     * @throws TimeoutException When timeout occurs.
     * @throws JsonRpcException When server returns an error.
     * @throws TransportException When receiving messages fails.
     */
    private function waitForResponse($request_id, float $timeout)
    {
        $start = microtime(true);

        while (true) {
            $elapsed   = microtime(true) - $start;
            $remaining = $timeout - $elapsed;

            if ($remaining <= 0) {
                $this->sendTimeoutCancellation($request_id);
                throw new TimeoutException("Request $request_id timed out after $timeout seconds");
            }

            $message_json = $this->transport->receive($remaining);

            if ($message_json === null) {
                $this->sendTimeoutCancellation($request_id);
                throw new TimeoutException("Request $request_id timed out after $timeout seconds");
            }

            $message = Message::fromJson($message_json);

            if ($message instanceof Response && $message->getId() === $request_id) {
                $error = $message->getError();

                if ($error !== null) {
                    throw new JsonRpcException(
                        $error->getMessage(),
                        $error->getCode(),
                        $error->getData()
                    );
                }

                return $message->getResult();
            }

            // Handle other incoming messages (requests/notifications from server)
            $this->handleMessage($message);
        }
    }

    /**
     * Send a cancellation notification for a timed-out request.
     *
     * Failures are logged at warning level but never mask the TimeoutException
     * that will follow.
     *
     * @param int|string $request_id The request ID that timed out.
     */
    private function sendTimeoutCancellation($request_id): void
    {
        try {
            $this->sendCancellation($request_id, 'Client timeout');
        } catch (Throwable $e) {
            $this->logger->warning('Failed to send timeout cancellation', [
                'requestId' => $request_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle an incoming JSON message.
     *
     * @param string $json The JSON message.
     *
     * @throws TransportException When sending responses fails.
     */
    private function handleIncomingMessage(string $json): void
    {
        try {
            $message = Message::fromJson($json);
            $this->handleMessage($message);
        } catch (JsonRpcException $e) {
            $this->logger->error('Failed to parse incoming message', [
                'error' => $e->getMessage(),
                'json'  => mb_substr($json, 0, 200) . (mb_strlen($json) > 200 ? '...' : ''),
            ]);
        }
    }

    /**
     * Handle a parsed message.
     *
     * @param Request|Notification|Response $message The message to handle.
     *
     * @throws TransportException When sending responses fails.
     * @throws JsonRpcException When encoding responses fails.
     */
    private function handleMessage(Message $message): void
    {
        if ($message instanceof Request) {
            $this->handleServerRequest($message);
        } elseif ($message instanceof Notification) {
            $this->handleServerNotification($message);
        } elseif ($message instanceof Response) {
            $this->logger->warning('Discarding unmatched response', [
                'id' => $message->getId(),
            ]);
        }
    }

    /**
     * Handle a server-initiated request.
     *
     * @param Request $request The request from the server.
     *
     * @throws TransportException When sending the response fails.
     * @throws JsonRpcException When encoding the response fails.
     */
    private function handleServerRequest(Request $request): void
    {
        $method = $request->getMethod();
        $params = $request->getParams() ?? [];

        $this->logger->debug('Received server request', [ 'method' => $method ]);

        // Handle roots/list: delegate to roots handler or return empty default.
        if ($method === 'roots/list') {
            $this->handleRootsListRequest($request);
            return;
        }

        foreach ($this->message_handlers as $handler) {
            if ($handler->supports($method)) {
                try {
                    $result   = $handler->handleRequest($method, $params);
                    $response = Response::success($request->getId(), $result);
                } catch (Throwable $e) {
                    $response = Response::error(
                        $request->getId(),
                        Error::internalError($e->getMessage())
                    );
                }

                $this->transport->send($response->toJson());

                return;
            }
        }

        // No handler found
        $response = Response::error(
            $request->getId(),
            Error::methodNotFound("Method not found: $method")
        );

        $this->transport->send($response->toJson());
    }

    /**
     * Handle a roots/list request from the server.
     *
     * Delegates to the registered RootsHandlerInterface if available.
     * Without a handler, returns an empty roots array as the default.
     *
     * @param Request $request The roots/list request.
     *
     * @throws TransportException When sending the response fails.
     * @throws JsonRpcException When encoding the response fails.
     */
    private function handleRootsListRequest(Request $request): void
    {
        try {
            $roots = $this->roots_handler !== null
                ? $this->roots_handler->handleListRoots()
                : [];

            $response = Response::success($request->getId(), ['roots' => $roots]);
        } catch (Throwable $e) {
            $this->logger->error('Error handling roots/list request', [
                'error' => $e->getMessage(),
            ]);

            $response = Response::error(
                $request->getId(),
                Error::internalError($e->getMessage())
            );
        }

        $this->transport->send($response->toJson());
    }

    /**
     * Handle a server-initiated notification.
     *
     * @param Notification $notification The notification from the server.
     */
    private function handleServerNotification(Notification $notification): void
    {
        $method = $notification->getMethod();
        $params = $notification->getParams() ?? [];

        $this->logger->debug('Received server notification', [ 'method' => $method ]);

        foreach ($this->message_handlers as $handler) {
            if ($handler->supports($method)) {
                try {
                    $handler->handleNotification($method, $params);
                } catch (Throwable $e) {
                    $this->logger->error('Error handling notification', [
                        'method' => $method,
                        'error'  => $e->getMessage(),
                    ]);
                }

                return;
            }
        }
    }

    /**
     * Ensure the client is connected.
     *
     * @throws ConnectionException When not connected.
     */
    private function ensureConnected(): void
    {
        if (! $this->initialized) {
            throw new ConnectionException('Client is not connected');
        }
    }

    /**
     * Ensure the server supports a required capability.
     *
     * @param string $capability The capability name (e.g. 'tools', 'resources').
     * @param string $method     The MCP method requiring the capability.
     *
     * @throws CapabilityException When the server does not support the capability.
     */
    private function ensureCapability(string $capability, string $method): void
    {
        if ($this->server_info === null) {
            throw new ConnectionException('Client is not connected');
        }

        $capabilities = $this->server_info->getCapabilities();

        switch ($capability) {
            case 'tools':
                $supported = $capabilities->hasTools();
                break;
            case 'resources':
                $supported = $capabilities->hasResources();
                break;
            case 'prompts':
                $supported = $capabilities->hasPrompts();
                break;
            case 'logging':
                $supported = $capabilities->hasLogging();
                break;
            default:
                $supported = false;
        }

        if (!$supported) {
            throw new CapabilityException(
                "Server does not support '{$capability}' capability required for {$method}"
            );
        }
    }

    /**
     * Execute a paginated list request with capability enforcement and response validation.
     *
     * @param string      $method     The JSON-RPC method name.
     * @param string      $capability The capability name required for this method.
     * @param string|null $cursor     Optional pagination cursor.
     * @param float       $timeout    Request timeout in seconds.
     *
     * @return array<string, mixed> The server response.
     *
     * @throws McpException When the request fails.
     * @throws CapabilityException When the server does not support the capability.
     */
    private function paginatedList(
        string $method,
        string $capability,
        ?string $cursor = null,
        float $timeout = 30.0
    ): array {
        $this->ensureConnected();
        $this->ensureCapability($capability, $method);

        $params = [];

        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        $result = $this->requestArray($method, $params, $timeout);
        $this->validateResponse($method, $result);

        return $result;
    }

    /**
     * Normalize arguments for JSON serialization.
     *
     * MCP servers expect empty objects as {} not []. This ensures empty
     * argument arrays are encoded as JSON objects.
     *
     * @param array<string, mixed> $arguments The arguments to normalize.
     *
     * @return stdClass|array<string, mixed> stdClass for empty, original array otherwise.
     */
    private function normalizeArguments(array $arguments)
    {
        return empty($arguments) ? new stdClass() : $arguments;
    }

    /**
     * Validate that a server response contains expected keys for the given method.
     *
     * @param string              $method The JSON-RPC method name.
     * @param array<string, mixed> $result The response result array.
     *
     * @throws McpException When the response is missing required fields.
     */
    private function validateResponse(string $method, array $result): void
    {
        $key = self::RESPONSE_REQUIRED_KEYS[$method] ?? null;

        if ($key !== null && !\array_key_exists($key, $result)) {
            throw new McpException(
                "Invalid response for {$method}: missing required '{$key}' field"
            );
        }
    }
}
