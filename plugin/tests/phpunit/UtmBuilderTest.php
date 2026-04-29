<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\UtmBuilder;
use PHPUnit\Framework\TestCase;

final class UtmBuilderTest extends TestCase {

	public function test_build_returns_empty_for_empty_base(): void {
		$this->assertSame( '', UtmBuilder::build( '', array( 'utm_source' => 'google' ) ) );
	}

	public function test_build_returns_base_when_no_params(): void {
		$this->assertSame( 'https://example.com/', UtmBuilder::build( 'https://example.com/', array() ) );
	}

	public function test_build_appends_utm_params(): void {
		$result = UtmBuilder::build(
			'https://example.com/page',
			array(
				'utm_source'   => 'google',
				'utm_medium'   => 'cpc',
				'utm_campaign' => 'spring_2026',
			)
		);
		$this->assertStringContainsString( 'utm_source=google', $result );
		$this->assertStringContainsString( 'utm_medium=cpc', $result );
		$this->assertStringContainsString( 'utm_campaign=spring_2026', $result );
		$this->assertStringStartsWith( 'https://example.com/page?', $result );
	}

	public function test_build_preserves_existing_query_string(): void {
		$result = UtmBuilder::build(
			'https://example.com/page?ref=internal&id=42',
			array( 'utm_source' => 'google' )
		);
		$this->assertStringContainsString( 'ref=internal', $result );
		$this->assertStringContainsString( 'id=42', $result );
		$this->assertStringContainsString( 'utm_source=google', $result );
	}

	public function test_build_overwrites_existing_utm_in_url(): void {
		$result = UtmBuilder::build(
			'https://example.com/page?utm_source=old',
			array( 'utm_source' => 'new' )
		);
		$this->assertStringContainsString( 'utm_source=new', $result );
		$this->assertStringNotContainsString( 'utm_source=old', $result );
	}

	public function test_build_preserves_fragment(): void {
		$result = UtmBuilder::build( 'https://example.com/#anchor', array( 'utm_source' => 'x' ) );
		$this->assertStringEndsWith( '#anchor', $result );
	}

	public function test_build_strips_empty_param_values(): void {
		$result = UtmBuilder::build(
			'https://example.com/',
			array(
				'utm_source'   => 'google',
				'utm_medium'   => '',
				'utm_campaign' => '   ',
			)
		);
		$this->assertStringContainsString( 'utm_source=google', $result );
		$this->assertStringNotContainsString( 'utm_medium=', $result );
		$this->assertStringNotContainsString( 'utm_campaign=', $result );
	}

	public function test_build_returns_base_for_invalid_url(): void {
		// No host = invalid; return as-is.
		$result = UtmBuilder::build( 'not a url', array( 'utm_source' => 'google' ) );
		$this->assertSame( 'not a url', $result );
	}

	public function test_clean_params_only_returns_known_utm_keys(): void {
		$result = UtmBuilder::clean_params(
			array(
				'utm_source'  => 'google',
				'utm_other'   => 'ignored',
				'random_key'  => 'also_ignored',
			)
		);
		$this->assertArrayHasKey( 'utm_source', $result );
		$this->assertArrayNotHasKey( 'utm_other', $result );
		$this->assertArrayNotHasKey( 'random_key', $result );
	}

	public function test_clean_params_trims_whitespace(): void {
		$result = UtmBuilder::clean_params( array( 'utm_source' => '  google  ' ) );
		$this->assertSame( 'google', $result['utm_source'] );
	}

	public function test_detect_taxonomy_drift_returns_null_for_unknown_value(): void {
		$known = array( 'google', 'facebook', 'linkedin' );
		$this->assertNull( UtmBuilder::detect_taxonomy_drift( 'tiktok', $known ) );
	}

	public function test_detect_taxonomy_drift_returns_null_for_exact_match(): void {
		$known = array( 'google', 'facebook' );
		$this->assertNull( UtmBuilder::detect_taxonomy_drift( 'google', $known ) );
	}

	public function test_detect_taxonomy_drift_catches_case_difference(): void {
		$known = array( 'google', 'facebook' );
		$this->assertSame( 'google', UtmBuilder::detect_taxonomy_drift( 'Google', $known ) );
	}

	public function test_detect_taxonomy_drift_handles_empty_input(): void {
		$this->assertNull( UtmBuilder::detect_taxonomy_drift( '', array( 'google' ) ) );
		$this->assertNull( UtmBuilder::detect_taxonomy_drift( '   ', array( 'google' ) ) );
	}

	public function test_parse_bulk_csv_handles_basic_input(): void {
		$csv  = "base_url,utm_source,utm_medium,utm_campaign\nhttps://example.com/x,google,cpc,spring\nhttps://example.com/y,linkedin,social,fall";
		$rows = UtmBuilder::parse_bulk_csv( $csv );

		$this->assertCount( 2, $rows );
		$this->assertSame( 'https://example.com/x', $rows[0]['base_url'] );
		$this->assertSame( 'google', $rows[0]['utm_source'] );
		$this->assertSame( 'fall', $rows[1]['utm_campaign'] );
	}

	public function test_parse_bulk_csv_returns_empty_for_blank_input(): void {
		$this->assertSame( array(), UtmBuilder::parse_bulk_csv( '' ) );
		$this->assertSame( array(), UtmBuilder::parse_bulk_csv( '   ' ) );
	}

	public function test_parse_bulk_csv_skips_rows_without_base_url(): void {
		$csv  = "base_url,utm_source\n,google\nhttps://example.com/x,linkedin";
		$rows = UtmBuilder::parse_bulk_csv( $csv );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'https://example.com/x', $rows[0]['base_url'] );
	}

	public function test_generate_bulk_csv_appends_tagged_url_column(): void {
		$rows = array(
			array(
				'base_url'     => 'https://example.com/x',
				'utm_source'   => 'google',
				'utm_medium'   => 'cpc',
				'utm_campaign' => 'spring',
			),
		);
		$csv = UtmBuilder::generate_bulk_csv( $rows );

		$this->assertStringContainsString( 'tagged_url', $csv );
		$this->assertStringContainsString( 'https://example.com/x?', $csv );
		$this->assertStringContainsString( 'utm_source=google', $csv );
	}

	public function test_generate_bulk_csv_quotes_values_with_commas(): void {
		$rows = array(
			array(
				'base_url'     => 'https://example.com/',
				'utm_source'   => 'has, comma',
				'utm_medium'   => 'cpc',
				'utm_campaign' => 'x',
			),
		);
		$csv = UtmBuilder::generate_bulk_csv( $rows );

		$this->assertStringContainsString( '"has, comma"', $csv );
	}
}
