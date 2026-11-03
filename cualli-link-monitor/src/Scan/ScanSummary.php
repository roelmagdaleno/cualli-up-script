<?php
/**
 * Totals of a finished scan.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Scan;

/**
 * Totals shown in the report and in `wp cualli-links status`.
 */
final class ScanSummary {

	/**
	 * Constructor.
	 *
	 * @param int $started_at       Unix time of the start.
	 * @param int $finished_at      Unix time of the end.
	 * @param int $scanned_pages    Number of pages scanned.
	 * @param int $checked_urls     Number of unique URLs requested.
	 * @param int $broken_references Number of broken references.
	 */
	public function __construct(
		public readonly int $started_at,
		public readonly int $finished_at,
		public readonly int $scanned_pages,
		public readonly int $checked_urls,
		public readonly int $broken_references
	) {
	}

	/**
	 * Restores a summary saved with `to_array()`.
	 *
	 * @param array<string, int> $data Saved summary.
	 */
	public static function from_array( array $data ): self {
		return new self(
			(int) ( $data['started_at'] ?? 0 ),
			(int) ( $data['finished_at'] ?? 0 ),
			(int) ( $data['scanned_pages'] ?? 0 ),
			(int) ( $data['checked_urls'] ?? 0 ),
			(int) ( $data['broken_references'] ?? 0 )
		);
	}

	/**
	 * Returns the summary as an array that can be stored in an option.
	 *
	 * @return array<string, int>
	 */
	public function to_array(): array {
		return get_object_vars( $this );
	}

	/**
	 * Duration of the scan in seconds.
	 */
	public function duration_in_seconds(): int {
		return max( 0, $this->finished_at - $this->started_at );
	}
}
