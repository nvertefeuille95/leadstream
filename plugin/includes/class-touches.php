<?php
/**
 * Multi-touch attribution storage. Every meaningful visit (a UTM-tagged,
 * click-ID-tagged, or external-referrer URL) writes a row to the touches
 * table, keyed by a long-lived visitor UUID. Submissions JOIN touches by
 * visitor_id at conversion time to apply the configured attribution model.
 *
 * Dedup strategy: touch_id is a deterministic hash of
 * (visitor_id, source, medium, campaign, click_id, minute_bucket) so a page
 * refresh or a quick second pageview within the same minute does not write
 * a duplicate row.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Touches {

	public const TABLE_SUFFIX         = 'leadstream_touches';
	public const DEDUP_BUCKET_SECONDS = 60;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Decide if the classified attribution counts as a meaningful new touch.
	 * Direct same-host navigation is filtered out before we get here
	 * (Classifier returns an empty array for internal nav). What we filter
	 * here is "no real signal" — both source and medium are 'direct' AND no
	 * click_id is present.
	 */
	public static function is_meaningful( array $data ): bool {
		if ( empty( $data ) ) {
			return false;
		}
		if ( ! empty( $data['click_id'] ) ) {
			return true;
		}
		$source = isset( $data['utm_source'] ) ? (string) $data['utm_source'] : '';
		$medium = isset( $data['utm_medium'] ) ? (string) $data['utm_medium'] : '';
		// Both 'direct' means classifier had nothing to work with; skip.
		return ! ( 'direct' === $source && 'direct' === $medium );
	}

	public static function deterministic_touch_id( string $visitor_id, array $data, ?int $bucket = null ): string {
		if ( null === $bucket ) {
			$bucket = (int) floor( time() / self::DEDUP_BUCKET_SECONDS );
		}
		$signature = implode(
			'|',
			array(
				$visitor_id,
				$data['utm_source'] ?? '',
				$data['utm_medium'] ?? '',
				$data['utm_campaign'] ?? '',
				$data['click_id'] ?? '',
				$data['click_id_type'] ?? '',
				(string) $bucket,
			)
		);
		return hash( 'sha256', $signature );
	}

	public static function record( string $visitor_id, array $data, array $context = array() ): int {
		global $wpdb;

		if ( '' === $visitor_id || ! Visitor::is_valid( $visitor_id ) ) {
			return 0;
		}
		if ( ! self::is_meaningful( $data ) ) {
			return 0;
		}

		$row = array(
			'visitor_id'    => $visitor_id,
			'touch_id'      => self::deterministic_touch_id( $visitor_id, $data ),
			'utm_source'    => isset( $data['utm_source'] ) ? (string) $data['utm_source'] : null,
			'utm_medium'    => isset( $data['utm_medium'] ) ? (string) $data['utm_medium'] : null,
			'utm_campaign'  => isset( $data['utm_campaign'] ) ? (string) $data['utm_campaign'] : null,
			'utm_term'      => isset( $data['utm_term'] ) ? (string) $data['utm_term'] : null,
			'utm_content'   => isset( $data['utm_content'] ) ? (string) $data['utm_content'] : null,
			'click_id'      => isset( $data['click_id'] ) ? (string) $data['click_id'] : null,
			'click_id_type' => isset( $data['click_id_type'] ) ? (string) $data['click_id_type'] : null,
			'referrer'      => isset( $context['referrer'] ) ? (string) $context['referrer'] : null,
			'page_url'      => isset( $context['page_url'] ) ? (string) $context['page_url'] : null,
			'ip_hash'       => isset( $context['ip_hash'] ) ? (string) $context['ip_hash'] : null,
			'user_agent'    => isset( $context['user_agent'] ) ? (string) $context['user_agent'] : null,
		);

		$filtered = array_filter(
			$row,
			static function ( $v ) {
				return null !== $v && '' !== $v;
			}
		);
		$formats  = array_fill( 0, count( $filtered ), '%s' );

		// INSERT IGNORE so dedup does not surface as an error.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$columns      = '`' . implode( '`, `', array_keys( $filtered ) ) . '`';
		$placeholders = implode( ', ', $formats );
		$values       = array_values( $filtered );
		$table        = self::table();
		$sql          = "INSERT IGNORE INTO {$table} ({$columns}) VALUES ({$placeholders})";
		$prepared     = $wpdb->prepare( $sql, $values );
		$wpdb->query( $prepared );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $wpdb->insert_id ? (int) $wpdb->insert_id : 0;
	}

	public static function for_visitor( string $visitor_id, int $limit = 50 ): array {
		global $wpdb;
		if ( '' === $visitor_id || ! Visitor::is_valid( $visitor_id ) ) {
			return array();
		}
		$table = self::table();
		$limit = max( 1, min( 200, $limit ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE visitor_id = %s ORDER BY created_at ASC LIMIT %d",
				$visitor_id,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	public static function count_all(): int {
		global $wpdb;
		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $count;
	}
}
