<?php
/**
 * Admin settings page. Settings → LeadStream. Exposes cookie duration,
 * subdomain tracking, consent enforcement, debug mode, and uninstall cleanup
 * opt-in. Uses the WordPress Settings API so save, sanitize, and nonce are
 * handled by core.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const PAGE_SLUG     = 'leadstream';
	public const OPTION_GROUP  = 'leadstream_settings';
	public const SECTION       = 'leadstream_capture';
	public const SECTION_FORMS = 'leadstream_forms';
	public const CAPABILITY    = 'manage_options';

	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . LEADSTREAM_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	public static function add_menu(): void {
		add_options_page(
			__( 'LeadStream Settings', 'leadstream' ),
			__( 'LeadStream', 'leadstream' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			'leadstream_cookie_duration',
			array(
				'type'              => 'integer',
				'default'           => 30,
				'sanitize_callback' => array( __CLASS__, 'sanitize_duration' ),
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'leadstream_subdomain_tracking',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'leadstream_require_consent',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'leadstream_debug',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'leadstream_delete_on_uninstall',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'leadstream_gf_auto_append_notifications',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'leadstream_elementor_auto_inject',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			)
		);

		add_settings_section(
			self::SECTION,
			__( 'Capture', 'leadstream' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'leadstream_cookie_duration',
			__( 'Cookie duration (days)', 'leadstream' ),
			array( __CLASS__, 'field_duration' ),
			self::PAGE_SLUG,
			self::SECTION
		);
		add_settings_field(
			'leadstream_subdomain_tracking',
			__( 'Subdomain tracking', 'leadstream' ),
			array( __CLASS__, 'field_subdomain' ),
			self::PAGE_SLUG,
			self::SECTION
		);
		add_settings_field(
			'leadstream_require_consent',
			__( 'Require consent', 'leadstream' ),
			array( __CLASS__, 'field_require_consent' ),
			self::PAGE_SLUG,
			self::SECTION
		);
		add_settings_field(
			'leadstream_debug',
			__( 'Debug logging', 'leadstream' ),
			array( __CLASS__, 'field_debug' ),
			self::PAGE_SLUG,
			self::SECTION
		);
		add_settings_field(
			'leadstream_delete_on_uninstall',
			__( 'Delete data on uninstall', 'leadstream' ),
			array( __CLASS__, 'field_delete_on_uninstall' ),
			self::PAGE_SLUG,
			self::SECTION
		);

		add_settings_section(
			self::SECTION_FORMS,
			__( 'Form enrichment', 'leadstream' ),
			array( __CLASS__, 'render_forms_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'leadstream_gf_auto_append_notifications',
			__( 'Auto-append to Gravity Forms notifications', 'leadstream' ),
			array( __CLASS__, 'field_gf_auto_append' ),
			self::PAGE_SLUG,
			self::SECTION_FORMS
		);

		add_settings_field(
			'leadstream_elementor_auto_inject',
			__( 'Auto-inject into Elementor submissions', 'leadstream' ),
			array( __CLASS__, 'field_elementor_auto_inject' ),
			self::PAGE_SLUG,
			self::SECTION_FORMS
		);
	}

	public static function render_forms_section_intro(): void {
		echo '<p>' . esc_html__( 'Gravity Forms merge tags are registered automatically. Use tags like {leadstream:utm_source} or {leadstream:click_id} in notification bodies, subjects, or webhook payloads. Elementor Pro submissions receive the same attribution injected into the record, with no hidden field setup required.', 'leadstream' ) . '</p>';
	}

	public static function field_gf_auto_append(): void {
		$value = (bool) get_option( 'leadstream_gf_auto_append_notifications', false );
		printf(
			'<label><input type="checkbox" name="leadstream_gf_auto_append_notifications" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Append captured attribution to the bottom of every Gravity Forms notification email.', 'leadstream' )
		);
	}

	public static function field_elementor_auto_inject(): void {
		$value = (bool) get_option( 'leadstream_elementor_auto_inject', true );
		printf(
			'<label><input type="checkbox" name="leadstream_elementor_auto_inject" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Add leadstream_ prefixed fields to every Elementor Pro form submission so notifications, webhooks, and integrations include attribution without hidden fields on the form.', 'leadstream' )
		);
	}

	public static function sanitize_duration( $value ): int {
		if ( ! is_numeric( $value ) || (int) $value < 1 ) {
			return 30;
		}
		return min( 365, (int) $value );
	}

	public static function sanitize_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
		}
		return (bool) $value;
	}

	public static function field_duration(): void {
		$value = (int) get_option( 'leadstream_cookie_duration', 30 );
		printf(
			'<input type="number" id="leadstream_cookie_duration" name="leadstream_cookie_duration" value="%d" min="1" max="365" class="small-text" /> <span class="description">%s</span>',
			esc_attr( (string) $value ),
			esc_html__( 'How long attribution cookies persist, in days. Range 1 to 365.', 'leadstream' )
		);
	}

	public static function field_subdomain(): void {
		$value = (bool) get_option( 'leadstream_subdomain_tracking', false );
		printf(
			'<label><input type="checkbox" name="leadstream_subdomain_tracking" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Write cookies on the parent domain so subdomains share attribution. Leave off for single-TLD hosts like .co.uk.', 'leadstream' )
		);
	}

	public static function field_require_consent(): void {
		$value = (bool) get_option( 'leadstream_require_consent', false );
		printf(
			'<label><input type="checkbox" name="leadstream_require_consent" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Gate capture behind WP Consent API, Cookiebot, or Complianz signals.', 'leadstream' )
		);
	}

	public static function field_debug(): void {
		$value = (bool) get_option( 'leadstream_debug', false );
		printf(
			'<label><input type="checkbox" name="leadstream_debug" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Log capture activity to the browser console. Auto-enabled on beta domains.', 'leadstream' )
		);
	}

	public static function field_delete_on_uninstall(): void {
		$value = (bool) get_option( 'leadstream_delete_on_uninstall', false );
		printf(
			'<label><input type="checkbox" name="leadstream_delete_on_uninstall" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Drop the events table and delete plugin options when the plugin is deleted.', 'leadstream' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'leadstream' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'LeadStream Settings', 'leadstream' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::OPTION_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';
		self::render_consent_status();
		self::render_form_integrations();
		self::render_footer();
		echo '</div>';
	}

	private static function render_consent_status(): void {
		$detected = array();
		if ( function_exists( 'wp_has_consent' ) ) {
			$detected[] = 'WP Consent API';
		}
		if ( defined( 'CYBOT_COOKIEBOT_VERSION' ) || defined( 'CYBOT_COOKIEBOT_FILE' ) ) {
			$detected[] = 'Cookiebot';
		}
		if ( defined( 'cmplz_version' ) || defined( 'COMPLIANZ_VERSION' ) ) {
			$detected[] = 'Complianz';
		}

		echo '<h2>' . esc_html__( 'Consent detection', 'leadstream' ) . '</h2>';
		if ( empty( $detected ) ) {
			echo '<p>' . esc_html__( 'No consent plugin detected. If "Require consent" is on, capture is blocked until a supported plugin is active.', 'leadstream' ) . '</p>';
		} else {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %s: comma-separated list of consent plugin names */
					__( 'Detected: %s', 'leadstream' ),
					implode( ', ', $detected )
				)
			) . '</p>';
		}
	}

	private static function render_form_integrations(): void {
		echo '<h2>' . esc_html__( 'Form integrations', 'leadstream' ) . '</h2>';
		echo '<ul>';
		self::integration_line( 'Elementor Pro Forms', defined( 'ELEMENTOR_PRO_VERSION' ) );
		self::integration_line( 'Gravity Forms', class_exists( '\GFForms' ) );
		echo '</ul>';
	}

	private static function integration_line( string $name, bool $active ): void {
		$status = $active
			? __( 'active', 'leadstream' )
			: __( 'not installed', 'leadstream' );
		echo '<li>' . esc_html( $name ) . ': ' . esc_html( $status ) . '</li>';
	}

	private static function render_footer(): void {
		echo '<hr /><p class="description">' . esc_html__( 'LeadStream by Timberbrook Marketing.', 'leadstream' ) . '</p>';
	}

	public static function action_links( array $links ): array {
		$url           = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		$settings_link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'leadstream' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
