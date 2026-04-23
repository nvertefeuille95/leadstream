<?php
/**
 * Elementor Pro Forms integration. Injects leadstream_* fields into the
 * submission record so notifications, webhooks, and Mailchimp/ActiveCampaign
 * integrations see attribution without any admin field setup.
 *
 * @package LeadStream
 */

namespace LeadStream\Forms;

use LeadStream\Cookies;

defined( 'ABSPATH' ) || exit;

final class ElementorForms {

	public static function register(): void {
		if ( ! self::is_active() ) {
			return;
		}
		add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'inject_attribution' ), 5, 1 );
		add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'record_submission' ), 20, 1 );
	}

	public static function is_active(): bool {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}

	/**
	 * Adds one hidden field per non-empty attribution cookie. Field ids are
	 * prefixed with leadstream_ to avoid colliding with admin-defined fields.
	 *
	 * @param object $record Elementor Pro Form_Record.
	 */
	public static function inject_attribution( $record ): void {
		if ( ! get_option( 'leadstream_elementor_auto_inject', true ) ) {
			return;
		}
		if ( ! is_object( $record ) || ! method_exists( $record, 'set_field' ) ) {
			return;
		}

		foreach ( Cookies::all() as $key => $value ) {
			$record->set_field(
				array(
					'id'        => 'leadstream_' . $key,
					'type'      => 'hidden',
					'title'     => 'LeadStream ' . str_replace( '_', ' ', $key ),
					'value'     => $value,
					'raw_value' => $value,
				)
			);
		}
	}

	public static function record_submission( $record ): void {
		$form_id = '';
		if ( is_object( $record ) && method_exists( $record, 'get_form_settings' ) ) {
			$form_id = (string) $record->get_form_settings( 'id' );
		}
		\LeadStream\Events::record(
			array(
				'form_source' => 'elementor',
				'form_id'     => '' !== $form_id ? $form_id : null,
			)
		);
	}
}
