<?php
/**
 * Sends the report of a scan.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Report;

use Cualli\LinkMonitor\Config;
use Cualli\LinkMonitor\Scan\ScanResult;

/**
 * Emails the report through Resend, and makes a failed delivery visible.
 *
 * The email goes out after every scan, also when it finds no broken references.
 */
final class ReportMailer {

	/**
	 * Constructor.
	 *
	 * @param Config        $config  Plugin settings.
	 * @param ReportBuilder $builder Writes the report.
	 * @param ResendClient  $client  Sends the email.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly ReportBuilder $builder,
		private readonly ResendClient $client
	) {
	}

	/**
	 * Sends the report when there is someone to send it to.
	 *
	 * @param ScanResult $result Result of the scan.
	 *
	 * @return bool Whether Resend accepted an email.
	 */
	public function send( ScanResult $result ): bool {
		$recipients = $this->config->recipients();

		if ( array() === $recipients ) {
			return false;
		}

		$email_id = $this->client->send( $this->builder->build( $result ), $recipients );

		if ( is_wp_error( $email_id ) ) {
			$this->report_failure( $email_id->get_error_message() );
			return false;
		}

		do_action( 'cualli_link_monitor_report_sent', $email_id );

		return true;
	}

	/**
	 * Logs a failed delivery, so that a failure during a cron scan is not lost.
	 *
	 * @param string $message Reason of the failure.
	 */
	private function report_failure( string $message ): void {
		error_log( 'Cualli Link Monitor: no se pudo enviar el reporte. ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron has no other output.

		do_action( 'cualli_link_monitor_report_failed', $message );
	}
}
