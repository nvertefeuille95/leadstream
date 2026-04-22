<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\License;
use PHPUnit\Framework\TestCase;

final class LicenseTest extends TestCase {

	public function test_free_tier_is_default(): void {
		$this->assertTrue( License::is_free() );
		$this->assertFalse( License::is_pro() );
		$this->assertFalse( License::is_agency() );
		$this->assertSame( 'free', License::tier() );
	}
}
