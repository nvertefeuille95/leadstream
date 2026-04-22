<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Forms\ElementorForms;
use PHPUnit\Framework\TestCase;
use WP_Mock;

final class FakeFormRecord {
	public array $fields = array();

	public function set_field( array $args ): void {
		$this->fields[ $args['id'] ] = $args;
	}
}

final class FakeRecordWithoutSetField {
	public string $noop = '';
}

final class ElementorFormsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		$_COOKIE = array();
		parent::tearDown();
	}

	public function test_is_active_false_when_elementor_pro_not_defined(): void {
		$this->assertFalse( ElementorForms::is_active() );
	}

	public function test_inject_adds_leadstream_prefixed_fields_per_non_empty_cookie(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_elementor_auto_inject', true )
			->andReturn( true );

		$_COOKIE['leadstream_utm_source'] = 'linkedin';
		$_COOKIE['leadstream_utm_medium'] = 'paid';
		$_COOKIE['leadstream_click_id']   = 'li_abc';

		$record = new FakeFormRecord();
		ElementorForms::inject_attribution( $record );

		$this->assertArrayHasKey( 'leadstream_utm_source', $record->fields );
		$this->assertArrayHasKey( 'leadstream_utm_medium', $record->fields );
		$this->assertArrayHasKey( 'leadstream_click_id', $record->fields );
		$this->assertSame( 'linkedin', $record->fields['leadstream_utm_source']['value'] );
		$this->assertSame( 'hidden', $record->fields['leadstream_utm_source']['type'] );
	}

	public function test_inject_skips_empty_cookies(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_elementor_auto_inject', true )
			->andReturn( true );

		$_COOKIE['leadstream_utm_source'] = 'linkedin';

		$record = new FakeFormRecord();
		ElementorForms::inject_attribution( $record );

		$this->assertArrayHasKey( 'leadstream_utm_source', $record->fields );
		$this->assertArrayNotHasKey( 'leadstream_utm_medium', $record->fields );
		$this->assertArrayNotHasKey( 'leadstream_click_id', $record->fields );
	}

	public function test_inject_no_op_when_setting_disabled(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_elementor_auto_inject', true )
			->andReturn( false );

		$_COOKIE['leadstream_utm_source'] = 'linkedin';

		$record = new FakeFormRecord();
		ElementorForms::inject_attribution( $record );

		$this->assertSame( array(), $record->fields );
	}

	public function test_inject_handles_record_without_set_field_method(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_elementor_auto_inject', true )
			->andReturn( true );

		$_COOKIE['leadstream_utm_source'] = 'linkedin';

		$record = new FakeRecordWithoutSetField();
		// Should not throw even though set_field is missing.
		ElementorForms::inject_attribution( $record );
		$this->assertSame( '', $record->noop );
	}
}
