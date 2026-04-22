<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	/**
	 * @dataProvider durationProvider
	 */
	public function test_sanitize_duration( $input, int $expected ): void {
		$this->assertSame( $expected, Settings::sanitize_duration( $input ) );
	}

	public function durationProvider(): array {
		return array(
			'mid-range'      => array( 45, 45 ),
			'minimum'        => array( 1, 1 ),
			'maximum'        => array( 365, 365 ),
			'below min'      => array( 0, 30 ),
			'negative'       => array( -10, 30 ),
			'above max'      => array( 999, 365 ),
			'string numeric' => array( '60', 60 ),
			'non-numeric'    => array( 'banana', 30 ),
			'float'          => array( 14.7, 14 ),
		);
	}

	/**
	 * @dataProvider boolProvider
	 */
	public function test_sanitize_bool( $input, bool $expected ): void {
		$this->assertSame( $expected, Settings::sanitize_bool( $input ) );
	}

	public function boolProvider(): array {
		return array(
			'true bool'    => array( true, true ),
			'false bool'   => array( false, false ),
			'string 1'     => array( '1', true ),
			'string 0'     => array( '0', false ),
			'string true'  => array( 'true', true ),
			'string false' => array( 'false', false ),
			'string yes'   => array( 'yes', true ),
			'string on'    => array( 'on', true ),
			'empty string' => array( '', false ),
			'int 1'        => array( 1, true ),
			'int 0'        => array( 0, false ),
			'null'         => array( null, false ),
		);
	}

	public function test_action_links_prepends_settings_link(): void {
		$existing = array( '<a href="/deactivate">Deactivate</a>' );
		$result   = Settings::action_links( $existing );

		$this->assertCount( 2, $result );
		$this->assertStringContainsString( 'page=' . Settings::PAGE_SLUG, $result[0] );
		$this->assertStringContainsString( 'Settings', $result[0] );
		$this->assertSame( $existing[0], $result[1] );
	}
}
