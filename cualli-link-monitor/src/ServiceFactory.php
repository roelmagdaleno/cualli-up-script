<?php
/**
 * Builds the plugin object graph.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor;

use Cualli\LinkMonitor\Discovery\PageUrlCollector;
use Cualli\LinkMonitor\Html\InternalUrlPolicy;
use Cualli\LinkMonitor\Html\ReferenceExtractor;
use Cualli\LinkMonitor\Html\UrlNormalizer;
use Cualli\LinkMonitor\Http\HttpClient;
use Cualli\LinkMonitor\Report\ReportBuilder;
use Cualli\LinkMonitor\Report\ReportMailer;
use Cualli\LinkMonitor\Report\ResendClient;
use Cualli\LinkMonitor\Scan\BatchDispatcher;
use Cualli\LinkMonitor\Scan\PageScanner;
use Cualli\LinkMonitor\Scan\ScanRunner;
use Cualli\LinkMonitor\Scan\ScanStateRepository;
use Cualli\LinkMonitor\Schedule\WeeklyScheduler;

/**
 * Composition root: the only place that knows how services are wired.
 *
 * Each service is created once, on first use.
 */
final class ServiceFactory {

	/**
	 * Created services, keyed by class name.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Returns the plugin settings.
	 */
	public function config(): Config {
		return $this->shared( Config::class, static fn (): Config => new Config() );
	}

	/**
	 * Returns the scheduler of the weekly cron event.
	 */
	public function weekly_scheduler(): WeeklyScheduler {
		return $this->shared( WeeklyScheduler::class, fn (): WeeklyScheduler => new WeeklyScheduler( $this->config() ) );
	}

	/**
	 * Returns the storage of the scan in progress and the last summary.
	 */
	public function scan_state_repository(): ScanStateRepository {
		return $this->shared( ScanStateRepository::class, static fn (): ScanStateRepository => new ScanStateRepository() );
	}

	/**
	 * Returns the writer of the report.
	 */
	public function report_builder(): ReportBuilder {
		return $this->shared( ReportBuilder::class, static fn (): ReportBuilder => new ReportBuilder() );
	}

	/**
	 * Returns the scan orchestrator.
	 */
	public function scan_runner(): ScanRunner {
		return $this->shared( ScanRunner::class, fn (): ScanRunner => $this->create_scan_runner() );
	}

	/**
	 * Builds the scan orchestrator and its dependencies.
	 */
	private function create_scan_runner(): ScanRunner {
		$config      = $this->config();
		$http_client = new HttpClient( $config );
		$extractor   = new ReferenceExtractor( new UrlNormalizer(), new InternalUrlPolicy( $config ) );

		return new ScanRunner(
			$config,
			new PageUrlCollector( $config ),
			new PageScanner( $http_client, $extractor ),
			$this->scan_state_repository(),
			new BatchDispatcher(),
			new ReportMailer( $config, $this->report_builder(), new ResendClient( $config ) )
		);
	}

	/**
	 * Returns the stored instance of a service, creating it on first use.
	 *
	 * @param string   $id      Service class name.
	 * @param callable $creator Creates the service.
	 */
	private function shared( string $id, callable $creator ): object {
		if ( ! isset( $this->instances[ $id ] ) ) {
			$this->instances[ $id ] = $creator();
		}

		return $this->instances[ $id ];
	}
}
