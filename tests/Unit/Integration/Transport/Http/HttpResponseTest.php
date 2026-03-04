<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\Integration\Transport\Http;

use GalatanOvidiu\PhpMcpClient\Transport\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HttpResponse value object.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\Transport\Http\HttpResponse
 */
final class HttpResponseTest extends TestCase
{
    /**
     * Test creating a response with all properties and retrieving them.
     */
    public function test_constructor_withAllProperties_storesValues(): void
    {
        $status_code = 200;
        $body        = '{"result":1}';
        $headers     = [ 'Content-Type' => 'application/json' ];

        $response = new HttpResponse($status_code, $body, $headers);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"result":1}', $response->getBody());
        $this->assertSame([ 'Content-Type' => 'application/json' ], $response->getHeaders());
    }

    /**
     * Test creating a response with default empty headers.
     */
    public function test_constructor_withoutHeaders_defaultsToEmptyArray(): void
    {
        $response = new HttpResponse(404, 'Not Found');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $response->getBody());
        $this->assertSame([], $response->getHeaders());
    }

    /**
     * Test getHeader with exact case match.
     */
    public function test_getHeader_withExactCase_returnsValue(): void
    {
        $response = new HttpResponse(200, '', [ 'Content-Type' => 'application/json' ]);

        $this->assertSame('application/json', $response->getHeader('Content-Type'));
    }

    /**
     * Test getHeader with different case (case-insensitive lookup).
     */
    public function test_getHeader_withDifferentCase_returnsValue(): void
    {
        $response = new HttpResponse(200, '', [ 'content-type' => 'application/json' ]);

        $this->assertSame('application/json', $response->getHeader('Content-Type'));
    }

    /**
     * Test getHeader with all lowercase header name.
     */
    public function test_getHeader_withLowercaseKey_returnsValue(): void
    {
        $response = new HttpResponse(200, '', [ 'CONTENT-TYPE' => 'text/html' ]);

        $this->assertSame('text/html', $response->getHeader('content-type'));
    }

    /**
     * Test getHeader with missing header.
     */
    public function test_getHeader_withMissingHeader_returnsNull(): void
    {
        $response = new HttpResponse(200, '', []);

        $this->assertNull($response->getHeader('X-Missing'));
    }

    /**
     * Test getHeader with missing header when other headers exist.
     */
    public function test_getHeader_withNonExistentHeader_returnsNull(): void
    {
        $response = new HttpResponse(200, '', [ 'Content-Type' => 'application/json' ]);

        $this->assertNull($response->getHeader('X-Custom-Header'));
    }

    /**
     * Test isJson with standard application/json content type.
     */
    public function test_isJson_withApplicationJson_returnsTrue(): void
    {
        $response = new HttpResponse(200, '{}', [ 'Content-Type' => 'application/json' ]);

        $this->assertTrue($response->isJson());
    }

    /**
     * Test isJson with application/json and charset parameter.
     */
    public function test_isJson_withCharsetParameter_returnsTrue(): void
    {
        $response = new HttpResponse(200, '{}', [ 'Content-Type' => 'application/json; charset=utf-8' ]);

        $this->assertTrue($response->isJson());
    }

    /**
     * Test isJson with text/html content type.
     */
    public function test_isJson_withTextHtml_returnsFalse(): void
    {
        $response = new HttpResponse(200, '<html></html>', [ 'Content-Type' => 'text/html' ]);

        $this->assertFalse($response->isJson());
    }

    /**
     * Test isJson with text/plain content type.
     */
    public function test_isJson_withTextPlain_returnsFalse(): void
    {
        $response = new HttpResponse(200, 'plain text', [ 'Content-Type' => 'text/plain' ]);

        $this->assertFalse($response->isJson());
    }

    /**
     * Test isJson with no Content-Type header.
     */
    public function test_isJson_withNoContentType_returnsFalse(): void
    {
        $response = new HttpResponse(200, '{}', []);

        $this->assertFalse($response->isJson());
    }

    /**
     * Test isJson with case-insensitive Content-Type header.
     */
    public function test_isJson_withLowercaseContentTypeHeader_returnsTrue(): void
    {
        $response = new HttpResponse(200, '{}', [ 'content-type' => 'application/json' ]);

        $this->assertTrue($response->isJson());
    }

    /**
     * Test isJson with uppercase content type value.
     */
    public function test_isJson_withUppercaseContentTypeValue_returnsTrue(): void
    {
        $response = new HttpResponse(200, '{}', [ 'Content-Type' => 'APPLICATION/JSON' ]);

        $this->assertTrue($response->isJson());
    }

    /**
     * Test isJson with content type containing extra whitespace.
     */
    public function test_isJson_withWhitespace_returnsTrue(): void
    {
        $response = new HttpResponse(200, '{}', [ 'Content-Type' => ' application/json ; charset=utf-8' ]);

        $this->assertTrue($response->isJson());
    }

    /**
     * Test that getHeaders returns original case.
     */
    public function test_getHeaders_preservesOriginalCase(): void
    {
        $original_headers = [
            'Content-Type'   => 'application/json',
            'X-Custom-HEADER' => 'custom-value',
        ];

        $response = new HttpResponse(200, '', $original_headers);

        $this->assertSame($original_headers, $response->getHeaders());
    }

    /**
     * Test response with multiple headers.
     */
    public function test_getHeader_withMultipleHeaders_returnsCorrectValues(): void
    {
        $headers = [
            'Content-Type'     => 'application/json',
            'Content-Length'   => '123',
            'X-Request-Id'     => 'abc-123',
            'Mcp-Session-Id'   => 'session-456',
        ];

        $response = new HttpResponse(200, '{}', $headers);

        $this->assertSame('application/json', $response->getHeader('Content-Type'));
        $this->assertSame('123', $response->getHeader('Content-Length'));
        $this->assertSame('abc-123', $response->getHeader('X-Request-Id'));
        $this->assertSame('session-456', $response->getHeader('Mcp-Session-Id'));
    }

    /**
     * Test response with empty body.
     */
    public function test_constructor_withEmptyBody_storesEmptyString(): void
    {
        $response = new HttpResponse(204, '');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getBody());
    }

    /**
     * Test response with various status codes.
     *
     * @dataProvider statusCodeProvider
     */
    public function test_getStatusCode_withVariousStatusCodes_returnsCorrectCode(int $status_code): void
    {
        $response = new HttpResponse($status_code, '');

        $this->assertSame($status_code, $response->getStatusCode());
    }

    /**
     * Data provider for status code tests.
     *
     * @return array<string, array{int}>
     */
    public static function statusCodeProvider(): array
    {
        return [
            '200 OK'                  => [ 200 ],
            '201 Created'             => [ 201 ],
            '204 No Content'          => [ 204 ],
            '400 Bad Request'         => [ 400 ],
            '401 Unauthorized'        => [ 401 ],
            '403 Forbidden'           => [ 403 ],
            '404 Not Found'           => [ 404 ],
            '500 Internal Server Error' => [ 500 ],
            '502 Bad Gateway'         => [ 502 ],
        ];
    }
}
