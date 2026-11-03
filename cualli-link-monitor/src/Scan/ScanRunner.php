<?php
/**
 * Runs a full scan.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Scan;

use Cualli\LinkMonitor\Config;
use Cualli\LinkMonitor\Discovery\PageUrlCollector;
use Cualli\LinkMonitor\Report\ReportMailer;

/**
 * Orchestrates a scan: lists the pages, scans them in time-limited batches
 * and sends the report when the last page is done.
 *
 * Cron flow: `start()` runs the first batch right away. While pages are left,
 * each batch saves the state and schedules the next one.
 * WP-CLI flow: `run_now()` scans every page in one process.
 */
final class ScanRunner {

	/**
	 * A saved scan older than this is treated as abandoned and replaced.
	 */
	private const ABANDONED_SCAN_SECONDS = 12 * HOUR_IN_SECONDS;

	/**
	 * Constructor.
	 *
	 * @param Config              $config     Plugin settings.
	 * @param PageUrlCollector    $collector  Lists the pages to scan.
	 * @param PageScanner         $scanner    Scans one page.
	 * @param ScanStateRepository $repository Stores the scan between batches.
	 * @param BatchDispatcher     $dispatcher Schedules the next batch.
	 * @param ReportMailer        $mailer     Sends the report.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly PageUrlCollector $collector,
		private readonly PageScanner $scanner,
		private readonly ScanStateRepository $repository,
		private readonly BatchDispatcher $dispatcher,
		private readonly ReportMailer $mailer
	) {
	}

	/**
	 * Starts a cron scan, unless one is already running.
	 */
	public function start(): void {
		if ( $this->is_scan_running() ) {
			return;
		}

		$this->repository->save_state( ScanState::begin( $this->collector->collect(), time() ) );
		$this->process_batch( 1 );
	}

	/**
	 * Scans pages until the time budget runs out, then schedules the next
	 * batch or finishes the scan.
	 *
	 * @param int $batch_number Number of this batch, starting at 1.
	 */
	public function process_batch( int $batch_number ): void {
		$state = $this->repository->load_state();

		if ( null === $state ) {
			return;
		}

		$this->scan_pages( $state, time() + $this->config->batch_time_budget() );

		if ( $state->has_pending_pages() ) {
			$this->repository->save_state( $state );
			$this->dispatcher->dispatch( $batch_number + 1 );
			return;
		}

		$this->repository->delete_state();
		$this->complete( $state );
	}

	/**
	 * Scans every page in this process, without batches. Used by WP-CLI.
	 *
	 * Does not touch a cron scan in progress.
	 *
	 * @param callable(string): void $on_page Called with each page URL before it is scanned.
	 */
	public function run_now( callable $on_page ): ScanResult {
		$state = ScanState::begin( $this->collector->collect(), time() );

		$this->scan_pages( $state, PHP_INT_MAX, $on_page );

		return $this->complete( $state );
	}

	/**
	 * Scans pending pages until none are left or the deadline passes.
	 *
	 * @param ScanState                     $state    State of the scan.
	 * @param int                           $deadline Unix time after which no new page starts.
	 * @param (callable(string): void)|null $on_page  Called with each page URL before it is scanned.
	 */
	private function scan_pages( ScanState $state, int $deadline, ?callable $on_page = null ): void {
		while ( $state->has_pending_pages() && time() < $deadline ) {
			$page_url = $state->take_next_page();

			if ( null !== $on_page ) {
				$on_page( $page_url );
			}

			$this->scanner->scan( $page_url, $state );
		}
	}

	/**
	 * Saves the summary and sends the report of a finished scan.
	 *
	 * @param ScanState $state State of the finished scan.
	 */
	private function complete( ScanState $state ): ScanResult {
		$result = new ScanResult( $state->summarize( time() ), $state->broken_references() );

		$this->repository->save_last_summary( $result->summary );
		$this->mailer->send( $result );

		return $result;
	}

	/**
	 * Whether a cron scan started less than ABANDONED_SCAN_SECONDS ago is still saved.
	 */
	private function is_scan_running(): bool {
		$state = $this->repository->load_state();

		return null !== $state && $state->started_at() > time() - self::ABANDONED_SCAN_SECONDS;
	}
}
