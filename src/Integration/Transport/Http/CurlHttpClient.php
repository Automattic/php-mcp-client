<?php

declare( strict_types=1 );

namespace Ovidiu\McpClient\Integration\Transport\Http;

/**
 * HTTP client implementation using native PHP cURL.
 *
 * Provides HTTP operations required by the MCP HTTP transport using
 * the cURL extension for maximum compatibility and control.
 *
 * @since n.e.x.t
 */
final class CurlHttpClient implements HttpClientInterface {
	/**
	 * Request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Connection timeout in seconds.
	 */
	private int $connect_timeout;

	/**
	 * Whether to verify SSL certificates.
	 */
	private bool $verify_ssl;

	/**
	 * Create a new cURL HTTP client.
	 *
	 * @param int  $timeout         Request timeout in seconds.
	 * @param int  $connect_timeout Connection timeout in seconds.
	 * @param bool $verify_ssl      Whether to verify SSL certificates.
	 */
	public function __construct(
		int $timeout = 30,
		int $connect_timeout = 10,
		bool $verify_ssl = true
	) {
		$this->timeout         = $timeout;
		$this->connect_timeout = $connect_timeout;
		$this->verify_ssl      = $verify_ssl;
	}

	/**
	 * {@inheritDoc}
	 */
	public function post( string $url, string $body, array $headers ): HttpResponse {
		$curl = $this->initCurl( $url );

		curl_setopt( $curl, CURLOPT_POST, true );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $body );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $this->formatHeaders( $headers ) );

		return $this->executeRequest( $curl, $url );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( string $url, array $headers ): void {
		$curl = $this->initCurl( $url );

		curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'DELETE' );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $this->formatHeaders( $headers ) );

		$this->executeRequest( $curl, $url );
	}

	/**
	 * Initialize a cURL handle with common options.
	 *
	 * @param string $url The URL for the request.
	 *
	 * @return resource|\CurlHandle The initialized cURL handle.
	 *
	 * @throws HttpClientException When cURL initialization fails.
	 */
	private function initCurl( string $url ) {
		$curl = curl_init( $url );

		if ( $curl === false ) {
			throw new HttpClientException( 'Failed to initialize cURL' );
		}

		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_HEADER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, $this->timeout );
		curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, $this->verify_ssl ? 2 : 0 );

		return $curl;
	}

	/**
	 * Execute a cURL request and parse the response.
	 *
	 * @param resource|\CurlHandle $curl The cURL handle.
	 * @param string               $url  The URL for error messages.
	 *
	 * @return HttpResponse The HTTP response.
	 *
	 * @throws HttpClientException When the request fails.
	 */
	private function executeRequest( $curl, string $url ): HttpResponse {
		$raw_response = curl_exec( $curl );

		if ( $raw_response === false ) {
			$error_code    = curl_errno( $curl );
			$error_message = curl_error( $curl );
			curl_close( $curl );

			throw $this->createCurlException( $error_code, $error_message, $url );
		}

		/** @var string $raw_response */
		$status_code = (int) curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		$header_size = (int) curl_getinfo( $curl, CURLINFO_HEADER_SIZE );

		curl_close( $curl );

		$raw_headers = substr( $raw_response, 0, $header_size );
		$body        = substr( $raw_response, $header_size );
		$headers     = $this->parseHeaders( $raw_headers );

		return new HttpResponse( $status_code, $body, $headers );
	}

	/**
	 * Format headers array for cURL.
	 *
	 * Converts associative header array to array of "Name: Value" strings.
	 *
	 * @param array<string, string> $headers The headers to format.
	 *
	 * @return array<string> The formatted headers.
	 */
	private function formatHeaders( array $headers ): array {
		$formatted = [];

		foreach ( $headers as $name => $value ) {
			$formatted[] = sprintf( '%s: %s', $name, $value );
		}

		return $formatted;
	}

	/**
	 * Parse raw HTTP headers into an associative array.
	 *
	 * @param string $raw_headers The raw headers string.
	 *
	 * @return array<string, string> The parsed headers.
	 */
	private function parseHeaders( string $raw_headers ): array {
		$headers = [];
		$lines   = explode( "\r\n", $raw_headers );

		foreach ( $lines as $line ) {
			// Skip status line and empty lines
			if ( strpos( $line, ':' ) === false ) {
				continue;
			}

			$parts = explode( ':', $line, 2 );

			if ( count( $parts ) === 2 ) {
				$name            = trim( $parts[0] );
				$value           = trim( $parts[1] );
				$headers[ $name ] = $value;
			}
		}

		return $headers;
	}

	/**
	 * Create an appropriate exception for a cURL error.
	 *
	 * @param int    $error_code    The cURL error code.
	 * @param string $error_message The cURL error message.
	 * @param string $url           The URL that failed.
	 *
	 * @return HttpClientException The exception to throw.
	 */
	private function createCurlException( int $error_code, string $error_message, string $url ): HttpClientException {
		// Connection errors
		if ( in_array( $error_code, [ CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST ], true ) ) {
			return HttpClientException::connectionFailed( $url );
		}

		// Timeout errors
		if ( $error_code === CURLE_OPERATION_TIMEDOUT ) {
			return HttpClientException::curlError( $error_code, 'Request timed out' );
		}

		return HttpClientException::curlError( $error_code, $error_message );
	}
}
