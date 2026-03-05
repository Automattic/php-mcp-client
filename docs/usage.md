# Usage guide

This guide covers everyday usage of the PHP MCP Client: connecting to servers, working with tools, resources, and prompts, and handling results.

## Connecting to a server

Every MCP session follows the same pattern: create a transport, configure capabilities, build the client, connect, do work, disconnect.

```php
<?php

use GalatanOvidiu\PhpMcpClient\Client\ClientCapabilities;
use GalatanOvidiu\PhpMcpClient\Client\McpClient;
use GalatanOvidiu\PhpMcpClient\Transport\StdioTransport;

// 1. Create a transport
$transport = new StdioTransport('npx', ['-y', '@modelcontextprotocol/server-filesystem', '/tmp']);

// 2. Configure client capabilities
$capabilities = new ClientCapabilities();

// 3. Build the client
$client = new McpClient(
    $transport,
    $capabilities,
    'my-app',      // client name
    '1.0.0'        // client version
);

// 4. Connect (performs MCP handshake)
$client->connect();

// 5. Do work...

// 6. Disconnect
$client->disconnect();
```

The `connect()` method handles the full MCP initialization handshake: it sends the `initialize` request with your client info and capabilities, verifies the protocol version matches, and sends the `notifications/initialized` notification.

### Client identity

You can provide optional identity fields that servers may display or log:

```php
$client = new McpClient(
    $transport,
    $capabilities,
    'my-app',                          // name (required)
    '2.1.0',                           // version (required)
    'A file management application',   // description (optional)
    'My App',                          // human-readable title (optional)
    'https://myapp.example.com'        // website URL (optional)
);
```

### Checking connection state

```php
if ($client->isConnected()) {
    // Client is connected and initialized
}
```

## Server info

After connecting, you can inspect what the server reported during the handshake:

```php
$server = $client->getServerInfo();

echo $server->getName();            // e.g. "filesystem-server"
echo $server->getVersion();         // e.g. "1.0.0"
echo $server->getProtocolVersion(); // "2025-11-25"
echo $server->getInstructions();    // Optional server instructions
echo $server->getDescription();     // Optional description
echo $server->getTitle();           // Optional human-readable title
echo $server->getWebsiteUrl();      // Optional URL
```

### Checking server capabilities

Before calling tools, resources, or prompts, check whether the server supports them:

```php
$caps = $server->getCapabilities();

$caps->hasTools();        // Can you call tools?
$caps->hasResources();    // Can you read resources?
$caps->hasPrompts();      // Can you use prompts?
$caps->hasLogging();      // Can you set logging level?
$caps->hasCompletions();  // Does it support completions?
```

The client enforces these automatically — calling `listTools()` on a server without tools capability throws a `CapabilityException`. But checking first lets you adapt your UI or logic.

## Tools

Tools are functions that the server exposes for the client to call.

### Listing tools

```php
$result = $client->listTools();

foreach ($result['tools'] as $tool) {
    echo $tool['name'] . ': ' . $tool['description'] . "\n";
    // $tool['inputSchema'] contains the JSON Schema for arguments
}
```

### Calling a tool

```php
$result = $client->callTool('read_file', ['path' => '/tmp/data.json']);

foreach ($result['content'] as $content) {
    if ($content['type'] === 'text') {
        echo $content['text'];
    }
}

// Check if the tool reported an error
if (!empty($result['isError'])) {
    echo "Tool returned an error\n";
}
```

Tool calls have a longer default timeout (60 seconds) since tools may perform complex operations. You can override it:

```php
$result = $client->callTool('slow_operation', ['input' => 'data'], 120.0);
```

## Resources

Resources are data that the server exposes for the client to read — files, database records, API responses, etc.

### Listing resources

```php
$result = $client->listResources();

foreach ($result['resources'] as $resource) {
    echo $resource['uri'] . ': ' . $resource['name'] . "\n";
}
```

### Reading a resource

```php
$result = $client->readResource('file:///tmp/config.json');

foreach ($result['contents'] as $content) {
    echo $content['text'];  // or $content['blob'] for binary
}
```

### Resource templates

Some servers expose URI templates that accept parameters:

```php
$result = $client->listResourceTemplates();

foreach ($result['resourceTemplates'] as $template) {
    echo $template['uriTemplate'] . ': ' . $template['name'] . "\n";
}
```

## Prompts

Prompts are reusable message templates that the server provides.

### Listing prompts

```php
$result = $client->listPrompts();

foreach ($result['prompts'] as $prompt) {
    echo $prompt['name'] . ': ' . $prompt['description'] . "\n";
}
```

### Getting a prompt

```php
$result = $client->getPrompt('summarize', ['text' => 'Long article content...']);

foreach ($result['messages'] as $message) {
    echo $message['role'] . ': ';
    echo $message['content']['text'] . "\n";
}
```

## Pagination

List operations (`listTools`, `listResources`, `listResourceTemplates`, `listPrompts`) support cursor-based pagination. When the server has more results, the response includes a `nextCursor`:

```php
$cursor = null;

do {
    $result = $client->listTools($cursor);

    foreach ($result['tools'] as $tool) {
        echo $tool['name'] . "\n";
    }

    $cursor = $result['nextCursor'] ?? null;
} while ($cursor !== null);
```

## Ping

Verify the server is still responsive:

```php
$client->ping(); // Throws TimeoutException if server doesn't respond within 5 seconds
```

You can set a custom timeout:

```php
$client->ping(2.0); // 2-second timeout
```

## Logging

Set the server's logging level (requires the server to support the `logging` capability):

```php
$client->setLoggingLevel('warning');
```

Valid levels: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`.

## Always disconnect

Use `try`/`finally` to ensure cleanup:

```php
$client->connect();

try {
    $result = $client->callTool('my_tool', ['arg' => 'value']);
} finally {
    $client->disconnect();
}
```

For stdio transport, `disconnect()` sends SIGTERM to the server process and waits up to 2 seconds for graceful shutdown before escalating to SIGKILL. For HTTP transport, it sends an HTTP DELETE to close the session.

## Next steps

- [Transports](transports.md) — choosing and configuring stdio vs HTTP transport
- [Advanced usage](advanced-usage.md) — message handlers, roots, cancellation, error handling
