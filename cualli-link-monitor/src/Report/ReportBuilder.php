<?php
/**
 * Writes the report of a scan.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Report;

use Cualli\LinkMonitor\Model\BrokenReference;
use Cualli\LinkMonitor\Model\ReferenceType;
use Cualli\LinkMonitor\Scan\ScanResult;
use Cualli\LinkMonitor\Scan\ScanSummary;

/**
 * Builds the plain-text report: the totals, then one section per reference
 * type with each broken URL and the pages that use it.
 */
final class ReportBuilder {

	/**
	 * Pages listed per broken reference. The rest are counted, not listed.
	 */
	private const MAX_LISTED_PAGES = 10;

	/**
	 * Builds the report.
	 *
	 * @param ScanResult $result Result of the scan.
	 */
	public function build( ScanResult $result ): Report {
		$sections = array_map(
			fn ( ReferenceType $type ): string => $this->type_section( $type, $result->broken_references ),
			ReferenceType::cases()
		);

		$body = implode( "\n\n", array_filter( array_merge( array( $this->summary_section( $result->summary ) ), $sections ) ) );

		return new Report( $this->subject( $result->summary ), $body . "\n" );
	}

	/**
	 * Email subject with the number of broken references.
	 *
	 * @param ScanSummary $summary Totals of the scan.
	 */
	private function subject( ScanSummary $summary ): string {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		if ( 0 === $summary->broken_references ) {
			return sprintf( '[%s] Escaneo de enlaces sin errores', $site_name );
		}

		return sprintf( '[%s] %d recursos rotos encontrados', $site_name, $summary->broken_references );
	}

	/**
	 * Totals of the scan.
	 *
	 * @param ScanSummary $summary Totals of the scan.
	 */
	private function summary_section( ScanSummary $summary ): string {
		$lines = array(
			'Escaneo semanal de enlaces de ' . home_url( '/' ),
			'',
			'Inicio: ' . wp_date( 'Y-m-d H:i', $summary->started_at ),
			'Duración: ' . human_time_diff( $summary->started_at, $summary->finished_at ),
			'Páginas escaneadas: ' . $summary->scanned_pages,
			'URLs revisadas: ' . $summary->checked_urls,
			'Recursos rotos: ' . $summary->broken_references,
		);

		return implode( "\n", $lines );
	}

	/**
	 * Section of one reference type. Empty when the type has no broken references.
	 *
	 * @param ReferenceType     $type             Type of the section.
	 * @param BrokenReference[] $broken_references All broken references.
	 */
	private function type_section( ReferenceType $type, array $broken_references ): string {
		$of_type = array_filter(
			$broken_references,
			static fn ( BrokenReference $broken ): bool => $type === $broken->reference->type
		);

		if ( array() === $of_type ) {
			return '';
		}

		$title   = sprintf( '== %s (%d) ==', $type->label(), count( $of_type ) );
		$entries = array_map( array( $this, 'reference_entry' ), $of_type );

		return $title . "\n\n" . implode( "\n\n", $entries );
	}

	/**
	 * One broken reference: URL, failure and the pages that use it.
	 *
	 * @param BrokenReference $broken Broken reference.
	 */
	private function reference_entry( BrokenReference $broken ): string {
		$lines = array(
			$broken->reference->url,
			'  Resultado: ' . $broken->result_text,
		);

		return implode( "\n", array_merge( $lines, $this->found_on_lines( $broken->found_on ) ) );
	}

	/**
	 * Lines that list the pages that use a reference.
	 *
	 * @param string[] $page_urls URLs of the pages.
	 *
	 * @return string[]
	 */
	private function found_on_lines( array $page_urls ): array {
		if ( array() === $page_urls ) {
			return array();
		}

		$listed_pages = array_slice( $page_urls, 0, self::MAX_LISTED_PAGES );
		$hidden_count = count( $page_urls ) - count( $listed_pages );
		$lines        = array_merge(
			array( '  Aparece en:' ),
			array_map( static fn ( string $page_url ): string => '  - ' . $page_url, $listed_pages )
		);

		if ( $hidden_count > 0 ) {
			$lines[] = sprintf( '  - … y %d páginas más', $hidden_count );
		}

		return $lines;
	}
}
