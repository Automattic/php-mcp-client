# Advanced usage

This guide covers features you will need when building production integrations: handling server-initiated messages, providing roots, cancelling requests, dealing with errors, and adding logging.

## Client capabilities

Capabilities tell the server what your client supports. Configure them before connecting:

```php
use Automattic\PhpMcpClient\Client\ClientCapabilities;

$capabilities = (new ClientCapabilities())
    ->withRoots()        // Advertise roots support
    ->withSampling()     // Allow server to request LLM completions
    ->withElicitation(); // Allow server to request additional user info
```

`ClientCapabilities` is immutable — each `with*()` method returns a new instance.

If you do not enable any capabilities, the client sends an empty capabilities object during initialization, which is valid.

### Experimental capabilities

Pass arbitrary experimental capabilities as a key-value array:

```php
$capabilities = (new ClientCapabilities())
    ->withExperimental([
        'myFeature' => ['version' => 1],
    ]);
```

## Message handlers

MCP servers can send requests and notifications to the client at any time. Register handlers to respond to them.

### Implementing a handler

```php
use Automattic\PhpMcpClient\Contracts\MessageHandlerInterface;

class LoggingHandler implements MessageHandlerInterface
{
    public function supports(string $method): bool
    {
        return $method === 'notifications/message';
    }

    public function handleRequest(string $method, array $params)
    {
        // Return a value to send as the response
        return ['status' => 'ok'];
    }

    public function handleNotification(string $method, array $params): void
    {
        // Handle server log messages
        $level = $params['level'] ?? 'info';
        $data  = $params['data'] ?? '';
        echo "[{$level}] {$data}\n";
    }
}
```

### Registering handlers

Register handlers before connecting:

```php
$client->addMessageHandler(new LoggingHandler());
$client->addMessageHandler(new AnotherHandler());

$client->connect();
```

You can register multiple handlers. The client checks each one in order via `supports()` and dispatches to the first match.

If a handler throws an exception during `handleRequest()`, the client catches it and returns a JSON-RPC internal error response to the server. For `handleNotification()`, exceptions are logged but do not affect the client.

### Polling for messages

Server-initiated messages arrive through the transport. While waiting for a response (inside `request()`, `callTool()`, etc.), the client processes them automatically. For long-running sessions where you need to handle messages between your own requests, call `processMessages()`:

```php
// Poll for server messages with a short timeout
$client->processMessages(0.1); // Wait up to 100ms
```

This is useful when your application has idle periods and you want to stay responsive to server notifications.

## Roots handler

When a server sends a `roots/list` request, the client can respond with a list of filesystem or URI boundaries that your application exposes. This helps servers understand the scope of your project.

### Implementing the roots handler

```php
use Automattic\PhpMcpClient\Contracts\RootsHandlerInterface;

class ProjectRootsHandler implements RootsHandlerInterface
{
    private string $project_path;

    public function __construct(string $project_path)
    {
        $this->project_path = $project_path;
    }

    public function handleListRoots(): array
    {
        return [
            ['uri' => 'file://' . $this->project_path, 'name' => 'Project root'],
            ['uri' => 'file://' . $this->project_path . '/src', 'name' => 'Source code'],
        ];
    }
}
```

### Registering the roots handler

```php
$capabilities = (new ClientCapabilities())->withRoots();
$client       = new McpClient($transport, $capabilities);

$client->setRootsHandler(new ProjectRootsHandler('/home/user/my-project'));
$client->connect();
```

Without a registered handler, the client returns an empty roots array by default.

## Request cancellation

### Automatic cancellation on timeout

When a request times out, the client automatically sends a `notifications/cancelled` notification to the server before throwing `TimeoutException`. This tells the server to stop processing the request and free resources.

```php
try {
    // If this times out, a cancellation is sent automatically
    $result = $client->callTool('slow_tool', ['data' => 'large'], 10.0);
} catch (\Automattic\PhpMcpClient\Exception\TimeoutException $e) {
    // Server has been notified to cancel
    echo "Request timed out: " . $e->getMessage() . "\n";
}
```

### Manual cancellation

For more control, use `sendCancellation()` to cancel a request by its ID:

```php
$client->sendCancellation($request_id, 'User cancelled the operation');
```

This sends a `notifications/cancelled` notification. Note that the server may or may not honor the cancellation — it is advisory.

## Protocol-level metadata

Some MCP operations accept a `_meta` parameter for protocol-level metadata like progress tokens. Pass it as the last argument:

```php
$result = $client->callTool('long_operation', ['input' => 'data'], 120.0, [
    'progressToken' => 'my-progress-token-123',
]);

$result = $client->readResource('file:///large-file.bin', 30.0, [
    'progressToken' => 'resource-read-456',
]);

$result = $client->getPrompt('analyze', ['text' => '...'], 30.0, [
    'progressToken' => 'prompt-789',
]);
```

## Low-level requests

For MCP methods not covered by the convenience methods, use `request()` or `requestArray()` directly:

```php
// request() returns mixed
$result = $client->request('custom/method', ['key' => 'value'], 30.0);

// requestArray() asserts the result is an array
$result = $client->requestArray('custom/method', ['key' => 'value'], 30.0);
```

### Sending notifications

Send a one-way notification to the server (no response expected):

```php
$client->notify('custom/event', ['action' => 'completed']);
```

## Error handling

All exceptions extend `McpException`, so you can catch everything with a single catch block or handle specific cases.

### Exception hierarchy

```
McpException                    Base class for all MCP errors
├── ConnectionException         Not connected or connection lost
├── CapabilityException         Server doesn't support a required capability
├── TimeoutException            Request timed out
├── TransportException          Transport layer failure
│   └── HttpClientException     HTTP-specific failure (adds getStatusCode())
└── JsonRpcException            Server returned a JSON-RPC error
                                (adds getErrorCode(), getErrorData())
```

### Handling specific errors

```php
use Automattic\PhpMcpClient\Exception\CapabilityException;
use Automattic\PhpMcpClient\Exception\ConnectionException;
use Automattic\PhpMcpClient\Exception\JsonRpcException;
use Automattic\PhpMcpClient\Exception\McpException;
use Automattic\PhpMcpClient\Exception\TimeoutException;
use Automattic\PhpMcpClient\Exception\TransportException;

try {
    $result = $client->callTool('my_tool', ['arg' => 'value']);
} catch (TimeoutException $e) {
    // Request took too long — server has been notified to cancel
    echo "Timed out\n";
} catch (CapabilityException $e) {
    // Server doesn't support tools
    echo "Not supported: " . $e->getMessage() . "\n";
} catch (JsonRpcException $e) {
    // Server returned an error response
    echo "Server error {$e->getErrorCode()}: " . $e->getMessage() . "\n";
    $details = $e->getErrorData(); // Additional error context (may be null)
} catch (ConnectionException $e) {
    // Client is not connected
    echo "Not connected\n";
} catch (TransportException $e) {
    // Transport layer failure (process died, HTTP error, etc.)
    echo "Transport error: " . $e->getMessage() . "\n";
} catch (McpException $e) {
    // Catch-all for any other MCP error
    echo "Error: " . $e->getMessage() . "\n";
}
```

### JSON-RPC error codes

When the server returns a JSON-RPC error, `JsonRpcException` provides the standard error codes:

| Code | Constant | Meaning |
|---|---|---|
| -32700 | `PARSE_ERROR` | Invalid JSON |
| -32600 | `INVALID_REQUEST` | Invalid request object |
| -32601 | `METHOD_NOT_FOUND` | Method does not exist |
| -32602 | `INVALID_PARAMS` | Invalid method parameters |
| -32603 | `INTERNAL_ERROR` | Internal server error |

## Logging

The client accepts any PSR-3 logger. Pass it in the constructor:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('mcp');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

$client = new McpClient(
    $transport,
    $capabilities,
    'my-app',
    '1.0.0',
    null,    // description
    null,    // title
    null,    // website URL
    $logger
);
```

The client logs:
- `debug` — every request sent and response received, connection events
- `info` — successful initialization with server name and protocol version
- `warning` — failed timeout cancellations, unmatched responses
- `error` — failed message parsing, handler exceptions

The HTTP transport also accepts a separate logger for transport-level debugging:

```php
$transport = new HttpTransport(
    'https://mcp.example.com/api',
    null,
    $logger  // Transport-level logging
);
```

Session IDs are masked in transport logs (only the first 8 characters are shown) to prevent leaking sensitive tokens.

## Complete example

Putting it all together — a client that connects with full capabilities, handles server messages, and manages errors:

```php
<?php

use Automattic\PhpMcpClient\Client\ClientCapabilities;
use Automattic\PhpMcpClient\Client\McpClient;
use Automattic\PhpMcpClient\Exception\McpException;
use Automattic\PhpMcpClient\Transport\StdioTransport;

$transport    = new StdioTransport('npx', ['-y', '@modelcontextprotocol/server-filesystem', '/tmp']);
$capabilities = (new ClientCapabilities())->withRoots();

$client = new McpClient($transport, $capabilities, 'my-app', '1.0.0');

// Register handlers
$client->setRootsHandler(new ProjectRootsHandler('/home/user/project'));
$client->addMessageHandler(new LoggingHandler());

try {
    $client->connect();

    $server = $client->getServerInfo();
    echo "Connected to {$server->getName()} v{$server->getVersion()}\n";

    // List and call tools if supported
    if ($server->getCapabilities()->hasTools()) {
        $tools = $client->listTools();

        foreach ($tools['tools'] as $tool) {
            echo "  {$tool['name']}: {$tool['description']}\n";
        }
    }

    // Poll for any server-initiated messages
    $client->processMessages();

} catch (McpException $e) {
    echo "MCP error: " . $e->getMessage() . "\n";
} finally {
    $client->disconnect();
}
```

## Related docs

- [Usage guide](usage.md) — everyday usage: tools, resources, prompts, pagination
- [Transports](transports.md) — stdio vs HTTP, custom transports
