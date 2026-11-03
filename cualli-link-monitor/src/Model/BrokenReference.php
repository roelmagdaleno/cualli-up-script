<?php
/**
 * A broken reference and the pages that use it.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Model;

/**
 * Read-only view of one broken reference, used to build the report.
 */
final class BrokenReference {

	/**
	 * Constructor.
	 *
	 * @param Reference $reference    The broken reference.
	 * @param string    $result_text Short description of the failure, such as "HTTP 404".
	 * @param string[]  $found_on    URLs of the pages that use the reference.
	 */
	public function __construct(
		public readonly Reference $reference,
		public readonly string $result_text,
		public readonly array $found_on
	) {
	}
}
