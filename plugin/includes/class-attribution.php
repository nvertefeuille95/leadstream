<?php
/**
 * Attribution model logic. Given an ordered list of touches (oldest first)
 * and a model name, returns the same list with a 'credit' float on each
 * touch that sums to 1.0. The journey detail UI uses this to highlight
 * which touch(es) "win" the conversion.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Attribution {

	public const MODEL_LAST     = 'last';
	public const MODEL_FIRST    = 'first';
	public const MODEL_LINEAR   = 'linear';
	public const MODEL_POSITION = 'position';
	public const MODEL_TIME     = 'time';

	/**
	 * Position-based weights. First and last touch each get 40%, middle
	 * touches share the remaining 20%.
	 */
	private const POSITION_FIRST_LAST = 0.4;
	private const POSITION_MIDDLE     = 0.2;

	/**
	 * Time-decay half-life in seconds. Default 7 days; touches twice as old
	 * as the most recent get half the weight, and so on.
	 */
	private const TIME_DECAY_HALF_LIFE_SECONDS = 604800;

	public static function apply( array $touches, string $model = self::MODEL_LAST ): array {
		$count = count( $touches );
		if ( 0 === $count ) {
			return array();
		}

		$weights = self::weights( $count, $touches, $model );

		foreach ( $touches as $i => $touch ) {
			$touches[ $i ]['credit'] = isset( $weights[ $i ] ) ? round( $weights[ $i ], 6 ) : 0.0;
		}
		return $touches;
	}

	public static function weights( int $count, array $touches, string $model ): array {
		if ( 0 === $count ) {
			return array();
		}
		if ( 1 === $count ) {
			return array( 1.0 );
		}

		switch ( $model ) {
			case self::MODEL_FIRST:
				$w    = array_fill( 0, $count, 0.0 );
				$w[0] = 1.0;
				return $w;

			case self::MODEL_LINEAR:
				return array_fill( 0, $count, 1.0 / $count );

			case self::MODEL_POSITION:
				return self::position_weights( $count );

			case self::MODEL_TIME:
				return self::time_decay_weights( $touches );

			case self::MODEL_LAST:
			default:
				$w               = array_fill( 0, $count, 0.0 );
				$w[ $count - 1 ] = 1.0;
				return $w;
		}
	}

	private static function position_weights( int $count ): array {
		if ( 2 === $count ) {
			// Edge case: only first and last; split 50/50 to avoid 0.4 + 0.4 = 0.8.
			return array( 0.5, 0.5 );
		}
		$w               = array_fill( 0, $count, 0.0 );
		$w[0]            = self::POSITION_FIRST_LAST;
		$w[ $count - 1 ] = self::POSITION_FIRST_LAST;
		$middle_share    = self::POSITION_MIDDLE / ( $count - 2 );
		for ( $i = 1; $i < $count - 1; $i++ ) {
			$w[ $i ] = $middle_share;
		}
		return $w;
	}

	private static function time_decay_weights( array $touches ): array {
		// Anchor the most recent touch to 1.0 and decay backwards by half-life.
		$count = count( $touches );
		if ( 0 === $count ) {
			return array();
		}

		$last_ts = self::timestamp_of( end( $touches ) );
		reset( $touches );

		$raw = array();
		foreach ( $touches as $t ) {
			$ts    = self::timestamp_of( $t );
			$age   = max( 0, $last_ts - $ts );
			$decay = pow( 0.5, $age / self::TIME_DECAY_HALF_LIFE_SECONDS );
			$raw[] = $decay;
		}

		$sum = array_sum( $raw );
		if ( 0.0 === $sum ) {
			return array_fill( 0, $count, 1.0 / $count );
		}
		return array_map(
			static function ( $w ) use ( $sum ) {
				return $w / $sum;
			},
			$raw
		);
	}

	private static function timestamp_of( array $touch ): int {
		if ( ! isset( $touch['created_at'] ) ) {
			return 0;
		}
		$ts = strtotime( (string) $touch['created_at'] . ' UTC' );
		return false === $ts ? 0 : (int) $ts;
	}
}
