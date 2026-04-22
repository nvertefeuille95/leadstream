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

if ( ! defined( 'LEADSTREAM_VERSION' ) ) {
	define( 'LEADSTREAM_VERSION', '0.1.0-test' );
}

if ( ! defined( 'LEADSTREAM_URL' ) ) {
	define( 'LEADSTREAM_URL', 'http://example.test/wp-content/plugins/leadstream/' );
}

if ( ! defined( 'LEADSTREAM_BETA_DOMAINS' ) ) {
	define( 'LEADSTREAM_BETA_DOMAINS', array( 'timberbrookmarketing.com', 'nolimitcarts.com', 'controlstation.com', 'nexeris.us' ) );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? trim( $str ) : '';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

WP_Mock::bootstrap();
