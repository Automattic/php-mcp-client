<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Tests\Unit\Client;

use Automattic\PhpMcpClient\Client\ClientCapabilities;
use Automattic\PhpMcpClient\Client\McpClient;
use Automattic\PhpMcpClient\Contracts\RootsHandlerInterface;
use Automattic\PhpMcpClient\Exception\CapabilityException;
use Automattic\PhpMcpClient\Exception\JsonRpcException;
use Automattic\PhpMcpClient\Exception\McpException;
use Automattic\PhpMcpClient\Exception\TimeoutException;
use Automattic\PhpMcpClient\Exception\TransportException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for McpClient.
 *
 * @covers \Automattic\PhpMcpClient\Client\McpClient
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

    // -------------------------------------------------------------------------
    // callTool isError field (3 tests)
    // -------------------------------------------------------------------------

    /**
     * Test callTool returns isError field when server responds with isError true.
     */
    public function test_callTool_withIsErrorTrue_returnsResultWithIsError(): void
    {
        [$client, $transport] = $this->createConnectedClient(['tools' => new \stdClass()]);

        $expected = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Something went wrong',
                ],
            ],
            'isError' => true,
        ];

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => $expected,
        ]));

        $result = $client->callTool('failing-tool');

        $this->assertSame($expected, $result);
        $this->assertTrue($result['isError']);
    }

    /**
     * Test callTool returns isError field when server responds with isError false.
     */
    public function test_callTool_withIsErrorFalse_returnsResultWithIsError(): void
    {
        [$client, $transport] = $this->createConnectedClient(['tools' => new \stdClass()]);

        $expected = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Success',
                ],
            ],
            'isError' => false,
        ];

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => $expected,
        ]));

        $result = $client->callTool('successful-tool');

        $this->assertSame($expected, $result);
        $this->assertFalse($result['isError']);
    }

    /**
     * Test callTool returns result without isError when server omits the field.
     */
    public function test_callTool_withoutIsError_returnsResultWithoutIsError(): void
    {
        [$client, $transport] = $this->createConnectedClient(['tools' => new \stdClass()]);

        $expected = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Hello world',
                ],
            ],
        ];

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => $expected,
        ]));

        $result = $client->callTool('simple-tool');

        $this->assertSame($expected, $result);
        $this->assertArrayNotHasKey('isError', $result);
    }

    // -------------------------------------------------------------------------
    // Protocol Version Negotiation (3 tests)
    // -------------------------------------------------------------------------

    /**
     * Test connect throws McpException when server returns an unsupported protocol version.
     */
    public function test_connect_withUnsupportedProtocolVersion_throwsMcpException(): void
    {
        $transport = new MockTransport();

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'protocolVersion' => '2024-01-01',
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
            '1.0.0'
        );

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('server returned 2024-01-01');

        $client->connect();
    }

    /**
     * Test connect succeeds when server returns an older but supported protocol version.
     */
    public function test_connect_withOlderSupportedVersion_succeeds(): void
    {
        $transport = new MockTransport();

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'protocolVersion' => '2025-06-18',
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
            '1.0.0'
        );

        $client->connect();

        $this->assertSame('test-server', $client->getServerInfo()->getName());
        $this->assertSame('2025-06-18', $client->getServerInfo()->getProtocolVersion());
    }

    /**
     * Test connect throws McpException when server omits protocolVersion field.
     */
    public function test_connect_withMissingProtocolVersion_throwsMcpException(): void
    {
        $transport = new MockTransport();

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'serverInfo' => [
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
            '1.0.0'
        );

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('protocolVersion');

        $client->connect();
    }

    // -------------------------------------------------------------------------
    // _meta parameter support (3 tests)
    // -------------------------------------------------------------------------

    /**
     * Test callTool includes _meta with progressToken in request params.
     */
    public function test_callTool_withProgressToken_includesMetaInRequest(): void
    {
        [$client, $transport] = $this->createConnectedClient(['tools' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Done',
                    ],
                ],
            ],
        ]));

        $client->callTool('my-tool', ['arg' => 'val'], 60.0, ['progressToken' => 'tok-123']);

        $messages     = $transport->getSentMessages();
        $last_message = json_decode($messages[count($messages) - 1], true);

        $this->assertSame('tools/call', $last_message['method']);
        $this->assertArrayHasKey('_meta', $last_message['params']);
        $this->assertSame('tok-123', $last_message['params']['_meta']['progressToken']);
        // Verify original params are preserved alongside _meta.
        $this->assertSame('my-tool', $last_message['params']['name']);
        $this->assertSame(['arg' => 'val'], $last_message['params']['arguments']);
    }

    /**
     * Test callTool without meta does not include _meta in request params.
     */
    public function test_callTool_withoutMeta_doesNotIncludeMetaInRequest(): void
    {
        [$client, $transport] = $this->createConnectedClient(['tools' => new \stdClass()]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Done',
                    ],
                ],
            ],
        ]));

        $client->callTool('my-tool', ['arg' => 'val']);

        $messages     = $transport->getSentMessages();
        $last_message = json_decode($messages[count($messages) - 1], true);

        $this->assertSame('tools/call', $last_message['method']);
        $this->assertArrayNotHasKey('_meta', $last_message['params']);
    }

    /**
     * Test request() merges _meta into params when meta is non-empty.
     */
    public function test_request_withMeta_mergesMetaIntoParams(): void
    {
        [$client, $transport] = $this->createConnectedClient([]);

        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'result'  => new \stdClass(),
        ]));

        $client->request('ping', ['key' => 'value'], 30.0, ['progressToken' => 'tok-456']);

        $messages     = $transport->getSentMessages();
        $last_message = json_decode($messages[count($messages) - 1], true);

        $this->assertSame('ping', $last_message['method']);
        $this->assertSame('value', $last_message['params']['key']);
        $this->assertSame(['progressToken' => 'tok-456'], $last_message['params']['_meta']);
    }

    // -------------------------------------------------------------------------
    // sendCancellation (2 tests)
    // -------------------------------------------------------------------------

    /**
     * Test sendCancellation sends notifications/cancelled with reason when provided.
     */
    public function test_sendCancellation_withReason_sendsCorrectNotification(): void
    {
        [$client, $transport] = $this->createConnectedClient([]);

        $client->sendCancellation(42, 'User cancelled the request');

        $messages     = $transport->getSentMessages();
        $last_message = json_decode($messages[count($messages) - 1], true);

        $this->assertSame('notifications/cancelled', $last_message['method']);
        $this->assertSame(42, $last_message['params']['requestId']);
        $this->assertSame('User cancelled the request', $last_message['params']['reason']);
        // Notifications must not have an id field.
        $this->assertArrayNotHasKey('id', $last_message);
    }

    /**
     * Test sendCancellation omits reason field when null.
     */
    public function test_sendCancellation_withoutReason_omitsReasonField(): void
    {
        [$client, $transport] = $this->createConnectedClient([]);

        $client->sendCancellation('req-abc');

        $messages     = $transport->getSentMessages();
        $last_message = json_decode($messages[count($messages) - 1], true);

        $this->assertSame('notifications/cancelled', $last_message['method']);
        $this->assertSame('req-abc', $last_message['params']['requestId']);
        $this->assertArrayNotHasKey('reason', $last_message['params']);
    }

    // -------------------------------------------------------------------------
    // Auto-cancellation on timeout (2 tests)
    // -------------------------------------------------------------------------

    /**
     * Test that waitForResponse sends a cancellation notification before throwing TimeoutException.
     */
    public function test_request_onTimeout_sendsCancellationNotification(): void
    {
        [$client, $transport] = $this->createConnectedClient(['tools' => new \stdClass()]);

        // Do not queue any response for the tools/call request — receive() returns null → timeout.

        try {
            $client->callTool('slow-tool', [], 0.1);
            $this->fail('Expected TimeoutException was not thrown');
        } catch (TimeoutException $e) {
            // Expected.
        }

        $messages = $transport->getSentMessages();

        // Messages: 1) init request, 2) initialized notification, 3) tools/call request, 4) cancellation.
        $this->assertCount(4, $messages);

        $cancellation = json_decode($messages[3], true);

        $this->assertSame('notifications/cancelled', $cancellation['method']);
        $this->assertSame(2, $cancellation['params']['requestId']);
        $this->assertSame('Client timeout', $cancellation['params']['reason']);
        $this->assertArrayNotHasKey('id', $cancellation);
    }

    /**
     * Test that TimeoutException still propagates when cancellation send fails.
     */
    public function test_request_onTimeout_stillThrowsTimeoutExceptionIfCancellationFails(): void
    {
        [$client, $transport] = $this->createConnectedClient(['tools' => new \stdClass()]);

        // Do not queue any response — will trigger timeout.
        // The sequence after createConnectedClient is:
        //   send(tools/call request) → receive() returns null → sendTimeoutCancellation() → send(cancellation)
        // Use $after=1 so the tools/call send succeeds but the cancellation send throws.
        $transport->throwOnNextSend(new TransportException('Broken pipe'), 1);

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage('timed out');

        $client->callTool('slow-tool', [], 0.1);
    }

    // -------------------------------------------------------------------------
    // roots/list server request handling (5 tests)
    // -------------------------------------------------------------------------

    /**
     * Test roots/list without a handler returns empty roots array.
     */
    public function test_rootsList_withoutHandler_returnsEmptyRootsArray(): void
    {
        [$client, $transport] = $this->createConnectedClient([]);

        // Queue a server-initiated roots/list request.
        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 100,
            'method'  => 'roots/list',
        ]));

        $client->processMessages();

        $messages      = $transport->getSentMessages();
        $last_message  = json_decode($messages[count($messages) - 1], true);

        $this->assertSame('2.0', $last_message['jsonrpc']);
        $this->assertSame(100, $last_message['id']);
        $this->assertArrayHasKey('result', $last_message);
        $this->assertSame(['roots' => []], $last_message['result']);
    }

    /**
     * Test roots/list with a registered handler delegates and returns handler result.
     */
    public function test_rootsList_withHandler_delegatesToHandler(): void
    {
        [$client, $transport] = $this->createConnectedClient([]);

        $expected_roots = [
            ['uri' => 'file:///home/user/project', 'name' => 'My Project'],
            ['uri' => 'file:///tmp'],
        ];

        $handler = $this->createMock(RootsHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handleListRoots')
            ->willReturn($expected_roots);

        $client->setRootsHandler($handler);

        // Queue a server-initiated roots/list request.
        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 200,
            'method'  => 'roots/list',
        ]));

        $client->processMessages();

        $messages      = $transport->getSentMessages();
        $last_message  = json_decode($messages[count($messages) - 1], true);

        $this->assertSame(200, $last_message['id']);
        $this->assertSame(['roots' => $expected_roots], $last_message['result']);
        $this->assertArrayNotHasKey('error', $last_message);
    }

    /**
     * Test roots/list with a handler that throws returns an internal error response.
     */
    public function test_rootsList_withHandlerException_returnsInternalError(): void
    {
        [$client, $transport] = $this->createConnectedClient([]);

        $handler = $this->createMock(RootsHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handleListRoots')
            ->willThrowException(new \RuntimeException('Filesystem not available'));

        $client->setRootsHandler($handler);

        // Queue a server-initiated roots/list request.
        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 300,
            'method'  => 'roots/list',
        ]));

        $client->processMessages();

        $messages      = $transport->getSentMessages();
        $last_message  = json_decode($messages[count($messages) - 1], true);

        $this->assertSame(300, $last_message['id']);
        $this->assertArrayHasKey('error', $last_message);
        $this->assertArrayNotHasKey('result', $last_message);
        $this->assertSame(JsonRpcException::INTERNAL_ERROR, $last_message['error']['code']);
        $this->assertSame('Filesystem not available', $last_message['error']['message']);
    }

    /**
     * Test roots/list replacing handler uses the last registered handler.
     */
    public function test_rootsList_withReplacedHandler_usesLastHandler(): void
    {
        [$client, $transport] = $this->createConnectedClient([]);

        $first_handler = $this->createMock(RootsHandlerInterface::class);
        $first_handler->expects($this->never())
            ->method('handleListRoots');

        $second_roots = [
            ['uri' => 'file:///second'],
        ];

        $second_handler = $this->createMock(RootsHandlerInterface::class);
        $second_handler->expects($this->once())
            ->method('handleListRoots')
            ->willReturn($second_roots);

        $client->setRootsHandler($first_handler);
        $client->setRootsHandler($second_handler);

        // Queue a server-initiated roots/list request.
        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 400,
            'method'  => 'roots/list',
        ]));

        $client->processMessages();

        $messages      = $transport->getSentMessages();
        $last_message  = json_decode($messages[count($messages) - 1], true);

        $this->assertSame(['roots' => $second_roots], $last_message['result']);
    }

    /**
     * Test roots/list is handled before generic MessageHandlerInterface handlers.
     */
    public function test_rootsList_bypassesGenericMessageHandler(): void
    {
        [$client, $transport] = $this->createConnectedClient([]);

        // Register a generic MessageHandler that supports roots/list.
        $generic_handler = $this->createMock(\Automattic\PhpMcpClient\Contracts\MessageHandlerInterface::class);
        $generic_handler->method('supports')
            ->with('roots/list')
            ->willReturn(true);
        $generic_handler->expects($this->never())
            ->method('handleRequest');

        $client->addMessageHandler($generic_handler);

        // Queue a server-initiated roots/list request.
        $transport->queueResponse(json_encode([
            'jsonrpc' => '2.0',
            'id'      => 500,
            'method'  => 'roots/list',
        ]));

        $client->processMessages();

        $messages      = $transport->getSentMessages();
        $last_message  = json_decode($messages[count($messages) - 1], true);

        // Should use the built-in default (empty roots), not the generic handler.
        $this->assertSame(['roots' => []], $last_message['result']);
    }
}
