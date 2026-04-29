<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Touches;
use PHPUnit\Framework\TestCase;

final class TouchesTest extends TestCase {

	public function test_is_meaningful_false_for_empty_data(): void {
		$this->assertFalse( Touches::is_meaningful( array() ) );
	}

	public function test_is_meaningful_true_for_click_id(): void {
		$this->assertTrue( Touches::is_meaningful( array( 'click_id' => 'gclid_abc' ) ) );
	}

	public function test_is_meaningful_true_for_utm_source(): void {
		$this->assertTrue( Touches::is_meaningful( array( 'utm_source' => 'google', 'utm_medium' => 'cpc' ) ) );
	}

	public function test_is_meaningful_false_for_direct_only(): void {
		$this->assertFalse(
			Touches::is_meaningful(
				array(
					'utm_source' => 'direct',
					'utm_medium' => 'direct',
				)
			)
		);
	}

	public function test_is_meaningful_true_for_referral_classification(): void {
		$this->assertTrue(
			Touches::is_meaningful(
				array(
					'utm_source' => 'github.com',
					'utm_medium' => 'referral',
				)
			)
		);
	}

	public function test_deterministic_touch_id_stable_within_bucket(): void {
		$visitor = '550e8400-e29b-41d4-a716-446655440000';
		$data    = array(
			'utm_source' => 'google',
			'utm_medium' => 'cpc',
			'click_id'   => 'abc',
		);

		$bucket = 12345;
		$a      = Touches::deterministic_touch_id( $visitor, $data, $bucket );
		$b      = Touches::deterministic_touch_id( $visitor, $data, $bucket );

		$this->assertSame( $a, $b );
		$this->assertSame( 64, strlen( $a ) );
	}

	public function test_deterministic_touch_id_changes_across_buckets(): void {
		$visitor = '550e8400-e29b-41d4-a716-446655440000';
		$data    = array( 'utm_source' => 'google' );

		$a = Touches::deterministic_touch_id( $visitor, $data, 100 );
		$b = Touches::deterministic_touch_id( $visitor, $data, 200 );

		$this->assertNotSame( $a, $b );
	}

	public function test_deterministic_touch_id_changes_with_data(): void {
		$visitor = '550e8400-e29b-41d4-a716-446655440000';

		$a = Touches::deterministic_touch_id( $visitor, array( 'utm_source' => 'google' ), 100 );
		$b = Touches::deterministic_touch_id( $visitor, array( 'utm_source' => 'facebook' ), 100 );

		$this->assertNotSame( $a, $b );
	}

	public function test_table_uses_wpdb_prefix(): void {
		global $wpdb;
		$wpdb         = new \stdClass();
		$wpdb->prefix = 'wp_test_';

		$this->assertSame( 'wp_test_leadstream_touches', Touches::table() );
	}
}
