<?php
/**
 * Result of downloading a page.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Http;

use Cualli\LinkMonitor\Model\CheckResult;

/**
 * Status of a page request and the HTML it returned.
 */
final class PageResponse {

	/**
	 * Constructor.
	 *
	 * @param CheckResult $result Status of the request.
	 * @param string      $html   Response body. Empty when the request failed.
	 */
	public function __construct(
		public readonly CheckResult $result,
		public readonly string $html
	) {
	}
}
