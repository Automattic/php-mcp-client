<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Tests\Unit\Exception;

use Automattic\PhpMcpClient\Exception\JsonRpcException;
use Automattic\PhpMcpClient\Exception\McpException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for JsonRpcException.
 *
 * @covers \Automattic\PhpMcpClient\Exception\JsonRpcException
 */
final class JsonRpcExceptionTest extends TestCase
{
	/**
	 * Test constructor sets message, error_code, and error_data.
	 */
	public function test_constructor_withAllArguments_storesValues(): void
	{
		$data      = ['detail' => 'something went wrong'];
		$exception = new JsonRpcException('Test error', -32600, $data);

		$this->assertSame('Test error', $exception->getMessage());
		$this->assertSame(-32600, $exception->getErrorCode());
		$this->assertSame($data, $exception->getErrorData());
	}

	/**
	 * Test constructor defaults error_code to INTERNAL_ERROR.
	 */
	public function test_constructor_withMessageOnly_defaultsErrorCodeToInternalError(): void
	{
		$exception = new JsonRpcException('Some error');

		$this->assertSame(JsonRpcException::INTERNAL_ERROR, $exception->getErrorCode());
		$this->assertSame(-32603, $exception->getCode());
	}

	/**
	 * Test constructor defaults error_data to null.
	 */
	public function test_constructor_withoutErrorData_defaultsToNull(): void
	{
		$exception = new JsonRpcException('Some error', -32700);

		$this->assertNull($exception->getErrorData());
	}

	/**
	 * Test getErrorCode returns the JSON-RPC error code.
	 */
	public function test_getErrorCode_returnsErrorCode(): void
	{
		$exception = new JsonRpcException('Parse error', JsonRpcException::PARSE_ERROR);

		$this->assertSame(-32700, $exception->getErrorCode());
	}

	/**
	 * Test getErrorData returns the error data.
	 */
	public function test_getErrorData_withData_returnsData(): void
	{
		$data      = 'string data';
		$exception = new JsonRpcException('Error', -32603, $data);

		$this->assertSame('string data', $exception->getErrorData());
	}

	/**
	 * Test PARSE_ERROR constant value.
	 */
	public function test_constants_parseError_hasCorrectValue(): void
	{
		$this->assertSame(-32700, JsonRpcException::PARSE_ERROR);
	}

	/**
	 * Test INVALID_REQUEST constant value.
	 */
	public function test_constants_invalidRequest_hasCorrectValue(): void
	{
		$this->assertSame(-32600, JsonRpcException::INVALID_REQUEST);
	}

	/**
	 * Test METHOD_NOT_FOUND constant value.
	 */
	public function test_constants_methodNotFound_hasCorrectValue(): void
	{
		$this->assertSame(-32601, JsonRpcException::METHOD_NOT_FOUND);
	}

	/**
	 * Test INVALID_PARAMS constant value.
	 */
	public function test_constants_invalidParams_hasCorrectValue(): void
	{
		$this->assertSame(-32602, JsonRpcException::INVALID_PARAMS);
	}

	/**
	 * Test INTERNAL_ERROR constant value.
	 */
	public function test_constants_internalError_hasCorrectValue(): void
	{
		$this->assertSame(-32603, JsonRpcException::INTERNAL_ERROR);
	}

	/**
	 * Test that JsonRpcException extends McpException.
	 */
	public function test_inheritance_extendsMcpException(): void
	{
		$exception = new JsonRpcException('Error');

		$this->assertInstanceOf(McpException::class, $exception);
	}

	/**
	 * Test that the parent exception code is set to the error_code.
	 */
	public function test_constructor_setsParentCodeToErrorCode(): void
	{
		$exception = new JsonRpcException('Error', -32601);

		$this->assertSame(-32601, $exception->getCode());
	}
}
