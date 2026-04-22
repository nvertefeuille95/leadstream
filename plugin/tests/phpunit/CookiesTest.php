<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Cookies;
use PHPUnit\Framework\TestCase;

final class CookiesTest extends TestCase {

	protected function tearDown(): void {
		$_COOKIE = array();
		parent::tearDown();
	}

	public function test_get_returns_empty_when_cookie_missing(): void {
		$this->assertSame( '', Cookies::get( 'utm_source' ) );
	}

	public function test_get_reads_leadstream_prefixed_cookie(): void {
		$_COOKIE['leadstream_utm_source'] = 'google';
		$_COOKIE['utm_source']            = 'should-not-match';

		$this->assertSame( 'google', Cookies::get( 'utm_source' ) );
	}

	public function test_get_strips_slashes(): void {
		$_COOKIE['leadstream_click_id'] = "abc\\'xyz";
		$this->assertSame( "abc'xyz", Cookies::get( 'click_id' ) );
	}

	public function test_all_returns_only_non_empty_values(): void {
		$_COOKIE['leadstream_utm_source'] = 'linkedin';
		$_COOKIE['leadstream_utm_medium'] = '';
		$_COOKIE['leadstream_click_id']   = 'li_abc';

		$result = Cookies::all();

		$this->assertArrayHasKey( 'utm_source', $result );
		$this->assertArrayHasKey( 'click_id', $result );
		$this->assertArrayNotHasKey( 'utm_medium', $result );
		$this->assertSame( 'linkedin', $result['utm_source'] );
		$this->assertSame( 'li_abc', $result['click_id'] );
	}

	public function test_all_returns_empty_when_no_cookies(): void {
		$this->assertSame( array(), Cookies::all() );
	}

	public function test_field_keys_includes_all_attribution_fields(): void {
		$this->assertContains( 'utm_source', Cookies::FIELD_KEYS );
		$this->assertContains( 'click_id', Cookies::FIELD_KEYS );
		$this->assertContains( 'first_page', Cookies::FIELD_KEYS );
		$this->assertContains( 'referrer', Cookies::FIELD_KEYS );
		$this->assertCount( 9, Cookies::FIELD_KEYS );
	}
}
