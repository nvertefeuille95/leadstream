<?php
/**
 * Frontend asset enqueueing. Loads capture.js in the head with a localized
 * config object so the script has settings before it runs.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public const HANDLE = 'leadstream-capture';

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ) );
	}

	public static function enqueue_frontend(): void {
		wp_enqueue_script(
			self::HANDLE,
			LEADSTREAM_URL . 'assets/js/capture.js',
			array(),
			LEADSTREAM_VERSION,
			false
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.leadstreamConfig = ' . wp_json_encode( self::js_config() ) . ';',
			'before'
		);
	}

	public static function js_config(): array {
		$beta = self::is_beta_domain();

		return array(
			'cookiePrefix'      => 'leadstream_',
			'cookieDuration'    => (int) get_option( 'leadstream_cookie_duration', 30 ),
			'debug'             => (bool) ( $beta || get_option( 'leadstream_debug', false ) ),
			'subdomainTracking' => (bool) get_option( 'leadstream_subdomain_tracking', false ),
			'requireConsent'    => (bool) get_option( 'leadstream_require_consent', false ),
			'restUrl'           => esc_url_raw( rest_url( Rest::NAMESPACE . Rest::ROUTE ) ),
			'restNonce'         => wp_create_nonce( 'wp_rest' ),
			'universalInject'   => (bool) get_option( 'leadstream_universal_inject', true ),
		);
	}

	public static function is_beta_domain(): bool {
		if ( ! defined( 'LEADSTREAM_BETA_DOMAINS' ) || ! is_array( LEADSTREAM_BETA_DOMAINS ) ) {
			return false;
		}

		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$host = preg_replace( '/^www\./', '', strtolower( $host ) );

		return in_array( $host, LEADSTREAM_BETA_DOMAINS, true );
	}
}
