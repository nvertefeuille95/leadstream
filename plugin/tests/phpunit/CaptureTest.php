<?php
/**
 * @package LeadStream
 */

namespace LeadStream\Tests;

use LeadStream\Capture;
use PHPUnit\Framework\TestCase;
use WP_Mock;

final class CaptureTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_register_skips_server_capture_by_default(): void {
		WP_Mock::expectActionNotAdded( 'init', array( Capture::class, 'maybe_capture' ), 5 );

		Capture::register();

		WP_Mock::assertHooksAdded();
		$this->addToAssertionCount( 1 );
	}

	public function test_register_hooks_init_when_filter_enables_server_capture(): void {
		WP_Mock::onFilter( 'leadstream_server_capture' )->with( false )->reply( true );
		WP_Mock::expectActionAdded( 'init', array( Capture::class, 'maybe_capture' ), 5 );

		Capture::register();

		WP_Mock::assertHooksAdded();
		$this->addToAssertionCount( 1 );
	}
}
