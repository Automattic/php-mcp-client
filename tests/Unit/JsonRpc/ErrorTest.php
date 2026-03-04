<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\JsonRpc;

use GalatanOvidiu\PhpMcpClient\Exception\JsonRpcException;
use GalatanOvidiu\PhpMcpClient\JsonRpc\Error;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Error value object.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\JsonRpc\Error
 */
final class ErrorTest extends TestCase
{
	/**
	 * Test constructor stores code, message, and data.
	 */
	public function test_constructor_withAllArguments_storesValues(): void
	{
		$error = new Error(-32600, 'Invalid Request', ['detail' => 'missing field']);

		$this->assertSame(-32600, $error->getCode());
		$this->assertSame('Invalid Request', $error->getMessage());
		$this->assertSame(['detail' => 'missing field'], $error->getData());
	}

	/**
	 * Test constructor defaults data to null.
	 */
	public function test_constructor_withoutData_defaultsToNull(): void
	{
		$error = new Error(-32603, 'Internal error');

		$this->assertSame(-32603, $error->getCode());
		$this->assertSame('Internal error', $error->getMessage());
		$this->assertNull($error->getData());
	}

	/**
	 * Test toArray includes code and message, omits null data.
	 */
	public function test_toArray_withoutData_omitsDataKey(): void
	{
		$error = new Error(-32700, 'Parse error');

		$expected = [
			'code'    => -32700,
			'message' => 'Parse error',
		];

		$this->assertSame($expected, $error->toArray());
	}

	/**
	 * Test toArray includes data when present.
	 */
	public function test_toArray_withData_includesDataKey(): void
	{
		$error = new Error(-32600, 'Invalid Request', 'extra info');

		$expected = [
			'code'    => -32600,
			'message' => 'Invalid Request',
			'data'    => 'extra info',
		];

		$this->assertSame($expected, $error->toArray());
	}

	/**
	 * Test createFromArray with valid data returns Error.
	 */
	public function test_createFromArray_withValidData_returnsError(): void
	{
		$error = Error::createFromArray([
			'code'    => -32601,
			'message' => 'Method not found',
			'data'    => ['method' => 'foo'],
		]);

		$this->assertSame(-32601, $error->getCode());
		$this->assertSame('Method not found', $error->getMessage());
		$this->assertSame(['method' => 'foo'], $error->getData());
	}

	/**
	 * Test createFromArray without data key defaults data to null.
	 */
	public function test_createFromArray_withoutDataKey_defaultsDataToNull(): void
	{
		$error = Error::createFromArray([
			'code'    => -32603,
			'message' => 'Internal error',
		]);

		$this->assertNull($error->getData());
	}

	/**
	 * Test createFromArray with missing code throws exception.
	 */
	public function test_createFromArray_withMissingCode_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid error code');

		Error::createFromArray(['message' => 'oops']);
	}

	/**
	 * Test createFromArray with non-int code throws exception.
	 */
	public function test_createFromArray_withNonIntCode_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid error code');

		Error::createFromArray(['code' => 'not-int', 'message' => 'oops']);
	}

	/**
	 * Test createFromArray with missing message throws exception.
	 */
	public function test_createFromArray_withMissingMessage_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid error message');

		Error::createFromArray(['code' => -32600]);
	}

	/**
	 * Test createFromArray with non-string message throws exception.
	 */
	public function test_createFromArray_withNonStringMessage_throwsJsonRpcException(): void
	{
		$this->expectException(JsonRpcException::class);
		$this->expectExceptionMessage('Invalid error message');

		Error::createFromArray(['code' => -32600, 'message' => 123]);
	}

	/**
	 * Test parseError factory creates error with PARSE_ERROR code.
	 */
	public function test_parseError_withDefaultMessage_createsParseError(): void
	{
		$error = Error::parseError();

		$this->assertSame(JsonRpcException::PARSE_ERROR, $error->getCode());
		$this->assertSame('Parse error', $error->getMessage());
		$this->assertNull($error->getData());
	}

	/**
	 * Test parseError factory with custom message.
	 */
	public function test_parseError_withCustomMessage_usesCustomMessage(): void
	{
		$error = Error::parseError('bad JSON');

		$this->assertSame(JsonRpcException::PARSE_ERROR, $error->getCode());
		$this->assertSame('bad JSON', $error->getMessage());
	}

	/**
	 * Test invalidRequest factory creates error with INVALID_REQUEST code.
	 */
	public function test_invalidRequest_withDefaultMessage_createsInvalidRequest(): void
	{
		$error = Error::invalidRequest();

		$this->assertSame(JsonRpcException::INVALID_REQUEST, $error->getCode());
		$this->assertSame('Invalid Request', $error->getMessage());
	}

	/**
	 * Test methodNotFound factory creates error with METHOD_NOT_FOUND code.
	 */
	public function test_methodNotFound_withDefaultMessage_createsMethodNotFound(): void
	{
		$error = Error::methodNotFound();

		$this->assertSame(JsonRpcException::METHOD_NOT_FOUND, $error->getCode());
		$this->assertSame('Method not found', $error->getMessage());
	}

	/**
	 * Test invalidParams factory creates error with INVALID_PARAMS code.
	 */
	public function test_invalidParams_withDefaultMessage_createsInvalidParams(): void
	{
		$error = Error::invalidParams();

		$this->assertSame(JsonRpcException::INVALID_PARAMS, $error->getCode());
		$this->assertSame('Invalid params', $error->getMessage());
	}

	/**
	 * Test internalError factory creates error with INTERNAL_ERROR code.
	 */
	public function test_internalError_withDefaultMessage_createsInternalError(): void
	{
		$error = Error::internalError();

		$this->assertSame(JsonRpcException::INTERNAL_ERROR, $error->getCode());
		$this->assertSame('Internal error', $error->getMessage());
	}
}
