<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Reports;
use PHPUnit\Framework\TestCase;

final class ReportsTest extends TestCase {

	public function test_aggregate_empty_returns_empty(): void {
		$this->assertSame( array(), Reports::aggregate( array(), 'last', 'utm_source' ) );
	}

	public function test_aggregate_no_touches_falls_back_to_event_attribution(): void {
		$events = array(
			array( 'utm_source' => 'google', 'touches' => array() ),
			array( 'utm_source' => 'google', 'touches' => array() ),
			array( 'utm_source' => 'linkedin', 'touches' => array() ),
		);
		$result = Reports::aggregate( $events, 'last', 'utm_source' );

		$this->assertCount( 2, $result );
		$this->assertSame( 'google', $result[0]['value'] );
		$this->assertSame( 2.0, $result[0]['conversions'] );
		$this->assertSame( 'linkedin', $result[1]['value'] );
		$this->assertSame( 1.0, $result[1]['conversions'] );
	}

	public function test_aggregate_last_click_credits_only_final_touch(): void {
		$events = array(
			array(
				'visitor_id' => 'v1',
				'touches'    => array(
					array( 'utm_source' => 'linkedin' ),
					array( 'utm_source' => 'google' ),
				),
			),
		);
		$result = Reports::aggregate( $events, 'last', 'utm_source' );

		$this->assertCount( 1, $result );
		$this->assertSame( 'google', $result[0]['value'] );
		$this->assertSame( 1.0, $result[0]['conversions'] );
		$this->assertSame( 100.0, $result[0]['percentage'] );
	}

	public function test_aggregate_first_click_credits_only_first_touch(): void {
		$events = array(
			array(
				'touches' => array(
					array( 'utm_source' => 'linkedin' ),
					array( 'utm_source' => 'google' ),
				),
			),
		);
		$result = Reports::aggregate( $events, 'first', 'utm_source' );

		$this->assertSame( 'linkedin', $result[0]['value'] );
		$this->assertSame( 1.0, $result[0]['conversions'] );
	}

	public function test_aggregate_linear_splits_credit_evenly(): void {
		$events = array(
			array(
				'touches' => array(
					array( 'utm_source' => 'linkedin' ),
					array( 'utm_source' => 'google' ),
					array( 'utm_source' => 'facebook' ),
					array( 'utm_source' => 'google' ),
				),
			),
		);
		$result = Reports::aggregate( $events, 'linear', 'utm_source' );

		// linkedin = 0.25, google = 0.5, facebook = 0.25
		$by_source = array();
		foreach ( $result as $row ) {
			$by_source[ $row['value'] ] = $row['conversions'];
		}
		$this->assertEqualsWithDelta( 0.5, $by_source['google'], 0.01 );
		$this->assertEqualsWithDelta( 0.25, $by_source['linkedin'], 0.01 );
		$this->assertEqualsWithDelta( 0.25, $by_source['facebook'], 0.01 );
	}

	public function test_aggregate_unknown_value_grouped_as_unknown(): void {
		$events = array(
			array(
				'touches' => array(
					array( 'utm_source' => '' ),
					array( 'utm_source' => 'google' ),
				),
			),
		);
		$result = Reports::aggregate( $events, 'first', 'utm_source' );

		$this->assertSame( '(unknown)', $result[0]['value'] );
	}

	public function test_aggregate_sums_to_total_credit(): void {
		$events = array(
			array(
				'touches' => array(
					array( 'utm_source' => 'a' ),
					array( 'utm_source' => 'b' ),
				),
			),
			array(
				'touches' => array(
					array( 'utm_source' => 'a' ),
					array( 'utm_source' => 'c' ),
				),
			),
		);

		foreach ( array( 'last', 'first', 'linear' ) as $model ) {
			$result = Reports::aggregate( $events, $model, 'utm_source' );
			$sum    = array_sum( array_column( $result, 'conversions' ) );
			$this->assertEqualsWithDelta( 2.0, $sum, 0.01, "Model: $model" );
		}
	}

	public function test_summary_counts_conversions_visitors_and_avg_touches(): void {
		$events = array(
			array( 'visitor_id' => 'v1', 'touches' => array( array(), array() ) ),
			array( 'visitor_id' => 'v2', 'touches' => array( array() ) ),
			array( 'visitor_id' => 'v1', 'touches' => array( array(), array(), array() ) ),
		);
		$result = Reports::summary( $events );

		$this->assertSame( 3, $result['conversions'] );
		$this->assertSame( 2, $result['unique_visitors'] );
		$this->assertEqualsWithDelta( 2.0, $result['avg_touches_per_conversion'], 0.01 );
	}

	public function test_summary_handles_empty_input(): void {
		$result = Reports::summary( array() );

		$this->assertSame( 0, $result['conversions'] );
		$this->assertSame( 0, $result['unique_visitors'] );
		$this->assertSame( 0.0, $result['avg_touches_per_conversion'] );
	}

	public function test_aggregate_results_sorted_descending_by_credit(): void {
		$events = array(
			array(
				'touches' => array(
					array( 'utm_source' => 'small' ),
					array( 'utm_source' => 'big' ),
					array( 'utm_source' => 'big' ),
					array( 'utm_source' => 'big' ),
				),
			),
		);
		$result = Reports::aggregate( $events, 'linear', 'utm_source' );

		$this->assertSame( 'big', $result[0]['value'] );
		$this->assertSame( 'small', $result[1]['value'] );
	}

	public function test_aggregate_supports_medium_and_campaign_dimensions(): void {
		$events = array(
			array(
				'touches' => array(
					array( 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'spring' ),
				),
			),
			array(
				'touches' => array(
					array( 'utm_source' => 'linkedin', 'utm_medium' => 'social', 'utm_campaign' => 'fall' ),
				),
			),
		);

		$by_medium = Reports::aggregate( $events, 'last', 'utm_medium' );
		$this->assertCount( 2, $by_medium );
		$mediums = array_column( $by_medium, 'value' );
		$this->assertContains( 'cpc', $mediums );
		$this->assertContains( 'social', $mediums );

		$by_campaign = Reports::aggregate( $events, 'last', 'utm_campaign' );
		$this->assertCount( 2, $by_campaign );
		$campaigns = array_column( $by_campaign, 'value' );
		$this->assertContains( 'spring', $campaigns );
		$this->assertContains( 'fall', $campaigns );
	}
}
