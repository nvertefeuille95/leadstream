<?php
/**
 * Reader for leadstream_* cookies. Central place so form integrations agree
 * on the set of fields we care about and the sanitization strategy.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Cookies {

	public const FIELD_KEYS = array(
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'click_id',
		'click_id_type',
		'first_page',
		'referrer',
	);

	public static function get( string $key ): string {
		$name = 'leadstream_' . $key;
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
	}

	public static function all(): array {
		$out = array();
		foreach ( self::FIELD_KEYS as $key ) {
			$value = self::get( $key );
			if ( '' !== $value ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}
}
