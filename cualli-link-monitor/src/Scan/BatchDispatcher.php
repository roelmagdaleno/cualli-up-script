<?php
/**
 * Schedules the next batch of a scan.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Scan;

use Cualli\LinkMonitor\Config;

/**
 * Schedules a one-time cron event that continues the scan.
 *
 * WP-Cron runs the event on the next cron run: a site visit, or the server
 * cron when Kinsta has it enabled for the site.
 */
final class BatchDispatcher {

	/**
	 * Schedules the batch.
	 *
	 * The batch number is the event argument, so each batch is a different
	 * event and WordPress does not drop it as a duplicate.
	 *
	 * @param int $batch_number Number of the batch to run.
	 */
	public function dispatch( int $batch_number ): void {
		wp_schedule_single_event( time(), Config::BATCH_HOOK, array( $batch_number ) );
	}
}
