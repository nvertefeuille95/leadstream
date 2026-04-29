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
		// Inject at process time (after validation, BEFORE form actions run).
		// Adds fields to the record array. Useful for custom actions that
		// iterate $record->get('fields'); does NOT affect the Email action's
		// body or the Submissions UI, both of which read from the form's
		// defined field list rather than the runtime record.
		add_action( 'elementor_pro/forms/process', array( __CLASS__, 'inject_attribution' ), 5, 1 );

		// record_submission writes to our own events table and queues ad
		// platform uploads. Runs after actions complete.
		add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'record_submission' ), 20, 1 );

		// Append attribution to Elementor notification email bodies. This is
		// how attribution reaches CRMs that ingest leads via email parsing
		// (LeadSimple, Follow Up Boss). Opt-in because it affects every
		// Elementor email on the site, including user auto-responders if
		// configured.
		add_filter( 'elementor_pro/forms/wp_mail_message', array( __CLASS__, 'append_attribution_to_email' ) );
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

		self::maybe_enqueue_google_ads_upload( $record );
	}

	private static function maybe_enqueue_google_ads_upload( $record ): void {
		if ( ! get_option( 'leadstream_gads_enabled', false ) ) {
			return;
		}
		if ( ! \LeadStream\Platforms\GoogleAds::is_configured() ) {
			return;
		}

		$click_id      = \LeadStream\Cookies::get( 'click_id' );
		$click_id_type = \LeadStream\Cookies::get( 'click_id_type' );
		if ( '' === $click_id || 'gclid' !== $click_id_type ) {
			return;
		}

		$email      = self::extract_email_from_record( $record );
		$email_hash = '' !== $email ? \LeadStream\Uploads::hash_email( $email ) : '';
		$event_id   = \LeadStream\Events::uuid();

		\LeadStream\Uploads::enqueue(
			array(
				'event_id'      => $event_id,
				'platform'      => \LeadStream\Platforms\GoogleAds::PLATFORM,
				'click_id'      => $click_id,
				'click_id_type' => $click_id_type,
				'email_hash'    => $email_hash,
			)
		);
	}

	/**
	 * Append a parseable attribution block to Elementor notification emails.
	 *
	 * Format keeps each field on its own "Label: value" line so CRM email
	 * parsers (LeadSimple, Follow Up Boss, regex-based pipelines) can pick
	 * out individual values without HTML parsing. HTML body emails get a
	 * preceded <br><br> separator; plain-text bodies just get newlines.
	 */
	public static function append_attribution_to_email( $email_text ) {
		if ( ! is_string( $email_text ) ) {
			return $email_text;
		}
		if ( ! get_option( 'leadstream_elementor_email_append', false ) ) {
			return $email_text;
		}

		$cookies = \LeadStream\Cookies::all();
		if ( empty( $cookies ) ) {
			return $email_text;
		}

		$labels = array(
			'utm_source'    => 'Source',
			'utm_medium'    => 'Medium',
			'utm_campaign'  => 'Campaign',
			'utm_term'      => 'Term',
			'utm_content'   => 'Content',
			'click_id'      => 'Click ID',
			'click_id_type' => 'Click ID Type',
			'first_page'    => 'First Page',
			'referrer'      => 'Referrer',
		);

		$is_html    = false !== stripos( $email_text, '<html' ) || false !== stripos( $email_text, '<br' );
		$line_break = $is_html ? '<br />' : "\n";
		$separator  = $is_html ? '<br /><br />' : "\n\n";

		$lines   = array( '--- LeadStream Attribution ---' );
		$lines[] = 'gclid: ' . ( ( ( $cookies['click_id_type'] ?? '' ) === 'gclid' ) ? ( $cookies['click_id'] ?? '' ) : '' );
		foreach ( $labels as $key => $label ) {
			if ( ! empty( $cookies[ $key ] ) ) {
				$lines[] = $label . ': ' . $cookies[ $key ];
			}
		}

		return $email_text . $separator . implode( $line_break, $lines );
	}

	public static function extract_email_from_record( $record ): string {
		if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) ) {
			return '';
		}
		$fields = $record->get( 'fields' );
		if ( ! is_array( $fields ) ) {
			return '';
		}
		foreach ( $fields as $field ) {
			$value = '';
			if ( is_array( $field ) && isset( $field['value'] ) ) {
				$value = (string) $field['value'];
			} elseif ( is_object( $field ) && isset( $field->value ) ) {
				$value = (string) $field->value;
			}
			$value = trim( $value );
			if ( '' !== $value && filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
				return $value;
			}
		}
		return '';
	}
}
