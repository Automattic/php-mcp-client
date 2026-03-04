<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\JsonRpc;

use GalatanOvidiu\PhpMcpClient\Exception\JsonRpcException;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Notification;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Notification value object.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\JsonRpc\Notification
 */
final class NotificationTest extends TestCase
{
	/**
	 * Test constructor stores method and params.
	 */
	public function test_constructor_withAllArguments_storesValues(): void
	{
		$notification = new Notification('notifications/initialized', ['key' => 'value']);

		$this->assertSame('notifications/initialized', $notification->getMethod());
		$this->assertSame(['key' => 'value'], $notification->getParams());
	}

	/**
	 * Test constructor defaults params to null.
	 */
	public function test_constructor_withoutParams_defaultsToNull(): void
	{
		$notification = new Notification('notifications/initialized');

		$this->assertNull($notification->getParams());
	}

	/**
	 * Test toArray includes jsonrpc and method; omits params when null.
	 */
	public function test_toArray_withoutParams_omitsParamsKey(): void
	{
		$notification = new Notification('notifications/initialized');

		$expected = [
			'jsonrpc' => '2.0',
			'method'  => 'notifications/initialized',
		];

		$this->assertSame($expected, $notification->toArray());
	}

	/**
	 * Test toArray includes params when present.
	 */
	public function test_toArray_withParams_includesParamsKey(): void
	{
		$notification = new Notification('notifications/progress', ['token' => 'abc']);

		$result = $notification->toArray();

		$this->assertSame('2.0', $result['jsonrpc']);
		$this->assertSame('notifications/progress', $result['method']);
		$this->assertSame(['token' => 'abc'], $result['params']);
	}

	/**
	 * Test toJson produces valid JSON.
	 */
	public function test_toJson_withValidNotification_producesValidJson(): void
	{
		$notification = new Notification('notifications/initialized');

		$json    = $notification->toJson();
		$decoded = json_decode($json, true);

		$this->assertIsArray($decoded);
		$this->assertSame('2.0', $decoded['jsonrpc']);
		$this->assertSame('notifications/initialized', $decoded['method']);
		$this->assertArrayNotHasKey('id', $decoded);
	}

	/**
	 * Test createFromArray with valid data returns Notification.
	 */
	public function test_createFromArray_withValidData_returnsNotification(): void
	{
		$notification = Notification::createFromArray([
			'method' => 'notifications/cancelled',
			'params' => ['requestId' => 1],
		]);

		$this->assertSame('notifications/cancelled', $notification->getMethod());
		$this->assertSame(['requestId' => 1], $notification->getParams());
	}

	/**
	 * Test createFromArray with non-string method throws exception.
	 */
	public function test_createFromArray_withNonStringMethod_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid method');

		Notification::createFromArray(['method' => 123]);
	}

	/**
	 * Test createFromArray with missing method throws exception.
	 */
	public function test_createFromArray_withMissingMethod_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid method');

		Notification::createFromArray([]);
	}

	/**
	 * Test createFromArray with non-array params throws exception.
	 */
	public function test_createFromArray_withNonArrayParams_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid params');

		Notification::createFromArray([
			'method' => 'notifications/initialized',
			'params' => 'not-array',
		]);
	}
}
