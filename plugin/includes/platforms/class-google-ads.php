<?php
/**
 * Google Ads API client (REST, no SDK). Handles OAuth refresh-token exchange
 * and the uploadClickConversions call. Called by the cron Worker, not
 * directly by form submissions.
 *
 * @package LeadStream
 */

namespace LeadStream\Platforms;

defined( 'ABSPATH' ) || exit;

final class GoogleAds {

	public const PLATFORM               = 'google_ads';
	public const DEFAULT_API_VERSION    = 'v20';
	public const API_BASE               = 'https://googleads.googleapis.com/';
	public const TOKEN_URL              = 'https://oauth2.googleapis.com/token';
	public const ACCESS_TOKEN_TRANSIENT = 'leadstream_gads_access_token';

	public static function is_configured(): bool {
		$c = self::creds();
		return '' !== $c['developer_token']
			&& '' !== $c['refresh_token']
			&& '' !== $c['client_id']
			&& '' !== $c['client_secret']
			&& '' !== $c['customer_id']
			&& '' !== $c['conversion_action'];
	}

	/**
	 * Attempt a single click conversion upload.
	 *
	 * @param array $row The uploads-table row.
	 * @return array ['success' => bool, 'response' => string, 'transient' => bool]
	 */
	public static function upload_click_conversion( array $row ): array {
		if ( ! self::is_configured() ) {
			return array(
				'success'   => false,
				'response'  => 'Google Ads not configured.',
				'transient' => false,
			);
		}

		$click_id = isset( $row['click_id'] ) ? (string) $row['click_id'] : '';
		$event_id = isset( $row['event_id'] ) ? (string) $row['event_id'] : '';
		if ( '' === $click_id || '' === $event_id ) {
			return array(
				'success'   => false,
				'response'  => 'Missing click_id or event_id on uploads row.',
				'transient' => false,
			);
		}

		$access = self::get_access_token();
		if ( '' === $access ) {
			return array(
				'success'   => false,
				'response'  => 'Failed to obtain OAuth access token.',
				'transient' => true,
			);
		}

		$creds   = self::creds();
		$payload = self::build_payload( $row, $creds );
		$url     = self::API_BASE . $creds['api_version'] . '/customers/' . rawurlencode( $creds['customer_id'] ) . ':uploadClickConversions';

		$headers = array(
			'Authorization'   => 'Bearer ' . $access,
			'developer-token' => $creds['developer_token'],
			'Content-Type'    => 'application/json',
		);
		if ( '' !== $creds['login_customer_id'] ) {
			$headers['login-customer-id'] = $creds['login_customer_id'];
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success'   => false,
				'response'  => 'HTTP error: ' . $response->get_error_message(),
				'transient' => true,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code >= 200 && $code < 300 ) {
			$decoded = json_decode( $body, true );
			if ( is_array( $decoded ) && isset( $decoded['partialFailureError'] ) ) {
				return array(
					'success'   => false,
					'response'  => 'Partial failure: ' . wp_json_encode( $decoded['partialFailureError'] ),
					'transient' => false,
				);
			}
			return array(
				'success'   => true,
				'response'  => $body,
				'transient' => false,
			);
		}

		// 5xx and 429 are transient; 4xx (except 429) are permanent.
		$transient = ( $code >= 500 ) || 429 === $code;
		return array(
			'success'   => false,
			'response'  => 'HTTP ' . $code . ': ' . $body,
			'transient' => $transient,
		);
	}

	private static function get_access_token(): string {
		$cached = get_transient( self::ACCESS_TOKEN_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$creds = self::creds();
		if ( '' === $creds['refresh_token'] || '' === $creds['client_id'] || '' === $creds['client_secret'] ) {
			return '';
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body'    => array(
					'client_id'     => $creds['client_id'],
					'client_secret' => $creds['client_secret'],
					'refresh_token' => $creds['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
			return '';
		}

		$token   = (string) $data['access_token'];
		$expires = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 0;
		if ( $expires > 120 ) {
			set_transient( self::ACCESS_TOKEN_TRANSIENT, $token, $expires - 60 );
		}

		return $token;
	}

	public static function build_payload( array $row, array $creds ): array {
		$conversion_value = isset( $row['conversion_value'] ) && '' !== $row['conversion_value']
			? (float) $row['conversion_value']
			: (float) $creds['default_value'];

		$conversion = array(
			'conversionAction'   => $creds['conversion_action'],
			'gclid'              => (string) $row['click_id'],
			'conversionDateTime' => gmdate( 'Y-m-d H:i:sO', time() ),
			'conversionValue'    => $conversion_value,
			'currencyCode'       => $creds['currency'],
			'orderId'            => (string) $row['event_id'],
		);

		if ( ! empty( $row['email_hash'] ) ) {
			$conversion['userIdentifiers'] = array(
				array(
					'hashedEmail'          => (string) $row['email_hash'],
					'userIdentifierSource' => 'FIRST_PARTY',
				),
			);
		}

		return array(
			'conversions'    => array( $conversion ),
			'partialFailure' => true,
			'validateOnly'   => false,
		);
	}

	public static function creds(): array {
		$api_version = (string) get_option( 'leadstream_gads_api_version', self::DEFAULT_API_VERSION );
		if ( '' === $api_version || ! preg_match( '/^v\d+$/', $api_version ) ) {
			$api_version = self::DEFAULT_API_VERSION;
		}
		return array(
			'developer_token'   => (string) get_option( 'leadstream_gads_developer_token', '' ),
			'refresh_token'     => (string) get_option( 'leadstream_gads_refresh_token', '' ),
			'client_id'         => (string) get_option( 'leadstream_gads_client_id', '' ),
			'client_secret'     => (string) get_option( 'leadstream_gads_client_secret', '' ),
			'customer_id'       => (string) get_option( 'leadstream_gads_customer_id', '' ),
			'login_customer_id' => (string) get_option( 'leadstream_gads_login_customer_id', '' ),
			'conversion_action' => (string) get_option( 'leadstream_gads_conversion_action', '' ),
			'default_value'     => (float) get_option( 'leadstream_gads_default_value', 0 ),
			'currency'          => (string) get_option( 'leadstream_gads_currency', 'USD' ),
			'api_version'       => $api_version,
		);
	}
}
