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

	public const PAGE_SLUG         = 'leadstream-settings';
	public const OPTION_GROUP      = 'leadstream_settings';
	public const SECTION           = 'leadstream_capture';
	public const SECTION_FORMS     = 'leadstream_forms';
	public const SECTION_PLATFORMS = 'leadstream_platforms';
	public const CAPABILITY        = 'manage_options';

	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . LEADSTREAM_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	public static function add_menu(): void {
		add_submenu_page(
			Admin::MENU_SLUG,
			__( 'LeadStream Settings', 'leadstream' ),
			__( 'Settings', 'leadstream' ),
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
			'leadstream_gf_auto_inject',
			array(
				'type'              => 'boolean',
				'default'           => true,
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
		register_setting(
			self::OPTION_GROUP,
			'leadstream_elementor_email_append',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'leadstream_universal_inject',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			)
		);

		$platform_string_options = array(
			'leadstream_gads_developer_token',
			'leadstream_gads_refresh_token',
			'leadstream_gads_client_id',
			'leadstream_gads_client_secret',
			'leadstream_gads_customer_id',
			'leadstream_gads_login_customer_id',
			'leadstream_gads_conversion_action',
			'leadstream_gads_currency',
			'leadstream_gads_api_version',
		);
		foreach ( $platform_string_options as $key ) {
			$default = '';
			if ( 'leadstream_gads_currency' === $key ) {
				$default = 'USD';
			} elseif ( 'leadstream_gads_api_version' === $key ) {
				$default = \LeadStream\Platforms\GoogleAds::DEFAULT_API_VERSION;
			}
			register_setting(
				self::OPTION_GROUP,
				$key,
				array(
					'type'              => 'string',
					'default'           => $default,
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
		}
		register_setting(
			self::OPTION_GROUP,
			'leadstream_gads_default_value',
			array(
				'type'              => 'number',
				'default'           => 0,
				'sanitize_callback' => array( __CLASS__, 'sanitize_money' ),
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'leadstream_gads_enabled',
			array(
				'type'              => 'boolean',
				'default'           => false,
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
			'leadstream_gf_auto_inject',
			__( 'Auto-inject into Gravity Forms entries', 'leadstream' ),
			array( __CLASS__, 'field_gf_auto_inject' ),
			self::PAGE_SLUG,
			self::SECTION_FORMS
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

		add_settings_field(
			'leadstream_elementor_email_append',
			__( 'Append attribution to Elementor emails', 'leadstream' ),
			array( __CLASS__, 'field_elementor_email_append' ),
			self::PAGE_SLUG,
			self::SECTION_FORMS
		);

		add_settings_field(
			'leadstream_universal_inject',
			__( 'Universal form injection (all form plugins)', 'leadstream' ),
			array( __CLASS__, 'field_universal_inject' ),
			self::PAGE_SLUG,
			self::SECTION_FORMS
		);

		add_settings_section(
			self::SECTION_PLATFORMS,
			__( 'Ad platforms', 'leadstream' ),
			array( __CLASS__, 'render_platforms_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field( 'leadstream_gads_enabled', __( 'Google Ads: enabled', 'leadstream' ), array( __CLASS__, 'field_gads_enabled' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
		add_settings_field( 'leadstream_gads_developer_token', __( 'Developer token', 'leadstream' ), array( __CLASS__, 'field_gads_developer_token' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
		add_settings_field( 'leadstream_gads_client_id', __( 'OAuth client ID', 'leadstream' ), array( __CLASS__, 'field_gads_client_id' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
		add_settings_field( 'leadstream_gads_client_secret', __( 'OAuth client secret', 'leadstream' ), array( __CLASS__, 'field_gads_client_secret' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
		add_settings_field( 'leadstream_gads_refresh_token', __( 'OAuth refresh token', 'leadstream' ), array( __CLASS__, 'field_gads_refresh_token' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
		add_settings_field( 'leadstream_gads_customer_id', __( 'Customer ID (no dashes)', 'leadstream' ), array( __CLASS__, 'field_gads_customer_id' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
		add_settings_field( 'leadstream_gads_login_customer_id', __( 'Login customer ID (MCC, optional)', 'leadstream' ), array( __CLASS__, 'field_gads_login_customer_id' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
		add_settings_field( 'leadstream_gads_conversion_action', __( 'Conversion action resource name', 'leadstream' ), array( __CLASS__, 'field_gads_conversion_action' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
		add_settings_field( 'leadstream_gads_default_value', __( 'Default conversion value', 'leadstream' ), array( __CLASS__, 'field_gads_default_value' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
		add_settings_field( 'leadstream_gads_currency', __( 'Currency code', 'leadstream' ), array( __CLASS__, 'field_gads_currency' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
		add_settings_field( 'leadstream_gads_api_version', __( 'API version', 'leadstream' ), array( __CLASS__, 'field_gads_api_version' ), self::PAGE_SLUG, self::SECTION_PLATFORMS );
	}

	public static function sanitize_money( $value ): float {
		if ( ! is_numeric( $value ) || (float) $value < 0 ) {
			return 0.0;
		}
		return round( (float) $value, 2 );
	}

	public static function render_platforms_section_intro(): void {
		echo '<p>' . esc_html__( 'Upload qualifying form submissions as offline conversions to ad platforms. Currently Google Ads only; Meta CAPI, LinkedIn, and TikTok will land in future releases. Credentials are stored in wp_options as plain text; treat this install like you would any other place you keep API tokens.', 'leadstream' ) . '</p>';
	}

	public static function field_gads_enabled(): void {
		$value = (bool) get_option( 'leadstream_gads_enabled', false );
		printf(
			'<label><input type="checkbox" name="leadstream_gads_enabled" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Upload gclid-tagged submissions to Google Ads via the 15-minute worker.', 'leadstream' )
		);
	}

	public static function field_gads_developer_token(): void {
		self::text_field( 'leadstream_gads_developer_token', __( 'From Google Ads UI > Tools > API Center.', 'leadstream' ) );
	}

	public static function field_gads_client_id(): void {
		self::text_field( 'leadstream_gads_client_id', __( 'From Google Cloud Console OAuth 2.0 credentials.', 'leadstream' ) );
	}

	public static function field_gads_client_secret(): void {
		self::text_field( 'leadstream_gads_client_secret', __( 'Paired with client ID.', 'leadstream' ), 'password' );
	}

	public static function field_gads_refresh_token(): void {
		self::text_field( 'leadstream_gads_refresh_token', __( 'Generate via OAuth Playground with scope https://www.googleapis.com/auth/adwords.', 'leadstream' ), 'password' );
	}

	public static function field_gads_customer_id(): void {
		self::text_field( 'leadstream_gads_customer_id', __( '10-digit Google Ads customer ID, no dashes.', 'leadstream' ) );
	}

	public static function field_gads_login_customer_id(): void {
		self::text_field( 'leadstream_gads_login_customer_id', __( 'Set to the MCC ID when managing through a manager account.', 'leadstream' ) );
	}

	public static function field_gads_conversion_action(): void {
		self::text_field(
			'leadstream_gads_conversion_action',
			__( 'Full resource name of the conversion action, e.g. customers/1234567890/conversionActions/987654321.', 'leadstream' )
		);
	}

	public static function field_gads_default_value(): void {
		$value = (float) get_option( 'leadstream_gads_default_value', 0 );
		printf(
			'<input type="number" name="leadstream_gads_default_value" value="%s" min="0" step="0.01" class="small-text" /> <span class="description">%s</span>',
			esc_attr( (string) $value ),
			esc_html__( 'Used when the submission has no per-form value. Set higher than 0 so Google Ads optimizes against qualified leads.', 'leadstream' )
		);
	}

	public static function field_gads_currency(): void {
		self::text_field( 'leadstream_gads_currency', __( 'ISO currency code, e.g. USD, EUR, CAD.', 'leadstream' ) );
	}

	public static function field_gads_api_version(): void {
		$value = (string) get_option( 'leadstream_gads_api_version', \LeadStream\Platforms\GoogleAds::DEFAULT_API_VERSION );
		if ( '' === $value ) {
			$value = \LeadStream\Platforms\GoogleAds::DEFAULT_API_VERSION;
		}
		printf(
			'<input type="text" name="leadstream_gads_api_version" value="%s" class="small-text" placeholder="v20" /> <p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'Google Ads API version (e.g. v19, v20, v21). Google rolls these every few months; bump here when you see a 404 from the API without having to update the plugin.', 'leadstream' )
		);
	}

	private static function text_field( string $option, string $help, string $type = 'text' ): void {
		$value = (string) get_option( $option, '' );
		printf(
			'<input type="%1$s" name="%2$s" value="%3$s" class="regular-text" autocomplete="off" /> <p class="description">%4$s</p>',
			esc_attr( $type ),
			esc_attr( $option ),
			esc_attr( $value ),
			esc_html( $help )
		);
	}

	public static function render_forms_section_intro(): void {
		echo '<p>' . esc_html__( 'Gravity Forms merge tags are registered automatically. Use tags like {leadstream:utm_source} or {leadstream:click_id} in notification bodies, subjects, or webhook payloads. Elementor Pro submissions receive the same attribution injected into the record, with no hidden field setup required.', 'leadstream' ) . '</p>';
	}

	public static function field_gf_auto_inject(): void {
		$value = (bool) get_option( 'leadstream_gf_auto_inject', true );
		printf(
			'<label><input type="checkbox" name="leadstream_gf_auto_inject" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Add leadstream_ hidden fields to every Gravity Forms form at render time so attribution is stored on the entry itself, not only in notifications or webhooks.', 'leadstream' )
		);
	}

	public static function field_gf_auto_append(): void {
		$value = (bool) get_option( 'leadstream_gf_auto_append_notifications', false );
		printf(
			'<label><input type="checkbox" name="leadstream_gf_auto_append_notifications" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Append captured attribution to the bottom of every Gravity Forms notification email.', 'leadstream' )
		);
	}

	public static function field_elementor_email_append(): void {
		$value = (bool) get_option( 'leadstream_elementor_email_append', false );
		printf(
			'<label><input type="checkbox" name="leadstream_elementor_email_append" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Append a parseable attribution block (gclid, source, medium, campaign, etc.) to every Elementor notification email body. Useful for CRMs that ingest leads via email parsing (LeadSimple, Follow Up Boss). Affects all Elementor emails on this site, including any user auto-responder, so leave off unless you specifically need the attribution to flow through email.', 'leadstream' )
		);
	}

	public static function field_elementor_auto_inject(): void {
		$value = (bool) get_option( 'leadstream_elementor_auto_inject', true );
		printf(
			'<label><input type="checkbox" name="leadstream_elementor_auto_inject" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'Inject leadstream_ prefixed attribution fields into Elementor Pro submissions at process time, before form actions run. Webhook payloads, integrations, and email notifications that read from $record->get(\'fields\') will include the attribution. Elementor does not expose a hook for adding fields to the rendered HTML; for fields you also want visible in the admin Submissions UI, define them as hidden fields in the form builder.', 'leadstream' )
		);
	}

	public static function field_universal_inject(): void {
		$value = (bool) get_option( 'leadstream_universal_inject', true );
		printf(
			'<label><input type="checkbox" name="leadstream_universal_inject" value="1" %s /> %s</label>',
			checked( $value, true, false ),
			esc_html__( 'JS-inject hidden attribution fields (utm_source, click_id, gclid/fbclid alias, etc.) into every form on the page, no matter the plugin. Skips GET forms (search) and standard WP forms (login/comment). Watches for forms added by popups or AJAX. Disable if a form rejects unexpected fields.', 'leadstream' )
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
		$url           = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$settings_link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'leadstream' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
