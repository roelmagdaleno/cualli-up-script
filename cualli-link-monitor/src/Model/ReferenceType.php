<?php
/**
 * Kinds of references the scanner checks.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Model;

/**
 * Kind of a checked URL. The case order is the section order of the report.
 */
enum ReferenceType: string {

	case Page       = 'page';
	case Link       = 'link';
	case Image      = 'image';
	case Stylesheet = 'stylesheet';
	case Script     = 'script';

	/**
	 * Plural label used as a section title in the report.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Page       => 'Páginas',
			self::Link       => 'Enlaces internos',
			self::Image      => 'Imágenes',
			self::Stylesheet => 'Hojas de estilo (CSS)',
			self::Script     => 'Scripts (JS)',
		};
	}
}
