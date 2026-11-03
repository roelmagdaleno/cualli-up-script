<?php
/**
 * Progress of a scan.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Scan;

use Cualli\LinkMonitor\Model\BrokenReference;
use Cualli\LinkMonitor\Model\CheckResult;
use Cualli\LinkMonitor\Model\Reference;
use Cualli\LinkMonitor\Model\ReferenceType;

/**
 * Everything a scan knows between batches: the pages left, the URLs already
 * checked and the broken references found.
 *
 * It holds only arrays and scalars, so it can be stored in an option and read
 * back after a plugin update without class unserialization problems.
 */
final class ScanState {

	/**
	 * Constructor. Use `begin()` or `from_array()` instead.
	 *
	 * @param int                                                                    $started_at    Unix time of the start.
	 * @param string[]                                                               $pending_pages URLs of the pages not scanned yet.
	 * @param int                                                                    $scanned_pages Number of pages scanned.
	 * @param array<string, true>                                                    $checked_urls  URLs already requested, as keys.
	 * @param array<string, array{type: string, result: string, found_on: string[]}> $broken        Broken references keyed by URL.
	 */
	private function __construct(
		private readonly int $started_at,
		private array $pending_pages,
		private int $scanned_pages,
		private array $checked_urls,
		private array $broken
	) {
	}

	/**
	 * Creates the state of a new scan.
	 *
	 * @param string[] $page_urls  URLs of the pages to scan.
	 * @param int      $started_at Unix time of the start.
	 */
	public static function begin( array $page_urls, int $started_at ): self {
		return new self( $started_at, array_values( $page_urls ), 0, array(), array() );
	}

	/**
	 * Restores a state saved with `to_array()`.
	 *
	 * @param array<string, mixed> $data Saved state.
	 */
	public static function from_array( array $data ): self {
		return new self(
			(int) ( $data['started_at'] ?? 0 ),
			(array) ( $data['pending_pages'] ?? array() ),
			(int) ( $data['scanned_pages'] ?? 0 ),
			(array) ( $data['checked_urls'] ?? array() ),
			(array) ( $data['broken'] ?? array() )
		);
	}

	/**
	 * Returns the state as an array that can be stored in an option.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'started_at'    => $this->started_at,
			'pending_pages' => $this->pending_pages,
			'scanned_pages' => $this->scanned_pages,
			'checked_urls'  => $this->checked_urls,
			'broken'        => $this->broken,
		);
	}

	/**
	 * Unix time of the start of the scan.
	 */
	public function started_at(): int {
		return $this->started_at;
	}

	/**
	 * Whether pages are left to scan.
	 */
	public function has_pending_pages(): bool {
		return array() !== $this->pending_pages;
	}

	/**
	 * Removes the next page from the queue and counts it as scanned.
	 *
	 * Call `has_pending_pages()` first.
	 */
	public function take_next_page(): string {
		++$this->scanned_pages;

		return (string) array_shift( $this->pending_pages );
	}

	/**
	 * Whether the URL was already requested in this scan.
	 *
	 * @param string $url Absolute URL.
	 */
	public function has_checked( string $url ): bool {
		return isset( $this->checked_urls[ $url ] );
	}

	/**
	 * Records that the URL was requested.
	 *
	 * @param string $url Absolute URL.
	 */
	public function mark_checked( string $url ): void {
		$this->checked_urls[ $url ] = true;
	}

	/**
	 * Records a broken reference. Pages already recorded for the URL are kept.
	 *
	 * @param Reference   $reference The broken reference.
	 * @param CheckResult $result   Result of the request.
	 */
	public function record_broken( Reference $reference, CheckResult $result ): void {
		$this->broken[ $reference->url ] = array(
			'type'     => $reference->type->value,
			'result'   => $result->describe(),
			'found_on' => $this->broken[ $reference->url ]['found_on'] ?? array(),
		);
	}

	/**
	 * Records that a page uses a URL. Does nothing when the URL is not broken.
	 *
	 * @param string $url      URL of the reference.
	 * @param string $page_url URL of the page that uses it.
	 */
	public function add_occurrence( string $url, string $page_url ): void {
		if ( ! isset( $this->broken[ $url ] ) || in_array( $page_url, $this->broken[ $url ]['found_on'], true ) ) {
			return;
		}

		$this->broken[ $url ]['found_on'][] = $page_url;
	}

	/**
	 * Returns the broken references found so far.
	 *
	 * @return BrokenReference[]
	 */
	public function broken_references(): array {
		$broken_references = array();

		foreach ( $this->broken as $url => $entry ) {
			$reference           = new Reference( $url, ReferenceType::from( $entry['type'] ) );
			$broken_references[] = new BrokenReference( $reference, $entry['result'], $entry['found_on'] );
		}

		return $broken_references;
	}

	/**
	 * Returns the totals of the scan.
	 *
	 * @param int $finished_at Unix time of the end.
	 */
	public function summarize( int $finished_at ): ScanSummary {
		return new ScanSummary(
			$this->started_at,
			$finished_at,
			$this->scanned_pages,
			count( $this->checked_urls ),
			count( $this->broken )
		);
	}
}
