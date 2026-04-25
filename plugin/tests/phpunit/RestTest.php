<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Rest;
use PHPUnit\Framework\TestCase;
use WP_Mock;

final class RestTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_extract_query_params_parses_utms_and_click_ids(): void {
		$params = Rest::extract_query_params(
			'https://example.com/page?utm_source=google&utm_medium=cpc&gclid=abc123'
		);

		$this->assertSame( 'google', $params['utm_source'] );
		$this->assertSame( 'cpc', $params['utm_medium'] );
		$this->assertSame( 'abc123', $params['gclid'] );
	}

	public function test_extract_query_params_empty_for_no_query(): void {
		$this->assertSame( array(), Rest::extract_query_params( 'https://example.com/page' ) );
		$this->assertSame( array(), Rest::extract_query_params( '' ) );
	}

	public function test_host_from_url(): void {
		$this->assertSame( 'example.com', Rest::host_from_url( 'https://example.com/foo' ) );
		$this->assertSame( 'sub.example.com', Rest::host_from_url( 'https://Sub.Example.com/?x=1' ) );
		$this->assertSame( '', Rest::host_from_url( '' ) );
		$this->assertSame( '', Rest::host_from_url( 'not a url' ) );
	}

	public function test_check_capture_permission_rejects_missing_nonce(): void {
		$request = new \stdClass();
		$request->headers = array();
		// Anonymous object without get_header => no nonce => deny.
		$this->assertFalse( Rest::check_capture_permission( null ) );
	}

	public function test_check_capture_permission_rejects_bad_nonce(): void {
		WP_Mock::userFunction( 'wp_verify_nonce' )
			->with( 'bad-nonce', 'wp_rest' )
			->andReturn( false );

		$request = new RestTestRequest( array( 'X-WP-Nonce' => 'bad-nonce' ) );
		$this->assertFalse( Rest::check_capture_permission( $request ) );
	}

	public function test_check_capture_permission_accepts_valid_nonce(): void {
		WP_Mock::userFunction( 'wp_verify_nonce' )
			->with( 'good-nonce', 'wp_rest' )
			->andReturn( 1 );

		$request = new RestTestRequest( array( 'X-WP-Nonce' => 'good-nonce' ) );
		$this->assertTrue( Rest::check_capture_permission( $request ) );
	}
}

/**
 * Minimal request stand-in that mimics WP_REST_Request's get_header method.
 */
final class RestTestRequest {
	private array $headers;

	public function __construct( array $headers ) {
		$normalized = array();
		foreach ( $headers as $key => $value ) {
			$normalized[ strtolower( str_replace( '-', '_', $key ) ) ] = $value;
		}
		$this->headers = $normalized;
	}

	public function get_header( string $name ): string {
		$key = strtolower( str_replace( '-', '_', $name ) );
		return isset( $this->headers[ $key ] ) ? (string) $this->headers[ $key ] : '';
	}
}
