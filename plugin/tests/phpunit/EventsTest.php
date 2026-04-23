<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Events;
use PHPUnit\Framework\TestCase;

final class EventsTest extends TestCase {

	public function test_uuid_returns_36_character_v4_format(): void {
		$uuid = Events::uuid();

		$this->assertSame( 36, strlen( $uuid ) );
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$uuid
		);
	}

	public function test_uuid_returns_unique_values(): void {
		$a = Events::uuid();
		$b = Events::uuid();
		$this->assertNotSame( $a, $b );
	}

	public function test_table_uses_wpdb_prefix(): void {
		global $wpdb;
		$wpdb        = new \stdClass();
		$wpdb->prefix = 'wp_test_';

		$this->assertSame( 'wp_test_leadstream_events', Events::table() );
	}
}
