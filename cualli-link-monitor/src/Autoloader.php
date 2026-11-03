<?php
/**
 * PSR-4 autoloader for the plugin classes.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor;

/**
 * Maps `Cualli\LinkMonitor\Foo\Bar` to `src/Foo/Bar.php`.
 *
 * The plugin runs on a server without Composer, so this class replaces
 * Composer's autoloader at runtime. `composer.json` declares the same mapping
 * for development tools.
 */
final class Autoloader {

	private const NAMESPACE_PREFIX = 'Cualli\\LinkMonitor\\';

	/**
	 * Registers the autoloader.
	 *
	 * @param string $base_directory Absolute path to the `src` directory.
	 */
	public static function register( string $base_directory ): void {
		spl_autoload_register(
			static function ( string $class_name ) use ( $base_directory ): void {
				self::load( $class_name, $base_directory );
			}
		);
	}

	/**
	 * Requires the file of a plugin class.
	 *
	 * @param string $class_name     Fully qualified class name.
	 * @param string $base_directory Absolute path to the `src` directory.
	 */
	private static function load( string $class_name, string $base_directory ): void {
		if ( ! str_starts_with( $class_name, self::NAMESPACE_PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( self::NAMESPACE_PREFIX ) );
		$file_path      = $base_directory . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file_path ) ) {
			require_once $file_path;
		}
	}
}
