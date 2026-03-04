<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\Client;

use GalatanOvidiu\PhpMcpClient\Client\ServerCapabilities;
use GalatanOvidiu\PhpMcpClient\Client\ServerInfo;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ServerInfo value object.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\Client\ServerInfo
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
}
