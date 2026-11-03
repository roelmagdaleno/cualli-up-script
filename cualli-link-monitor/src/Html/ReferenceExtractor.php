<?php
/**
 * Finds the references referenced by a page.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Html;

use Cualli\LinkMonitor\Model\Reference;
use Cualli\LinkMonitor\Model\ReferenceType;

/**
 * Reads the rendered HTML of a page and returns its internal links, images,
 * stylesheets and scripts.
 *
 * The HTML is the page as visitors get it, so links added by Elementor,
 * the header and the footer are included.
 */
final class ReferenceExtractor {

	/**
	 * What to look for: an XPath query, the attribute that holds the URL, and
	 * the kind of reference. To check a new kind of element, add a rule here.
	 */
	private const RULES = array(
		array(
			'query'     => '//a[@href]',
			'attribute' => 'href',
			'type'      => ReferenceType::Link,
		),
		array(
			'query'     => '//img[@src]',
			'attribute' => 'src',
			'type'      => ReferenceType::Image,
		),
		array(
			'query'     => '//img[@srcset]',
			'attribute' => 'srcset',
			'type'      => ReferenceType::Image,
		),
		array(
			'query'     => '//source[@srcset]',
			'attribute' => 'srcset',
			'type'      => ReferenceType::Image,
		),
		array(
			'query'     => '//link[contains(concat(" ", normalize-space(@rel), " "), " stylesheet ")][@href]',
			'attribute' => 'href',
			'type'      => ReferenceType::Stylesheet,
		),
		array(
			'query'     => '//script[@src]',
			'attribute' => 'src',
			'type'      => ReferenceType::Script,
		),
	);

	/**
	 * Constructor.
	 *
	 * @param UrlNormalizer     $normalizer Resolves attribute values to absolute URLs.
	 * @param InternalUrlPolicy $policy     Decides which URLs are checked.
	 */
	public function __construct(
		private readonly UrlNormalizer $normalizer,
		private readonly InternalUrlPolicy $policy
	) {
	}

	/**
	 * Returns the references of the page, one per URL.
	 *
	 * When a URL appears in more than one element, the first rule that finds it
	 * sets its type.
	 *
	 * @param string $html     Rendered HTML of the page.
	 * @param string $page_url URL of the page, used to resolve relative URLs.
	 *
	 * @return array<string, Reference> References keyed by URL.
	 */
	public function extract( string $html, string $page_url ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}

		$xpath      = $this->create_xpath( $html );
		$references = array();

		foreach ( self::RULES as $rule ) {
			$references += $this->extract_rule( $xpath, $rule, $page_url );
		}

		return $references;
	}

	/**
	 * Returns the references that match one rule.
	 *
	 * @param \DOMXPath                                                    $xpath    Query object of the page.
	 * @param array{query: string, attribute: string, type: ReferenceType} $rule     Rule from RULES.
	 * @param string                                                       $page_url URL of the page.
	 *
	 * @return array<string, Reference>
	 */
	private function extract_rule( \DOMXPath $xpath, array $rule, string $page_url ): array {
		$references = array();

		foreach ( $xpath->query( $rule['query'] ) as $element ) {
			$raw_value = $element->getAttribute( $rule['attribute'] );
			$raw_urls  = 'srcset' === $rule['attribute'] ? $this->parse_srcset( $raw_value ) : array( $raw_value );

			$references += $this->to_references( $raw_urls, $rule['type'], $page_url );
		}

		return $references;
	}

	/**
	 * Turns raw URLs into references, dropping the ones that are not checked.
	 *
	 * @param string[]      $raw_urls Raw attribute values.
	 * @param ReferenceType $type     Kind of reference.
	 * @param string        $page_url URL of the page.
	 *
	 * @return array<string, Reference>
	 */
	private function to_references( array $raw_urls, ReferenceType $type, string $page_url ): array {
		$references = array();

		foreach ( $raw_urls as $raw_url ) {
			$url = $this->normalizer->normalize( $raw_url, $page_url );

			if ( '' !== $url && $this->policy->should_check( $url ) ) {
				$references[ $url ] = new Reference( $url, $type );
			}
		}

		return $references;
	}

	/**
	 * Returns the URLs of a `srcset` value, such as "a.jpg 300w, b.jpg 600w".
	 *
	 * @param string $srcset Value of the `srcset` attribute.
	 *
	 * @return string[]
	 */
	private function parse_srcset( string $srcset ): array {
		$candidates = array_filter( array_map( 'trim', explode( ',', $srcset ) ) );

		return array_map( static fn ( string $candidate ): string => strtok( $candidate, " \t\n" ), $candidates );
	}

	/**
	 * Parses the HTML and returns an XPath object to query it.
	 *
	 * @param string $html Rendered HTML of the page.
	 */
	private function create_xpath( string $html ): \DOMXPath {
		$document        = new \DOMDocument();
		$previous_errors = libxml_use_internal_errors( true );

		// The XML declaration makes DOMDocument read the HTML as UTF-8.
		$document->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );

		return new \DOMXPath( $document );
	}
}
