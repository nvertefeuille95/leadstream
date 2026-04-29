<?php
/**
 * REST endpoint for ITP-safe attribution capture. JS calls this on first
 * load instead of writing document.cookie directly. The endpoint runs the
 * Classifier and emits Set-Cookie HTTP headers so cookies are first-party
 * HTTP-set (subject to ITP's lenient inactivity rule rather than the harsh
 * 7-day creation cap that DOM-set cookies hit on Safari).
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Rest {

	public const NAMESPACE = 'leadstream/v1';
	public const ROUTE     = '/capture';

	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_capture' ),
				'permission_callback' => array( __CLASS__, 'check_capture_permission' ),
				'args'                => array(
					'url'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
					'referrer' => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	/**
	 * Nonce-based permission. Visitors are anonymous, so we authenticate via
	 * the WP REST nonce that Assets::js_config localizes. This is not a
	 * privileged endpoint; the nonce just confirms the request originated
	 * from our own page rather than being scripted by a third party.
	 */
	public static function check_capture_permission( $request ): bool {
		$nonce = '';
		if ( is_object( $request ) && method_exists( $request, 'get_header' ) ) {
			$nonce = (string) $request->get_header( 'x_wp_nonce' );
			if ( '' === $nonce ) {
				$nonce = (string) $request->get_header( 'X-WP-Nonce' );
			}
		}
		if ( '' === $nonce ) {
			return false;
		}
		return false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}

	public static function handle_capture( $request ) {
		$url      = '';
		$referrer = '';
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$url      = (string) $request->get_param( 'url' );
			$referrer = (string) $request->get_param( 'referrer' );
		}

		$params       = self::extract_query_params( $url );
		$current_host = self::host_from_url( $url );

		$data = Classifier::classify( $params, $referrer, $current_host );

		// Visitor identity is set even on internal navigation so the cookie
		// lifetime extends for active users. Only the touch record requires a
		// meaningful classification.
		$visitor_id = Visitor::get_or_create();
		Visitor::set_cookie( $visitor_id );

		if ( empty( $data ) ) {
			return rest_ensure_response(
				array(
					'captured'   => false,
					'reason'     => 'internal',
					'visitor_id' => $visitor_id,
				)
			);
		}

		$duration = max( 1, min( 365, (int) get_option( 'leadstream_cookie_duration', 30 ) ) );
		$expires  = time() + ( $duration * DAY_IN_SECONDS );

		foreach ( $data as $key => $value ) {
			self::set_cookie( 'leadstream_' . $key, (string) $value, $expires );
		}

		if ( '' !== $url ) {
			self::set_cookie( 'leadstream_first_page', $url, $expires );
		}
		if ( '' !== $referrer ) {
			self::set_cookie( 'leadstream_referrer', $referrer, $expires );
		}

		// Record the touch (multi-touch attribution journey).
		Touches::record(
			$visitor_id,
			$data,
			array(
				'referrer'   => $referrer,
				'page_url'   => $url,
				'ip_hash'    => self::ip_hash(),
				'user_agent' => self::user_agent(),
			)
		);

		return rest_ensure_response(
			array(
				'captured'   => true,
				'fields'     => array_keys( $data ),
				'visitor_id' => $visitor_id,
			)
		);
	}

	private static function ip_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return '' !== $ip ? hash( 'sha256', $ip ) : '';
	}

	private static function user_agent(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return '' !== $ua ? substr( $ua, 0, 500 ) : '';
	}

	public static function extract_query_params( string $url ): array {
		if ( '' === $url ) {
			return array();
		}
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return array();
		}
		$out = array();
		parse_str( $query, $out );
		$clean = array();
		foreach ( $out as $key => $value ) {
			if ( is_string( $value ) ) {
				$clean[ (string) $key ] = sanitize_text_field( $value );
			}
		}
		return $clean;
	}

	public static function host_from_url( string $url ): string {
		if ( '' === $url ) {
			return '';
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
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
}
