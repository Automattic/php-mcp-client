<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\Client;

use GalatanOvidiu\PhpMcpClient\Client\ClientCapabilities;
use GalatanOvidiu\PhpMcpClient\Client\McpClient;
use GalatanOvidiu\PhpMcpClient\Exception\CapabilityException;
use GalatanOvidiu\PhpMcpClient\Exception\McpException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for McpClient.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\Client\McpClient
 */
final class McpClientTest extends TestCase
{
    /**
     * Create a connected McpClient with a MockTransport.
     *
     * The mock server will advertise the given capabilities during initialization.
     * After connect(), the transport will have received the initialize request (id=1)
     * and the notifications/initialized notification.
     *
     * @param array<string, mixed> $capabilities Server capabilities to advertise.
     *
     * @return array{0: McpClient, 1: MockTransport}
     */
    private function createConnectedClient(array $capabilities = []): array
    {
        $transport = new MockTransport();

        // Queue the initialize response (for request id=1).
        $init_response = json_encode([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'protocolVersion' => '2025-11-25',
                'serverInfo'      => [
                    'name'    => 'test-server',
                    'version' => '1.0.0',
                ],
                'capabilities' => $capabilities,
            ],
        ]);

        $transport->queueResponse($init_response);

        $client = new McpClient(
            $transport,
            new ClientCapabilities(),
            'test-client',
            '1.0.0'
        );

        $client->connect();

        return [$client, $transport];
    }

    // -------------------------------------------------------------------------
    // Capability Enforcement (9 tests)
    // -------------------------------------------------------------------------

    /**
     * Test listTools throws CapabilityException when server lacks tools capability.
     */
    public function test_listTools_withoutToolsCapability_throwsCapabilityException(): void
    {
        [$client] = $this->createConnectedClient([]);

        $this->expectException(CapabilityException::class);
        $this->expectExceptionMessage("'tools'");

        $client->listTools();
    }

    /**
     * Test callTool throws CapabilityException when server lacks tools capability.
     */
    public function test_callTool_withoutToolsCapability_throwsCapabilityException(): void
    {
        [$client] = $this->createConnectedClient([]);

        $this->expectException(CapabilityException::class);
        $this->expectExceptionMessage("'tools'");

        $client->callTool('some-tool');
    }

    /**
     * Test listResources throws CapabilityException when server lacks resources capability.
     */
    public function test_listResources_withoutResourcesCapability_throwsCapabilityException(): void
    {
        [$client] = $this->createConnectedClient([]);

        $this->expectException(CapabilityException::class);
        $this->expectExceptionMessage("'resources'");

        $client->listResources();
    }

    /**
     * Test readResource throws CapabilityException when server lacks resources capability.
     */
    public function test_readResource_withoutResourcesCapability_throwsCapabilityException(): void
    {
        [$client] = $this->createConnectedClient([]);

        $this->expectException(CapabilityException::class);
        $this->expectExceptionMessage("'resources'");

        $client->readResource('file:///tmp/test.txt');
    }

    /**
     * Test listResourceTemplates throws CapabilityException when server lacks resources capability.
     */
    public function test_listResourceTemplates_withoutResourcesCapability_throwsCapabilityException(): void
    {
        [$client] = $this->createConnectedClient([]);

        $this->expectException(CapabilityException::class);
        $this->expectExceptionMessage("'resources'");

        $client->listResourceTemplates();
    }

    /**
     * Test listPrompts throws CapabilityException when server lacks prompts capability.
     */
    public function test_listPrompts_withoutPromptsCapability_throwsCapabilityException(): void
    {
        [$client] = $this->createConnectedClient([]);

        $this->expectException(CapabilityException::class);
        $this->expectExceptionMessage("'prompts'");

        $client->listPrompts();
    }

    /**
     * Test getPrompt throws CapabilityException when server lacks prompts capability.
     */
    public function test_getPrompt_withoutPromptsCapability_throwsCapabilityException(): void
    {
        [$client] = $this->createConnectedClient([]);

        $this->expectException(CapabilityException::class);
        $this->expectExceptionMessage("'prompts'");

        $client->getPrompt('some-prompt');
    }

    /**
     * Test setLoggingLevel throws CapabilityException when server lacks logging capability.
     */
    public function test_setLoggingLevel_withoutLoggingCapability_throwsCapabilityException(): void
    {
        [$client] = $this->createConnectedClient([]);

        $this->expectException(CapabilityException::class);
        $this->expectExceptionMessage("'logging'");

        $client->setLoggingLevel('debug');
    }

    /**
     * Test ping does not throw CapabilityException even with empty capabilities.
     */
    public function test_ping_withNoCapabilities_doesNotThrowCapabilityException(): void
    {
        [$client, $transport] = $this->createConnectedClient([]);

        // Queue the ping response (next id after initialization is 2).
        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => new \stdClass(),
        ]));

        $client->ping();

        // Verify a ping request was actually sent (3rd message: init request, init notification, ping).
        $messages = $transport->getSentMessages();
        $this->assertCount(3, $messages);

        $ping_message = json_decode($messages[2], true);
        $this->assertSame('ping', $ping_message['method']);
    }

    // -------------------------------------------------------------------------
    // listResourceTemplates (4 tests)
    // -------------------------------------------------------------------------

    /**
     * Test listResourceTemplates sends correct JSON-RPC method.
     */
    public function test_listResourceTemplates_sendsCorrectMethod(): void
    {
        [$client, $transport] = $this->createConnectedClient(['resources' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => [
                'resourceTemplates' => [],
            ],
        ]));

        $client->listResourceTemplates();

        $messages     = $transport->getSentMessages();
        $last_message = json_decode($messages[count($messages) - 1], true);

        $this->assertSame('resources/templates/list', $last_message['method']);
    }

    /**
     * Test listResourceTemplates includes cursor in params when provided.
     */
    public function test_listResourceTemplates_withCursor_includesCursorInParams(): void
    {
        [$client, $transport] = $this->createConnectedClient(['resources' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => [
                'resourceTemplates' => [],
            ],
        ]));

        $client->listResourceTemplates('next-page-token');

        $messages     = $transport->getSentMessages();
        $last_message = json_decode($messages[count($messages) - 1], true);

        $this->assertSame('next-page-token', $last_message['params']['cursor']);
    }

    /**
     * Test listResourceTemplates sends empty params without cursor.
     */
    public function test_listResourceTemplates_withoutCursor_sendsNoParams(): void
    {
        [$client, $transport] = $this->createConnectedClient(['resources' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => [
                'resourceTemplates' => [],
            ],
        ]));

        $client->listResourceTemplates();

        $messages     = $transport->getSentMessages();
        $last_message = json_decode($messages[count($messages) - 1], true);

        // When no cursor is provided, params should be absent (null params are omitted).
        $this->assertArrayNotHasKey('params', $last_message);
    }

    /**
     * Test listResourceTemplates returns server response.
     */
    public function test_listResourceTemplates_returnsServerResponse(): void
    {
        [$client, $transport] = $this->createConnectedClient(['resources' => new \stdClass()]);

        $expected = [
            'resourceTemplates' => [
                [
                    'uriTemplate' => 'file:///{path}',
                    'name'        => 'File Template',
                ],
            ],
            'nextCursor' => 'cursor-abc',
        ];

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => $expected,
        ]));

        $result = $client->listResourceTemplates();

        $this->assertSame($expected, $result);
    }

    // -------------------------------------------------------------------------
    // setLoggingLevel (3 tests)
    // -------------------------------------------------------------------------

    /**
     * Test setLoggingLevel sends correct method and params.
     */
    public function test_setLoggingLevel_withValidLevel_sendsCorrectRequest(): void
    {
        [$client, $transport] = $this->createConnectedClient(['logging' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => new \stdClass(),
        ]));

        $client->setLoggingLevel('warning');

        $messages     = $transport->getSentMessages();
        $last_message = json_decode($messages[count($messages) - 1], true);

        $this->assertSame('logging/setLevel', $last_message['method']);
        $this->assertSame('warning', $last_message['params']['level']);
    }

    /**
     * Test setLoggingLevel throws McpException for invalid level.
     */
    public function test_setLoggingLevel_withInvalidLevel_throwsMcpException(): void
    {
        [$client, $transport] = $this->createConnectedClient(['logging' => new \stdClass()]);

        $sent_before = count($transport->getSentMessages());

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Invalid logging level');

        $client->setLoggingLevel('verbose');

        // No additional request should have been sent.
        $this->assertCount($sent_before, $transport->getSentMessages());
    }

    /**
     * Test setLoggingLevel accepts all valid levels.
     */
    public function test_setLoggingLevel_acceptsAllValidLevels(): void
    {
        $valid_levels = [
            'debug',
            'info',
            'notice',
            'warning',
            'error',
            'critical',
            'alert',
            'emergency',
        ];

        foreach ($valid_levels as $level) {
            [$client, $transport] = $this->createConnectedClient(['logging' => new \stdClass()]);

            // The init used id=1, so the next request uses id=2.
            $transport->queueResponse(json_encode([
                'jsonrpc' => '2.0',
                'id'      => 2,
                'result'  => new \stdClass(),
            ]));

            // Should not throw.
            $client->setLoggingLevel($level);

            $messages     = $transport->getSentMessages();
            $last_message = json_decode($messages[count($messages) - 1], true);

            $this->assertSame(
                $level,
                $last_message['params']['level'],
                "Failed asserting that level '{$level}' was sent correctly"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Response Validation (8 tests)
    // -------------------------------------------------------------------------

    /**
     * Test listTools throws McpException when response lacks 'tools' key.
     */
    public function test_listTools_withMissingToolsKey_throwsMcpException(): void
    {
        [$client, $transport] = $this->createConnectedClient(['tools' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => ['notTools' => []],
        ]));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage("missing required 'tools' field");

        $client->listTools();
    }

    /**
     * Test callTool throws McpException when response lacks 'content' key.
     */
    public function test_callTool_withMissingContentKey_throwsMcpException(): void
    {
        [$client, $transport] = $this->createConnectedClient(['tools' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => ['notContent' => []],
        ]));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage("missing required 'content' field");

        $client->callTool('test-tool');
    }

    /**
     * Test listResources throws McpException when response lacks 'resources' key.
     */
    public function test_listResources_withMissingResourcesKey_throwsMcpException(): void
    {
        [$client, $transport] = $this->createConnectedClient(['resources' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => ['notResources' => []],
        ]));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage("missing required 'resources' field");

        $client->listResources();
    }

    /**
     * Test readResource throws McpException when response lacks 'contents' key.
     */
    public function test_readResource_withMissingContentsKey_throwsMcpException(): void
    {
        [$client, $transport] = $this->createConnectedClient(['resources' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => ['notContents' => []],
        ]));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage("missing required 'contents' field");

        $client->readResource('file:///tmp/test.txt');
    }

    /**
     * Test listResourceTemplates throws McpException when response lacks 'resourceTemplates' key.
     */
    public function test_listResourceTemplates_withMissingResourceTemplatesKey_throwsMcpException(): void
    {
        [$client, $transport] = $this->createConnectedClient(['resources' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => ['notResourceTemplates' => []],
        ]));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage("missing required 'resourceTemplates' field");

        $client->listResourceTemplates();
    }

    /**
     * Test listPrompts throws McpException when response lacks 'prompts' key.
     */
    public function test_listPrompts_withMissingPromptsKey_throwsMcpException(): void
    {
        [$client, $transport] = $this->createConnectedClient(['prompts' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => ['notPrompts' => []],
        ]));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage("missing required 'prompts' field");

        $client->listPrompts();
    }

    /**
     * Test getPrompt throws McpException when response lacks 'messages' key.
     */
    public function test_getPrompt_withMissingMessagesKey_throwsMcpException(): void
    {
        [$client, $transport] = $this->createConnectedClient(['prompts' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => ['notMessages' => []],
        ]));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage("missing required 'messages' field");

        $client->getPrompt('test-prompt');
    }

    /**
     * Test connect stores instructions from init response in ServerInfo.
     */
    public function test_connect_withInstructions_storesInstructionsInServerInfo(): void
    {
        $transport = new MockTransport();

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'protocolVersion' => '2025-11-25',
                'serverInfo'      => [
                    'name'    => 'test-server',
                    'version' => '1.0.0',
                ],
                'capabilities'  => [],
                'instructions'  => 'Use this server to access filesystem resources.',
            ],
        ]));

        $client = new McpClient(
            $transport,
            new ClientCapabilities(),
            'test-client',
            '1.0.0'
        );

        $client->connect();

        $this->assertSame(
            'Use this server to access filesystem resources.',
            $client->getServerInfo()->getInstructions()
        );
    }

    /**
     * Test connect without instructions results in null instructions.
     */
    public function test_connect_withoutInstructions_returnsNullInstructions(): void
    {
        [$client] = $this->createConnectedClient([]);

        $this->assertNull($client->getServerInfo()->getInstructions());
    }

    /**
     * Test listTools returns normally with valid response containing 'tools' key.
     */
    public function test_listTools_withValidResponse_returnsResult(): void
    {
        [$client, $transport] = $this->createConnectedClient(['tools' => new \stdClass()]);

        $expected = [
            'tools' => [
                [
                    'name'        => 'read_file',
                    'description' => 'Read a file',
                    'inputSchema' => ['type' => 'object'],
                ],
            ],
        ];

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => $expected,
        ]));

        $result = $client->listTools();

        $this->assertSame($expected, $result);
    }

    // -------------------------------------------------------------------------
    // Implementation fields: clientInfo (outgoing)
    // -------------------------------------------------------------------------

    /**
     * Test clientInfo includes description, title, websiteUrl when provided.
     */
    public function test_connect_withImplementationFields_sendsFieldsInClientInfo(): void
    {
        $transport = new MockTransport();

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'protocolVersion' => '2025-11-25',
                'serverInfo'      => [
                    'name'    => 'test-server',
                    'version' => '1.0.0',
                ],
                'capabilities' => [],
            ],
        ]));

        $client = new McpClient(
            $transport,
            new ClientCapabilities(),
            'test-client',
            '1.0.0',
            'A test MCP client',
            'Test Client',
            'https://example.com'
        );

        $client->connect();

        $messages     = $transport->getSentMessages();
        $init_message = json_decode($messages[0], true);
        $client_info  = $init_message['params']['clientInfo'];

        $this->assertSame('test-client', $client_info['name']);
        $this->assertSame('1.0.0', $client_info['version']);
        $this->assertSame('A test MCP client', $client_info['description']);
        $this->assertSame('Test Client', $client_info['title']);
        $this->assertSame('https://example.com', $client_info['websiteUrl']);
    }

    /**
     * Test clientInfo omits description, title, websiteUrl when null.
     */
    public function test_connect_withoutImplementationFields_omitsFieldsFromClientInfo(): void
    {
        [$client, $transport] = $this->createConnectedClient([]);

        $messages     = $transport->getSentMessages();
        $init_message = json_decode($messages[0], true);
        $client_info  = $init_message['params']['clientInfo'];

        $this->assertArrayNotHasKey('description', $client_info);
        $this->assertArrayNotHasKey('title', $client_info);
        $this->assertArrayNotHasKey('websiteUrl', $client_info);
    }

    /**
     * Test clientInfo includes only non-null Implementation fields.
     */
    public function test_connect_withPartialImplementationFields_includesOnlyNonNullFields(): void
    {
        $transport = new MockTransport();

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'protocolVersion' => '2025-11-25',
                'serverInfo'      => [
                    'name'    => 'test-server',
                    'version' => '1.0.0',
                ],
                'capabilities' => [],
            ],
        ]));

        $client = new McpClient(
            $transport,
            new ClientCapabilities(),
            'test-client',
            '1.0.0',
            'A test MCP client',
            null,
            null
        );

        $client->connect();

        $messages     = $transport->getSentMessages();
        $init_message = json_decode($messages[0], true);
        $client_info  = $init_message['params']['clientInfo'];

        $this->assertSame('A test MCP client', $client_info['description']);
        $this->assertArrayNotHasKey('title', $client_info);
        $this->assertArrayNotHasKey('websiteUrl', $client_info);
    }

    // -------------------------------------------------------------------------
    // Implementation fields: ServerInfo (incoming)
    // -------------------------------------------------------------------------

    /**
     * Test ServerInfo stores description, title, websiteUrl from server response.
     */
    public function test_connect_withServerImplementationFields_storesFieldsInServerInfo(): void
    {
        $transport = new MockTransport();

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'protocolVersion' => '2025-11-25',
                'serverInfo'      => [
                    'name'        => 'test-server',
                    'version'     => '1.0.0',
                    'description' => 'A filesystem access server',
                    'title'       => 'Filesystem Server',
                    'websiteUrl'  => 'https://server.example.com',
                ],
                'capabilities' => [],
            ],
        ]));

        $client = new McpClient(
            $transport,
            new ClientCapabilities(),
            'test-client',
            '1.0.0'
        );

        $client->connect();

        $server_info = $client->getServerInfo();

        $this->assertSame('A filesystem access server', $server_info->getDescription());
        $this->assertSame('Filesystem Server', $server_info->getTitle());
        $this->assertSame('https://server.example.com', $server_info->getWebsiteUrl());
    }

    /**
     * Test ServerInfo returns null for missing Implementation fields.
     */
    public function test_connect_withoutServerImplementationFields_returnsNullForFields(): void
    {
        [$client] = $this->createConnectedClient([]);

        $server_info = $client->getServerInfo();

        $this->assertNull($server_info->getDescription());
        $this->assertNull($server_info->getTitle());
        $this->assertNull($server_info->getWebsiteUrl());
    }
}
