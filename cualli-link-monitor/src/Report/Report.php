<?php
/**
 * Email report of a scan.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Report;

/**
 * Subject and plain-text body of the report email.
 */
final class Report {

	/**
	 * Constructor.
	 *
	 * @param string $subject Email subject.
	 * @param string $body    Plain-text email body.
	 */
	public function __construct(
		public readonly string $subject,
		public readonly string $body
	) {
	}
}
