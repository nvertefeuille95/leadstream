<?php
/**
 * Cron worker that drains the uploads queue. Runs every 15 minutes (custom
 * schedule), claims up to 50 pending/retrying rows, dispatches each to the
 * correct platform handler, and records success or schedules a retry.
 *
 * @package LeadStream
 */

namespace LeadStream;

use LeadStream\Platforms\GoogleAds;

defined( 'ABSPATH' ) || exit;

final class Worker {

	public const HOOK              = 'leadstream_process_uploads';
	public const SCHEDULE          = 'leadstream_fifteen_minutes';
	public const SCHEDULE_INTERVAL = 900; // 15 * 60

	public static function register(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( self::HOOK, array( __CLASS__, 'run' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, self::SCHEDULE, self::HOOK );
		}
	}

	public static function unregister(): void {
		$ts = wp_next_scheduled( self::HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
		}
	}

	public static function add_schedule( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			return $schedules;
		}
		$schedules[ self::SCHEDULE ] = array(
			'interval' => self::SCHEDULE_INTERVAL,
			'display'  => __( 'Every 15 Minutes (LeadStream)', 'leadstream' ),
		);
		return $schedules;
	}

	public static function run(): void {
		$rows = Uploads::pending( 50 );
		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			self::process( $row );
		}
	}

	private static function process( array $row ): void {
		$id       = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$platform = isset( $row['platform'] ) ? (string) $row['platform'] : '';
		if ( $id <= 0 || '' === $platform ) {
			return;
		}

		$attempts = isset( $row['attempts'] ) ? (int) $row['attempts'] + 1 : 1;

		$result = self::dispatch( $platform, $row );

		if ( $result['success'] ) {
			Uploads::mark_success( $id, (string) $result['response'] );
			return;
		}

		// If the failure is non-transient (bad creds, bad config, 4xx), fail immediately.
		if ( empty( $result['transient'] ) ) {
			Uploads::mark_retry( $id, Uploads::MAX_ATTEMPTS, (string) $result['response'] );
			return;
		}

		Uploads::mark_retry( $id, $attempts, (string) $result['response'] );
	}

	private static function dispatch( string $platform, array $row ): array {
		switch ( $platform ) {
			case GoogleAds::PLATFORM:
				return GoogleAds::upload_click_conversion( $row );
		}

		return array(
			'success'   => false,
			'response'  => 'Unknown platform: ' . $platform,
			'transient' => false,
		);
	}
}
