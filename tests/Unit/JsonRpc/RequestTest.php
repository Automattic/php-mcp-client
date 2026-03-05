<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Tests\Unit\JsonRpc;

use Automattic\PhpMcpClient\Exception\JsonRpcException;
use Automattic\PhpMcpClient\JsonRpc\Request;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Request value object.
 *
 * @covers \Automattic\PhpMcpClient\JsonRpc\Request
 */
final class RequestTest extends TestCase
{
	/**
	 * Test constructor stores id, method, and params.
	 */
	public function test_constructor_withAllArguments_storesValues(): void
	{
		$request = new Request(1, 'tools/list', ['cursor' => 'abc']);

		$this->assertSame(1, $request->getId());
		$this->assertSame('tools/list', $request->getMethod());
		$this->assertSame(['cursor' => 'abc'], $request->getParams());
	}

	/**
	 * Test constructor defaults params to null.
	 */
	public function test_constructor_withoutParams_defaultsToNull(): void
	{
		$request = new Request(2, 'initialize');

		$this->assertNull($request->getParams());
	}

	/**
	 * Test constructor accepts string id.
	 */
	public function test_constructor_withStringId_storesStringId(): void
	{
		$request = new Request('abc-123', 'ping');

		$this->assertSame('abc-123', $request->getId());
	}

	/**
	 * Test toArray includes jsonrpc, id, method; omits params when null.
	 */
	public function test_toArray_withoutParams_omitsParamsKey(): void
	{
		$request = new Request(1, 'initialize');

		$expected = [
			'jsonrpc' => '2.0',
			'id'      => 1,
			'method'  => 'initialize',
		];

		$this->assertSame($expected, $request->toArray());
	}

	/**
	 * Test toArray includes params when present.
	 */
	public function test_toArray_withParams_includesParamsKey(): void
	{
		$request = new Request(5, 'tools/call', ['name' => 'read_file']);

		$result = $request->toArray();

		$this->assertSame('2.0', $result['jsonrpc']);
		$this->assertSame(5, $result['id']);
		$this->assertSame('tools/call', $result['method']);
		$this->assertSame(['name' => 'read_file'], $result['params']);
	}

	/**
	 * Test toJson produces valid JSON string.
	 */
	public function test_toJson_withValidRequest_producesValidJson(): void
	{
		$request = new Request(1, 'initialize');

		$json = $request->toJson();
		$decoded = json_decode($json, true);

		$this->assertIsArray($decoded);
		$this->assertSame('2.0', $decoded['jsonrpc']);
		$this->assertSame(1, $decoded['id']);
		$this->assertSame('initialize', $decoded['method']);
	}

	/**
	 * Test createFromArray with valid data returns Request.
	 */
	public function test_createFromArray_withValidData_returnsRequest(): void
	{
		$request = Request::createFromArray([
			'id'     => 3,
			'method' => 'resources/list',
			'params' => ['uri' => 'file:///tmp'],
		]);

		$this->assertSame(3, $request->getId());
		$this->assertSame('resources/list', $request->getMethod());
		$this->assertSame(['uri' => 'file:///tmp'], $request->getParams());
	}

	/**
	 * Test createFromArray with string id returns Request.
	 */
	public function test_createFromArray_withStringId_returnsRequest(): void
	{
		$request = Request::createFromArray([
			'id'     => 'req-42',
			'method' => 'ping',
		]);

		$this->assertSame('req-42', $request->getId());
	}

	/**
	 * Test createFromArray with non-string/int id throws exception.
	 */
	public function test_createFromArray_withInvalidIdType_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid request ID');

		Request::createFromArray([
			'id'     => 3.14,
			'method' => 'ping',
		]);
	}

	/**
	 * Test createFromArray with null id throws exception.
	 */
	public function test_createFromArray_withNullId_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid request ID');

		Request::createFromArray([
			'method' => 'ping',
		]);
	}

	/**
	 * Test createFromArray with non-string method throws exception.
	 */
	public function test_createFromArray_withNonStringMethod_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid method');

		Request::createFromArray([
			'id'     => 1,
			'method' => 123,
		]);
	}

	/**
	 * Test createFromArray with missing method throws exception.
	 */
	public function test_createFromArray_withMissingMethod_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid method');

		Request::createFromArray([
			'id' => 1,
		]);
	}

	/**
	 * Test createFromArray with non-array params throws exception.
	 */
	public function test_createFromArray_withNonArrayParams_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid params');

		Request::createFromArray([
			'id'     => 1,
			'method' => 'ping',
			'params' => 'not-an-array',
		]);
	}
}
