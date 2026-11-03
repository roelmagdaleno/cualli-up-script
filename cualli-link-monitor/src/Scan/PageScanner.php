<?php
/**
 * Scans one page.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Scan;

use Cualli\LinkMonitor\Html\ReferenceExtractor;
use Cualli\LinkMonitor\Http\HttpClient;
use Cualli\LinkMonitor\Model\Reference;
use Cualli\LinkMonitor\Model\ReferenceType;

/**
 * Downloads a page, checks every URL it references and records the
 * broken ones in the scan state.
 *
 * Each URL is requested once per scan, however many pages use it.
 */
final class PageScanner {

	/**
	 * Constructor.
	 *
	 * @param HttpClient         $http_client Sends the requests.
	 * @param ReferenceExtractor $extractor   Finds the references of a page.
	 */
	public function __construct(
		private readonly HttpClient $http_client,
		private readonly ReferenceExtractor $extractor
	) {
	}

	/**
	 * Scans one page.
	 *
	 * @param string    $page_url URL of the page.
	 * @param ScanState $state    State of the scan, updated in place.
	 */
	public function scan( string $page_url, ScanState $state ): void {
		$page = $this->http_client->fetch_page( $page_url );

		$state->mark_checked( $page_url );

		if ( $page->result->is_broken() ) {
			$state->record_broken( new Reference( $page_url, ReferenceType::Page ), $page->result );
			return;
		}

		foreach ( $this->extractor->extract( $page->html, $page_url ) as $reference ) {
			$this->check_reference( $reference, $page_url, $state );
		}
	}

	/**
	 * Checks one reference of a page, unless it was checked before in this scan.
	 *
	 * @param Reference $reference Reference found in the page.
	 * @param string    $page_url URL of the page.
	 * @param ScanState $state    State of the scan.
	 */
	private function check_reference( Reference $reference, string $page_url, ScanState $state ): void {
		if ( ! $state->has_checked( $reference->url ) ) {
			$this->request_reference( $reference, $state );
		}

		$state->add_occurrence( $reference->url, $page_url );
	}

	/**
	 * Requests a reference and records it when it is broken.
	 *
	 * @param Reference $reference Reference to request.
	 * @param ScanState $state    State of the scan.
	 */
	private function request_reference( Reference $reference, ScanState $state ): void {
		$state->mark_checked( $reference->url );

		$result = $this->http_client->check( $reference->url );

		if ( $result->is_broken() ) {
			$state->record_broken( $reference, $result );
		}
	}
}
