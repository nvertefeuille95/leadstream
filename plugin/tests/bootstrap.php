<?php
/**
 * PHPUnit bootstrap. Loads Composer autoload and WP_Mock.
 *
 * @package LeadStream
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'LEADSTREAM_BASENAME' ) ) {
	define( 'LEADSTREAM_BASENAME', 'leadstream/leadstream.php' );
}

WP_Mock::bootstrap();
