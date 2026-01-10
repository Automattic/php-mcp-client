<?php

declare(strict_types=1);

namespace Ovidiu\McpClient\Core\Client;

use Ovidiu\McpClient\Core\Contracts\MessageHandlerInterface;
use Ovidiu\McpClient\Core\Contracts\TransportInterface;
use Ovidiu\McpClient\Core\Exception\ConnectionException;
use Ovidiu\McpClient\Core\Exception\JsonRpcException;
use Ovidiu\McpClient\Core\Exception\McpException;
use Ovidiu\McpClient\Core\Exception\TimeoutException;
use Ovidiu\McpClient\Core\Exception\TransportException;
use Ovidiu\McpClient\Core\JsonRpc\Error;
use Ovidiu\McpClient\Core\JsonRpc\IdGenerator;
use Ovidiu\McpClient\Core\JsonRpc\Message;
use Ovidiu\McpClient\Core\JsonRpc\Notification;
use Ovidiu\McpClient\Core\JsonRpc\Request;
use Ovidiu\McpClient\Core\JsonRpc\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use Throwable;

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

    private TransportInterface $transport;
    private ClientCapabilities $capabilities;
    private LoggerInterface $logger;
    private IdGenerator $id_generator;

    private ?ServerInfo $server_info = null;
    private bool $initialized = false;

    /** @var array<MessageHandlerInterface> */
    private array $message_handlers = [];

    private string $client_name;
    private string $client_version;

    /**
     * Create a new MCP client.
     *
     * @param TransportInterface $transport The transport to use.
     * @param ClientCapabilities $capabilities The client capabilities.
     * @param string $client_name The client application name.
     * @param string $client_version The client application version.
     * @param LoggerInterface|null $logger Optional logger.
     */
    public function __construct(
        TransportInterface $transport,
        ClientCapabilities $capabilities,
        string $client_name = 'php-mcp-client',
        string $client_version = '1.0.0',
        ?LoggerInterface $logger = null
    ) {
        $this->transport      = $transport;
        $this->capabilities   = $capabilities;
        $this->client_name    = $client_name;
        $this->client_version = $client_version;
        $this->logger         = $logger ?? new NullLogger();
        $this->id_generator   = new IdGenerator();
    }

    /**
     * Add a message handler for server-initiated requests/notifications.
     */
    public function addMessageHandler(MessageHandlerInterface $handler): void
    {
        $this->message_handlers[] = $handler;
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
        $params = [];

        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        return $this->requestArray('tools/list', $params, $timeout);
    }

    /**
     * Call a tool on the server.
     *
     * @param string $name The tool name.
     * @param array<string, mixed> $arguments The tool arguments.
     * @param float $timeout Request timeout in seconds.
     *
     * @return array<string, mixed> The tool result.
     *
     * @throws McpException When the request fails.
     */
    public function callTool(string $name, array $arguments = [], float $timeout = 60.0): array
    {
        return $this->requestArray('tools/call', [
            'name'      => $name,
            'arguments' => empty($arguments) ? new stdClass() : $arguments,
        ], $timeout);
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
        $params = [];

        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        return $this->requestArray('resources/list', $params, $timeout);
    }

    /**
     * Read a resource from the server.
     *
     * @param string $uri The resource URI.
     * @param float $timeout Request timeout in seconds.
     *
     * @return array<string, mixed> The resource content.
     *
     * @throws McpException When the request fails.
     */
    public function readResource(string $uri, float $timeout = 30.0): array
    {
        return $this->requestArray('resources/read', [
            'uri' => $uri,
        ], $timeout);
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
        $params = [];

        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        return $this->requestArray('prompts/list', $params, $timeout);
    }

    /**
     * Get a prompt from the server.
     *
     * @param string $name The prompt name.
     * @param array<string, mixed> $arguments The prompt arguments.
     * @param float $timeout Request timeout in seconds.
     *
     * @return array<string, mixed> The prompt result.
     *
     * @throws McpException When the request fails.
     */
    public function getPrompt(string $name, array $arguments = [], float $timeout = 30.0): array
    {
        return $this->requestArray('prompts/get', [
            'name'      => $name,
            'arguments' => empty($arguments) ? new stdClass() : $arguments,
        ], $timeout);
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
     * @param string $method The method name.
     * @param array<string, mixed> $params The request parameters.
     * @param float $timeout Request timeout in seconds.
     *
     * @return array<string, mixed> The response result.
     *
     * @throws McpException When the request fails or result is not an array.
     */
    public function requestArray(string $method, array $params = [], float $timeout = 30.0): array
    {
        $result = $this->request($method, $params, $timeout);

        if (! is_array($result)) {
            throw new McpException("Expected array response for $method, got " . gettype($result));
        }

        return $result;
    }

    /**
     * Send a request and wait for response.
     *
     * @param string $method The method name.
     * @param array<string, mixed> $params The request parameters.
     * @param float $timeout Request timeout in seconds.
     *
     * @return mixed The response result.
     *
     * @throws McpException When the request fails.
     */
    public function request(string $method, array $params = [], float $timeout = 30.0)
    {
        $this->ensureConnected();

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

        $init_request = new Request($id, 'initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => $this->capabilities->toObject(),
            'clientInfo'      => [
                'name'    => $this->client_name,
                'version' => $this->client_version,
            ],
        ]);

        $this->logger->debug('Sending initialize request');

        $this->transport->send($init_request->toJson());

        $result = $this->waitForResponse($id, $timeout);

        if (! is_array($result)) {
            throw new McpException('Invalid initialize response');
        }

        $protocol_version = $result['protocolVersion'] ?? self::PROTOCOL_VERSION;
        $server_info_data = $result['serverInfo'] ?? [];
        $capabilities     = $result['capabilities'] ?? [];

        $this->server_info = new ServerInfo(
            $server_info_data['name'] ?? 'Unknown',
            $server_info_data['version'] ?? '0.0.0',
            $protocol_version,
            new ServerCapabilities($capabilities)
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
                throw new TimeoutException("Request $request_id timed out after $timeout seconds");
            }

            $message_json = $this->transport->receive($remaining);

            if ($message_json === null) {
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
                'json'  => $json,
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
        }
        // Responses are handled in waitForResponse
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
}
