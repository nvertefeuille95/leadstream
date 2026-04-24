<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Platforms\GoogleAds;
use PHPUnit\Framework\TestCase;
use WP_Mock;

final class GoogleAdsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_build_payload_shape(): void {
		$row = array(
			'event_id'         => 'evt-123',
			'click_id'         => 'gclid-abc',
			'click_id_type'    => 'gclid',
			'email_hash'       => str_repeat( 'a', 64 ),
			'conversion_value' => null,
		);
		$creds = array(
			'conversion_action' => 'customers/1/conversionActions/2',
			'default_value'     => 100.0,
			'currency'          => 'USD',
		);

		$payload = GoogleAds::build_payload( $row, $creds );

		$this->assertArrayHasKey( 'conversions', $payload );
		$this->assertTrue( $payload['partialFailure'] );
		$this->assertFalse( $payload['validateOnly'] );

		$conv = $payload['conversions'][0];
		$this->assertSame( 'customers/1/conversionActions/2', $conv['conversionAction'] );
		$this->assertSame( 'gclid-abc', $conv['gclid'] );
		$this->assertSame( 'evt-123', $conv['orderId'] );
		$this->assertSame( 100.0, $conv['conversionValue'] );
		$this->assertSame( 'USD', $conv['currencyCode'] );
		$this->assertSame( str_repeat( 'a', 64 ), $conv['userIdentifiers'][0]['hashedEmail'] );
		$this->assertSame( 'FIRST_PARTY', $conv['userIdentifiers'][0]['userIdentifierSource'] );
	}

	public function test_build_payload_uses_row_value_over_default(): void {
		$row   = array(
			'event_id'         => 'evt-1',
			'click_id'         => 'g',
			'conversion_value' => 250.50,
		);
		$creds = array(
			'conversion_action' => 'x',
			'default_value'     => 100.0,
			'currency'          => 'USD',
		);

		$payload = GoogleAds::build_payload( $row, $creds );
		$this->assertSame( 250.50, $payload['conversions'][0]['conversionValue'] );
	}

	public function test_build_payload_omits_identifiers_when_no_email_hash(): void {
		$row   = array(
			'event_id' => 'evt-1',
			'click_id' => 'g',
		);
		$creds = array(
			'conversion_action' => 'x',
			'default_value'     => 0.0,
			'currency'          => 'USD',
		);

		$payload = GoogleAds::build_payload( $row, $creds );
		$this->assertArrayNotHasKey( 'userIdentifiers', $payload['conversions'][0] );
	}

	public function test_is_configured_false_when_any_cred_empty(): void {
		WP_Mock::userFunction( 'get_option' )->andReturn( '' );
		$this->assertFalse( GoogleAds::is_configured() );
	}

	public function test_is_configured_true_when_all_required_present(): void {
		WP_Mock::userFunction( 'get_option' )->andReturnUsing(
			static function ( $key, $default = false ) {
				$map = array(
					'leadstream_gads_developer_token'   => 'dev-token',
					'leadstream_gads_refresh_token'     => 'refresh-token',
					'leadstream_gads_client_id'         => 'client-id',
					'leadstream_gads_client_secret'     => 'client-secret',
					'leadstream_gads_customer_id'       => '1234567890',
					'leadstream_gads_login_customer_id' => '',
					'leadstream_gads_conversion_action' => 'customers/1/conversionActions/2',
					'leadstream_gads_default_value'     => 100,
					'leadstream_gads_currency'          => 'USD',
					'leadstream_gads_api_version'       => 'v20',
				);
				return $map[ $key ] ?? $default;
			}
		);
		$this->assertTrue( GoogleAds::is_configured() );
	}

	public function test_creds_falls_back_to_default_api_version_when_invalid(): void {
		WP_Mock::userFunction( 'get_option' )->andReturnUsing(
			static function ( $key, $default = false ) {
				if ( 'leadstream_gads_api_version' === $key ) {
					return 'not-a-version';
				}
				return $default;
			}
		);
		$creds = GoogleAds::creds();
		$this->assertSame( GoogleAds::DEFAULT_API_VERSION, $creds['api_version'] );
	}

	public function test_creds_accepts_valid_api_version(): void {
		WP_Mock::userFunction( 'get_option' )->andReturnUsing(
			static function ( $key, $default = false ) {
				if ( 'leadstream_gads_api_version' === $key ) {
					return 'v21';
				}
				return $default;
			}
		);
		$creds = GoogleAds::creds();
		$this->assertSame( 'v21', $creds['api_version'] );
	}
}
