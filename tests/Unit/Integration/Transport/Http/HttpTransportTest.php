<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\Integration\Transport\Http;

use GalatanOvidiu\PhpMcpClient\Contracts\TransportInterface;
use GalatanOvidiu\PhpMcpClient\Exception\TransportException;
use GalatanOvidiu\PhpMcpClient\Transport\Http\HttpClientException;
use GalatanOvidiu\PhpMcpClient\Transport\Http\HttpResponse;
use GalatanOvidiu\PhpMcpClient\Transport\Http\HttpTransport;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for HttpTransport.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\Transport\Http\HttpTransport
 */
final class HttpTransportTest extends TestCase
{
    private const VALID_URL = 'https://example.com/mcp';

    private MockHttpClient $mock_client;

    protected function setUp(): void
    {
        $this->mock_client = new MockHttpClient();
    }

    /**
     * Test that HttpTransport implements TransportInterface.
     */
    public function test_implements_transportInterface(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    /**
     * Test connect() validates URL and marks connected.
     */
    public function test_connect_withValidUrl_marksConnected(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);

        $transport->connect();

        $this->assertTrue($transport->isConnected());
    }

    /**
     * Test connect() throws on invalid URL without scheme.
     */
    public function test_connect_withInvalidUrl_throwsException(): void
    {
        $transport = new HttpTransport('not-a-url', $this->mock_client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('missing scheme');

        $transport->connect();
    }

    /**
     * Test connect() throws on URL with invalid scheme.
     */
    public function test_connect_withInvalidScheme_throwsException(): void
    {
        $transport = new HttpTransport('ftp://example.com/mcp', $this->mock_client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('scheme must be http or https');

        $transport->connect();
    }

    /**
     * Test connect() throws on URL without host.
     */
    public function test_connect_withMissingHost_throwsException(): void
    {
        // http:// is completely unparseable
        $transport = new HttpTransport('http://', $this->mock_client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('unable to parse');

        $transport->connect();
    }

    /**
     * Test connect() accepts HTTP scheme.
     */
    public function test_connect_withHttpScheme_connects(): void
    {
        $transport = new HttpTransport('http://example.com/mcp', $this->mock_client);

        $transport->connect();

        $this->assertTrue($transport->isConnected());
    }

    /**
     * Test connect() is idempotent.
     */
    public function test_connect_whenAlreadyConnected_isIdempotent(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);

        $transport->connect();
        $transport->connect();

        $this->assertTrue($transport->isConnected());
        $this->assertSame(0, $this->mock_client->getRequestCount());
    }

    /**
     * Test send() posts message and buffers response.
     */
    public function test_send_postsMessageAndBuffersResponse(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $response_body = '{"jsonrpc":"2.0","id":1,"result":{}}';
        $this->mock_client->queueResponse(
            new HttpResponse(200, $response_body, [])
        );

        $message = '{"jsonrpc":"2.0","id":1,"method":"test"}';
        $transport->send($message);

        $request = $this->mock_client->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('POST', $request['method']);
        $this->assertSame(self::VALID_URL, $request['url']);
        $this->assertSame($message, $request['body']);

        $this->assertSame($response_body, $transport->receive());
    }

    /**
     * Test send() extracts session ID from response header.
     */
    public function test_send_extractsSessionIdFromResponse(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $this->mock_client->queueResponse(
            new HttpResponse(200, '{}', [ 'Mcp-Session-Id' => 'sess-123' ])
        );

        $transport->send('{}');

        $this->assertSame('sess-123', $transport->getSessionId());
    }

    /**
     * Test send() includes session ID in subsequent requests.
     */
    public function test_send_includesSessionIdInSubsequentRequests(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        // First request returns session ID
        $this->mock_client->queueResponse(
            new HttpResponse(200, '{}', [ 'Mcp-Session-Id' => 'sess-123' ])
        );
        $transport->send('{}');

        // Second request should include session ID
        $this->mock_client->queueResponse(
            new HttpResponse(200, '{}', [])
        );
        $transport->send('{}');

        $request = $this->mock_client->getLastRequest();
        $this->assertNotNull($request);
        $this->assertArrayHasKey('Mcp-Session-Id', $request['headers']);
        $this->assertSame('sess-123', $request['headers']['Mcp-Session-Id']);
    }

    /**
     * Test send() includes Content-Type and Accept headers.
     */
    public function test_send_includesRequiredHeaders(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $this->mock_client->queueResponse(
            new HttpResponse(200, '{}', [])
        );

        $transport->send('{"id":1}');

        $request = $this->mock_client->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('application/json', $request['headers']['Content-Type']);
        $this->assertSame('application/json', $request['headers']['Accept']);
    }

    /**
     * Test send() throws when not connected.
     */
    public function test_send_whenNotConnected_throwsException(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('not connected');

        $transport->send('{}');
    }

    /**
     * Test send() throws TransportException on HTTP client exception.
     */
    public function test_send_onHttpClientException_throwsTransportException(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $this->mock_client->throwOnNextRequest(
            new HttpClientException('Connection refused')
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('HTTP request failed');

        $transport->send('{}');
    }

    /**
     * Test receive() returns buffered response.
     */
    public function test_receive_returnsBufferedResponse(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $response_body = '{"jsonrpc":"2.0","id":1,"result":{"data":"value"}}';
        $this->mock_client->queueResponse(
            new HttpResponse(200, $response_body, [])
        );

        $transport->send('{}');

        $this->assertSame($response_body, $transport->receive());
    }

    /**
     * Test receive() returns null after buffer consumed.
     */
    public function test_receive_afterBufferConsumed_returnsNull(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $this->mock_client->queueResponse(
            new HttpResponse(200, '{"result":{}}', [])
        );

        $transport->send('{}');

        // First receive gets the response
        $this->assertNotNull($transport->receive());

        // Second receive returns null
        $this->assertNull($transport->receive());
    }

    /**
     * Test receive() returns null when no buffered response.
     */
    public function test_receive_withNoBufferedResponse_returnsNull(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $this->assertNull($transport->receive(0.1));
    }

    /**
     * Test receive() throws when not connected.
     */
    public function test_receive_whenNotConnected_throwsException(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('not connected');

        $transport->receive();
    }

    /**
     * Test disconnect() sends DELETE with session ID.
     */
    public function test_disconnect_withSessionId_sendsDelete(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        // Establish session
        $this->mock_client->queueResponse(
            new HttpResponse(200, '{}', [ 'Mcp-Session-Id' => 'sess-123' ])
        );
        $transport->send('{}');

        $this->mock_client->clearRequests();

        $transport->disconnect();

        $request = $this->mock_client->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('DELETE', $request['method']);
        $this->assertSame(self::VALID_URL, $request['url']);
        $this->assertSame('sess-123', $request['headers']['Mcp-Session-Id']);
    }

    /**
     * Test disconnect() marks transport as not connected.
     */
    public function test_disconnect_marksAsNotConnected(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $transport->disconnect();

        $this->assertFalse($transport->isConnected());
    }

    /**
     * Test disconnect() clears session and buffer.
     */
    public function test_disconnect_clearsSessionAndBuffer(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        // Establish session and buffer
        $this->mock_client->queueResponse(
            new HttpResponse(200, '{"response":"data"}', [ 'Mcp-Session-Id' => 'sess-123' ])
        );
        $transport->send('{}');

        $transport->disconnect();

        $this->assertNull($transport->getSessionId());

        // Reconnect to verify buffer is cleared
        $transport->connect();
        $this->assertNull($transport->receive());
    }

    /**
     * Test disconnect() handles DELETE failure gracefully.
     */
    public function test_disconnect_onDeleteFailure_doesNotThrow(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        // Establish session
        $this->mock_client->queueResponse(
            new HttpResponse(200, '{}', [ 'Mcp-Session-Id' => 'sess-123' ])
        );
        $transport->send('{}');

        // Configure DELETE to fail
        $this->mock_client->throwOnNextRequest(
            new HttpClientException('Server error')
        );

        // Should not throw
        $transport->disconnect();

        $this->assertFalse($transport->isConnected());
    }

    /**
     * Test disconnect() without session does not send DELETE.
     */
    public function test_disconnect_withoutSession_doesNotSendDelete(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $transport->disconnect();

        $this->assertSame(0, $this->mock_client->getRequestCount());
    }

    /**
     * Test disconnect() is idempotent.
     */
    public function test_disconnect_whenAlreadyDisconnected_isIdempotent(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);

        // Should not throw
        $transport->disconnect();
        $transport->disconnect();

        $this->assertFalse($transport->isConnected());
    }

    /**
     * Test HTTP error response throws TransportException.
     */
    public function test_send_onHttpErrorResponse_throwsException(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $this->mock_client->queueResponse(
            new HttpResponse(500, '{"error":"server error"}', [])
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('HTTP error 500');

        $transport->send('{}');
    }

    /**
     * Test HTTP 404 on session indicates expired session.
     */
    public function test_send_onHttp404WithSession_throwsSessionExpiredException(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        // Establish session
        $this->mock_client->queueResponse(
            new HttpResponse(200, '{}', [ 'Mcp-Session-Id' => 'sess-123' ])
        );
        $transport->send('{}');

        // Next request returns 404
        $this->mock_client->queueResponse(
            new HttpResponse(404, '', [])
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Session expired');

        $transport->send('{}');
    }

    /**
     * Test HTTP 404 without session throws generic error.
     */
    public function test_send_onHttp404WithoutSession_throwsGenericError(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $this->mock_client->queueResponse(
            new HttpResponse(404, 'Not Found', [])
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('HTTP error 404');

        $transport->send('{}');
    }

    /**
     * Test HTTP 400 error includes response body in message.
     */
    public function test_send_onHttpClientError_includesBodyInMessage(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $this->mock_client->queueResponse(
            new HttpResponse(400, 'Bad Request: invalid JSON', [])
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Bad Request: invalid JSON');

        $transport->send('{}');
    }

    /**
     * Test empty response body does not buffer.
     */
    public function test_send_withEmptyResponseBody_doesNotBuffer(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $this->mock_client->queueResponse(
            new HttpResponse(200, '', [])
        );

        $transport->send('{}');

        $this->assertNull($transport->receive());
    }

    /**
     * Test logger receives debug messages.
     */
    public function test_logsDebugMessages(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('debug');

        $transport = new HttpTransport(self::VALID_URL, $this->mock_client, $logger);

        $this->mock_client->queueResponse(
            new HttpResponse(200, '{}', [])
        );

        $transport->connect();
        $transport->send('{}');
        $transport->disconnect();
    }

    /**
     * Test default HTTP client is used when none provided.
     */
    public function test_constructor_withNoHttpClient_usesDefault(): void
    {
        // We can't easily test this without making a real HTTP call,
        // but we can verify the transport is created without error
        $transport = new HttpTransport(self::VALID_URL);

        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    /**
     * Test long error body is truncated in exception message.
     */
    public function test_send_onHttpError_truncatesLongBody(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $long_body = str_repeat('x', 500);
        $this->mock_client->queueResponse(
            new HttpResponse(500, $long_body, [])
        );

        try {
            $transport->send('{}');
            $this->fail('Expected TransportException');
        } catch (TransportException $e) {
            // Body should be truncated to 200 chars + "..."
            $this->assertStringEndsWith('...', $e->getMessage());
            $this->assertLessThan(300, strlen($e->getMessage()));
        }
    }

    /**
     * Test session ID is cleared on 404 response.
     */
    public function test_send_onHttp404_clearsSessionId(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        // Establish session
        $this->mock_client->queueResponse(
            new HttpResponse(200, '{}', [ 'Mcp-Session-Id' => 'sess-123' ])
        );
        $transport->send('{}');

        $this->assertSame('sess-123', $transport->getSessionId());

        // 404 should clear session
        $this->mock_client->queueResponse(
            new HttpResponse(404, '', [])
        );

        try {
            $transport->send('{}');
        } catch (TransportException $e) {
            // Expected
        }

        $this->assertNull($transport->getSessionId());
    }

    /**
     * Test session ID header is case-insensitive when reading.
     */
    public function test_send_extractsSessionIdCaseInsensitive(): void
    {
        $transport = new HttpTransport(self::VALID_URL, $this->mock_client);
        $transport->connect();

        $this->mock_client->queueResponse(
            new HttpResponse(200, '{}', [ 'mcp-session-id' => 'lower-case-sess' ])
        );

        $transport->send('{}');

        $this->assertSame('lower-case-sess', $transport->getSessionId());
    }
}
