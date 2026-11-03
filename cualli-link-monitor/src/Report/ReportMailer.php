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
 * Emails the report with `wp_mail()`.
 *
 * By default the email goes out only when the scan finds broken references.
 */
final class ReportMailer {

	/**
	 * Constructor.
	 *
	 * @param Config        $config  Plugin settings.
	 * @param ReportBuilder $builder Writes the report.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly ReportBuilder $builder
	) {
	}

	/**
	 * Sends the report when there is something to report and someone to send it to.
	 *
	 * @param ScanResult $result Result of the scan.
	 *
	 * @return bool Whether an email was sent.
	 */
	public function send( ScanResult $result ): bool {
		$recipients = $this->config->recipients();

		if ( array() === $recipients || ! $this->should_notify( $result ) ) {
			return false;
		}

		$report = $this->builder->build( $result );

		return wp_mail( $recipients, $report->subject, $report->body );
	}

	/**
	 * Whether the scan result is worth an email.
	 *
	 * @param ScanResult $result Result of the scan.
	 */
	private function should_notify( ScanResult $result ): bool {
		return $result->summary->broken_references > 0 || $this->config->notify_when_clean();
	}
}
