<?php
/**
 * License abstraction. Wraps the licensing provider so feature code never calls it directly.
 * Currently returns free-tier defaults; Freemius wiring lands in Phase 2.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class License {

	public static function is_free(): bool {
		return ! self::is_pro() && ! self::is_agency();
	}

	public static function is_pro(): bool {
		return false;
	}

	public static function is_agency(): bool {
		return false;
	}

	public static function tier(): string {
		if ( self::is_agency() ) {
			return 'agency';
		}
		if ( self::is_pro() ) {
			return 'pro';
		}
		return 'free';
	}
}
