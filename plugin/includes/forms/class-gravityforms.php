<?php
/**
 * Gravity Forms integration. Auto-populates hidden fields from cookies via
 * gform_field_value_* filters and stores attribution as entry meta on
 * submission.
 *
 * @package LeadStream
 */

namespace LeadStream\Forms;

defined( 'ABSPATH' ) || exit;

final class GravityForms {

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

	public static function register(): void {
		if ( ! self::is_active() ) {
			return;
		}

		foreach ( self::FIELD_KEYS as $key ) {
			$resolver = static function () use ( $key ) {
				return self::cookie( $key );
			};
			add_filter( 'gform_field_value_' . $key, $resolver );
			add_filter( 'gform_field_value_ls_' . $key, $resolver );
		}

		add_action( 'gform_after_submission', array( __CLASS__, 'attach_meta' ), 10, 1 );
	}

	public static function is_active(): bool {
		return class_exists( '\GFForms' );
	}

	public static function attach_meta( array $entry ): void {
		if ( ! function_exists( 'gform_update_meta' ) ) {
			return;
		}
		$entry_id = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
		if ( $entry_id <= 0 ) {
			return;
		}
		foreach ( self::FIELD_KEYS as $key ) {
			$value = self::cookie( $key );
			if ( '' !== $value ) {
				gform_update_meta( $entry_id, 'leadstream_' . $key, $value );
			}
		}
	}

	public static function cookie( string $key ): string {
		$name = 'leadstream_' . $key;
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
	}
}
