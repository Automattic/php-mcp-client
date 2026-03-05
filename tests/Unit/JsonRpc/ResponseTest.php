<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Tests\Unit\JsonRpc;

use Automattic\PhpMcpClient\Exception\JsonRpcException;
use Automattic\PhpMcpClient\JsonRpc\Error;
use Automattic\PhpMcpClient\JsonRpc\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Response value object.
 *
 * @covers \Automattic\PhpMcpClient\JsonRpc\Response
 */
final class ResponseTest extends TestCase
{
	/**
	 * Test success factory creates response with result and no error.
	 */
	public function test_success_withResult_createsSuccessResponse(): void
	{
		$response = Response::success(1, ['tools' => []]);

		$this->assertSame(1, $response->getId());
		$this->assertSame(['tools' => []], $response->getResult());
		$this->assertNull($response->getError());
		$this->assertFalse($response->isError());
	}

	/**
	 * Test error factory creates response with error and no result.
	 */
	public function test_error_withError_createsErrorResponse(): void
	{
		$error    = new Error(-32601, 'Method not found');
		$response = Response::error(1, $error);

		$this->assertSame(1, $response->getId());
		$this->assertNull($response->getResult());
		$this->assertSame($error, $response->getError());
		$this->assertTrue($response->isError());
	}

	/**
	 * Test error factory with null id (valid for parse errors).
	 */
	public function test_error_withNullId_createsErrorResponse(): void
	{
		$error    = Error::parseError();
		$response = Response::error(null, $error);

		$this->assertNull($response->getId());
		$this->assertTrue($response->isError());
	}

	/**
	 * Test toArray for success response includes result key.
	 */
	public function test_toArray_withSuccessResponse_includesResultKey(): void
	{
		$response = Response::success(5, 'done');

		$expected = [
			'jsonrpc' => '2.0',
			'id'      => 5,
			'result'  => 'done',
		];

		$this->assertSame($expected, $response->toArray());
	}

	/**
	 * Test toArray for error response includes error key.
	 */
	public function test_toArray_withErrorResponse_includesErrorKey(): void
	{
		$error    = new Error(-32600, 'Invalid Request');
		$response = Response::error(2, $error);

		$result = $response->toArray();

		$this->assertSame('2.0', $result['jsonrpc']);
		$this->assertSame(2, $result['id']);
		$this->assertArrayHasKey('error', $result);
		$this->assertSame(-32600, $result['error']['code']);
		$this->assertSame('Invalid Request', $result['error']['message']);
		$this->assertArrayNotHasKey('result', $result);
	}

	/**
	 * Test toJson produces valid JSON for success response.
	 */
	public function test_toJson_withSuccessResponse_producesValidJson(): void
	{
		$response = Response::success(1, ['status' => 'ok']);

		$json    = $response->toJson();
		$decoded = json_decode($json, true);

		$this->assertIsArray($decoded);
		$this->assertSame('2.0', $decoded['jsonrpc']);
		$this->assertSame(1, $decoded['id']);
		$this->assertSame(['status' => 'ok'], $decoded['result']);
	}

	/**
	 * Test createFromArray with result returns success Response.
	 */
	public function test_createFromArray_withResult_returnsSuccessResponse(): void
	{
		$response = Response::createFromArray([
			'jsonrpc' => '2.0',
			'id'      => 1,
			'result'  => ['tools' => []],
		]);

		$this->assertSame(1, $response->getId());
		$this->assertSame(['tools' => []], $response->getResult());
		$this->assertFalse($response->isError());
	}

	/**
	 * Test createFromArray with error returns error Response.
	 */
	public function test_createFromArray_withError_returnsErrorResponse(): void
	{
		$response = Response::createFromArray([
			'jsonrpc' => '2.0',
			'id'      => 1,
			'error'   => [
				'code'    => -32601,
				'message' => 'Method not found',
			],
		]);

		$this->assertSame(1, $response->getId());
		$this->assertTrue($response->isError());
		$this->assertNotNull($response->getError());
		$this->assertSame(-32601, $response->getError()->getCode());
	}

	/**
	 * Test createFromArray with both result and error throws exception.
	 */
	public function test_createFromArray_withBothResultAndError_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('contains both result and error');

		Response::createFromArray([
			'jsonrpc' => '2.0',
			'id'      => 1,
			'result'  => 'ok',
			'error'   => ['code' => -32600, 'message' => 'bad'],
		]);
	}

	/**
	 * Test createFromArray with non-array error format throws exception.
	 */
	public function test_createFromArray_withNonArrayError_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid error format');

		Response::createFromArray([
			'jsonrpc' => '2.0',
			'id'      => 1,
			'error'   => 'not-an-array',
		]);
	}

	/**
	 * Test createFromArray with invalid id type throws exception.
	 */
	public function test_createFromArray_withInvalidIdType_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid response ID');

		Response::createFromArray([
			'jsonrpc' => '2.0',
			'id'      => 3.14,
			'result'  => 'ok',
		]);
	}

	/**
	 * Test createFromArray with null id is valid (e.g. parse errors).
	 */
	public function test_createFromArray_withNullId_returnsResponse(): void
	{
		$response = Response::createFromArray([
			'jsonrpc' => '2.0',
			'id'      => null,
			'error'   => [
				'code'    => -32700,
				'message' => 'Parse error',
			],
		]);

		$this->assertNull($response->getId());
		$this->assertTrue($response->isError());
	}

	/**
	 * Test createFromArray with string id returns Response.
	 */
	public function test_createFromArray_withStringId_returnsResponse(): void
	{
		$response = Response::createFromArray([
			'jsonrpc' => '2.0',
			'id'      => 'req-1',
			'result'  => null,
		]);

		$this->assertSame('req-1', $response->getId());
	}
}
