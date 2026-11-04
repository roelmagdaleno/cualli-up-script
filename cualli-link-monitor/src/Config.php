<?php
/**
 * Plugin settings.
 *
 * @package Cualli\LinkMonitor
 */

namespace Cualli\LinkMonitor;

/**
 * Holds every setting of the plugin in one place.
 *
 * There is no settings screen. Each value has a default here and a filter to
 * change it from another plugin or from `wp-config.php`.
 */
final class Config {

	public const WEEKLY_SCAN_HOOK = 'cualli_link_monitor_weekly_scan';

	public const BATCH_HOOK = 'cualli_link_monitor_process_batch';

	private const DEFAULT_RECIPIENTS = array( 'roelmagdaleno@gmail.com' );

	private const DEFAULT_FROM = 'Cualli Link Monitor <sistemas@cualli.mx>';

	private const RESEND_API_KEY_CONSTANT = 'CUALLI_LINK_MONITOR_RESEND_API_KEY';

	private const DEFAULT_POST_TYPES = array( 'page', 'post' );

	private const DEFAULT_TAXONOMIES = array( 'category', 'post_tag' );

	private const DEFAULT_EXCLUDED_PATHS = array(
		'/wp-admin',
		'/wp-login.php',
		'/wp-json',
		'/xmlrpc.php',
		'/feed',
		'/cdn-cgi',
		// Author archives are blocked on purpose for security, so they always return 404.
		'/author',
	);

	/**
	 * Email addresses that receive the report.
	 *
	 * @return string[]
	 */
	public function recipients(): array {
		return (array) apply_filters( 'cualli_link_monitor_recipients', self::DEFAULT_RECIPIENTS );
	}

	/**
	 * Sender of the report.
	 *
	 * The default works without a verified domain, but Resend then delivers
	 * only to the email address of the Resend account.
	 */
	public function email_from(): string {
		return (string) apply_filters( 'cualli_link_monitor_email_from', self::DEFAULT_FROM );
	}

	/**
	 * Resend API key, from a constant in `wp-config.php` so it stays out of the repository.
	 */
	public function resend_api_key(): string {
		$api_key = defined( self::RESEND_API_KEY_CONSTANT ) ? constant( self::RESEND_API_KEY_CONSTANT ) : '';

		return (string) apply_filters( 'cualli_link_monitor_resend_api_key', $api_key );
	}

	/**
	 * Whether to send the report when the scan finds no broken references.
	 */
	public function notify_when_clean(): bool {
		return (bool) apply_filters( 'cualli_link_monitor_notify_when_clean', false );
	}

	/**
	 * Day of the week of the weekly scan, in English (as `strtotime()` reads it).
	 */
	public function scan_weekday(): string {
		return (string) apply_filters( 'cualli_link_monitor_scan_weekday', 'monday' );
	}

	/**
	 * Time of the weekly scan, `H:i` in the site timezone.
	 */
	public function scan_time(): string {
		return (string) apply_filters( 'cualli_link_monitor_scan_time', '03:00' );
	}

	/**
	 * Seconds that one cron batch may spend scanning pages.
	 *
	 * Kept well under the PHP `max_execution_time` (300 s on Kinsta).
	 */
	public function batch_time_budget(): int {
		return (int) apply_filters( 'cualli_link_monitor_batch_time_budget', 120 );
	}

	/**
	 * Timeout of each HTTP request, in seconds.
	 */
	public function request_timeout(): int {
		return (int) apply_filters( 'cualli_link_monitor_request_timeout', 15 );
	}

	/**
	 * Post types whose published entries are scanned.
	 *
	 * @return string[]
	 */
	public function post_types(): array {
		return (array) apply_filters( 'cualli_link_monitor_post_types', self::DEFAULT_POST_TYPES );
	}

	/**
	 * Taxonomies whose term archives are scanned.
	 *
	 * @return string[]
	 */
	public function taxonomies(): array {
		return (array) apply_filters( 'cualli_link_monitor_taxonomies', self::DEFAULT_TAXONOMIES );
	}

	/**
	 * URL paths that are never checked. A path also excludes its sub-paths.
	 *
	 * Includes the custom login slug of WPS Hide Login when it is set.
	 *
	 * @return string[]
	 */
	public function excluded_paths(): array {
		$hidden_login_slug = (string) get_option( 'whl_page', '' );
		$hidden_login_path = '' === $hidden_login_slug ? array() : array( '/' . trim( $hidden_login_slug, '/' ) );
		$excluded_paths    = array_merge( self::DEFAULT_EXCLUDED_PATHS, $hidden_login_path );

		return (array) apply_filters( 'cualli_link_monitor_excluded_paths', $excluded_paths );
	}
}
