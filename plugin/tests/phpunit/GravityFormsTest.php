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
		$_POST   = array();
		unset( $_SERVER['REQUEST_METHOD'] );
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

	public function test_replace_merge_tags_substitutes_cookie_values_on_post(): void {
		$_SERVER['REQUEST_METHOD']        = 'POST';
		$_COOKIE['leadstream_utm_source'] = 'linkedin';
		$_COOKIE['leadstream_click_id']   = 'abc123';

		$text   = 'Lead from {leadstream:utm_source} with click {leadstream:click_id}.';
		$result = GravityForms::replace_merge_tags( $text );

		$this->assertSame( 'Lead from linkedin with click abc123.', $result );
	}

	public function test_replace_merge_tags_missing_cookies_become_empty(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$text   = 'Source: {leadstream:utm_source}.';
		$result = GravityForms::replace_merge_tags( $text );

		$this->assertSame( 'Source: .', $result );
	}

	/**
	 * GF runs this filter while rendering default values, placeholders and
	 * HTML fields. A page load must not write cookies into cacheable HTML.
	 */
	public function test_replace_merge_tags_empty_on_page_load(): void {
		$_SERVER['REQUEST_METHOD']        = 'GET';
		$_COOKIE['leadstream_referrer']   = 'https://www.google.com/search?q=private';
		$_COOKIE['leadstream_first_page'] = 'https://controlstation.com/landing/?gclid=TBMTEST123';
		$_COOKIE['leadstream_click_id']   = 'TBMTEST123';

		$text   = 'Ref {leadstream:referrer} page {leadstream:first_page} click {leadstream:click_id} {embed_url}';
		$result = GravityForms::replace_merge_tags( $text );

		$this->assertSame( 'Ref  page  click  {embed_url}', $result );
	}

	public function test_replace_merge_tags_empty_without_request_method(): void {
		$_COOKIE['leadstream_utm_source'] = 'google';
		$this->assertSame( 'Source: ', GravityForms::replace_merge_tags( 'Source: {leadstream:utm_source}' ) );
	}

	public function test_is_post_request(): void {
		$this->assertFalse( GravityForms::is_post_request() );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->assertFalse( GravityForms::is_post_request() );

		$_SERVER['REQUEST_METHOD'] = 'HEAD';
		$this->assertFalse( GravityForms::is_post_request() );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->assertTrue( GravityForms::is_post_request() );

		$_SERVER['REQUEST_METHOD'] = 'post';
		$this->assertTrue( GravityForms::is_post_request() );
	}

	public function test_field_value_empty_on_page_load(): void {
		$_SERVER['REQUEST_METHOD']           = 'GET';
		$_COOKIE['leadstream_utm_source']    = 'google';
		$_COOKIE['leadstream_referrer']      = 'https://www.google.com/';
		$_COOKIE['leadstream_click_id']      = 'TBMTEST123';
		$_COOKIE['leadstream_click_id_type'] = 'gclid';

		$this->assertSame( '', GravityForms::field_value( 'utm_source' ) );
		$this->assertSame( '', GravityForms::field_value( 'referrer' ) );
		$this->assertSame( '', GravityForms::field_value( 'click_id' ) );
		$this->assertSame( '', GravityForms::field_value( 'gclid' ) );
	}

	public function test_field_value_uses_cookies_on_post(): void {
		$_SERVER['REQUEST_METHOD']           = 'POST';
		$_COOKIE['leadstream_utm_source']    = 'google';
		$_COOKIE['leadstream_click_id']      = 'TBMTEST123';
		$_COOKIE['leadstream_click_id_type'] = 'gclid';

		$this->assertSame( 'google', GravityForms::field_value( 'utm_source' ) );
		$this->assertSame( 'TBMTEST123', GravityForms::field_value( 'gclid' ) );
		$this->assertSame( '', GravityForms::field_value( 'fbclid' ) );
		$this->assertSame( '', GravityForms::field_value( 'utm_medium' ) );
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

	public function test_injected_field_props_carry_no_cookie_values_or_merge_tags(): void {
		$_COOKIE['leadstream_referrer'] = 'https://www.google.com/';
		$_COOKIE['leadstream_click_id'] = 'TBMTEST123';

		foreach ( GravityForms::injected_field_props( array( 'id' => 7 ) ) as $props ) {
			$this->assertSame( '', $props['defaultValue'], $props['inputName'] );
		}
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
		$this->assertSame( '', $source['defaultValue'] );
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

	public function test_field_key_matches_canonical_and_prefixed_names(): void {
		$this->assertSame( 'utm_source', GravityForms::field_key( (object) array( 'inputName' => 'utm_source' ) ) );
		$this->assertSame( 'utm_medium', GravityForms::field_key( (object) array( 'inputName' => 'ls_utm_medium' ) ) );
		$this->assertSame( 'click_id', GravityForms::field_key( array( 'inputName' => 'leadstream_click_id' ) ) );
		$this->assertSame( 'first_page', GravityForms::field_key( array( 'inputName' => 'first_page' ) ) );
	}

	public function test_field_key_matches_click_id_alias_case_insensitively(): void {
		$this->assertSame( 'gclid', GravityForms::field_key( (object) array( 'inputName' => 'gclid' ) ) );
		$this->assertSame( 'gclid', GravityForms::field_key( (object) array( 'inputName' => 'GCLID' ) ) );
		$this->assertSame( 'ScCid', GravityForms::field_key( (object) array( 'inputName' => 'sccid' ) ) );
	}

	public function test_field_key_empty_for_unrelated_or_missing_names(): void {
		$this->assertSame( '', GravityForms::field_key( (object) array( 'inputName' => 'email' ) ) );
		$this->assertSame( '', GravityForms::field_key( (object) array( 'inputName' => 'gclid_flag' ) ) );
		$this->assertSame( '', GravityForms::field_key( (object) array( 'inputName' => '' ) ) );
		$this->assertSame( '', GravityForms::field_key( (object) array( 'id' => 3 ) ) );
	}

	public function test_value_for_key_reads_cookie_for_canonical_keys(): void {
		$_COOKIE['leadstream_utm_source'] = 'google';
		$this->assertSame( 'google', GravityForms::value_for_key( 'utm_source' ) );
		$this->assertSame( '', GravityForms::value_for_key( 'utm_medium' ) );
	}

	public function test_value_for_key_alias_only_when_click_id_type_matches(): void {
		$_COOKIE['leadstream_click_id']      = 'TBMTEST123';
		$_COOKIE['leadstream_click_id_type'] = 'gclid';

		$this->assertSame( 'TBMTEST123', GravityForms::value_for_key( 'gclid' ) );
		$this->assertSame( '', GravityForms::value_for_key( 'fbclid' ) );
	}

	/**
	 * The cached-page case: the HTML carried a previous visitor's values, the
	 * browser posts them back, and the visitor's own cookies must win.
	 */
	public function test_override_posted_values_replaces_stale_values_with_cookies(): void {
		$_COOKIE['leadstream_utm_source']    = 'google';
		$_COOKIE['leadstream_utm_medium']    = 'cpc';
		$_COOKIE['leadstream_click_id']      = 'TBMTEST123';
		$_COOKIE['leadstream_click_id_type'] = 'gclid';

		$_POST = array(
			'input_1'  => 'jane@example.com',
			'input_13' => 'google',
			'input_14' => 'organic',
			'input_16' => 'direct',
			'input_21' => '',
			'input_30' => 'OLDGCLID',
		);
		$form  = array(
			'id'     => 3,
			'fields' => array(
				(object) array( 'id' => 1, 'inputName' => '' ),
				(object) array( 'id' => 13, 'inputName' => 'utm_source' ),
				(object) array( 'id' => 14, 'inputName' => 'utm_medium' ),
				(object) array( 'id' => 16, 'inputName' => 'leadstream_utm_source' ),
				(object) array( 'id' => 21, 'inputName' => 'leadstream_click_id' ),
				(object) array( 'id' => 30, 'inputName' => 'gclid' ),
			),
		);

		GravityForms::override_posted_values( $form );

		$this->assertSame( 'jane@example.com', $_POST['input_1'] );
		$this->assertSame( 'google', $_POST['input_13'] );
		$this->assertSame( 'cpc', $_POST['input_14'] );
		$this->assertSame( 'google', $_POST['input_16'] );
		$this->assertSame( 'TBMTEST123', $_POST['input_21'] );
		$this->assertSame( 'TBMTEST123', $_POST['input_30'] );
	}

	public function test_override_posted_values_blanks_stale_values_when_no_cookie(): void {
		$_POST = array(
			'input_13' => 'google',
			'input_22' => 'https://controlstation.com/somebody-elses-landing-page/',
		);
		$form  = array(
			'fields' => array(
				array( 'id' => 13, 'inputName' => 'utm_source' ),
				array( 'id' => 22, 'inputName' => 'leadstream_first_page' ),
			),
		);

		GravityForms::override_posted_values( $form );

		$this->assertSame( '', $_POST['input_13'] );
		$this->assertSame( '', $_POST['input_22'] );
	}

	public function test_override_posted_values_noop_on_bad_form(): void {
		$_POST = array( 'input_1' => 'x' );
		GravityForms::override_posted_values( null );
		GravityForms::override_posted_values( array( 'id' => 1 ) );
		$this->assertSame( array( 'input_1' => 'x' ), $_POST );
	}

	public function test_field_css_class_tags_mapped_fields(): void {
		$this->assertSame(
			'gfield leadstream-injected leadstream-field-utm_source',
			GravityForms::field_css_class( 'gfield leadstream-injected', (object) array( 'inputName' => 'leadstream_utm_source' ) )
		);
		$this->assertSame(
			'gfield leadstream-field-gclid',
			GravityForms::field_css_class( 'gfield', (object) array( 'inputName' => 'gclid' ) )
		);
	}

	public function test_field_css_class_passthrough_for_unmapped_fields(): void {
		$this->assertSame( 'gfield', GravityForms::field_css_class( 'gfield', (object) array( 'inputName' => 'email' ) ) );
	}

	public function test_has_injected_fields(): void {
		$form = array( 'id' => 3, 'fields' => array( (object) array( 'id' => 1 ) ) );
		$this->assertFalse( GravityForms::has_injected_fields( $form ) );

		$form['fields'] = array_merge( $form['fields'], GravityForms::injected_field_props( $form ) );
		$this->assertTrue( GravityForms::has_injected_fields( $form ) );
	}
}
