<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Classifier;
use PHPUnit\Framework\TestCase;

final class ClassifierTest extends TestCase {

	/**
	 * @dataProvider clickIdProvider
	 */
	public function test_detects_click_id( string $param, string $source, string $medium ): void {
		$result = Classifier::detect_click_id( array( $param => 'abc123' ) );

		$this->assertNotNull( $result );
		$this->assertSame( $param, $result['type'] );
		$this->assertSame( 'abc123', $result['id'] );
		$this->assertSame( $source, $result['source'] );
		$this->assertSame( $medium, $result['medium'] );
	}

	public function clickIdProvider(): array {
		return array(
			'gclid'      => array( 'gclid', 'google', 'cpc' ),
			'dclid'      => array( 'dclid', 'google', 'display' ),
			'gbraid'     => array( 'gbraid', 'google', 'cpc' ),
			'wbraid'     => array( 'wbraid', 'google', 'cpc' ),
			'gad_source' => array( 'gad_source', 'google', 'cpc' ),
			'msclkid'    => array( 'msclkid', 'bing', 'cpc' ),
			'fbclid'     => array( 'fbclid', 'facebook', 'cpc' ),
			'ttclid'     => array( 'ttclid', 'tiktok', 'cpc' ),
			'twclid'     => array( 'twclid', 'twitter', 'cpc' ),
			'li_fat_id'  => array( 'li_fat_id', 'linkedin', 'cpc' ),
			'ScCid'      => array( 'ScCid', 'snapchat', 'cpc' ),
			'epik'       => array( 'epik', 'pinterest', 'cpc' ),
		);
	}

	public function test_detect_click_id_returns_null_for_empty_params(): void {
		$this->assertNull( Classifier::detect_click_id( array() ) );
	}

	public function test_detect_click_id_ignores_empty_string_values(): void {
		$this->assertNull( Classifier::detect_click_id( array( 'gclid' => '' ) ) );
	}

	public function test_detect_click_id_ignores_non_string_values(): void {
		$this->assertNull( Classifier::detect_click_id( array( 'gclid' => 123 ) ) );
	}

	public function test_detect_click_id_first_match_wins(): void {
		$result = Classifier::detect_click_id(
			array(
				'fbclid' => 'fb-value',
				'gclid'  => 'g-value',
			)
		);
		$this->assertSame( 'gclid', $result['type'] );
	}

	/**
	 * @dataProvider referrerProvider
	 */
	public function test_classifies_referrer( string $referrer, string $source, string $medium ): void {
		$result = Classifier::classify_referrer( $referrer, 'mysite.com' );

		$this->assertNotNull( $result );
		$this->assertSame( $source, $result['source'] );
		$this->assertSame( $medium, $result['medium'] );
	}

	public function referrerProvider(): array {
		return array(
			'google.com'             => array( 'https://www.google.com/search', 'google', 'organic' ),
			'google.co.uk'           => array( 'https://www.google.co.uk/search', 'google', 'organic' ),
			'bing.com'               => array( 'https://www.bing.com/', 'bing', 'organic' ),
			'yahoo.com'              => array( 'https://www.yahoo.com/', 'yahoo', 'organic' ),
			'duckduckgo.com'         => array( 'https://duckduckgo.com/', 'duckduckgo', 'organic' ),
			'baidu.com'              => array( 'https://www.baidu.com/', 'baidu', 'organic' ),
			'yandex.ru'              => array( 'https://yandex.ru/', 'yandex', 'organic' ),
			'ecosia.org'             => array( 'https://www.ecosia.org/', 'ecosia', 'organic' ),
			'search.brave.com'       => array( 'https://search.brave.com/', 'brave', 'organic' ),
			'facebook.com'           => array( 'https://www.facebook.com/post/123', 'facebook', 'social' ),
			'l.facebook.com'         => array( 'https://l.facebook.com/l.php', 'facebook', 'social' ),
			'fb.com'                 => array( 'https://fb.com/page', 'facebook', 'social' ),
			'instagram.com'          => array( 'https://www.instagram.com/profile', 'instagram', 'social' ),
			'linkedin.com'           => array( 'https://www.linkedin.com/feed', 'linkedin', 'social' ),
			'lnkd.in'                => array( 'https://lnkd.in/abc', 'linkedin', 'social' ),
			'twitter.com'            => array( 'https://twitter.com/status/1', 'twitter', 'social' ),
			'x.com'                  => array( 'https://x.com/status/1', 'twitter', 'social' ),
			't.co'                   => array( 'https://t.co/xyz', 'twitter', 'social' ),
			'tiktok.com'             => array( 'https://www.tiktok.com/@user', 'tiktok', 'social' ),
			'youtube.com'            => array( 'https://www.youtube.com/watch', 'youtube', 'social' ),
			'youtu.be'               => array( 'https://youtu.be/abc', 'youtube', 'social' ),
			'pinterest.com'          => array( 'https://www.pinterest.com/pin/1', 'pinterest', 'social' ),
			'pin.it'                 => array( 'https://pin.it/xyz', 'pinterest', 'social' ),
			'reddit.com'             => array( 'https://www.reddit.com/r/x', 'reddit', 'social' ),
			'snapchat.com'           => array( 'https://www.snapchat.com/', 'snapchat', 'social' ),
			'threads.net'            => array( 'https://www.threads.net/@user', 'threads', 'social' ),
			'whatsapp.com'           => array( 'https://www.whatsapp.com/', 'whatsapp', 'messaging' ),
			'wa.me'                  => array( 'https://wa.me/1234', 'whatsapp', 'messaging' ),
			'telegram.org'           => array( 'https://telegram.org/', 'telegram', 'messaging' ),
			't.me'                   => array( 'https://t.me/channel', 'telegram', 'messaging' ),
			'github.com'             => array( 'https://github.com/user/repo', 'github', 'referral' ),
		);
	}

	public function test_empty_referrer_is_direct(): void {
		$result = Classifier::classify_referrer( '', 'mysite.com' );
		$this->assertSame( 'direct', $result['source'] );
		$this->assertSame( 'direct', $result['medium'] );
	}

	public function test_internal_referrer_returns_null(): void {
		$this->assertNull(
			Classifier::classify_referrer( 'https://mysite.com/about', 'mysite.com' )
		);
	}

	public function test_internal_referrer_is_case_insensitive(): void {
		$this->assertNull(
			Classifier::classify_referrer( 'https://MySite.com/about', 'mysite.com' )
		);
	}

	public function test_unknown_referrer_falls_back_to_host(): void {
		$result = Classifier::classify_referrer( 'https://randomforum.example/', 'mysite.com' );
		$this->assertSame( 'randomforum.example', $result['source'] );
		$this->assertSame( 'referral', $result['medium'] );
	}

	public function test_malformed_referrer_returns_unknown(): void {
		$result = Classifier::classify_referrer( 'not a url', 'mysite.com' );
		$this->assertSame( 'unknown', $result['source'] );
		$this->assertSame( 'referral', $result['medium'] );
	}

	public function test_precedence_utm_wins_over_click_id(): void {
		$result = Classifier::classify(
			array(
				'utm_source' => 'newsletter',
				'utm_medium' => 'email',
				'gclid'      => 'abc',
			),
			'https://google.com/',
			'mysite.com'
		);

		$this->assertSame( 'newsletter', $result['utm_source'] );
		$this->assertSame( 'email', $result['utm_medium'] );
		$this->assertSame( 'abc', $result['click_id'] );
		$this->assertSame( 'gclid', $result['click_id_type'] );
	}

	public function test_precedence_utm_source_without_medium_borrows_from_click_id(): void {
		$result = Classifier::classify(
			array(
				'utm_source' => 'newsletter',
				'gclid'      => 'abc',
			)
		);

		$this->assertSame( 'newsletter', $result['utm_source'] );
		$this->assertSame( 'cpc', $result['utm_medium'] );
	}

	public function test_precedence_utm_source_without_medium_or_click_id_is_unknown(): void {
		$result = Classifier::classify( array( 'utm_source' => 'newsletter' ) );

		$this->assertSame( 'newsletter', $result['utm_source'] );
		$this->assertSame( 'unknown', $result['utm_medium'] );
	}

	public function test_precedence_click_id_wins_over_referrer(): void {
		$result = Classifier::classify(
			array( 'fbclid' => 'fb-abc' ),
			'https://www.google.com/',
			'mysite.com'
		);

		$this->assertSame( 'facebook', $result['utm_source'] );
		$this->assertSame( 'cpc', $result['utm_medium'] );
		$this->assertSame( 'fb-abc', $result['click_id'] );
	}

	public function test_precedence_referrer_wins_over_direct(): void {
		$result = Classifier::classify(
			array(),
			'https://www.linkedin.com/',
			'mysite.com'
		);

		$this->assertSame( 'linkedin', $result['utm_source'] );
		$this->assertSame( 'social', $result['utm_medium'] );
	}

	public function test_precedence_no_signals_is_direct(): void {
		$result = Classifier::classify( array(), '', 'mysite.com' );

		$this->assertSame( 'direct', $result['utm_source'] );
		$this->assertSame( 'direct', $result['utm_medium'] );
	}

	public function test_internal_navigation_returns_empty(): void {
		$result = Classifier::classify(
			array(),
			'https://mysite.com/home',
			'mysite.com'
		);

		$this->assertSame( array(), $result );
	}

	public function test_includes_campaign_term_content_when_present(): void {
		$result = Classifier::classify(
			array(
				'utm_source'   => 'google',
				'utm_medium'   => 'cpc',
				'utm_campaign' => 'spring_sale',
				'utm_term'     => 'attribution+tool',
				'utm_content'  => 'banner_a',
			)
		);

		$this->assertSame( 'spring_sale', $result['utm_campaign'] );
		$this->assertSame( 'attribution+tool', $result['utm_term'] );
		$this->assertSame( 'banner_a', $result['utm_content'] );
	}

	public function test_omits_optional_fields_when_empty(): void {
		$result = Classifier::classify( array( 'utm_source' => 'google', 'utm_medium' => 'cpc' ) );

		$this->assertArrayNotHasKey( 'utm_campaign', $result );
		$this->assertArrayNotHasKey( 'utm_term', $result );
		$this->assertArrayNotHasKey( 'utm_content', $result );
		$this->assertArrayNotHasKey( 'click_id', $result );
	}
}
