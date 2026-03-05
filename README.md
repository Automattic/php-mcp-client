# PHP MCP Client

A PHP client for the [Model Context Protocol](https://modelcontextprotocol.io/) (MCP). Connects to MCP servers over stdio or HTTP, letting you call tools, read resources, and use prompts from any PHP application.

**Protocol version:** 2025-11-25

## Features

- **Two transports** — stdio (subprocess) and HTTP (remote server)
- **Full MCP support** — tools, resources, resource templates, and prompts
- **Capability negotiation** — automatic handshake with protocol version verification
- **Server-initiated messages** — handle requests and notifications from the server
- **Roots support** — respond to `roots/list` server requests
- **Request cancellation** — manual and automatic cancellation on timeout
- **Pagination** — cursor-based pagination for list operations
- **Logging** — PSR-3 compatible, pass any logger implementation
- **Timeouts** — configurable per-request timeouts with sensible defaults

## Requirements

- PHP 7.4 or higher
- `ext-json`
- `ext-curl` (for HTTP transport)
- `proc_open` enabled (for stdio transport)

## Installation

```bash
composer require galatanovidiu/php-mcp-client
```

> **Note:** This package depends on `wordpress/php-mcp-schema` which is loaded from a VCS repository. Add the following to your project's `composer.json` if it is not already present:
>
> ```json
> "repositories": [
>     {
>         "type": "vcs",
>         "url": "https://github.com/WordPress/php-mcp-schema"
>     }
> ]
> ```

## Quick start

### Stdio transport

Connect to an MCP server running as a local subprocess:

```php
<?php

use Automattic\PhpMcpClient\Client\ClientCapabilities;
use Automattic\PhpMcpClient\Client\McpClient;
use Automattic\PhpMcpClient\Transport\StdioTransport;

$transport    = new StdioTransport('npx', ['-y', '@modelcontextprotocol/server-filesystem', '/tmp']);
$capabilities = new ClientCapabilities();
$client       = new McpClient($transport, $capabilities);

$client->connect();

// List and call tools
$tools  = $client->listTools();
$result = $client->callTool('read_file', ['path' => '/tmp/example.txt']);

$client->disconnect();
```

### HTTP transport

Connect to a remote MCP server over HTTP:

```php
<?php

use Automattic\PhpMcpClient\Client\ClientCapabilities;
use Automattic\PhpMcpClient\Client\McpClient;
use Automattic\PhpMcpClient\Transport\Http\HttpTransport;

$transport = new HttpTransport(
    'https://mcp.example.com/api',
    null,
    null,
    ['Authorization' => 'Bearer your-token']
);

$capabilities = new ClientCapabilities();
$client       = new McpClient($transport, $capabilities);

$client->connect();

$tools = $client->listTools();

$client->disconnect();
```

## Documentation

- [Usage guide](docs/usage.md) — connecting, listing tools/resources/prompts, calling tools, pagination
- [Transports](docs/transports.md) — stdio vs HTTP, configuration, custom HTTP clients
- [Advanced usage](docs/advanced-usage.md) — message handlers, roots, cancellation, error handling, logging

## Running the examples

```bash
# Stdio transport with the filesystem server
php examples/example-stdio.php npx -y @modelcontextprotocol/server-filesystem /tmp

# HTTP transport
php examples/example-http.php http://localhost:8080/mcp
php examples/example-http.php https://api.example.com/mcp "your-bearer-token"
```

## Architecture

```
src/
├── Client/         # McpClient, capabilities, server info
├── Contracts/      # TransportInterface, MessageHandlerInterface, RootsHandlerInterface
├── Exception/      # McpException hierarchy
├── JsonRpc/        # JSON-RPC 2.0 message types
└── Transport/      # Stdio and HTTP transport implementations
```

The client communicates with MCP servers using JSON-RPC 2.0 messages sent through a transport layer. The transport is pluggable — implement `TransportInterface` to add custom transports.

## Development

```bash
composer install          # Install dependencies
composer test             # Run tests
composer phpstan          # Static analysis (level 9)
composer phpcs            # Code style checks
```

## License

MIT
