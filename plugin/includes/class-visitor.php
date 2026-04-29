<?php
/**
 * Visitor identity. A long-lived UUID cookie that survives across sessions
 * and is the key for joining touches to conversions in multi-touch
 * attribution. Set HTTP-side via the REST capture endpoint and PHP-side
 * Capture for ITP friendliness.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Visitor {

	public const COOKIE         = 'leadstream_visitor';
	public const LIFETIME_DAYS  = 365;
	private const VALID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

	/**
	 * Return the existing visitor ID from the cookie if present and valid,
	 * otherwise generate a new UUID. Caller is responsible for setting the
	 * cookie when a new ID is generated (we cannot do that from a static
	 * helper without coupling to REST/PHP-side flow).
	 *
	 * @param string|null $existing Optional override (used in tests).
	 */
	public static function get_or_create( ?string $existing = null ): string {
		$candidate = null === $existing
			? ( isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '' )
			: $existing;

		if ( '' !== $candidate && self::is_valid( $candidate ) ) {
			return $candidate;
		}

		return Events::uuid();
	}

	public static function is_valid( string $candidate ): bool {
		return 1 === preg_match( self::VALID_PATTERN, $candidate );
	}

	public static function set_cookie( string $visitor_id, ?int $expires = null ): void {
		if ( headers_sent() ) {
			return;
		}
		if ( ! self::is_valid( $visitor_id ) ) {
			return;
		}
		if ( null === $expires ) {
			$expires = time() + ( self::LIFETIME_DAYS * DAY_IN_SECONDS );
		}
		setcookie(
			self::COOKIE,
			$visitor_id,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'secure'   => function_exists( 'is_ssl' ) && is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $visitor_id;
	}

	public static function current(): string {
		if ( ! isset( $_COOKIE[ self::COOKIE ] ) ) {
			return '';
		}
		$value = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		return self::is_valid( $value ) ? $value : '';
	}
}
