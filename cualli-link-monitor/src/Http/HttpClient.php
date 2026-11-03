<?php
/**
 * HTTP requests to the site.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Http;

use Cualli\LinkMonitor\Config;
use Cualli\LinkMonitor\Model\CheckResult;

/**
 * Downloads pages and checks URLs with the WordPress HTTP API.
 */
final class HttpClient {

	/**
	 * Status codes some servers return for HEAD although GET works.
	 * A URL that returns one of them is checked again with GET.
	 */
	private const HEAD_NOT_SUPPORTED_STATUSES = array( 403, 405, 501 );

	private const FALLBACK_GET_MAX_BYTES = 1024;

	private const MAX_REDIRECTS = 5;

	/**
	 * Seconds to wait before repeating a request that timed out or got a 5xx.
	 * Such failures are often momentary, and a single retry avoids false alarms.
	 */
	private const RETRY_DELAY_SECONDS = 3;

	private const FIRST_SERVER_ERROR_STATUS = 500;

	/**
	 * Constructor.
	 *
	 * @param Config $config Plugin settings.
	 */
	public function __construct( private readonly Config $config ) {
	}

	/**
	 * Downloads the HTML of a page.
	 *
	 * @param string $url Page URL.
	 */
	public function fetch_page( string $url ): PageResponse {
		$response = $this->send( 'GET', $url );

		return new PageResponse( $this->to_check_result( $response ), (string) wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Checks a URL with HEAD, and with a partial GET when HEAD is not supported.
	 *
	 * @param string $url URL to check.
	 */
	public function check( string $url ): CheckResult {
		$head_result = $this->to_check_result( $this->send( 'HEAD', $url ) );

		if ( ! in_array( $head_result->status_code, self::HEAD_NOT_SUPPORTED_STATUSES, true ) ) {
			return $head_result;
		}

		return $this->to_check_result( $this->send( 'GET', $url, array( 'limit_response_size' => self::FALLBACK_GET_MAX_BYTES ) ) );
	}

	/**
	 * Sends a request, and repeats it once when it fails in a way that is often momentary.
	 *
	 * @param string               $method     HTTP method.
	 * @param string               $url        URL to request.
	 * @param array<string, mixed> $extra_args Arguments added to the shared ones.
	 *
	 * @return array|\WP_Error Response of `wp_remote_request()`.
	 */
	private function send( string $method, string $url, array $extra_args = array() ): array|\WP_Error {
		$args     = array_merge( $this->request_args(), $extra_args, array( 'method' => $method ) );
		$response = wp_remote_request( $url, $args );

		if ( ! $this->is_retryable( $response ) ) {
			return $response;
		}

		sleep( self::RETRY_DELAY_SECONDS );

		return wp_remote_request( $url, $args );
	}

	/**
	 * Whether the response is a connection error or a 5xx status.
	 *
	 * @param array|\WP_Error $response Response of `wp_remote_request()`.
	 */
	private function is_retryable( array|\WP_Error $response ): bool {
		return is_wp_error( $response )
			|| (int) wp_remote_retrieve_response_code( $response ) >= self::FIRST_SERVER_ERROR_STATUS;
	}

	/**
	 * Converts a WordPress HTTP API response into a check result.
	 *
	 * @param array|\WP_Error $response Response of `wp_remote_*()`.
	 */
	private function to_check_result( array|\WP_Error $response ): CheckResult {
		if ( is_wp_error( $response ) ) {
			return CheckResult::connection_error( $response->get_error_message() );
		}

		return new CheckResult( (int) wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * Arguments shared by every request.
	 *
	 * HEAD requests do not follow redirects by default, so `redirection` is set
	 * for both methods.
	 *
	 * @return array<string, mixed>
	 */
	private function request_args(): array {
		return array(
			'timeout'     => $this->config->request_timeout(),
			'redirection' => self::MAX_REDIRECTS,
			'user-agent'  => 'CualliLinkMonitor/1.0 (+' . home_url( '/' ) . ')',
		);
	}
}
