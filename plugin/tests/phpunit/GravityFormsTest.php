<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Forms\GravityForms;
use PHPUnit\Framework\TestCase;
use WP_Mock;

final class GravityFormsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		$_COOKIE = array();
		parent::tearDown();
	}

	public function test_is_active_false_when_gravity_not_loaded(): void {
		$this->assertFalse( GravityForms::is_active() );
	}

	public function test_cookie_returns_empty_when_unset(): void {
		$this->assertSame( '', GravityForms::cookie( 'utm_source' ) );
	}

	public function test_cookie_returns_sanitized_value(): void {
		$_COOKIE['leadstream_utm_source'] = 'google';
		$this->assertSame( 'google', GravityForms::cookie( 'utm_source' ) );
	}

	public function test_cookie_strips_tags_and_slashes(): void {
		$_COOKIE['leadstream_click_id'] = "abc\\'123";
		$this->assertSame( "abc'123", GravityForms::cookie( 'click_id' ) );
	}

	public function test_cookie_scoped_to_leadstream_prefix(): void {
		$_COOKIE['utm_source']            = 'ignored';
		$_COOKIE['leadstream_utm_source'] = 'kept';
		$this->assertSame( 'kept', GravityForms::cookie( 'utm_source' ) );
	}

	public function test_field_keys_cover_all_cookies(): void {
		$expected = array(
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'utm_term',
			'utm_content',
			'click_id',
			'click_id_type',
			'first_page',
			'referrer',
		);
		$this->assertSame( $expected, GravityForms::FIELD_KEYS );
	}

	public function test_register_merge_tags_adds_one_entry_per_field(): void {
		$result = GravityForms::register_merge_tags( array() );

		$this->assertCount( count( GravityForms::FIELD_KEYS ), $result );
		foreach ( $result as $tag ) {
			$this->assertArrayHasKey( 'label', $tag );
			$this->assertArrayHasKey( 'tag', $tag );
			$this->assertStringStartsWith( 'LeadStream:', $tag['label'] );
			$this->assertStringStartsWith( '{leadstream:', $tag['tag'] );
		}
	}

	public function test_register_merge_tags_preserves_existing(): void {
		$existing = array( array( 'label' => 'Pre', 'tag' => '{pre}' ) );
		$result   = GravityForms::register_merge_tags( $existing );

		$this->assertSame( $existing[0], $result[0] );
		$this->assertCount( count( GravityForms::FIELD_KEYS ) + 1, $result );
	}

	public function test_replace_merge_tags_noop_when_no_tag_present(): void {
		$text = 'Plain text with no tags.';
		$this->assertSame( $text, GravityForms::replace_merge_tags( $text ) );
	}

	public function test_replace_merge_tags_substitutes_cookie_values(): void {
		$_COOKIE['leadstream_utm_source'] = 'linkedin';
		$_COOKIE['leadstream_click_id']   = 'abc123';

		$text   = 'Lead from {leadstream:utm_source} with click {leadstream:click_id}.';
		$result = GravityForms::replace_merge_tags( $text );

		$this->assertSame( 'Lead from linkedin with click abc123.', $result );
	}

	public function test_replace_merge_tags_missing_cookies_become_empty(): void {
		$text   = 'Source: {leadstream:utm_source}.';
		$result = GravityForms::replace_merge_tags( $text );

		$this->assertSame( 'Source: .', $result );
	}

	public function test_maybe_append_attribution_no_op_when_setting_off(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_gf_auto_append_notifications', false )
			->andReturn( false );

		$notification = array( 'message' => 'Hello' );
		$result       = GravityForms::maybe_append_attribution( $notification );

		$this->assertSame( $notification, $result );
	}

	public function test_maybe_append_attribution_appends_when_setting_on_and_cookies_present(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_gf_auto_append_notifications', false )
			->andReturn( true );

		$_COOKIE['leadstream_utm_source'] = 'google';
		$_COOKIE['leadstream_utm_medium'] = 'cpc';

		$notification = array( 'message' => 'Hello' );
		$result       = GravityForms::maybe_append_attribution( $notification );

		$this->assertStringContainsString( 'Hello', $result['message'] );
		$this->assertStringContainsString( 'Attribution', $result['message'] );
		$this->assertStringContainsString( 'google', $result['message'] );
		$this->assertStringContainsString( 'cpc', $result['message'] );
	}

	public function test_maybe_append_attribution_no_op_when_setting_on_but_no_cookies(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_gf_auto_append_notifications', false )
			->andReturn( true );

		$notification = array( 'message' => 'Hello' );
		$result       = GravityForms::maybe_append_attribution( $notification );

		$this->assertSame( $notification, $result );
	}

	public function test_render_attribution_block_empty_when_no_cookies(): void {
		$this->assertSame( '', GravityForms::render_attribution_block() );
	}

	public function test_label_for_known_keys(): void {
		$this->assertSame( 'Source', GravityForms::label_for( 'utm_source' ) );
		$this->assertSame( 'Click ID', GravityForms::label_for( 'click_id' ) );
		$this->assertSame( 'First page', GravityForms::label_for( 'first_page' ) );
	}

	public function test_label_for_unknown_key_returns_raw(): void {
		$this->assertSame( 'unknown_field', GravityForms::label_for( 'unknown_field' ) );
	}

	public function test_injected_field_props_one_per_cookie_key(): void {
		$form  = array( 'id' => 7, 'fields' => array() );
		$props = GravityForms::injected_field_props( $form );

		$this->assertCount( count( \LeadStream\Cookies::FIELD_KEYS ), $props );
	}

	public function test_injected_field_props_ids_start_past_max_existing(): void {
		$form = array(
			'id'     => 7,
			'fields' => array(
				(object) array( 'id' => 3 ),
				(object) array( 'id' => 9 ),
				array( 'id' => 5 ),
			),
		);

		$props = GravityForms::injected_field_props( $form );

		$this->assertSame( 10, $props[0]['id'] );
		$this->assertSame( 11, $props[1]['id'] );
	}

	public function test_injected_field_props_shape(): void {
		$form  = array( 'id' => 7, 'fields' => array() );
		$props = GravityForms::injected_field_props( $form );

		$source = $props[0];
		$this->assertSame( 'hidden', $source['type'] );
		$this->assertSame( 'leadstream_utm_source', $source['inputName'] );
		$this->assertSame( '{leadstream:utm_source}', $source['defaultValue'] );
		$this->assertSame( 7, $source['formId'] );
		$this->assertFalse( $source['adminOnly'] );
	}

	public function test_inject_fields_noop_when_setting_disabled(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_gf_auto_inject', true )
			->andReturn( false );

		$form   = array( 'id' => 1, 'fields' => array( array( 'id' => 1 ) ) );
		$result = GravityForms::inject_fields( $form );

		$this->assertSame( $form, $result );
	}

	public function test_inject_fields_noop_when_gf_fields_class_missing(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_gf_auto_inject', true )
			->andReturn( true );

		$form   = array( 'id' => 1, 'fields' => array( array( 'id' => 1 ) ) );
		$result = GravityForms::inject_fields( $form );

		// In test env GF_Fields is not loaded, so inject_fields should return
		// the form unchanged rather than blowing up.
		$this->assertSame( $form, $result );
	}

	public function test_inject_fields_noop_on_non_array_input(): void {
		$result = GravityForms::inject_fields( null );
		$this->assertNull( $result );
	}

	public function test_entry_list_columns_adds_leadstream_keys(): void {
		$existing = array( 1 => 'Name', 2 => 'Email' );
		$result   = GravityForms::entry_list_columns( $existing, 7 );

		$this->assertArrayHasKey( 'leadstream_utm_source', $result );
		$this->assertArrayHasKey( 'leadstream_click_id', $result );
		$this->assertArrayHasKey( 'leadstream_first_page', $result );
		$this->assertSame( 'Name', $result[1] );
		$this->assertSame( 'Email', $result[2] );
		$this->assertStringStartsWith( 'LS ', $result['leadstream_utm_source'] );
	}

	public function test_entry_list_columns_passthrough_on_non_array(): void {
		$this->assertNull( GravityForms::entry_list_columns( null ) );
	}

	public function test_entry_list_column_value_returns_meta_for_leadstream_keys(): void {
		WP_Mock::userFunction( 'gform_get_meta' )
			->with( 42, 'leadstream_utm_source' )
			->andReturn( 'google' );

		$value = GravityForms::entry_list_column_value( '', 7, 'leadstream_utm_source', array( 'id' => 42 ) );
		$this->assertSame( 'google', $value );
	}

	public function test_entry_list_column_value_passthrough_for_numeric_field_id(): void {
		$value = GravityForms::entry_list_column_value( 'original', 7, 1, array( 'id' => 42 ) );
		$this->assertSame( 'original', $value );
	}

	public function test_entry_list_column_value_passthrough_for_non_leadstream_key(): void {
		$value = GravityForms::entry_list_column_value( 'original', 7, 'other_key', array( 'id' => 42 ) );
		$this->assertSame( 'original', $value );
	}

	public function test_sidebar_meta_noop_for_invalid_entry(): void {
		ob_start();
		GravityForms::render_entry_sidebar_meta( array(), array() );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_sidebar_meta_noop_when_no_meta_present(): void {
		WP_Mock::userFunction( 'gform_get_meta' )->andReturn( '' );

		ob_start();
		GravityForms::render_entry_sidebar_meta( array(), array( 'id' => 42 ) );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_sidebar_meta_renders_postbox_when_meta_present(): void {
		WP_Mock::userFunction( 'gform_get_meta' )
			->with( 42, 'leadstream_utm_source' )
			->andReturn( 'google' );
		WP_Mock::userFunction( 'gform_get_meta' )
			->with( 42, 'leadstream_click_id' )
			->andReturn( 'abc123' );
		WP_Mock::userFunction( 'gform_get_meta' )->andReturn( '' );

		ob_start();
		GravityForms::render_entry_sidebar_meta( array(), array( 'id' => 42 ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'LeadStream Attribution', $output );
		$this->assertStringContainsString( 'google', $output );
		$this->assertStringContainsString( 'abc123', $output );
		$this->assertStringContainsString( 'postbox', $output );
	}
}
