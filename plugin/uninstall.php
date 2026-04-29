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

$events  = $wpdb->prefix . 'leadstream_events';
$uploads = $wpdb->prefix . 'leadstream_uploads';
$touches = $wpdb->prefix . 'leadstream_touches';

$wpdb->query( "DROP TABLE IF EXISTS {$events}" );  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$uploads}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$touches}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

$options_to_drop = array(
	'leadstream_schema_version',
	'leadstream_delete_on_uninstall',
	'leadstream_settings',
	'leadstream_cookie_duration',
	'leadstream_subdomain_tracking',
	'leadstream_require_consent',
	'leadstream_debug',
	'leadstream_gf_auto_inject',
	'leadstream_gf_auto_append_notifications',
	'leadstream_elementor_auto_inject',
	'leadstream_elementor_email_append',
	'leadstream_universal_inject',
	'leadstream_attribution_model',
	'leadstream_touches_retention_days',
	'leadstream_gads_enabled',
	'leadstream_gads_developer_token',
	'leadstream_gads_refresh_token',
	'leadstream_gads_client_id',
	'leadstream_gads_client_secret',
	'leadstream_gads_customer_id',
	'leadstream_gads_login_customer_id',
	'leadstream_gads_conversion_action',
	'leadstream_gads_default_value',
	'leadstream_gads_currency',
	'leadstream_gads_api_version',
);
foreach ( $options_to_drop as $opt ) {
	delete_option( $opt );
}

delete_transient( 'leadstream_gads_access_token' );
