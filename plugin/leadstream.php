<?php
/**
 * Plugin Name:       LeadStream by Timberbrook Marketing
 * Plugin URI:        https://leadstream.io
 * Description:       Attribution that flows through your whole funnel. Captures UTMs, click IDs, and referrer data; fills form hidden fields; and (Pro) pushes offline conversions back to ad platforms.
 * Version:           0.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Timberbrook Marketing
 * Author URI:        https://timberbrookmarketing.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       leadstream
 * Domain Path:       /languages
 *
 * @package LeadStream
 */

defined( 'ABSPATH' ) || exit;

define( 'LEADSTREAM_VERSION', '0.3.0' );
define( 'LEADSTREAM_FILE', __FILE__ );
define( 'LEADSTREAM_PATH', plugin_dir_path( __FILE__ ) );
define( 'LEADSTREAM_URL', plugin_dir_url( __FILE__ ) );
define( 'LEADSTREAM_BASENAME', plugin_basename( __FILE__ ) );

// Beta sites get verbose logging and rate-limit bypass. Remove before v1.0 public release.
define(
	'LEADSTREAM_BETA_DOMAINS',
	array(
		'timberbrookmarketing.com',
		'nolimitcarts.com',
		'controlstation.com',
		'nexeris.us',
	)
);

if ( file_exists( LEADSTREAM_PATH . 'vendor/autoload.php' ) ) {
	require_once LEADSTREAM_PATH . 'vendor/autoload.php';
}

register_activation_hook( __FILE__, array( '\LeadStream\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\LeadStream\Activator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\LeadStream\Core::instance()->init();
	}
);
