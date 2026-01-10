<?php

/**
 * Example: Using the MCP Client with HTTP transport.
 *
 * This example demonstrates connecting to an MCP server via HTTP transport,
 * listing available tools, and disconnecting.
 *
 * Usage:
 *   php examples/example-http.php <endpoint-url>
 *
 * Example:
 *   php examples/example-http.php http://localhost:8080/mcp
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Ovidiu\McpClient\Core\Client\ClientCapabilities;
use Ovidiu\McpClient\Core\Client\McpClient;
use Ovidiu\McpClient\Integration\Transport\Http\HttpTransport;

if ($argc < 2) {
    echo "Usage: php examples/example-http.php <endpoint-url>\n";
    echo "Example: php examples/example-http.php http://localhost:8080/mcp\n";
    exit(1);
}

$endpoint_url = $argv[1];

echo "Starting MCP client...\n";
echo "Endpoint: {$endpoint_url}\n\n";

// Create transport
$transport = new HttpTransport($endpoint_url);

// Create client capabilities
$capabilities = new ClientCapabilities();

// Create client
$client = new McpClient(
    $transport,
    $capabilities,
    'php-mcp-client-example',
    '1.0.0'
);

try {
    // Connect to server
    echo "Connecting to MCP server...\n";
    $client->connect(timeout: 30.0);

    $server_info = $client->getServerInfo();
    echo "Connected to: {$server_info->getName()} v{$server_info->getVersion()}\n";
    echo "Protocol: {$server_info->getProtocolVersion()}\n\n";

    // List available tools
    if ($server_info->getCapabilities()->hasTools()) {
        echo "Available tools:\n";
        $tools_result = $client->listTools();

        foreach ($tools_result['tools'] ?? [] as $tool) {
            echo "  - {$tool['name']}: {$tool['description']}\n";
        }
        echo "\n";
    }

    // List available resources
    if ($server_info->getCapabilities()->hasResources()) {
        echo "Available resources:\n";
        $resources_result = $client->listResources();

        foreach ($resources_result['resources'] ?? [] as $resource) {
            echo "  - {$resource['uri']}: {$resource['name']}\n";
        }
        echo "\n";
    }

    // List available prompts
    if ($server_info->getCapabilities()->hasPrompts()) {
        echo "Available prompts:\n";
        $prompts_result = $client->listPrompts();

        foreach ($prompts_result['prompts'] ?? [] as $prompt) {
            echo "  - {$prompt['name']}: {$prompt['description']}\n";
        }
        echo "\n";
    }

    // Test ping
    echo "Pinging server...\n";
    $client->ping();
    echo "Pong!\n\n";
} catch (\Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
} finally {
    $client->disconnect();
    echo "Disconnected.\n";
}
