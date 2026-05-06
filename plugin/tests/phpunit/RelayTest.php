<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Relay;
use PHPUnit\Framework\TestCase;
use WP_Mock;

final class RelayTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_is_configured_false_when_url_missing(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_backend_url', '' )
			->andReturn( '' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_license_key', '' )
			->andReturn( 'k' );

		$this->assertFalse( Relay::is_configured() );
	}

	public function test_is_configured_false_when_key_missing(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_backend_url', '' )
			->andReturn( 'https://relay.example.com' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_license_key', '' )
			->andReturn( '' );

		$this->assertFalse( Relay::is_configured() );
	}

	public function test_is_configured_true_when_both_set(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_backend_url', '' )
			->andReturn( 'https://relay.example.com' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_license_key', '' )
			->andReturn( 'abc123' );

		$this->assertTrue( Relay::is_configured() );
	}

	public function test_is_configured_false_for_whitespace_only_values(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_backend_url', '' )
			->andReturn( '   ' );
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_license_key', '' )
			->andReturn( '   ' );

		$this->assertFalse( Relay::is_configured() );
	}
}
