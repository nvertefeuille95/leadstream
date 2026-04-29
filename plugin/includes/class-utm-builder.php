<?php
/**
 * UTM Builder. Generates tagged URLs from a base URL plus utm_* parameters,
 * with taxonomy drift detection (warn on "Google" vs "google" case mismatch
 * against previously-seen values in the touches/events tables) and bulk CSV
 * processing.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class UtmBuilder {

	public const FIELDS = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );

	public static function build( string $base_url, array $params ): string {
		$base = trim( $base_url );
		if ( '' === $base ) {
			return '';
		}

		$clean = self::clean_params( $params );
		if ( empty( $clean ) ) {
			return $base;
		}

		$parts = wp_parse_url( $base );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $base;
		}

		$existing = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $existing );
		}
		$merged = array_merge( $existing, $clean );

		$rebuilt  = ( $parts['scheme'] ?? 'https' ) . '://';
		$rebuilt .= $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$rebuilt .= ':' . $parts['port'];
		}
		$rebuilt .= $parts['path'] ?? '';
		$rebuilt .= '?' . http_build_query( $merged );
		if ( ! empty( $parts['fragment'] ) ) {
			$rebuilt .= '#' . $parts['fragment'];
		}
		return $rebuilt;
	}

	public static function clean_params( array $params ): array {
		$clean = array();
		foreach ( self::FIELDS as $key ) {
			if ( isset( $params[ $key ] ) && is_string( $params[ $key ] ) ) {
				$value = trim( $params[ $key ] );
				if ( '' !== $value ) {
					$clean[ $key ] = $value;
				}
			}
		}
		return $clean;
	}

	/**
	 * If the new value differs only by case from an existing value, return the
	 * existing one as a suggested correction. Returns null when the value is
	 * brand new or already case-consistent.
	 */
	public static function detect_taxonomy_drift( string $value, array $known ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		foreach ( $known as $known_value ) {
			if ( ! is_string( $known_value ) ) {
				continue;
			}
			if ( $known_value === $value ) {
				return null;
			}
			if ( strtolower( $known_value ) === strtolower( $value ) ) {
				return $known_value;
			}
		}
		return null;
	}

	public static function known_values( string $field ): array {
		global $wpdb;
		if ( ! in_array( $field, array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ), true ) ) {
			return array();
		}
		$touches = $wpdb->prefix . 'leadstream_touches';
		$events  = $wpdb->prefix . 'leadstream_events';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_col(
			"SELECT DISTINCT {$field} FROM {$touches} WHERE {$field} IS NOT NULL AND {$field} != ''
			 UNION
			 SELECT DISTINCT {$field} FROM {$events} WHERE {$field} IS NOT NULL AND {$field} != ''
			 ORDER BY 1 ASC"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? array_values( array_filter( $rows, 'is_string' ) ) : array();
	}

	/**
	 * Parse a bulk CSV input. Expected header row is base_url,utm_source,
	 * utm_medium,utm_campaign,utm_term,utm_content (utm_term and utm_content
	 * optional). Returns an array of normalized rows.
	 */
	public static function parse_bulk_csv( string $csv ): array {
		$csv = trim( $csv );
		if ( '' === $csv ) {
			return array();
		}
		$lines = preg_split( '/\r\n|\r|\n/', $csv );
		if ( ! is_array( $lines ) || count( $lines ) < 2 ) {
			return array();
		}

		$header = str_getcsv( array_shift( $lines ) );
		$header = array_map(
			static function ( $h ) {
				return strtolower( trim( (string) $h ) );
			},
			$header
		);
		$out    = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$cols = str_getcsv( $line );
			$row  = array();
			foreach ( $header as $idx => $name ) {
				$row[ $name ] = isset( $cols[ $idx ] ) ? trim( (string) $cols[ $idx ] ) : '';
			}
			if ( ! empty( $row['base_url'] ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	public static function generate_bulk_csv( array $rows ): string {
		$header  = array( 'base_url', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'tagged_url' );
		$lines   = array();
		$lines[] = self::csv_line( $header );
		foreach ( $rows as $row ) {
			$tagged  = self::build( (string) ( $row['base_url'] ?? '' ), $row );
			$lines[] = self::csv_line(
				array(
					$row['base_url'] ?? '',
					$row['utm_source'] ?? '',
					$row['utm_medium'] ?? '',
					$row['utm_campaign'] ?? '',
					$row['utm_term'] ?? '',
					$row['utm_content'] ?? '',
					$tagged,
				)
			);
		}
		return implode( "\n", $lines );
	}

	private static function csv_line( array $cols ): string {
		$escaped = array_map(
			static function ( $c ) {
				$c = (string) $c;
				if ( false !== strpos( $c, ',' ) || false !== strpos( $c, '"' ) || false !== strpos( $c, "\n" ) ) {
					return '"' . str_replace( '"', '""', $c ) . '"';
				}
				return $c;
			},
			$cols
		);
		return implode( ',', $escaped );
	}
}
