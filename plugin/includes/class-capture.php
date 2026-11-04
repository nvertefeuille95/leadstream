<?php
/**
 * Server-side attribution capture. Runs early in the WP init chain and sets
 * the leadstream_* cookies for visitors without JS. JS-side capture overrides
 * these on the same page if present.
 *
 * Off by default: page caches such as WP Engine's store the Set-Cookie
 * headers with the page and replay them to every visitor, handing everyone
 * the same visitor ID and a "direct" source. The REST endpoint the JS calls
 * is never cached and does the same work. Enable with the
 * leadstream_server_capture filter only on sites without a page cache.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Capture {

	public const SESSION_COOKIE = 'leadstream_session_captured';

	public static function register(): void {
		if ( ! apply_filters( 'leadstream_server_capture', false ) ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'maybe_capture' ), 5 );
	}

	public static function maybe_capture(): void {
		if ( ! self::should_run() ) {
			return;
		}

		$params       = self::collect_params();
		$referrer     = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$current_host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

		$data = Classifier::classify( $params, $referrer, $current_host );

		// Refresh visitor cookie even when classify returns empty (internal nav)
		// so the long-lived ID does not lapse for active users.
		$visitor_id = Visitor::get_or_create();
		Visitor::set_cookie( $visitor_id );

		if ( empty( $data ) ) {
			return;
		}

		$current_url = self::current_url();

		// Touch is recorded on every meaningful visit (multi-touch journal),
		// independent of whether we update attribution cookies below.
		Touches::record(
			$visitor_id,
			$data,
			array(
				'referrer'   => $referrer,
				'page_url'   => $current_url,
				'ip_hash'    => self::ip_hash(),
				'user_agent' => self::user_agent(),
			)
		);

		$model   = (string) get_option( 'leadstream_attribution_model', 'last' );
		$updates = Cookies::touch_updates( $data, $referrer, $model );

		if ( ! empty( $updates ) ) {
			$duration = max( 1, min( 365, (int) get_option( 'leadstream_cookie_duration', 30 ) ) );
			$expires  = time() + ( $duration * DAY_IN_SECONDS );

			// An empty value clears a field left over from an older touch.
			foreach ( $updates as $key => $value ) {
				self::set_cookie( 'leadstream_' . $key, $value, '' === $value ? 1 : $expires );
			}

			// first_page is immutable: only set on the truly first visit.
			if ( '' !== $current_url && empty( $_COOKIE['leadstream_first_page'] ) ) {
				self::set_cookie( 'leadstream_first_page', $current_url, $expires );
			}
		}

		self::set_cookie( self::SESSION_COOKIE, '1', 0 );
	}

	private static function ip_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return '' !== $ip ? hash( 'sha256', $ip ) : '';
	}

	private static function user_agent(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return '' !== $ua ? substr( $ua, 0, 500 ) : '';
	}

	private static function should_run(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( ! empty( $_COOKIE[ self::SESSION_COOKIE ] ) ) {
			return false;
		}
		// Consent enforcement lives in JS where browser-side APIs are available.
		if ( get_option( 'leadstream_require_consent', false ) ) {
			return false;
		}
		// Note: we no longer skip when leadstream_utm_source already exists.
		// We need to record touches on every meaningful visit. The cookie-
		// update logic in maybe_capture decides whether to overwrite cookies
		// based on the attribution model setting.
		return true;
	}

	private static function collect_params(): array {
		$allowed = array(
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_term',
			'utm_content',
			'gclid',
			'dclid',
			'gbraid',
			'wbraid',
			'gad_source',
			'msclkid',
			'fbclid',
			'ttclid',
			'twclid',
			'li_fat_id',
			'ScCid',
			'epik',
		);

		$out = array();
		foreach ( $allowed as $key ) {
			if ( isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$out[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		return $out;
	}

	private static function set_cookie( string $name, string $value, int $expires ): void {
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			$name,
			$value,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ $name ] = $value;
	}

	private static function current_url(): string {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $host ) {
			return '';
		}
		return ( is_ssl() ? 'https' : 'http' ) . '://' . $host . $uri;
	}
}
