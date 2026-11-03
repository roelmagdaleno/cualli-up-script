<?php
/**
 * WP-CLI command of the plugin.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Cli;

use Cualli\LinkMonitor\ServiceFactory;

/**
 * Escanea los enlaces internos, imágenes, CSS y JS del sitio.
 *
 * ## EXAMPLES
 *
 *     # Escanear ahora y mostrar el resultado sin enviar correo.
 *     $ wp cualli-links scan --no-email
 *
 *     # Ver cuándo corre el próximo escaneo semanal.
 *     $ wp cualli-links status
 */
final class LinkMonitorCommand {

	/**
	 * Constructor.
	 *
	 * @param ServiceFactory $services Creates the plugin services.
	 */
	public function __construct( private readonly ServiceFactory $services ) {
	}

	/**
	 * Escanea todas las páginas ahora mismo, en este proceso.
	 *
	 * No interfiere con un escaneo de cron en curso.
	 *
	 * ## OPTIONS
	 *
	 * [--[no-]email]
	 * : Envía el correo del reporte. Usa --no-email para solo ver el resultado.
	 * ---
	 * default: true
	 * ---
	 *
	 * [--notify-when-clean]
	 * : Envía el correo aunque no haya recursos rotos.
	 *
	 * @param string[]             $args       Positional arguments (unused).
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function scan( array $args, array $assoc_args ): void {
		$this->apply_email_flags( $assoc_args );

		$result = $this->services->scan_runner()->run_now(
			static function ( string $page_url ): void {
				\WP_CLI::log( 'Escaneando ' . $page_url );
			}
		);

		\WP_CLI::log( '' );
		\WP_CLI::log( $this->services->report_builder()->build( $result )->body );

		$this->print_outcome( $result->summary->broken_references );
	}

	/**
	 * Muestra el próximo escaneo programado y el resumen del último.
	 */
	public function status(): void {
		$next_run     = $this->services->weekly_scheduler()->next_run();
		$last_summary = $this->services->scan_state_repository()->load_last_summary();

		\WP_CLI::log( 'Próximo escaneo: ' . ( false === $next_run ? 'no programado' : wp_date( 'Y-m-d H:i T', $next_run ) ) );

		if ( null === $last_summary ) {
			\WP_CLI::log( 'Último escaneo: ninguno todavía' );
			return;
		}

		\WP_CLI::log( 'Último escaneo: ' . wp_date( 'Y-m-d H:i T', $last_summary->finished_at ) );
		\WP_CLI::log( 'Páginas escaneadas: ' . $last_summary->scanned_pages );
		\WP_CLI::log( 'URLs revisadas: ' . $last_summary->checked_urls );
		\WP_CLI::log( 'Recursos rotos: ' . $last_summary->broken_references );
	}

	/**
	 * Vuelve a programar el escaneo semanal con el día y la hora configurados.
	 */
	public function reschedule(): void {
		$next_run = $this->services->weekly_scheduler()->reschedule();

		\WP_CLI::success( 'Próximo escaneo: ' . wp_date( 'Y-m-d H:i T', $next_run ) );
	}

	/**
	 * Applies `--no-email` and `--notify-when-clean` through the plugin filters.
	 *
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	private function apply_email_flags( array $assoc_args ): void {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'email', true ) ) {
			add_filter( 'cualli_link_monitor_recipients', '__return_empty_array' );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'notify-when-clean', false ) ) {
			add_filter( 'cualli_link_monitor_notify_when_clean', '__return_true' );
		}
	}

	/**
	 * Prints the final line of the scan.
	 *
	 * @param int $broken_count Number of broken references.
	 */
	private function print_outcome( int $broken_count ): void {
		if ( 0 === $broken_count ) {
			\WP_CLI::success( 'Sin recursos rotos.' );
			return;
		}

		\WP_CLI::warning( sprintf( '%d recursos rotos encontrados.', $broken_count ) );
	}
}
