<?php
/**
 * Attribution reporting. Loads events in a date range, joins touches by
 * visitor_id, applies the configured attribution model, and aggregates
 * credit per dimension (source, medium, campaign).
 *
 * Aggregation is done in PHP rather than SQL so we can reuse the same
 * Attribution model code paths the journey detail view already uses.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Reports {

	public const DIMENSIONS = array(
		'utm_source'   => 'Source',
		'utm_medium'   => 'Medium',
		'utm_campaign' => 'Campaign',
	);

	/**
	 * Load all events created in the given date range (inclusive), each
	 * decorated with a 'touches' array of the visitor's full journey up to
	 * (and including) the conversion. Caller passes UTC date strings.
	 */
	public static function load_events_with_touches( string $from, string $to ): array {
		global $wpdb;
		$table = Events::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE created_at BETWEEN %s AND %s ORDER BY created_at ASC",
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $events ) ) {
			return array();
		}

		foreach ( $events as $i => $event ) {
			$visitor_id              = isset( $event['visitor_id'] ) ? (string) $event['visitor_id'] : '';
			$events[ $i ]['touches'] = '' !== $visitor_id ? Touches::for_visitor( $visitor_id, 100 ) : array();
		}
		return $events;
	}

	/**
	 * Aggregate attributed credit by dimension across a list of events that
	 * have been decorated with their visitor touches. Pure function — no DB.
	 */
	public static function aggregate( array $events_with_touches, string $model, string $dimension ): array {
		$totals = array();
		$total  = 0.0;

		foreach ( $events_with_touches as $event ) {
			$touches = isset( $event['touches'] ) && is_array( $event['touches'] ) ? $event['touches'] : array();

			if ( empty( $touches ) ) {
				// No touches recorded; the event itself counts at full weight,
				// using whatever attribution was on the cookie at submit time.
				$value            = self::extract_dimension( $event, $dimension );
				$totals[ $value ] = ( $totals[ $value ] ?? 0.0 ) + 1.0;
				$total           += 1.0;
				continue;
			}

			$weighted = Attribution::apply( $touches, $model );
			foreach ( $weighted as $touch ) {
				$value  = self::extract_dimension( $touch, $dimension );
				$credit = isset( $touch['credit'] ) ? (float) $touch['credit'] : 0.0;
				if ( $credit <= 0.0 ) {
					continue;
				}
				$totals[ $value ] = ( $totals[ $value ] ?? 0.0 ) + $credit;
				$total           += $credit;
			}
		}

		arsort( $totals );

		$out = array();
		foreach ( $totals as $value => $credit ) {
			$out[] = array(
				'value'       => (string) $value,
				'conversions' => round( $credit, 2 ),
				'percentage'  => $total > 0 ? round( ( $credit / $total ) * 100, 1 ) : 0.0,
			);
		}
		return $out;
	}

	public static function summary( array $events_with_touches ): array {
		$conversions   = count( $events_with_touches );
		$visitors      = array();
		$total_touches = 0;

		foreach ( $events_with_touches as $event ) {
			if ( ! empty( $event['visitor_id'] ) ) {
				$visitors[ (string) $event['visitor_id'] ] = true;
			}
			$total_touches += isset( $event['touches'] ) && is_array( $event['touches'] ) ? count( $event['touches'] ) : 0;
		}

		return array(
			'conversions'                => $conversions,
			'unique_visitors'            => count( $visitors ),
			'avg_touches_per_conversion' => $conversions > 0 ? round( $total_touches / $conversions, 2 ) : 0.0,
		);
	}

	private static function extract_dimension( array $row, string $dimension ): string {
		if ( ! isset( $row[ $dimension ] ) ) {
			return '(unknown)';
		}
		$value = trim( (string) $row[ $dimension ] );
		return '' === $value ? '(unknown)' : $value;
	}
}
