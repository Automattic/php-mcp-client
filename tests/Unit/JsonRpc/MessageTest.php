<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\JsonRpc;

use GalatanOvidiu\PhpMcpClient\Exception\JsonRpcException;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Message;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Notification;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Request;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Message abstract class (fromJson / fromArray dispatch).
 *
 * @covers \GalatanOvidiu\PhpMcpClient\JsonRpc\Message
 */
final class MessageTest extends TestCase
{
	// -------------------------------------------------------------------------
	// fromJson — valid messages
	// -------------------------------------------------------------------------

	/**
	 * Test fromJson with valid request JSON returns Request.
	 */
	public function test_fromJson_withValidRequestJson_returnsRequest(): void
	{
		$json = json_encode([
			'jsonrpc' => '2.0',
			'id'      => 1,
			'method'  => 'initialize',
			'params'  => ['protocolVersion' => '2025-11-25'],
		]);

		$message = Message::fromJson($json);

		$this->assertInstanceOf(Request::class, $message);
		$this->assertSame(1, $message->getId());
		$this->assertSame('initialize', $message->getMethod());
	}

	/**
	 * Test fromJson with valid notification JSON returns Notification.
	 */
	public function test_fromJson_withValidNotificationJson_returnsNotification(): void
	{
		$json = json_encode([
			'jsonrpc' => '2.0',
			'method'  => 'notifications/initialized',
		]);

		$message = Message::fromJson($json);

		$this->assertInstanceOf(Notification::class, $message);
		$this->assertSame('notifications/initialized', $message->getMethod());
	}

	/**
	 * Test fromJson with valid success response JSON returns Response.
	 */
	public function test_fromJson_withSuccessResponseJson_returnsResponse(): void
	{
		$json = json_encode([
			'jsonrpc' => '2.0',
			'id'      => 1,
			'result'  => ['tools' => []],
		]);

		$message = Message::fromJson($json);

		$this->assertInstanceOf(Response::class, $message);
		$this->assertFalse($message->isError());
	}

	/**
	 * Test fromJson with valid error response JSON returns Response.
	 */
	public function test_fromJson_withErrorResponseJson_returnsResponse(): void
	{
		$json = json_encode([
			'jsonrpc' => '2.0',
			'id'      => 1,
			'error'   => [
				'code'    => -32601,
				'message' => 'Method not found',
			],
		]);

		$message = Message::fromJson($json);

		$this->assertInstanceOf(Response::class, $message);
		$this->assertTrue($message->isError());
	}

	// -------------------------------------------------------------------------
	// fromJson — error cases
	// -------------------------------------------------------------------------

	/**
	 * Test fromJson with invalid JSON throws PARSE_ERROR.
	 */
	public function test_fromJson_withInvalidJson_throwsParseError(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Parse error');

		Message::fromJson('{invalid json');
	}

	/**
	 * Test fromJson with non-array JSON (e.g. a string) throws INVALID_REQUEST.
	 */
	public function test_fromJson_withNonArrayJson_throwsInvalidRequest(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid JSON-RPC message format');

		Message::fromJson('"hello"');
	}

	// -------------------------------------------------------------------------
	// fromArray — error cases
	// -------------------------------------------------------------------------

	/**
	 * Test fromArray with wrong jsonrpc version throws INVALID_REQUEST.
	 */
	public function test_fromArray_withWrongVersion_throwsInvalidRequest(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid JSON-RPC version');

		Message::fromArray([
			'jsonrpc' => '1.0',
			'method'  => 'ping',
		]);
	}

	/**
	 * Test fromArray with missing jsonrpc key throws INVALID_REQUEST.
	 */
	public function test_fromArray_withMissingVersion_throwsInvalidRequest(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid JSON-RPC version');

		Message::fromArray([
			'method' => 'ping',
		]);
	}

	/**
	 * Test fromArray with no method and no result/error throws INVALID_REQUEST.
	 */
	public function test_fromArray_withMissingMethodAndNoResultOrError_throwsInvalidRequest(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Missing or invalid method');

		Message::fromArray([
			'jsonrpc' => '2.0',
		]);
	}

	/**
	 * Test fromArray with non-string method and no result/error throws INVALID_REQUEST.
	 */
	public function test_fromArray_withNonStringMethod_throwsInvalidRequest(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Missing or invalid method');

		Message::fromArray([
			'jsonrpc' => '2.0',
			'method'  => 123,
		]);
	}

	// -------------------------------------------------------------------------
	// fromArray — routing logic
	// -------------------------------------------------------------------------

	/**
	 * Test fromArray routes to Request when id is present.
	 */
	public function test_fromArray_withIdAndMethod_returnsRequest(): void
	{
		$message = Message::fromArray([
			'jsonrpc' => '2.0',
			'id'      => 5,
			'method'  => 'tools/list',
		]);

		$this->assertInstanceOf(Request::class, $message);
	}

	/**
	 * Test fromArray routes to Notification when id is absent.
	 */
	public function test_fromArray_withMethodAndNoId_returnsNotification(): void
	{
		$message = Message::fromArray([
			'jsonrpc' => '2.0',
			'method'  => 'notifications/initialized',
		]);

		$this->assertInstanceOf(Notification::class, $message);
	}

	/**
	 * Test fromArray routes to Response when result key is present.
	 */
	public function test_fromArray_withResultKey_returnsResponse(): void
	{
		$message = Message::fromArray([
			'jsonrpc' => '2.0',
			'id'      => 1,
			'result'  => null,
		]);

		$this->assertInstanceOf(Response::class, $message);
	}

	/**
	 * Test fromArray routes to Response when error key is present.
	 */
	public function test_fromArray_withErrorKey_returnsResponse(): void
	{
		$message = Message::fromArray([
			'jsonrpc' => '2.0',
			'id'      => 1,
			'error'   => [
				'code'    => -32600,
				'message' => 'Invalid Request',
			],
		]);

		$this->assertInstanceOf(Response::class, $message);
	}
}
