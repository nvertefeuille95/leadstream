<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Attribution;
use PHPUnit\Framework\TestCase;

final class AttributionTest extends TestCase {

	public function test_apply_returns_empty_for_no_touches(): void {
		$this->assertSame( array(), Attribution::apply( array(), Attribution::MODEL_LAST ) );
	}

	public function test_single_touch_gets_full_credit_for_every_model(): void {
		$touch = array( array( 'utm_source' => 'google', 'created_at' => '2026-04-01 10:00:00' ) );

		foreach ( array( 'last', 'first', 'linear', 'position', 'time' ) as $model ) {
			$result = Attribution::apply( $touch, $model );
			$this->assertCount( 1, $result, "Model: $model" );
			$this->assertSame( 1.0, $result[0]['credit'], "Model: $model" );
		}
	}

	public function test_last_click_gives_full_credit_to_final_touch(): void {
		$touches = array(
			array( 'utm_source' => 'linkedin' ),
			array( 'utm_source' => 'google' ),
			array( 'utm_source' => 'facebook' ),
		);
		$result = Attribution::apply( $touches, Attribution::MODEL_LAST );

		$this->assertSame( 0.0, $result[0]['credit'] );
		$this->assertSame( 0.0, $result[1]['credit'] );
		$this->assertSame( 1.0, $result[2]['credit'] );
	}

	public function test_first_click_gives_full_credit_to_initial_touch(): void {
		$touches = array(
			array( 'utm_source' => 'linkedin' ),
			array( 'utm_source' => 'google' ),
			array( 'utm_source' => 'facebook' ),
		);
		$result = Attribution::apply( $touches, Attribution::MODEL_FIRST );

		$this->assertSame( 1.0, $result[0]['credit'] );
		$this->assertSame( 0.0, $result[1]['credit'] );
		$this->assertSame( 0.0, $result[2]['credit'] );
	}

	public function test_linear_splits_credit_equally(): void {
		$touches = array_fill( 0, 4, array( 'utm_source' => 'x' ) );
		$result  = Attribution::apply( $touches, Attribution::MODEL_LINEAR );

		foreach ( $result as $row ) {
			$this->assertEqualsWithDelta( 0.25, $row['credit'], 0.0001 );
		}
		$this->assertEqualsWithDelta( 1.0, array_sum( array_column( $result, 'credit' ) ), 0.0001 );
	}

	public function test_position_based_three_touches_40_20_40(): void {
		$touches = array(
			array( 'utm_source' => 'a' ),
			array( 'utm_source' => 'b' ),
			array( 'utm_source' => 'c' ),
		);
		$result = Attribution::apply( $touches, Attribution::MODEL_POSITION );

		$this->assertEqualsWithDelta( 0.4, $result[0]['credit'], 0.0001 );
		$this->assertEqualsWithDelta( 0.2, $result[1]['credit'], 0.0001 );
		$this->assertEqualsWithDelta( 0.4, $result[2]['credit'], 0.0001 );
	}

	public function test_position_based_two_touches_splits_50_50(): void {
		$touches = array(
			array( 'utm_source' => 'a' ),
			array( 'utm_source' => 'b' ),
		);
		$result = Attribution::apply( $touches, Attribution::MODEL_POSITION );

		$this->assertSame( 0.5, $result[0]['credit'] );
		$this->assertSame( 0.5, $result[1]['credit'] );
	}

	public function test_position_based_five_touches_split_middle_three_share_20pct(): void {
		$touches = array_fill( 0, 5, array( 'utm_source' => 'x' ) );
		$result  = Attribution::apply( $touches, Attribution::MODEL_POSITION );

		$this->assertEqualsWithDelta( 0.4, $result[0]['credit'], 0.0001 );
		$this->assertEqualsWithDelta( 0.4, $result[4]['credit'], 0.0001 );
		// Middle three share 0.2 -> ~0.0667 each.
		$this->assertEqualsWithDelta( 0.0667, $result[1]['credit'], 0.001 );
		$this->assertEqualsWithDelta( 0.0667, $result[2]['credit'], 0.001 );
		$this->assertEqualsWithDelta( 0.0667, $result[3]['credit'], 0.001 );
		$this->assertEqualsWithDelta( 1.0, array_sum( array_column( $result, 'credit' ) ), 0.001 );
	}

	public function test_time_decay_recent_touch_gets_more_credit(): void {
		$touches = array(
			array( 'created_at' => '2026-01-01 00:00:00' ), // older
			array( 'created_at' => '2026-04-01 00:00:00' ), // recent
		);
		$result = Attribution::apply( $touches, Attribution::MODEL_TIME );

		$this->assertGreaterThan( $result[0]['credit'], $result[1]['credit'] );
		$this->assertEqualsWithDelta( 1.0, array_sum( array_column( $result, 'credit' ) ), 0.0001 );
	}

	public function test_time_decay_same_timestamp_splits_evenly(): void {
		$touches = array(
			array( 'created_at' => '2026-04-01 12:00:00' ),
			array( 'created_at' => '2026-04-01 12:00:00' ),
			array( 'created_at' => '2026-04-01 12:00:00' ),
		);
		$result = Attribution::apply( $touches, Attribution::MODEL_TIME );

		foreach ( $result as $row ) {
			$this->assertEqualsWithDelta( 1.0 / 3, $row['credit'], 0.0001 );
		}
	}

	public function test_unknown_model_falls_back_to_last(): void {
		$touches = array(
			array( 'utm_source' => 'a' ),
			array( 'utm_source' => 'b' ),
		);
		$result = Attribution::apply( $touches, 'invalid_model' );

		$this->assertSame( 0.0, $result[0]['credit'] );
		$this->assertSame( 1.0, $result[1]['credit'] );
	}

	public function test_credits_always_sum_to_one(): void {
		$touches = array_map(
			static function ( $i ) {
				return array(
					'utm_source' => 's' . $i,
					'created_at' => '2026-04-' . str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) . ' 10:00:00',
				);
			},
			range( 0, 5 )
		);

		foreach ( array( 'last', 'first', 'linear', 'position', 'time' ) as $model ) {
			$result = Attribution::apply( $touches, $model );
			$sum    = array_sum( array_column( $result, 'credit' ) );
			$this->assertEqualsWithDelta( 1.0, $sum, 0.0001, "Model: $model" );
		}
	}
}
