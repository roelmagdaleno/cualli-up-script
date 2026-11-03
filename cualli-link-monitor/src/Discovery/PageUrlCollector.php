<?php
/**
 * Lists the pages to scan.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Discovery;

use Cualli\LinkMonitor\Config;

/**
 * Builds the list of page URLs from WordPress data: the home page, the
 * published entries of the configured post types and the term archives of the
 * configured taxonomies.
 */
final class PageUrlCollector {

	/**
	 * Constructor.
	 *
	 * @param Config $config Plugin settings.
	 */
	public function __construct( private readonly Config $config ) {
	}

	/**
	 * Returns the unique URLs of every page to scan, home page first.
	 *
	 * @return string[]
	 */
	public function collect(): array {
		$urls = array_merge( array( home_url( '/' ) ), $this->post_urls(), $this->term_urls() );

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Returns the permalinks of the published entries without password.
	 *
	 * @return string[]
	 */
	private function post_urls(): array {
		$post_ids = get_posts(
			array(
				'post_type'      => $this->config->post_types(),
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return array_filter( array_map( 'get_permalink', $post_ids ) );
	}

	/**
	 * Returns the archive URLs of the terms that have entries.
	 *
	 * @return string[]
	 */
	private function term_urls(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $this->config->taxonomies(),
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$term_links = array_map( 'get_term_link', $terms );

		return array_filter( $term_links, 'is_string' );
	}
}
