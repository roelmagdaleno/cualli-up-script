<?php
/**
 * Result of a finished scan.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Scan;

use Cualli\LinkMonitor\Model\BrokenReference;

/**
 * Totals and broken references of a finished scan.
 */
final class ScanResult {

	/**
	 * Constructor.
	 *
	 * @param ScanSummary       $summary          Totals of the scan.
	 * @param BrokenReference[] $broken_references Broken references found.
	 */
	public function __construct(
		public readonly ScanSummary $summary,
		public readonly array $broken_references
	) {
	}
}
