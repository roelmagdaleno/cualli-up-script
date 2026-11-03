<?php
/**
 * Decides which URLs the scanner checks.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Html;

use Cualli\LinkMonitor\Config;

/**
 * Accepts only URLs of this site that are not in an excluded path.
 *
 * External URLs are never checked.
 */
final class InternalUrlPolicy {

	/**
	 * Host of the site, without `www.`.
	 *
	 * @var string
	 */
	private readonly string $site_host;

	/**
	 * Paths that are never checked.
	 *
	 * @var string[]
	 */
	private readonly array $excluded_paths;

	/**
	 * Constructor.
	 *
	 * @param Config $config Plugin settings.
	 */
	public function __construct( Config $config ) {
		$this->site_host      = $this->bare_host( home_url() );
		$this->excluded_paths = $config->excluded_paths();
	}

	/**
	 * Whether the scanner should check the URL.
	 *
	 * @param string $url Absolute URL.
	 */
	public function should_check( string $url ): bool {
		return $this->is_internal( $url ) && ! $this->is_excluded( $url );
	}

	/**
	 * Whether the URL belongs to this site. `www.` is ignored.
	 *
	 * @param string $url Absolute URL.
	 */
	private function is_internal( string $url ): bool {
		return $this->site_host === $this->bare_host( $url );
	}

	/**
	 * Whether the URL path is an excluded path or one of its sub-paths.
	 *
	 * @param string $url Absolute URL.
	 */
	private function is_excluded( string $url ): bool {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		foreach ( $this->excluded_paths as $excluded_path ) {
			if ( $path === $excluded_path || str_starts_with( $path, trailingslashit( $excluded_path ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the lowercase host of a URL without the `www.` prefix.
	 *
	 * @param string $url Absolute URL.
	 */
	private function bare_host( string $url ): string {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		return (string) preg_replace( '/^www\./', '', $host );
	}
}
