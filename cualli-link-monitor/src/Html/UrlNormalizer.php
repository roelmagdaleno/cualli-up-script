<?php
/**
 * Turns raw attribute values into absolute URLs.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Html;

/**
 * Resolves relative URLs against the page URL and drops what cannot be checked.
 */
final class UrlNormalizer {

	private const SUPPORTED_SCHEMES = array( 'http', 'https' );

	/**
	 * Returns the absolute URL without fragment, or an empty string when the
	 * value is not an HTTP(S) URL (anchors, `mailto:`, `tel:`, `javascript:`, `data:`).
	 *
	 * @param string $raw_url  Value of an `href`, `src` or `srcset` entry.
	 * @param string $page_url URL of the page that contains the value.
	 */
	public function normalize( string $raw_url, string $page_url ): string {
		$trimmed_url = trim( $raw_url );

		if ( '' === $trimmed_url || str_starts_with( $trimmed_url, '#' ) ) {
			return '';
		}

		$absolute_url = \WP_Http::make_absolute_url( $trimmed_url, $page_url );
		$scheme       = strtolower( (string) wp_parse_url( $absolute_url, PHP_URL_SCHEME ) );

		if ( ! in_array( $scheme, self::SUPPORTED_SCHEMES, true ) ) {
			return '';
		}

		return strtok( $absolute_url, '#' );
	}
}
