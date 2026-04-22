<?php
/**
 * Activation and deactivation hooks. Creates the events table via dbDelta.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public const SCHEMA_VERSION        = '1';
	public const SCHEMA_VERSION_OPTION = 'leadstream_schema_version';

	public static function activate(): void {
		self::create_events_table();
		update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	public static function deactivate(): void {
		// Preserve all data on deactivation. Destructive cleanup happens only in uninstall.php and only if opted in.
	}

	private static function create_events_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'leadstream_events';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id VARCHAR(64) NOT NULL,
			session_id VARCHAR(64) DEFAULT NULL,
			utm_source VARCHAR(255) DEFAULT NULL,
			utm_medium VARCHAR(255) DEFAULT NULL,
			utm_campaign VARCHAR(255) DEFAULT NULL,
			utm_term VARCHAR(255) DEFAULT NULL,
			utm_content VARCHAR(255) DEFAULT NULL,
			click_id VARCHAR(512) DEFAULT NULL,
			click_id_type VARCHAR(32) DEFAULT NULL,
			referrer TEXT DEFAULT NULL,
			landing_page TEXT DEFAULT NULL,
			submitted_page TEXT DEFAULT NULL,
			form_source VARCHAR(64) DEFAULT NULL,
			form_id VARCHAR(64) DEFAULT NULL,
			form_data LONGTEXT DEFAULT NULL,
			ip_hash VARCHAR(64) DEFAULT NULL,
			user_agent TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id),
			KEY created_at (created_at),
			KEY click_id (click_id(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
