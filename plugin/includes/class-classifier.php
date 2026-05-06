<?php
/**
 * Attribution classifier. Pure logic, no WP dependencies. Mirrors the JS
 * classifier in assets/js/capture.js so both runtimes produce identical
 * results for a given URL/referrer pair.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Classifier {

	public const CLICK_IDS = array(
		array(
			'param'  => 'gclid',
			'source' => 'google',
			'medium' => 'cpc',
		),
		array(
			'param'  => 'dclid',
			'source' => 'google',
			'medium' => 'display',
		),
		array(
			'param'  => 'gbraid',
			'source' => 'google',
			'medium' => 'cpc',
		),
		array(
			'param'  => 'wbraid',
			'source' => 'google',
			'medium' => 'cpc',
		),
		array(
			'param'  => 'gad_source',
			'source' => 'google',
			'medium' => 'cpc',
		),
		array(
			'param'  => 'msclkid',
			'source' => 'bing',
			'medium' => 'cpc',
		),
		array(
			'param'  => 'fbclid',
			'source' => 'facebook',
			'medium' => 'cpc',
		),
		array(
			'param'  => 'ttclid',
			'source' => 'tiktok',
			'medium' => 'cpc',
		),
		array(
			'param'  => 'twclid',
			'source' => 'twitter',
			'medium' => 'cpc',
		),
		array(
			'param'  => 'li_fat_id',
			'source' => 'linkedin',
			'medium' => 'cpc',
		),
		array(
			'param'  => 'ScCid',
			'source' => 'snapchat',
			'medium' => 'cpc',
		),
		array(
			'param'  => 'epik',
			'source' => 'pinterest',
			'medium' => 'cpc',
		),
	);

	public const REFERRER_MAP = array(
		array(
			'pattern' => '/(^|\.)google\./',
			'source'  => 'google',
			'medium'  => 'organic',
		),
		array(
			'pattern' => '/(^|\.)bing\./',
			'source'  => 'bing',
			'medium'  => 'organic',
		),
		array(
			'pattern' => '/(^|\.)yahoo\./',
			'source'  => 'yahoo',
			'medium'  => 'organic',
		),
		array(
			'pattern' => '/(^|\.)duckduckgo\.com$/',
			'source'  => 'duckduckgo',
			'medium'  => 'organic',
		),
		array(
			'pattern' => '/(^|\.)baidu\.com$/',
			'source'  => 'baidu',
			'medium'  => 'organic',
		),
		array(
			'pattern' => '/(^|\.)yandex\./',
			'source'  => 'yandex',
			'medium'  => 'organic',
		),
		array(
			'pattern' => '/(^|\.)ecosia\.org$/',
			'source'  => 'ecosia',
			'medium'  => 'organic',
		),
		array(
			'pattern' => '/(^|\.)brave\.com$/',
			'source'  => 'brave',
			'medium'  => 'organic',
		),
		array(
			'pattern' => '/(^|\.)facebook\.com$|^fb\.com$/',
			'source'  => 'facebook',
			'medium'  => 'social',
		),
		array(
			'pattern' => '/(^|\.)instagram\.com$/',
			'source'  => 'instagram',
			'medium'  => 'social',
		),
		array(
			'pattern' => '/(^|\.)linkedin\.com$|^lnkd\.in$/',
			'source'  => 'linkedin',
			'medium'  => 'social',
		),
		array(
			'pattern' => '/(^|\.)(twitter|x)\.com$|^t\.co$/',
			'source'  => 'twitter',
			'medium'  => 'social',
		),
		array(
			'pattern' => '/(^|\.)tiktok\.com$/',
			'source'  => 'tiktok',
			'medium'  => 'social',
		),
		array(
			'pattern' => '/(^|\.)youtube\.com$|^youtu\.be$/',
			'source'  => 'youtube',
			'medium'  => 'social',
		),
		array(
			'pattern' => '/(^|\.)pinterest\.com$|^pin\.it$/',
			'source'  => 'pinterest',
			'medium'  => 'social',
		),
		array(
			'pattern' => '/(^|\.)reddit\.com$|^redd\.it$/',
			'source'  => 'reddit',
			'medium'  => 'social',
		),
		array(
			'pattern' => '/(^|\.)snapchat\.com$/',
			'source'  => 'snapchat',
			'medium'  => 'social',
		),
		array(
			'pattern' => '/(^|\.)threads\.net$/',
			'source'  => 'threads',
			'medium'  => 'social',
		),
		array(
			'pattern' => '/(^|\.)whatsapp\.com$|^wa\.me$/',
			'source'  => 'whatsapp',
			'medium'  => 'messaging',
		),
		array(
			'pattern' => '/(^|\.)telegram\.(org|me)$|^t\.me$/',
			'source'  => 'telegram',
			'medium'  => 'messaging',
		),
		array(
			'pattern' => '/(^|\.)github\.com$/',
			'source'  => 'github',
			'medium'  => 'referral',
		),
		// Email service providers. When the referrer is a marketing email
		// click tracker or newsletter sender, classify medium as 'email' so
		// reports separate email-driven traffic from organic referrals.
		array(
			'pattern' => '/(^|\.)list-manage\.com$/',
			'source'  => 'mailchimp',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)mailchimp\.com$/',
			'source'  => 'mailchimp',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)mc\.us$/',
			'source'  => 'mailchimp',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)constantcontact\.com$/',
			'source'  => 'constantcontact',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)ccsend\.com$/',
			'source'  => 'constantcontact',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)r20\.rs6\.net$/',
			'source'  => 'constantcontact',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)mailerlite\.com$/',
			'source'  => 'mailerlite',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)activecampaign\.com$/',
			'source'  => 'activecampaign',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)klaviyo\.com$/',
			'source'  => 'klaviyo',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)hsmsend\.com$/',
			'source'  => 'hubspot',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)convertkit\.com$/',
			'source'  => 'convertkit',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/^ck\.email$/',
			'source'  => 'convertkit',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)substack\.com$/',
			'source'  => 'substack',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)beehiiv\.com$/',
			'source'  => 'beehiiv',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)sendgrid\.net$/',
			'source'  => 'sendgrid',
			'medium'  => 'email',
		),
		array(
			'pattern' => '/(^|\.)mailgun\.org$/',
			'source'  => 'mailgun',
			'medium'  => 'email',
		),
	);

	public static function detect_click_id( array $params ): ?array {
		foreach ( self::CLICK_IDS as $c ) {
			$value = $params[ $c['param'] ] ?? null;
			if ( is_string( $value ) && '' !== $value ) {
				return array(
					'type'   => $c['param'],
					'id'     => $value,
					'source' => $c['source'],
					'medium' => $c['medium'],
				);
			}
		}
		return null;
	}

	/**
	 * Returns source/medium for the referrer. Returns null for internal
	 * navigation (same host). Returns ['source' => 'direct', ...] when no
	 * referrer is present.
	 */
	public static function classify_referrer( string $referrer, string $current_host = '' ): ?array {
		if ( '' === $referrer ) {
			return array(
				'source' => 'direct',
				'medium' => 'direct',
			);
		}

		$host = wp_parse_url( $referrer, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return array(
				'source' => 'unknown',
				'medium' => 'referral',
			);
		}

		$host         = strtolower( $host );
		$current_host = strtolower( $current_host );

		if ( '' !== $current_host && $host === $current_host ) {
			return null;
		}

		foreach ( self::REFERRER_MAP as $entry ) {
			if ( preg_match( $entry['pattern'], $host ) ) {
				return array(
					'source' => $entry['source'],
					'medium' => $entry['medium'],
				);
			}
		}

		return array(
			'source' => $host,
			'medium' => 'referral',
		);
	}

	/**
	 * Apply precedence: explicit UTM > click ID > referrer > direct.
	 * Returns an attribution payload keyed by cookie suffix, or an empty
	 * array if no capture should happen (internal navigation).
	 */
	public static function classify( array $params, string $referrer = '', string $current_host = '' ): array {
		$utm_source   = self::string_value( $params, 'utm_source' );
		$utm_medium   = self::string_value( $params, 'utm_medium' );
		$utm_campaign = self::string_value( $params, 'utm_campaign' );
		$utm_term     = self::string_value( $params, 'utm_term' );
		$utm_content  = self::string_value( $params, 'utm_content' );
		$click_id     = self::detect_click_id( $params );

		if ( '' !== $utm_source ) {
			$source = $utm_source;
			if ( '' !== $utm_medium ) {
				$medium = $utm_medium;
			} elseif ( $click_id ) {
				$medium = $click_id['medium'];
			} else {
				$medium = 'unknown';
			}
		} elseif ( $click_id ) {
			$source = $click_id['source'];
			$medium = $click_id['medium'];
		} else {
			$ref = self::classify_referrer( $referrer, $current_host );
			if ( null === $ref ) {
				return array();
			}
			$source = $ref['source'];
			$medium = $ref['medium'];
		}

		$out = array(
			'utm_source' => $source,
			'utm_medium' => $medium,
		);
		if ( '' !== $utm_campaign ) {
			$out['utm_campaign'] = $utm_campaign;
		}
		if ( '' !== $utm_term ) {
			$out['utm_term'] = $utm_term;
		}
		if ( '' !== $utm_content ) {
			$out['utm_content'] = $utm_content;
		}
		if ( $click_id ) {
			$out['click_id']      = $click_id['id'];
			$out['click_id_type'] = $click_id['type'];
		}

		return $out;
	}

	private static function string_value( array $params, string $key ): string {
		return isset( $params[ $key ] ) && is_string( $params[ $key ] ) ? $params[ $key ] : '';
	}
}
