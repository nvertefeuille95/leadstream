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

	private function set_ad_click_cookies(): void {
		$_COOKIE['leadstream_utm_source']    = 'google';
		$_COOKIE['leadstream_utm_medium']    = 'cpc';
		$_COOKIE['leadstream_utm_campaign']  = 'af26';
		$_COOKIE['leadstream_click_id']      = 'GCLID123';
		$_COOKIE['leadstream_click_id_type'] = 'gclid';
		$_COOKIE['leadstream_first_page']    = 'https://example.com/landing?gclid=GCLID123';
		$_COOKIE['leadstream_referrer']      = 'https://www.google.com/';
	}

	public function test_touch_updates_first_visit_writes_the_touch(): void {
		$data = array(
			'utm_source' => 'direct',
			'utm_medium' => 'direct',
		);

		$this->assertSame( $data, Cookies::touch_updates( $data, '', 'last' ) );
	}

	public function test_touch_updates_first_visit_includes_referrer(): void {
		$data = array(
			'utm_source' => 'linkedin',
			'utm_medium' => 'social',
		);

		$result = Cookies::touch_updates( $data, 'https://www.linkedin.com/', 'last' );

		$this->assertSame( 'https://www.linkedin.com/', $result['referrer'] );
		$this->assertSame( 'linkedin', $result['utm_source'] );
	}

	public function test_touch_updates_first_model_keeps_existing_cookies(): void {
		$this->set_ad_click_cookies();
		$data = array(
			'utm_source'   => 'newsletter',
			'utm_medium'   => 'email',
			'utm_campaign' => 'nov',
		);

		$this->assertSame( array(), Cookies::touch_updates( $data, '', 'first' ) );
	}

	public function test_touch_updates_direct_revisit_keeps_existing_cookies(): void {
		$this->set_ad_click_cookies();
		$data = array(
			'utm_source' => 'direct',
			'utm_medium' => 'direct',
		);

		$this->assertSame( array(), Cookies::touch_updates( $data, '', 'last' ) );
		$this->assertSame( array(), Cookies::touch_updates( $data, '', 'linear' ) );
	}

	public function test_touch_updates_new_source_clears_stale_campaign_and_click_id(): void {
		$this->set_ad_click_cookies();
		$data = array(
			'utm_source' => 'google',
			'utm_medium' => 'organic',
		);

		$result = Cookies::touch_updates( $data, 'https://www.google.com/', 'last' );

		$this->assertSame(
			array(
				'utm_source'    => 'google',
				'utm_medium'    => 'organic',
				'utm_campaign'  => '',
				'click_id'      => '',
				'click_id_type' => '',
				'referrer'      => 'https://www.google.com/',
			),
			$result
		);
	}

	public function test_touch_updates_tagged_visit_without_referrer_clears_old_referrer(): void {
		$this->set_ad_click_cookies();
		$data = array(
			'utm_source'   => 'newsletter',
			'utm_medium'   => 'email',
			'utm_campaign' => 'nov',
		);

		$result = Cookies::touch_updates( $data, '', 'last' );

		$this->assertSame( '', $result['referrer'] );
		$this->assertSame( 'nov', $result['utm_campaign'] );
		$this->assertSame( '', $result['click_id'] );
	}

	public function test_touch_updates_never_touches_first_page(): void {
		$this->set_ad_click_cookies();
		$data = array(
			'utm_source' => 'bing',
			'utm_medium' => 'organic',
		);

		$this->assertArrayNotHasKey( 'first_page', Cookies::touch_updates( $data, 'https://www.bing.com/', 'last' ) );
	}
}
