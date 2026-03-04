# PHP MCP Client

A PHP implementation of the Model Context Protocol (MCP) client.

## Requirements

- PHP 7.4 or higher
- Composer

## Installation

```bash
composer install
```

## Usage

### Basic Example with stdio transport

```php
<?php

use GalatanOvidiu\PhpMcpClient\Client\ClientCapabilities;
use GalatanOvidiu\PhpMcpClient\Client\McpClient;
use GalatanOvidiu\PhpMcpClient\Transport\StdioTransport;

// Create transport (connects to MCP server as subprocess)
$transport = new StdioTransport('npx', ['-y', '@modelcontextprotocol/server-filesystem', '/tmp']);

// Create client capabilities
$capabilities = new ClientCapabilities();

// Create and connect client
$client = new McpClient($transport, $capabilities);
$client->connect();

// List available tools
$tools = $client->listTools();

// Call a tool
$result = $client->callTool('read_file', ['path' => '/tmp/example.txt']);

// Disconnect when done
$client->disconnect();
```

### Running the Example

```bash
php examples/example-stdio.php npx -y @modelcontextprotocol/server-filesystem /tmp
```

## Architecture

The client follows a clean architecture with separation between Core and Integration layers:

- **Core**: Platform-agnostic code (contracts, JSON-RPC handling, client logic)
- **Integration**: Transport implementations (stdio, HTTP)

## License

MIT
