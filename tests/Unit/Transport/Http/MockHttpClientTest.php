<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\Transport\Http;

use GalatanOvidiu\PhpMcpClient\Transport\Http\HttpClientException;
use GalatanOvidiu\PhpMcpClient\Transport\Http\HttpClientInterface;
use GalatanOvidiu\PhpMcpClient\Transport\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MockHttpClient.
 *
 *
 */
final class MockHttpClientTest extends TestCase
{
    /**
     * Test that MockHttpClient implements HttpClientInterface.
     */
    public function test_implements_httpClientInterface(): void
    {
        $mock = new MockHttpClient();

        $this->assertInstanceOf(HttpClientInterface::class, $mock);
    }

    /**
     * Test that queued response is returned from post().
     */
    public function test_post_withQueuedResponse_returnsQueuedResponse(): void
    {
        $mock     = new MockHttpClient();
        $response = new HttpResponse(200, '{"id":1}', []);

        $mock->queueResponse($response);
        $result = $mock->post('http://example.com/mcp', '{}', []);

        $this->assertSame($response, $result);
    }

    /**
     * Test that multiple queued responses are returned in FIFO order.
     */
    public function test_post_withMultipleQueuedResponses_returnsFifoOrder(): void
    {
        $mock      = new MockHttpClient();
        $response1 = new HttpResponse(200, '{"id":1}', []);
        $response2 = new HttpResponse(200, '{"id":2}', []);
        $response3 = new HttpResponse(200, '{"id":3}', []);

        $mock->queueResponses($response1, $response2, $response3);

        $this->assertSame($response1, $mock->post('http://example.com', '{}', []));
        $this->assertSame($response2, $mock->post('http://example.com', '{}', []));
        $this->assertSame($response3, $mock->post('http://example.com', '{}', []));
    }

    /**
     * Test that requests are recorded for verification.
     */
    public function test_post_recordsRequestForVerification(): void
    {
        $mock = new MockHttpClient();
        $mock->queueResponse(new HttpResponse(200, '{}', []));

        $mock->post(
            'http://example.com/mcp',
            '{"x":1}',
            [ 'Content-Type' => 'application/json' ]
        );

        $requests = $mock->getRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame('http://example.com/mcp', $request['url']);
        $this->assertSame('{"x":1}', $request['body']);
        $this->assertSame([ 'Content-Type' => 'application/json' ], $request['headers']);
    }

    /**
     * Test that getLastRequest returns the most recent request.
     */
    public function test_getLastRequest_returnsLastRequest(): void
    {
        $mock = new MockHttpClient();
        $mock->queueResponses(
            new HttpResponse(200, '{}', []),
            new HttpResponse(200, '{}', [])
        );

        $mock->post('http://first.com', '{}', []);
        $mock->post('http://second.com', '{}', []);

        $last_request = $mock->getLastRequest();
        $this->assertSame('http://second.com', $last_request['url']);
    }

    /**
     * Test that getLastRequest returns null when no requests made.
     */
    public function test_getLastRequest_withNoRequests_returnsNull(): void
    {
        $mock = new MockHttpClient();

        $this->assertNull($mock->getLastRequest());
    }

    /**
     * Test that post throws exception when no responses queued.
     */
    public function test_post_withNoQueuedResponses_throwsException(): void
    {
        $mock = new MockHttpClient();

        $this->expectException(HttpClientException::class);
        $this->expectExceptionMessage('No responses queued');

        $mock->post('http://example.com', '{}', []);
    }

    /**
     * Test that configured exception is thrown on post.
     */
    public function test_post_whenConfiguredToThrow_throwsConfiguredException(): void
    {
        $mock      = new MockHttpClient();
        $exception = new HttpClientException('Connection failed');

        $mock->throwOnNextRequest($exception);

        $this->expectException(HttpClientException::class);
        $this->expectExceptionMessage('Connection failed');

        $mock->post('http://example.com', '{}', []);
    }

    /**
     * Test that exception is only thrown once.
     */
    public function test_throwOnNextRequest_onlyThrowsOnce(): void
    {
        $mock = new MockHttpClient();
        $mock->throwOnNextRequest(new HttpClientException('Error'));
        $mock->queueResponse(new HttpResponse(200, '{}', []));

        try {
            $mock->post('http://example.com', '{}', []);
            $this->fail('Expected exception was not thrown');
        } catch (HttpClientException $e) {
            // Expected
        }

        // Second call should return queued response, not throw
        $result = $mock->post('http://example.com', '{}', []);
        $this->assertSame(200, $result->getStatusCode());
    }

    /**
     * Test that delete records request for verification.
     */
    public function test_delete_recordsRequestForVerification(): void
    {
        $mock = new MockHttpClient();

        $mock->delete(
            'http://example.com/mcp',
            [ 'Mcp-Session-Id' => 'session-123' ]
        );

        $requests = $mock->getRequests();
        $this->assertCount(1, $requests);

        $request = $requests[0];
        $this->assertSame('DELETE', $request['method']);
        $this->assertSame('http://example.com/mcp', $request['url']);
        $this->assertSame('', $request['body']);
        $this->assertSame([ 'Mcp-Session-Id' => 'session-123' ], $request['headers']);
    }

    /**
     * Test that delete throws configured exception.
     */
    public function test_delete_whenConfiguredToThrow_throwsConfiguredException(): void
    {
        $mock = new MockHttpClient();
        $mock->throwOnNextRequest(new HttpClientException('Delete failed'));

        $this->expectException(HttpClientException::class);
        $this->expectExceptionMessage('Delete failed');

        $mock->delete('http://example.com/mcp', []);
    }

    /**
     * Test getRequestCount returns correct count.
     */
    public function test_getRequestCount_returnsCorrectCount(): void
    {
        $mock = new MockHttpClient();
        $mock->queueResponses(
            new HttpResponse(200, '{}', []),
            new HttpResponse(200, '{}', [])
        );

        $this->assertSame(0, $mock->getRequestCount());

        $mock->post('http://example.com', '{}', []);
        $this->assertSame(1, $mock->getRequestCount());

        $mock->post('http://example.com', '{}', []);
        $this->assertSame(2, $mock->getRequestCount());

        $mock->delete('http://example.com', []);
        $this->assertSame(3, $mock->getRequestCount());
    }

    /**
     * Test clearRequests removes recorded requests.
     */
    public function test_clearRequests_removesRecordedRequests(): void
    {
        $mock = new MockHttpClient();
        $mock->queueResponse(new HttpResponse(200, '{}', []));
        $mock->post('http://example.com', '{}', []);

        $this->assertSame(1, $mock->getRequestCount());

        $mock->clearRequests();

        $this->assertSame(0, $mock->getRequestCount());
        $this->assertSame([], $mock->getRequests());
    }

    /**
     * Test reset clears all state.
     */
    public function test_reset_clearsAllState(): void
    {
        $mock = new MockHttpClient();
        $mock->queueResponse(new HttpResponse(200, '{}', []));
        $mock->throwOnNextRequest(new HttpClientException('Error'));

        // Make a request to record it (this will throw, but that's expected)
        try {
            $mock->post('http://example.com', '{}', []);
        } catch (HttpClientException $e) {
            // Expected
        }

        $mock->reset();

        $this->assertSame(0, $mock->getRequestCount());
        $this->assertFalse($mock->hasQueuedResponses());
    }

    /**
     * Test hasQueuedResponses returns correct status.
     */
    public function test_hasQueuedResponses_returnsCorrectStatus(): void
    {
        $mock = new MockHttpClient();

        $this->assertFalse($mock->hasQueuedResponses());

        $mock->queueResponse(new HttpResponse(200, '{}', []));
        $this->assertTrue($mock->hasQueuedResponses());

        $mock->post('http://example.com', '{}', []);
        $this->assertFalse($mock->hasQueuedResponses());
    }

    /**
     * Test method chaining works correctly.
     */
    public function test_methodChaining_works(): void
    {
        $mock = new MockHttpClient();

        $result = $mock
            ->queueResponse(new HttpResponse(200, '{"a":1}', []))
            ->queueResponse(new HttpResponse(200, '{"b":2}', []));

        $this->assertSame($mock, $result);
        $this->assertTrue($mock->hasQueuedResponses());
    }

    /**
     * Test queueResponses with multiple responses.
     */
    public function test_queueResponses_queuesMultipleResponses(): void
    {
        $mock      = new MockHttpClient();
        $response1 = new HttpResponse(200, '{}', []);
        $response2 = new HttpResponse(201, '{}', []);

        $mock->queueResponses($response1, $response2);

        $this->assertSame(200, $mock->post('http://example.com', '{}', [])->getStatusCode());
        $this->assertSame(201, $mock->post('http://example.com', '{}', [])->getStatusCode());
    }

    /**
     * Test request is recorded even when exception is thrown.
     */
    public function test_post_withException_stillRecordsRequest(): void
    {
        $mock = new MockHttpClient();
        $mock->throwOnNextRequest(new HttpClientException('Error'));

        try {
            $mock->post('http://example.com', '{"data":true}', [ 'Header' => 'value' ]);
        } catch (HttpClientException $e) {
            // Expected
        }

        $this->assertSame(1, $mock->getRequestCount());
        $request = $mock->getLastRequest();
        $this->assertSame('http://example.com', $request['url']);
        $this->assertSame('{"data":true}', $request['body']);
    }

    /**
     * Test delete request is recorded even when exception is thrown.
     */
    public function test_delete_withException_stillRecordsRequest(): void
    {
        $mock = new MockHttpClient();
        $mock->throwOnNextRequest(new HttpClientException('Error'));

        try {
            $mock->delete('http://example.com/session', [ 'Session-Id' => 'abc' ]);
        } catch (HttpClientException $e) {
            // Expected
        }

        $this->assertSame(1, $mock->getRequestCount());
        $request = $mock->getLastRequest();
        $this->assertSame('DELETE', $request['method']);
        $this->assertSame('http://example.com/session', $request['url']);
    }
}
