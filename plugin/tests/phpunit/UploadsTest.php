<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Uploads;
use PHPUnit\Framework\TestCase;

final class UploadsTest extends TestCase {

	public function test_hash_email_normalizes_and_hashes(): void {
		$hash = Uploads::hash_email( '  Noah@Example.COM ' );
		$this->assertSame( 64, strlen( $hash ) );
		$this->assertSame( hash( 'sha256', 'noah@example.com' ), $hash );
	}

	public function test_hash_email_rejects_invalid(): void {
		$this->assertSame( '', Uploads::hash_email( 'not an email' ) );
		$this->assertSame( '', Uploads::hash_email( '' ) );
	}

	public function test_extract_email_from_entry_finds_first_email(): void {
		$entry = array(
			'1' => 'Noah',
			'2' => 'Vertefeuille',
			'3' => 'noah@example.com',
			'4' => '555-1234',
		);
		$this->assertSame( 'noah@example.com', Uploads::extract_email_from_entry( $entry ) );
	}

	public function test_extract_email_from_entry_empty_when_none(): void {
		$entry = array(
			'1' => 'Noah',
			'2' => '555-1234',
		);
		$this->assertSame( '', Uploads::extract_email_from_entry( $entry ) );
	}

	public function test_extract_email_trims_whitespace(): void {
		$entry = array( '1' => '  noah@example.com  ' );
		$this->assertSame( 'noah@example.com', Uploads::extract_email_from_entry( $entry ) );
	}

	public function test_next_retry_at_backoff_schedule(): void {
		$t1 = strtotime( Uploads::next_retry_at( 1 ) . ' UTC' );
		$t2 = strtotime( Uploads::next_retry_at( 2 ) . ' UTC' );
		$t3 = strtotime( Uploads::next_retry_at( 3 ) . ' UTC' );
		$t4 = strtotime( Uploads::next_retry_at( 4 ) . ' UTC' );

		// 30m, 120m, 360m, 1440m respectively; allow slack for clock drift during test.
		$this->assertGreaterThan( $t1, $t2 );
		$this->assertGreaterThan( $t2, $t3 );
		$this->assertGreaterThan( $t3, $t4 );

		$now = time();
		$this->assertEqualsWithDelta( $now + ( 30 * 60 ), $t1, 5 );
		$this->assertEqualsWithDelta( $now + ( 120 * 60 ), $t2, 5 );
		$this->assertEqualsWithDelta( $now + ( 360 * 60 ), $t3, 5 );
		$this->assertEqualsWithDelta( $now + ( 1440 * 60 ), $t4, 5 );
	}

	public function test_next_retry_at_clamps_beyond_schedule(): void {
		$t5 = strtotime( Uploads::next_retry_at( 5 ) . ' UTC' );
		$t9 = strtotime( Uploads::next_retry_at( 9 ) . ' UTC' );
		$this->assertEqualsWithDelta( $t5, $t9, 5 );
	}
}
