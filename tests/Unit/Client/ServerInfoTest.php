<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Tests\Unit\Client;

use Automattic\PhpMcpClient\Client\ServerCapabilities;
use Automattic\PhpMcpClient\Client\ServerInfo;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ServerInfo value object.
 *
 * @covers \Automattic\PhpMcpClient\Client\ServerInfo
 */
final class ServerInfoTest extends TestCase
{
    /**
     * Test getName returns the server name.
     */
    public function test_getName_withConstructorValue_returnsName(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([])
        );

        $this->assertSame('test-server', $info->getName());
    }

    /**
     * Test getVersion returns the server version.
     */
    public function test_getVersion_withConstructorValue_returnsVersion(): void
    {
        $info = new ServerInfo(
            'test-server',
            '2.3.1',
            '2025-11-25',
            new ServerCapabilities([])
        );

        $this->assertSame('2.3.1', $info->getVersion());
    }

    /**
     * Test getProtocolVersion returns the protocol version.
     */
    public function test_getProtocolVersion_withConstructorValue_returnsProtocolVersion(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([])
        );

        $this->assertSame('2025-11-25', $info->getProtocolVersion());
    }

    /**
     * Test getCapabilities returns the ServerCapabilities instance.
     */
    public function test_getCapabilities_withConstructorValue_returnsCapabilities(): void
    {
        $capabilities = new ServerCapabilities(['tools' => []]);
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            $capabilities
        );

        $this->assertSame($capabilities, $info->getCapabilities());
    }

    /**
     * Test all getters return correct values from a single instance.
     */
    public function test_allGetters_withAllValues_returnCorrectValues(): void
    {
        $capabilities = new ServerCapabilities([
            'tools'     => ['listChanged' => true],
            'resources' => ['subscribe' => true],
        ]);

        $info = new ServerInfo(
            'mcp-filesystem',
            '0.5.0',
            '2025-11-25',
            $capabilities
        );

        $this->assertSame('mcp-filesystem', $info->getName());
        $this->assertSame('0.5.0', $info->getVersion());
        $this->assertSame('2025-11-25', $info->getProtocolVersion());
        $this->assertSame($capabilities, $info->getCapabilities());
    }

    /**
     * Test constructor accepts empty strings.
     */
    public function test_constructor_withEmptyStrings_storesEmptyValues(): void
    {
        $info = new ServerInfo(
            '',
            '',
            '',
            new ServerCapabilities([])
        );

        $this->assertSame('', $info->getName());
        $this->assertSame('', $info->getVersion());
        $this->assertSame('', $info->getProtocolVersion());
    }

    /**
     * Test getInstructions returns instructions string when provided.
     */
    public function test_getInstructions_withInstructions_returnsInstructionsString(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([]),
            'Use this server to access filesystem resources.'
        );

        $this->assertSame('Use this server to access filesystem resources.', $info->getInstructions());
    }

    /**
     * Test getInstructions returns null when instructions are not provided.
     */
    public function test_getInstructions_withoutInstructions_returnsNull(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([])
        );

        $this->assertNull($info->getInstructions());
    }

    /**
     * Test getInstructions returns empty string when instructions are empty.
     */
    public function test_getInstructions_withEmptyString_returnsEmptyString(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([]),
            ''
        );

        $this->assertSame('', $info->getInstructions());
    }

    /**
     * Test getDescription returns the description when provided.
     */
    public function test_getDescription_withDescription_returnsDescription(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([]),
            null,
            'A filesystem access server'
        );

        $this->assertSame('A filesystem access server', $info->getDescription());
    }

    /**
     * Test getDescription returns null when not provided.
     */
    public function test_getDescription_withoutDescription_returnsNull(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([])
        );

        $this->assertNull($info->getDescription());
    }

    /**
     * Test getTitle returns the title when provided.
     */
    public function test_getTitle_withTitle_returnsTitle(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([]),
            null,
            null,
            'Filesystem Server'
        );

        $this->assertSame('Filesystem Server', $info->getTitle());
    }

    /**
     * Test getTitle returns null when not provided.
     */
    public function test_getTitle_withoutTitle_returnsNull(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([])
        );

        $this->assertNull($info->getTitle());
    }

    /**
     * Test getWebsiteUrl returns the website URL when provided.
     */
    public function test_getWebsiteUrl_withWebsiteUrl_returnsWebsiteUrl(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([]),
            null,
            null,
            null,
            'https://example.com'
        );

        $this->assertSame('https://example.com', $info->getWebsiteUrl());
    }

    /**
     * Test getWebsiteUrl returns null when not provided.
     */
    public function test_getWebsiteUrl_withoutWebsiteUrl_returnsNull(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([])
        );

        $this->assertNull($info->getWebsiteUrl());
    }

    /**
     * Test all Implementation fields are stored and returned correctly.
     */
    public function test_allImplementationFields_withAllValues_returnCorrectValues(): void
    {
        $info = new ServerInfo(
            'test-server',
            '1.0.0',
            '2025-11-25',
            new ServerCapabilities([]),
            'Server instructions',
            'A test MCP server',
            'Test Server',
            'https://test-server.example.com'
        );

        $this->assertSame('A test MCP server', $info->getDescription());
        $this->assertSame('Test Server', $info->getTitle());
        $this->assertSame('https://test-server.example.com', $info->getWebsiteUrl());
        $this->assertSame('Server instructions', $info->getInstructions());
    }
}
