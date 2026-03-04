<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\Transport\Http;

use GalatanOvidiu\PhpMcpClient\Transport\Http\CurlHttpClient;
use GalatanOvidiu\PhpMcpClient\Transport\Http\HttpClientException;
use GalatanOvidiu\PhpMcpClient\Transport\Http\HttpClientInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CurlHttpClient.
 *
 * Note: These tests focus on the class structure and behavior that can be tested
 * without a real HTTP server. Integration tests with actual HTTP servers should
 * be in a separate test suite.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\Transport\Http\CurlHttpClient
 */
final class CurlHttpClientTest extends TestCase
{
    /**
     * Test that CurlHttpClient implements HttpClientInterface.
     */
    public function test_implements_httpClientInterface(): void
    {
        $client = new CurlHttpClient();

        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }

    /**
     * Test constructor with default values.
     */
    public function test_constructor_withDefaults_createsInstance(): void
    {
        $client = new CurlHttpClient();

        $this->assertInstanceOf(CurlHttpClient::class, $client);
    }

    /**
     * Test constructor with custom timeout values.
     */
    public function test_constructor_withCustomTimeouts_createsInstance(): void
    {
        $client = new CurlHttpClient(60, 5, false);

        $this->assertInstanceOf(CurlHttpClient::class, $client);
    }

    /**
     * Test POST request to non-existent server throws exception.
     */
    public function test_post_toNonExistentServer_throwsHttpClientException(): void
    {
        $client = new CurlHttpClient(1, 1);

        $this->expectException(HttpClientException::class);

        $client->post(
            'http://localhost:19999/non-existent',
            '{"test": true}',
            [ 'Content-Type' => 'application/json' ]
        );
    }

    /**
     * Test DELETE request to non-existent server throws exception.
     */
    public function test_delete_toNonExistentServer_throwsHttpClientException(): void
    {
        $client = new CurlHttpClient(1, 1);

        $this->expectException(HttpClientException::class);

        $client->delete(
            'http://localhost:19999/non-existent',
            [ 'Mcp-Session-Id' => 'test-session' ]
        );
    }

    /**
     * Test exception contains meaningful message for connection failure.
     */
    public function test_post_connectionFailure_exceptionHasMeaningfulMessage(): void
    {
        $client = new CurlHttpClient(1, 1);

        try {
            $client->post(
                'http://localhost:19999/mcp',
                '{}',
                []
            );
            $this->fail('Expected HttpClientException was not thrown');
        } catch (HttpClientException $exception) {
            $message = $exception->getMessage();
            // Should contain URL or connection-related info
            $this->assertTrue(
                strpos($message, 'localhost') !== false
                || strpos($message, 'connect') !== false
                || strpos($message, 'cURL') !== false,
                sprintf('Exception message should indicate connection failure: %s', $message)
            );
        }
    }

    /**
     * Test that HttpClientException extends TransportException.
     */
    public function test_exception_extendsTransportException(): void
    {
        $client = new CurlHttpClient(1, 1);

        try {
            $client->post('http://localhost:19999/mcp', '{}', []);
            $this->fail('Expected HttpClientException was not thrown');
        } catch (HttpClientException $exception) {
            $this->assertInstanceOf(
                \GalatanOvidiu\PhpMcpClient\Exception\TransportException::class,
                $exception
            );
        }
    }

    /**
     * Test POST request to unreachable host throws appropriate exception.
     */
    public function test_post_toUnreachableHost_throwsHttpClientException(): void
    {
        $client = new CurlHttpClient(1, 1);

        $this->expectException(HttpClientException::class);

        // Using a non-routable IP to trigger connection timeout
        $client->post(
            'http://10.255.255.1:9999/mcp',
            '{}',
            []
        );
    }

    /**
     * Test constructor creates instance with SSL verification disabled.
     */
    public function test_constructor_withSslVerificationDisabled_createsInstance(): void
    {
        $client = new CurlHttpClient(30, 10, false);

        $this->assertInstanceOf(CurlHttpClient::class, $client);
    }

    /**
     * Test that short timeout properly limits connection attempts.
     *
     * This test verifies that the connect_timeout option is respected by using
     * a very short timeout and a non-routable address.
     */
    public function test_post_withShortTimeout_failsQuickly(): void
    {
        $client = new CurlHttpClient(1, 1);

        $start = microtime(true);

        try {
            // Using localhost with unused port - should fail quickly
            $client->post('http://localhost:19999/mcp', '{}', []);
        } catch (HttpClientException $exception) {
            // Expected
        }

        $elapsed = microtime(true) - $start;

        // Should fail within a reasonable time (allow some buffer)
        $this->assertLessThan(
            5.0,
            $elapsed,
            'Connection should fail quickly with short timeout'
        );
    }

    /**
     * Test POST with empty body.
     */
    public function test_post_withEmptyBody_throwsOnConnectionFailure(): void
    {
        $client = new CurlHttpClient(1, 1);

        $this->expectException(HttpClientException::class);

        $client->post('http://localhost:19999/mcp', '', []);
    }

    /**
     * Test POST with empty headers.
     */
    public function test_post_withEmptyHeaders_throwsOnConnectionFailure(): void
    {
        $client = new CurlHttpClient(1, 1);

        $this->expectException(HttpClientException::class);

        $client->post('http://localhost:19999/mcp', '{"id":1}', []);
    }

    /**
     * Test DELETE with multiple headers.
     */
    public function test_delete_withMultipleHeaders_throwsOnConnectionFailure(): void
    {
        $client = new CurlHttpClient(1, 1);

        $this->expectException(HttpClientException::class);

        $client->delete(
            'http://localhost:19999/mcp',
            [
                'Mcp-Session-Id' => 'session-123',
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
            ]
        );
    }
}
