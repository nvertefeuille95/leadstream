<?php
/**
 * Backend relay. Fire-and-forget POST of attribution events to the Cloud
 * Run service when the admin has configured a backend URL + license key.
 *
 * Hooks the same gform_after_submission and elementor_pro/forms/new_record
 * paths the rest of the plugin uses, but reads from the events table after
 * the submission has already been recorded so we relay enriched data
 * including visitor_id and the canonical event_id. Asynchronous via
 * non-blocking wp_remote_post timeout=0.01 so form submits never wait on
 * the backend.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Relay {

	public static function register(): void {
		add_action( 'leadstream_event_recorded', array( __CLASS__, 'maybe_relay' ), 10, 1 );
	}

	public static function is_configured(): bool {
		$url = (string) get_option( 'leadstream_backend_url', '' );
		$key = (string) get_option( 'leadstream_license_key', '' );
		return '' !== trim( $url ) && '' !== trim( $key );
	}

	/**
	 * Send an event payload to the backend asynchronously.
	 *
	 * @param array $event Event row from leadstream_events.
	 */
	public static function maybe_relay( array $event ): void {
		if ( ! self::is_configured() ) {
			return;
		}
		if ( empty( $event['event_id'] ) ) {
			return;
		}

		$url = rtrim( (string) get_option( 'leadstream_backend_url', '' ), '/' ) . '/v1/events';
		$key = (string) get_option( 'leadstream_license_key', '' );

		// Whitelist payload keys we send. Avoid leaking IP hash or UA to the
		// backend yet; storage policy can opt those in explicitly.
		$payload = array_filter(
			array(
				'event_id'       => (string) ( $event['event_id'] ?? '' ),
				'visitor_id'     => (string) ( $event['visitor_id'] ?? '' ),
				'utm_source'     => (string) ( $event['utm_source'] ?? '' ),
				'utm_medium'     => (string) ( $event['utm_medium'] ?? '' ),
				'utm_campaign'   => (string) ( $event['utm_campaign'] ?? '' ),
				'utm_term'       => (string) ( $event['utm_term'] ?? '' ),
				'utm_content'    => (string) ( $event['utm_content'] ?? '' ),
				'click_id'       => (string) ( $event['click_id'] ?? '' ),
				'click_id_type'  => (string) ( $event['click_id_type'] ?? '' ),
				'referrer'       => (string) ( $event['referrer'] ?? '' ),
				'landing_page'   => (string) ( $event['landing_page'] ?? '' ),
				'submitted_page' => (string) ( $event['submitted_page'] ?? '' ),
				'form_source'    => (string) ( $event['form_source'] ?? '' ),
				'form_id'        => (string) ( $event['form_id'] ?? '' ),
				'created_at'     => (string) ( $event['created_at'] ?? '' ),
				'site_url'       => home_url(),
				'type'           => 'conversion',
			),
			static function ( $v ) {
				return '' !== $v;
			}
		);

		// Fire-and-forget. timeout=0.01 forces an early return without
		// blocking the form submit pipeline; the request still gets sent.
		wp_remote_post(
			$url,
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'      => wp_json_encode( $payload ),
				'sslverify' => true,
			)
		);
	}
}
