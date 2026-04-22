<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Forms\GravityForms;
use PHPUnit\Framework\TestCase;

final class GravityFormsTest extends TestCase {

	protected function tearDown(): void {
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
}
