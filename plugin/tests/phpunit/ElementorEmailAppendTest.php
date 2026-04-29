<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Forms\ElementorForms;
use PHPUnit\Framework\TestCase;
use WP_Mock;

final class ElementorEmailAppendTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		$_COOKIE = array();
		parent::tearDown();
	}

	public function test_no_op_when_setting_disabled(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_elementor_email_append', false )
			->andReturn( false );

		$_COOKIE['leadstream_utm_source'] = 'google';

		$this->assertSame( 'Hello', ElementorForms::append_attribution_to_email( 'Hello' ) );
	}

	public function test_no_op_when_no_cookies(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_elementor_email_append', false )
			->andReturn( true );

		$this->assertSame( 'Hello', ElementorForms::append_attribution_to_email( 'Hello' ) );
	}

	public function test_appends_to_plain_text_email(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_elementor_email_append', false )
			->andReturn( true );

		$_COOKIE['leadstream_utm_source']    = 'google';
		$_COOKIE['leadstream_utm_medium']    = 'cpc';
		$_COOKIE['leadstream_click_id']      = 'gclid_abc';
		$_COOKIE['leadstream_click_id_type'] = 'gclid';

		$result = ElementorForms::append_attribution_to_email( 'Form submission body' );

		$this->assertStringContainsString( 'Form submission body', $result );
		$this->assertStringContainsString( 'LeadStream Attribution', $result );
		$this->assertStringContainsString( 'Source: google', $result );
		$this->assertStringContainsString( 'Medium: cpc', $result );
		$this->assertStringContainsString( 'gclid: gclid_abc', $result );
		$this->assertStringNotContainsString( '<br', $result );
	}

	public function test_uses_html_breaks_when_email_is_html(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_elementor_email_append', false )
			->andReturn( true );

		$_COOKIE['leadstream_utm_source'] = 'google';

		$html_body = '<html><body><p>Form submitted</p><br /></body></html>';
		$result    = ElementorForms::append_attribution_to_email( $html_body );

		$this->assertStringContainsString( '<br', $result );
		$this->assertStringContainsString( 'Source: google', $result );
	}

	public function test_returns_input_unchanged_for_non_string(): void {
		$this->assertNull( ElementorForms::append_attribution_to_email( null ) );
		$this->assertSame( 42, ElementorForms::append_attribution_to_email( 42 ) );
	}

	public function test_gclid_line_only_populated_when_click_id_type_is_gclid(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'leadstream_elementor_email_append', false )
			->andReturn( true );

		$_COOKIE['leadstream_click_id']      = 'fb_xyz';
		$_COOKIE['leadstream_click_id_type'] = 'fbclid';

		$result = ElementorForms::append_attribution_to_email( 'body' );

		$this->assertStringContainsString( 'gclid: ', $result );
		$this->assertStringNotContainsString( 'gclid: fb_xyz', $result );
		$this->assertStringContainsString( 'Click ID: fb_xyz', $result );
	}
}
