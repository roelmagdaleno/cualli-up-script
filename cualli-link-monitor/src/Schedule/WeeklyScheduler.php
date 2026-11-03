<?php
/**
 * Schedules the weekly scan.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Schedule;

use Cualli\LinkMonitor\Config;

/**
 * Keeps one recurring `weekly` cron event at the configured weekday and time,
 * in the site timezone (America/Mexico_City on cualli.mx).
 */
final class WeeklyScheduler {

	/**
	 * Constructor.
	 *
	 * @param Config $config Plugin settings.
	 */
	public function __construct( private readonly Config $config ) {
	}

	/**
	 * Schedules the weekly event if it is not scheduled yet.
	 */
	public function ensure_scheduled(): void {
		if ( false !== $this->next_run() ) {
			return;
		}

		wp_schedule_event( $this->next_run_timestamp(), 'weekly', Config::WEEKLY_SCAN_HOOK );
	}

	/**
	 * Replaces the weekly event. Use it after changing the weekday or time.
	 *
	 * @return int Unix time of the next run.
	 */
	public function reschedule(): int {
		wp_clear_scheduled_hook( Config::WEEKLY_SCAN_HOOK );
		$this->ensure_scheduled();

		return (int) $this->next_run();
	}

	/**
	 * Unix time of the next weekly run, or false when it is not scheduled.
	 */
	public function next_run(): int|false {
		return wp_next_scheduled( Config::WEEKLY_SCAN_HOOK );
	}

	/**
	 * Unix time of the next configured weekday and time, after now.
	 */
	private function next_run_timestamp(): int {
		$now            = new \DateTimeImmutable( 'now', wp_timezone() );
		$this_week_slot = $now->modify( sprintf( '%s this week %s', $this->config->scan_weekday(), $this->config->scan_time() ) );

		if ( $this_week_slot <= $now ) {
			return $this_week_slot->modify( '+1 week' )->getTimestamp();
		}

		return $this_week_slot->getTimestamp();
	}
}
