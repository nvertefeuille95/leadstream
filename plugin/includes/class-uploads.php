<?php
/**
 * Queue for ad-platform conversion uploads. Form submissions write a row
 * per (event_id, platform); the cron worker drains pending/retrying rows
 * and marks success or schedules a retry with exponential backoff.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Uploads {

	public const TABLE_SUFFIX = 'leadstream_uploads';

	public const STATUS_PENDING  = 'pending';
	public const STATUS_RETRYING = 'retrying';
	public const STATUS_SUCCESS  = 'success';
	public const STATUS_FAILED   = 'failed';

	public const MAX_ATTEMPTS = 5;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function enqueue( array $data ): int {
		global $wpdb;

		$event_id = isset( $data['event_id'] ) ? (string) $data['event_id'] : '';
		$platform = isset( $data['platform'] ) ? (string) $data['platform'] : '';
		if ( '' === $event_id || '' === $platform ) {
			return 0;
		}

		$row = array(
			'event_id'         => $event_id,
			'platform'         => $platform,
			'status'           => self::STATUS_PENDING,
			'click_id'         => isset( $data['click_id'] ) ? (string) $data['click_id'] : null,
			'click_id_type'    => isset( $data['click_id_type'] ) ? (string) $data['click_id_type'] : null,
			'email_hash'       => isset( $data['email_hash'] ) ? (string) $data['email_hash'] : null,
			'conversion_value' => isset( $data['conversion_value'] ) ? (float) $data['conversion_value'] : null,
			'attempts'         => 0,
			'response'         => null,
			'next_retry_at'    => null,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert( self::table(), $row, $formats );
		return false === $result ? 0 : (int) $wpdb->insert_id;
	}

	public static function pending( int $limit = 50 ): array {
		global $wpdb;
		$table = self::table();
		$limit = max( 1, min( 200, $limit ) );
		$now   = current_time( 'mysql', true );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE status IN ('pending', 'retrying')
				 AND (next_retry_at IS NULL OR next_retry_at <= %s)
				 ORDER BY created_at ASC
				 LIMIT %d",
				$now,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	public static function recent( int $limit = 100 ): array {
		global $wpdb;
		$table = self::table();
		$limit = max( 1, min( 500, $limit ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	public static function mark_success( int $id, string $response ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			self::table(),
			array(
				'status'        => self::STATUS_SUCCESS,
				'response'      => self::truncate_response( $response ),
				'next_retry_at' => null,
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function mark_retry( int $id, int $attempts, string $response ): void {
		global $wpdb;

		$permanent = $attempts >= self::MAX_ATTEMPTS;
		$status    = $permanent ? self::STATUS_FAILED : self::STATUS_RETRYING;
		$next      = $permanent ? null : self::next_retry_at( $attempts );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			self::table(),
			array(
				'status'        => $status,
				'attempts'      => $attempts,
				'response'      => self::truncate_response( $response ),
				'next_retry_at' => $next,
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function next_retry_at( int $attempts ): string {
		$backoff_minutes = array( 30, 120, 360, 1440 );
		$idx             = max( 0, min( count( $backoff_minutes ) - 1, $attempts - 1 ) );
		return gmdate( 'Y-m-d H:i:s', time() + ( $backoff_minutes[ $idx ] * MINUTE_IN_SECONDS ) );
	}

	public static function hash_email( string $email ): string {
		$normalized = strtolower( trim( $email ) );
		if ( '' === $normalized || ! filter_var( $normalized, FILTER_VALIDATE_EMAIL ) ) {
			return '';
		}
		return hash( 'sha256', $normalized );
	}

	public static function extract_email_from_entry( array $entry ): string {
		foreach ( $entry as $value ) {
			if ( is_string( $value ) && filter_var( trim( $value ), FILTER_VALIDATE_EMAIL ) ) {
				return trim( $value );
			}
		}
		return '';
	}

	private static function truncate_response( string $response ): string {
		return substr( $response, 0, 4000 );
	}
}
