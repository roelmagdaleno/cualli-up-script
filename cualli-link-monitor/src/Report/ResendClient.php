<?php
/**
 * Sends the report through the Resend API.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Report;

use Cualli\LinkMonitor\Config;

/**
 * Posts one plain-text email to Resend with the WordPress HTTP API.
 */
final class ResendClient {

	private const ENDPOINT = 'https://api.resend.com/emails';

	/**
	 * Seconds to wait before repeating a request that timed out or got a 5xx.
	 */
	private const RETRY_DELAY_SECONDS = 3;

	private const FIRST_SERVER_ERROR_STATUS = 500;

	private const MAX_SUCCESS_STATUS = 299;

	private const MIN_SUCCESS_STATUS = 200;

	/**
	 * Constructor.
	 *
	 * @param Config $config Plugin settings.
	 */
	public function __construct( private readonly Config $config ) {
	}

	/**
	 * Sends the report.
	 *
	 * @param Report   $report     Subject and body.
	 * @param string[] $recipients Email addresses.
	 *
	 * @return string|\WP_Error ID that Resend gave the email. It means Resend accepted the email, not that it arrived.
	 */
	public function send( Report $report, array $recipients ): string|\WP_Error {
		$api_key = $this->config->resend_api_key();

		if ( '' === $api_key ) {
			return new \WP_Error( 'cualli_link_monitor_missing_api_key', 'Falta la API key de Resend (CUALLI_LINK_MONITOR_RESEND_API_KEY).' );
		}

		return $this->to_result( $this->post( $this->request_args( $report, $recipients, $api_key ) ) );
	}

	/**
	 * Posts the email, and repeats it once when it fails in a way that is often momentary.
	 *
	 * The idempotency key in the arguments stops the second request from sending a duplicate.
	 *
	 * @param array<string, mixed> $args Arguments of `wp_remote_post()`.
	 *
	 * @return array|\WP_Error Response of `wp_remote_post()`.
	 */
	private function post( array $args ): array|\WP_Error {
		$response = wp_remote_post( self::ENDPOINT, $args );

		if ( ! $this->is_retryable( $response ) ) {
			return $response;
		}

		sleep( self::RETRY_DELAY_SECONDS );

		return wp_remote_post( self::ENDPOINT, $args );
	}

	/**
	 * Whether the response is a connection error or a 5xx status.
	 *
	 * @param array|\WP_Error $response Response of `wp_remote_post()`.
	 */
	private function is_retryable( array|\WP_Error $response ): bool {
		return is_wp_error( $response )
			|| (int) wp_remote_retrieve_response_code( $response ) >= self::FIRST_SERVER_ERROR_STATUS;
	}

	/**
	 * Converts the response into the email ID or an error.
	 *
	 * @param array|\WP_Error $response Response of `wp_remote_post()`.
	 */
	private function to_result( array|\WP_Error $response ): string|\WP_Error {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data   = is_array( $data ) ? $data : array();

		if ( $status < self::MIN_SUCCESS_STATUS || $status > self::MAX_SUCCESS_STATUS ) {
			return new \WP_Error( 'cualli_link_monitor_resend_error', sprintf( 'Resend respondió HTTP %d: %s', $status, (string) ( $data['message'] ?? 'sin detalle' ) ) );
		}

		return (string) ( $data['id'] ?? '' );
	}

	/**
	 * Arguments of the request. Resend rejects requests without a User-Agent.
	 *
	 * @param Report   $report     Subject and body.
	 * @param string[] $recipients Email addresses.
	 * @param string   $api_key    Resend API key.
	 *
	 * @return array<string, mixed>
	 */
	private function request_args( Report $report, array $recipients, string $api_key ): array {
		return array(
			'timeout'    => $this->config->request_timeout(),
			'user-agent' => 'CualliLinkMonitor/1.0 (+' . home_url( '/' ) . ')',
			'headers'    => array(
				'Authorization'   => 'Bearer ' . $api_key,
				'Content-Type'    => 'application/json',
				'Idempotency-Key' => wp_generate_uuid4(),
			),
			'body'       => (string) wp_json_encode(
				array(
					'from'    => $this->config->email_from(),
					'to'      => array_values( $recipients ),
					'subject' => $report->subject,
					'text'    => $report->body,
				)
			),
		);
	}
}
