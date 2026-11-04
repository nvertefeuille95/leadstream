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

	/**
	 * Decide which attribution cookies a new touch writes. Returns key =>
	 * value pairs, where '' means clear that cookie. An empty array means
	 * keep the existing cookies.
	 *
	 * Once a visitor has attribution, a direct visit never replaces it (last
	 * non-direct click), and the first-click model never replaces it at all.
	 * A real new touch replaces every per-touch field, so a campaign or click
	 * ID from an older touch cannot stick to the new source. first_page is
	 * never included: callers set it once on the first visit.
	 *
	 * @param array  $data     Classifier::classify() output.
	 * @param string $referrer Referrer of the new touch.
	 * @param string $model    Configured attribution model.
	 */
	public static function touch_updates( array $data, string $referrer, string $model ): array {
		if ( '' !== self::get( 'utm_source' ) ) {
			$is_direct = 'direct' === ( $data['utm_source'] ?? '' ) && 'direct' === ( $data['utm_medium'] ?? '' );
			if ( 'first' === $model || $is_direct ) {
				return array();
			}
		}

		$data['referrer'] = $referrer;
		$out              = array();
		foreach ( self::FIELD_KEYS as $key ) {
			if ( 'first_page' === $key ) {
				continue;
			}
			$value = (string) ( $data[ $key ] ?? '' );
			// Skip clearing a cookie that is not set.
			if ( '' === $value && '' === self::get( $key ) ) {
				continue;
			}
			$out[ $key ] = $value;
		}
		return $out;
	}
}
