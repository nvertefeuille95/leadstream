<?php
/**
 * Storage for captured attribution events. Backed by the leadstream_events
 * table created on activation. Form integrations call record() on each
 * submission; the admin view reads recent() for display.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Events {

	public const TABLE_SUFFIX = 'leadstream_events';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function record( array $overrides = array() ): int {
		global $wpdb;

		$cookies    = Cookies::all();
		$visitor_id = Visitor::current();
		$row        = array_merge(
			array(
				'event_id'       => self::uuid(),
				'visitor_id'     => '' !== $visitor_id ? $visitor_id : null,
				'utm_source'     => $cookies['utm_source'] ?? null,
				'utm_medium'     => $cookies['utm_medium'] ?? null,
				'utm_campaign'   => $cookies['utm_campaign'] ?? null,
				'utm_term'       => $cookies['utm_term'] ?? null,
				'utm_content'    => $cookies['utm_content'] ?? null,
				'click_id'       => $cookies['click_id'] ?? null,
				'click_id_type'  => $cookies['click_id_type'] ?? null,
				'referrer'       => $cookies['referrer'] ?? null,
				'landing_page'   => $cookies['first_page'] ?? null,
				'submitted_page' => self::current_url(),
				'form_source'    => null,
				'form_id'        => null,
				'ip_hash'        => self::ip_hash(),
				'user_agent'     => self::user_agent(),
			),
			$overrides
		);

		$filtered = array_filter(
			$row,
			static function ( $v ) {
				return null !== $v && '' !== $v;
			}
		);

		if ( empty( $filtered['event_id'] ) ) {
			return 0;
		}

		$formats = array_fill( 0, count( $filtered ), '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert( self::table(), $filtered, $formats );
		return false === $result ? 0 : (int) $wpdb->insert_id;
	}

	public static function recent( int $limit = 100 ): array {
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		$table = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ),
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

	public static function uuid(): string {
		try {
			$data = random_bytes( 16 );
		} catch ( \Throwable $e ) {
			return '';
		}
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	private static function current_url(): string {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $host ) {
			return '';
		}
		$scheme = ( function_exists( 'is_ssl' ) && is_ssl() ) ? 'https' : 'http';
		return $scheme . '://' . $host . $uri;
	}

	private static function ip_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return '' !== $ip ? hash( 'sha256', $ip ) : '';
	}

	private static function user_agent(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return '' !== $ua ? substr( $ua, 0, 500 ) : '';
	}
}
