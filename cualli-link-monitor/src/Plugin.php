<?php
/**
 * Registers the WordPress hooks of the plugin.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor;

use Cualli\LinkMonitor\Cli\LinkMonitorCommand;

/**
 * Connects WordPress hooks to the plugin services.
 *
 * Services are created only when a hook fires, so normal page loads only pay
 * for the `wp_next_scheduled()` check.
 */
final class Plugin {

	/**
	 * Constructor.
	 *
	 * @param ServiceFactory $services Creates the plugin services.
	 */
	public function __construct( private readonly ServiceFactory $services ) {
	}

	/**
	 * Registers the cron hooks and the WP-CLI command.
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'ensure_weekly_scan_is_scheduled' ) );
		add_action( Config::WEEKLY_SCAN_HOOK, array( $this, 'start_scan' ) );
		add_action( Config::BATCH_HOOK, array( $this, 'process_batch' ) );

		$this->register_cli_command();
	}

	/**
	 * Schedules the weekly scan if it is not scheduled yet.
	 */
	public function ensure_weekly_scan_is_scheduled(): void {
		$this->services->weekly_scheduler()->ensure_scheduled();
	}

	/**
	 * Runs when the weekly cron event fires.
	 */
	public function start_scan(): void {
		$this->services->scan_runner()->start();
	}

	/**
	 * Runs when a follow-up batch event fires.
	 *
	 * @param int $batch_number Number of the batch, starting at 1.
	 */
	public function process_batch( int $batch_number ): void {
		$this->services->scan_runner()->process_batch( $batch_number );
	}

	/**
	 * Registers `wp cualli-links` when WP-CLI is running.
	 */
	private function register_cli_command(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'cualli-links', new LinkMonitorCommand( $this->services ) );
	}
}
