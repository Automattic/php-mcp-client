<?php

/**
 * Example: Using the MCP Client with stdio transport.
 *
 * This example demonstrates connecting to an MCP server via stdio transport,
 * listing available tools, and calling a tool.
 *
 * Usage:
 *   php examples/example-stdio.php <server-command> [args...]
 *
 * Example with npx:
 *   php examples/example-stdio.php npx -y @modelcontextprotocol/server-filesystem /tmp
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Automattic\PhpMcpClient\Client\ClientCapabilities;
use Automattic\PhpMcpClient\Client\McpClient;
use Automattic\PhpMcpClient\Transport\StdioTransport;

if ($argc < 2) {
    echo "Usage: php examples/example-stdio.php <server-command> [args...]\n";
    echo "Example: php examples/example-stdio.php npx -y @modelcontextprotocol/server-filesystem /tmp\n";
    exit(1);
}

$command = $argv[1];
$args    = array_slice($argv, 2);

echo "Starting MCP client...\n";
echo "Command: {$command} " . implode(' ', $args) . "\n\n";

// Create transport
$transport = new StdioTransport($command, $args);

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
    $client->connect(30.0);

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

    // Example: Call tools
    if ($server_info->getCapabilities()->hasTools()) {
        // First, get allowed directories
        echo "Calling list_allowed_directories tool...\n";
        $result = $client->callTool('list_allowed_directories', []);

        $allowed_dir = null;
        foreach ($result['content'] ?? [] as $content) {
            if (isset($content['text'])) {
                echo "{$content['text']}\n";
                // Parse to find an actual directory path (starts with /)
                $lines = explode("\n", trim($content['text']));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, '/') === 0) {
                        $allowed_dir = $line;
                        break;
                    }
                }
            }
        }
        echo "\n";

        // List the allowed directory
        if ($allowed_dir) {
            echo "Calling list_directory on {$allowed_dir}...\n";
            $result = $client->callTool('list_directory', ['path' => $allowed_dir]);

            echo "Result:\n";
            foreach ($result['content'] ?? [] as $content) {
                if (isset($content['text'])) {
                    $text = $content['text'];
                    if (strlen($text) > 500) {
                        $text = substr($text, 0, 500) . "\n... (truncated)";
                    }
                    echo $text . "\n";
                }
            }
            echo "\n";
        }
    }
} catch (\Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
} finally {
    $client->disconnect();
    echo "Disconnected.\n";
}
