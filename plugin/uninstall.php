<?php
/**
 * Uninstall handler. Runs only when the plugin is deleted from the WP admin,
 * and only deletes data if the opt-in setting is true.
 *
 * @package LeadStream
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$delete_data = (bool) get_option( 'leadstream_delete_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

global $wpdb;

$table_name = $wpdb->prefix . 'leadstream_events';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

delete_option( 'leadstream_schema_version' );
delete_option( 'leadstream_delete_on_uninstall' );
delete_option( 'leadstream_settings' );
