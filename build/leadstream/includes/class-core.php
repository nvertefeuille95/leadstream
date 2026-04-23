<?php
/**
 * Core bootstrap. Single entry point for runtime wiring.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Core {

	private static ?Core $instance = null;

	public static function instance(): Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		load_plugin_textdomain( 'leadstream', false, dirname( LEADSTREAM_BASENAME ) . '/languages' );
		Assets::register();
		Capture::register();
		Forms\GravityForms::register();
		Forms\ElementorForms::register();
		if ( is_admin() ) {
			Admin::register();
			Settings::register();
		}
	}
}
