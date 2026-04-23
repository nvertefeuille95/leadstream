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
		add_filter( 'gform_custom_merge_tags', array( __CLASS__, 'register_merge_tags' ), 10, 1 );
		add_filter( 'gform_replace_merge_tags', array( __CLASS__, 'replace_merge_tags' ), 10, 1 );
		add_filter( 'gform_notification', array( __CLASS__, 'maybe_append_attribution' ), 10, 1 );

		// Inject attribution as hidden fields so every entry contains them, even
		// without admin form edits and without relying on notifications or webhooks.
		add_filter( 'gform_pre_render', array( __CLASS__, 'inject_fields' ) );
		add_filter( 'gform_pre_validation', array( __CLASS__, 'inject_fields' ) );
		add_filter( 'gform_pre_submission_filter', array( __CLASS__, 'inject_fields' ) );
	}

	public static function is_active(): bool {
		return class_exists( '\GFForms' );
	}

	public static function attach_meta( array $entry ): void {
		$entry_id = isset( $entry['id'] ) ? (int) $entry['id'] : 0;

		if ( $entry_id > 0 && function_exists( 'gform_update_meta' ) ) {
			foreach ( self::FIELD_KEYS as $key ) {
				$value = self::cookie( $key );
				if ( '' !== $value ) {
					gform_update_meta( $entry_id, 'leadstream_' . $key, $value );
				}
			}
		}

		\LeadStream\Events::record(
			array(
				'form_source' => 'gravity',
				'form_id'     => isset( $entry['form_id'] ) ? (string) $entry['form_id'] : null,
			)
		);
	}

	public static function cookie( string $key ): string {
		$name = 'leadstream_' . $key;
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
	}

	public static function register_merge_tags( array $merge_tags ): array {
		foreach ( self::FIELD_KEYS as $key ) {
			$merge_tags[] = array(
				'label' => sprintf( 'LeadStream: %s', self::label_for( $key ) ),
				'tag'   => '{leadstream:' . $key . '}',
			);
		}
		return $merge_tags;
	}

	public static function replace_merge_tags( string $text ): string {
		if ( false === strpos( $text, '{leadstream:' ) ) {
			return $text;
		}
		foreach ( self::FIELD_KEYS as $key ) {
			$text = str_replace( '{leadstream:' . $key . '}', self::cookie( $key ), $text );
		}
		return $text;
	}

	public static function maybe_append_attribution( array $notification ): array {
		if ( ! get_option( 'leadstream_gf_auto_append_notifications', false ) ) {
			return $notification;
		}
		$block = self::render_attribution_block();
		if ( '' === $block ) {
			return $notification;
		}
		$notification['message'] = ( $notification['message'] ?? '' ) . $block;
		return $notification;
	}

	public static function render_attribution_block(): string {
		$rows = array();
		foreach ( self::FIELD_KEYS as $key ) {
			$value = self::cookie( $key );
			if ( '' !== $value ) {
				$rows[] = sprintf(
					'<li><strong>%s:</strong> %s</li>',
					esc_html( self::label_for( $key ) ),
					esc_html( $value )
				);
			}
		}
		if ( empty( $rows ) ) {
			return '';
		}
		return '<hr /><h3>' . esc_html__( 'Attribution', 'leadstream' ) . '</h3><ul>' . implode( '', $rows ) . '</ul>';
	}

	/**
	 * Compute the field-prop arrays we want to inject, without any dependency
	 * on GF_Fields so this is easy to unit test.
	 */
	public static function injected_field_props( array $form ): array {
		$max_id = 0;
		if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$fid = is_object( $field ) ? (int) ( $field->id ?? 0 ) : (int) ( $field['id'] ?? 0 );
				if ( $fid > $max_id ) {
					$max_id = $fid;
				}
			}
		}

		$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
		$props   = array();
		foreach ( \LeadStream\Cookies::FIELD_KEYS as $key ) {
			++$max_id;
			$props[] = array(
				'type'         => 'hidden',
				'id'           => $max_id,
				'label'        => 'LeadStream ' . str_replace( '_', ' ', $key ),
				'inputName'    => 'leadstream_' . $key,
				'defaultValue' => '{leadstream:' . $key . '}',
				'formId'       => $form_id,
				'pageNumber'   => 1,
				'adminOnly'    => false,
				'cssClass'     => 'leadstream-injected',
			);
		}
		return $props;
	}

	public static function inject_fields( $form ) {
		if ( ! is_array( $form ) ) {
			return $form;
		}
		if ( ! get_option( 'leadstream_gf_auto_inject', true ) ) {
			return $form;
		}
		if ( ! class_exists( '\GF_Fields' ) ) {
			return $form;
		}

		if ( ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			$form['fields'] = array();
		}

		foreach ( self::injected_field_props( $form ) as $props ) {
			$form['fields'][] = \GF_Fields::create( $props );
		}
		return $form;
	}

	public static function label_for( string $key ): string {
		switch ( $key ) {
			case 'utm_source':
				return __( 'Source', 'leadstream' );
			case 'utm_medium':
				return __( 'Medium', 'leadstream' );
			case 'utm_campaign':
				return __( 'Campaign', 'leadstream' );
			case 'utm_term':
				return __( 'Term', 'leadstream' );
			case 'utm_content':
				return __( 'Content', 'leadstream' );
			case 'click_id':
				return __( 'Click ID', 'leadstream' );
			case 'click_id_type':
				return __( 'Click ID Type', 'leadstream' );
			case 'first_page':
				return __( 'First page', 'leadstream' );
			case 'referrer':
				return __( 'Referrer', 'leadstream' );
		}
		return $key;
	}
}
