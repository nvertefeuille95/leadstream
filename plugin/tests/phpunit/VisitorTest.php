<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Visitor;
use PHPUnit\Framework\TestCase;

final class VisitorTest extends TestCase {

	protected function tearDown(): void {
		unset( $_COOKIE[ Visitor::COOKIE ] );
		parent::tearDown();
	}

	public function test_is_valid_accepts_uuid_v4(): void {
		$this->assertTrue( Visitor::is_valid( '550e8400-e29b-41d4-a716-446655440000' ) );
	}

	public function test_is_valid_rejects_random_strings(): void {
		$this->assertFalse( Visitor::is_valid( 'not-a-uuid' ) );
		$this->assertFalse( Visitor::is_valid( '' ) );
		$this->assertFalse( Visitor::is_valid( '12345' ) );
	}

	public function test_is_valid_rejects_uuid_v1(): void {
		// Version 1 UUIDs have '1' in the version slot; we only accept v4.
		$this->assertFalse( Visitor::is_valid( 'd9b8b9d8-0000-1000-8000-000000000000' ) );
	}

	public function test_get_or_create_returns_existing_valid_cookie(): void {
		$existing = '550e8400-e29b-41d4-a716-446655440000';
		$_COOKIE[ Visitor::COOKIE ] = $existing;

		$this->assertSame( $existing, Visitor::get_or_create() );
	}

	public function test_get_or_create_generates_new_when_invalid(): void {
		$_COOKIE[ Visitor::COOKIE ] = 'garbage';

		$result = Visitor::get_or_create();

		$this->assertNotSame( 'garbage', $result );
		$this->assertTrue( Visitor::is_valid( $result ) );
	}

	public function test_get_or_create_generates_new_when_no_cookie(): void {
		$result = Visitor::get_or_create();

		$this->assertNotEmpty( $result );
		$this->assertTrue( Visitor::is_valid( $result ) );
	}

	public function test_get_or_create_explicit_override(): void {
		$_COOKIE[ Visitor::COOKIE ] = '550e8400-e29b-41d4-a716-446655440000';

		// Override should beat the cookie.
		$override = '660e8400-e29b-41d4-a716-446655440111';
		$this->assertSame( $override, Visitor::get_or_create( $override ) );
	}

	public function test_current_returns_empty_when_no_cookie(): void {
		$this->assertSame( '', Visitor::current() );
	}

	public function test_current_returns_value_when_valid(): void {
		$id = '550e8400-e29b-41d4-a716-446655440000';
		$_COOKIE[ Visitor::COOKIE ] = $id;
		$this->assertSame( $id, Visitor::current() );
	}

	public function test_current_returns_empty_when_cookie_invalid(): void {
		$_COOKIE[ Visitor::COOKIE ] = 'tampered';
		$this->assertSame( '', Visitor::current() );
	}
}
