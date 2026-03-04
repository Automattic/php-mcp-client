<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\Transport\Http;

use GalatanOvidiu\PhpMcpClient\Exception\TransportException;
use GalatanOvidiu\PhpMcpClient\Transport\Http\HttpClientException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for HttpClientException.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\Transport\Http\HttpClientException
 */
final class HttpClientExceptionTest extends TestCase
{
	/**
	 * Test constructor sets message, status_code, code, and previous.
	 */
	public function test_constructor_withAllArguments_storesValues(): void
	{
		$previous  = new RuntimeException('Previous error');
		$exception = new HttpClientException('HTTP error', 500, 42, $previous);

		$this->assertSame('HTTP error', $exception->getMessage());
		$this->assertSame(500, $exception->getStatusCode());
		$this->assertSame(42, $exception->getCode());
		$this->assertSame($previous, $exception->getPrevious());
	}

	/**
	 * Test constructor defaults status_code to null.
	 */
	public function test_constructor_withMessageOnly_defaultsStatusCodeToNull(): void
	{
		$exception = new HttpClientException('Error');

		$this->assertNull($exception->getStatusCode());
		$this->assertSame(0, $exception->getCode());
		$this->assertNull($exception->getPrevious());
	}

	/**
	 * Test getStatusCode returns the HTTP status code.
	 */
	public function test_getStatusCode_withStatusCode_returnsStatusCode(): void
	{
		$exception = new HttpClientException('Not Found', 404);

		$this->assertSame(404, $exception->getStatusCode());
	}

	/**
	 * Test getStatusCode returns null when no status code is set.
	 */
	public function test_getStatusCode_withoutStatusCode_returnsNull(): void
	{
		$exception = new HttpClientException('Connection failed');

		$this->assertNull($exception->getStatusCode());
	}

	/**
	 * Test connectionFailed factory creates exception with correct message.
	 */
	public function test_connectionFailed_withUrl_createsExceptionWithMessage(): void
	{
		$exception = HttpClientException::connectionFailed('https://example.com/mcp');

		$this->assertSame('Failed to connect to https://example.com/mcp', $exception->getMessage());
		$this->assertNull($exception->getStatusCode());
		$this->assertSame(0, $exception->getCode());
		$this->assertNull($exception->getPrevious());
	}

	/**
	 * Test connectionFailed factory chains previous exception.
	 */
	public function test_connectionFailed_withPrevious_chainsPreviousException(): void
	{
		$previous  = new RuntimeException('Connection refused');
		$exception = HttpClientException::connectionFailed('https://example.com', $previous);

		$this->assertSame('Failed to connect to https://example.com', $exception->getMessage());
		$this->assertSame($previous, $exception->getPrevious());
	}

	/**
	 * Test unexpectedStatus factory creates exception with status code and body.
	 */
	public function test_unexpectedStatus_withBody_includesBodyInMessage(): void
	{
		$exception = HttpClientException::unexpectedStatus(503, 'Service Unavailable');

		$this->assertSame('Unexpected HTTP status code: 503 - Service Unavailable', $exception->getMessage());
		$this->assertSame(503, $exception->getStatusCode());
	}

	/**
	 * Test unexpectedStatus factory with empty body omits body from message.
	 */
	public function test_unexpectedStatus_withEmptyBody_omitsBodyFromMessage(): void
	{
		$exception = HttpClientException::unexpectedStatus(404);

		$this->assertSame('Unexpected HTTP status code: 404', $exception->getMessage());
		$this->assertSame(404, $exception->getStatusCode());
	}

	/**
	 * Test unexpectedStatus factory truncates long body to 200 characters.
	 */
	public function test_unexpectedStatus_withLongBody_truncatesBodyTo200Characters(): void
	{
		$long_body = str_repeat('x', 300);
		$exception = HttpClientException::unexpectedStatus(500, $long_body);

		$expected_body = str_repeat('x', 200);
		$this->assertSame(
			'Unexpected HTTP status code: 500 - ' . $expected_body,
			$exception->getMessage()
		);
		$this->assertSame(500, $exception->getStatusCode());
	}

	/**
	 * Test curlError factory creates exception with correct message and code.
	 */
	public function test_curlError_createsExceptionWithCodeAndMessage(): void
	{
		$exception = HttpClientException::curlError(7, 'Failed to connect to host');

		$this->assertSame('cURL error 7: Failed to connect to host', $exception->getMessage());
		$this->assertNull($exception->getStatusCode());
		$this->assertSame(7, $exception->getCode());
	}

	/**
	 * Test that HttpClientException extends TransportException.
	 */
	public function test_inheritance_extendsTransportException(): void
	{
		$exception = new HttpClientException('Error');

		$this->assertInstanceOf(TransportException::class, $exception);
	}
}
