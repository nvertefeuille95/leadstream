<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Assets;
use PHPUnit\Framework\TestCase;
use WP_Mock;

final class AssetsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		unset( $_SERVER['HTTP_HOST'] );
		parent::tearDown();
	}

	public function test_is_beta_domain_true_for_listed_host(): void {
		$_SERVER['HTTP_HOST'] = 'nolimitcarts.com';
		$this->assertTrue( Assets::is_beta_domain() );
	}

	public function test_is_beta_domain_strips_www_prefix(): void {
		$_SERVER['HTTP_HOST'] = 'www.controlstation.com';
		$this->assertTrue( Assets::is_beta_domain() );
	}

	public function test_is_beta_domain_is_case_insensitive(): void {
		$_SERVER['HTTP_HOST'] = 'Nexeris.US';
		$this->assertTrue( Assets::is_beta_domain() );
	}

	public function test_is_beta_domain_false_for_unlisted_host(): void {
		$_SERVER['HTTP_HOST'] = 'example.com';
		$this->assertFalse( Assets::is_beta_domain() );
	}

	public function test_is_beta_domain_false_when_host_missing(): void {
		$this->assertFalse( Assets::is_beta_domain() );
	}

	public function test_js_config_returns_expected_shape(): void {
		WP_Mock::userFunction( 'get_option' )->andReturnUsing(
			static function ( $key, $default = false ) {
				return $default;
			}
		);
		WP_Mock::userFunction( 'rest_url' )->andReturnUsing(
			static function ( $path = '' ) {
				return 'https://example.com/wp-json/' . ltrim( (string) $path, '/' );
			}
		);
		WP_Mock::userFunction( 'wp_create_nonce' )->andReturn( 'test-nonce-abc' );

		$_SERVER['HTTP_HOST'] = 'example.com';
		$config               = Assets::js_config();

		$this->assertArrayHasKey( 'cookiePrefix', $config );
		$this->assertArrayHasKey( 'cookieDuration', $config );
		$this->assertArrayHasKey( 'debug', $config );
		$this->assertArrayHasKey( 'subdomainTracking', $config );
		$this->assertArrayHasKey( 'requireConsent', $config );
		$this->assertArrayHasKey( 'restUrl', $config );
		$this->assertArrayHasKey( 'restNonce', $config );
		$this->assertArrayHasKey( 'universalInject', $config );
		$this->assertSame( 'leadstream_', $config['cookiePrefix'] );
		$this->assertSame( 30, $config['cookieDuration'] );
		$this->assertFalse( $config['debug'] );
		$this->assertStringContainsString( 'leadstream/v1', $config['restUrl'] );
		$this->assertStringContainsString( 'capture', $config['restUrl'] );
		$this->assertSame( 'test-nonce-abc', $config['restNonce'] );
	}

	public function test_js_config_forces_debug_on_beta_domain(): void {
		WP_Mock::userFunction( 'get_option' )->andReturnUsing(
			static function ( $key, $default = false ) {
				return $default;
			}
		);
		WP_Mock::userFunction( 'rest_url' )->andReturn( 'https://example.com/wp-json/leadstream/v1/capture' );
		WP_Mock::userFunction( 'wp_create_nonce' )->andReturn( 'test-nonce' );

		$_SERVER['HTTP_HOST'] = 'timberbrookmarketing.com';
		$config               = Assets::js_config();

		$this->assertTrue( $config['debug'] );
	}
}
