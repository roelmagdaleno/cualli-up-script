<?php
/**
 * Result of requesting a URL.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Model;

/**
 * HTTP status code of a request, or the error when the request failed.
 */
final class CheckResult {

	private const CONNECTION_ERROR_STATUS = 0;

	private const FIRST_BROKEN_STATUS = 400;

	/**
	 * Constructor.
	 *
	 * @param int    $status_code   HTTP status code, or 0 when the request failed.
	 * @param string $error_message Error message when the request failed.
	 */
	public function __construct(
		public readonly int $status_code,
		public readonly string $error_message = ''
	) {
	}

	/**
	 * Creates the result of a request that got no HTTP response.
	 *
	 * @param string $error_message Error message.
	 */
	public static function connection_error( string $error_message ): self {
		return new self( self::CONNECTION_ERROR_STATUS, $error_message );
	}

	/**
	 * Whether the URL is broken: a 4xx or 5xx status, or no response at all.
	 */
	public function is_broken(): bool {
		return self::CONNECTION_ERROR_STATUS === $this->status_code
			|| $this->status_code >= self::FIRST_BROKEN_STATUS;
	}

	/**
	 * Short text for the report, such as "HTTP 404".
	 */
	public function describe(): string {
		if ( self::CONNECTION_ERROR_STATUS === $this->status_code ) {
			return 'Sin respuesta: ' . $this->error_message;
		}

		return 'HTTP ' . $this->status_code;
	}
}
