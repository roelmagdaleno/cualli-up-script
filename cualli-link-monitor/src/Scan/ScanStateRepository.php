<?php
/**
 * Storage of scans.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Scan;

/**
 * Saves the scan in progress and the summary of the last scan in options.
 *
 * Both options use `autoload = false`, so they are not loaded on every request.
 */
final class ScanStateRepository {

	private const STATE_OPTION = 'cualli_link_monitor_scan_state';

	private const SUMMARY_OPTION = 'cualli_link_monitor_last_summary';

	/**
	 * Returns the scan in progress, or null when no scan is running.
	 */
	public function load_state(): ?ScanState {
		$data = get_option( self::STATE_OPTION, array() );

		if ( ! is_array( $data ) || array() === $data ) {
			return null;
		}

		return ScanState::from_array( $data );
	}

	/**
	 * Saves the scan in progress.
	 *
	 * @param ScanState $state Scan in progress.
	 */
	public function save_state( ScanState $state ): void {
		update_option( self::STATE_OPTION, $state->to_array(), false );
	}

	/**
	 * Removes the scan in progress.
	 */
	public function delete_state(): void {
		delete_option( self::STATE_OPTION );
	}

	/**
	 * Returns the summary of the last finished scan, or null when no scan has finished yet.
	 */
	public function load_last_summary(): ?ScanSummary {
		$data = get_option( self::SUMMARY_OPTION, array() );

		if ( ! is_array( $data ) || array() === $data ) {
			return null;
		}

		return ScanSummary::from_array( $data );
	}

	/**
	 * Saves the summary of the last finished scan.
	 *
	 * @param ScanSummary $summary Totals of the scan.
	 */
	public function save_last_summary( ScanSummary $summary ): void {
		update_option( self::SUMMARY_OPTION, $summary->to_array(), false );
	}
}
