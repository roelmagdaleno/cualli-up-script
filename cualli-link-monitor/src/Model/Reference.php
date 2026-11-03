<?php
/**
 * A URL found in a page.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor\Model;

/**
 * An absolute URL and the kind of reference it points to.
 */
final class Reference {

	/**
	 * Constructor.
	 *
	 * @param string        $url  Absolute URL, without fragment.
	 * @param ReferenceType $type Kind of reference.
	 */
	public function __construct(
		public readonly string $url,
		public readonly ReferenceType $type
	) {
	}
}
