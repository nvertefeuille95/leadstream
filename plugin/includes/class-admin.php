<?php
/**
 * Admin area bootstrap. Registers the top-level LeadStream menu and renders
 * the Submissions view.
 *
 * @package LeadStream
 */

namespace LeadStream;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public const MENU_SLUG  = 'leadstream';
	public const CAPABILITY = 'manage_options';

	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 5 );
	}

	/**
	 * Inline SVG menu icon. Mirrors the Timberbrook brandmark: a horizontal
	 * hexagon outline with an upward-pointing arrow inside. Single path with
	 * evenodd fill so WP admin menu CSS can recolor it via filter.
	 */
	public static function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill-rule="evenodd"><path fill="black" d="M5 3L15 3L19 10L15 17L5 17L1 10ZM5.5 4.5L14.5 4.5L17.5 10L14.5 15.5L5.5 15.5L2.5 10ZM10 6L5.5 9.5L8 9.5L8 14L12 14L12 9.5L14.5 9.5Z"/></svg>';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WP admin menu icons use data: URIs; this is the standard idiom.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public static function add_menu(): void {
		add_menu_page(
			__( 'LeadStream', 'leadstream' ),
			__( 'LeadStream', 'leadstream' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_submissions' ),
			self::menu_icon(),
			30
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Submissions', 'leadstream' ),
			__( 'Submissions', 'leadstream' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_submissions' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Uploads', 'leadstream' ),
			__( 'Uploads', 'leadstream' ),
			self::CAPABILITY,
			'leadstream-uploads',
			array( __CLASS__, 'render_uploads' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'UTM Builder', 'leadstream' ),
			__( 'UTM Builder', 'leadstream' ),
			self::CAPABILITY,
			'leadstream-utm-builder',
			array( __CLASS__, 'render_utm_builder' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Reports', 'leadstream' ),
			__( 'Reports', 'leadstream' ),
			self::CAPABILITY,
			'leadstream-reports',
			array( __CLASS__, 'render_reports' )
		);
	}

	public static function render_reports(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'leadstream' ) );
		}

		$default_to   = gmdate( 'Y-m-d' );
		$default_from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter inputs.
		$from  = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : $default_from;
		$to    = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : $default_to;
		$model = isset( $_GET['model'] ) ? sanitize_key( wp_unslash( $_GET['model'] ) ) : (string) get_option( 'leadstream_attribution_model', 'last' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
			$from = $default_from;
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$to = $default_to;
		}
		if ( ! array_key_exists( $model, Settings::ATTRIBUTION_MODELS ) ) {
			$model = 'last';
		}

		$events  = Reports::load_events_with_touches( $from, $to );
		$summary = Reports::summary( $events );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'LeadStream Reports', 'leadstream' ) . '</h1>';

		// Filter form.
		echo '<form method="get" style="margin:20px 0">';
		echo '<input type="hidden" name="page" value="leadstream-reports" />';
		echo '<label>' . esc_html__( 'From', 'leadstream' ) . ' <input type="date" name="from" value="' . esc_attr( $from ) . '" /></label> ';
		echo '<label>' . esc_html__( 'To', 'leadstream' ) . ' <input type="date" name="to" value="' . esc_attr( $to ) . '" /></label> ';
		echo '<label>' . esc_html__( 'Model', 'leadstream' ) . ' <select name="model">';
		foreach ( Settings::ATTRIBUTION_MODELS as $key => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $model, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select></label> ';
		submit_button( __( 'Apply', 'leadstream' ), 'primary', 'submit', false );
		echo '</form>';

		// Summary tiles.
		echo '<div style="display:flex;gap:16px;margin-bottom:30px;flex-wrap:wrap">';
		self::summary_tile( __( 'Conversions', 'leadstream' ), (string) $summary['conversions'] );
		self::summary_tile( __( 'Unique Visitors', 'leadstream' ), (string) $summary['unique_visitors'] );
		self::summary_tile( __( 'Avg Touches per Conversion', 'leadstream' ), (string) $summary['avg_touches_per_conversion'] );
		echo '</div>';

		if ( 0 === $summary['conversions'] ) {
			echo '<p>' . esc_html__( 'No conversions in this date range. Try widening the dates or check back after a few form submissions.', 'leadstream' ) . '</p>';
			echo '</div>';
			return;
		}

		// Per-dimension breakdowns.
		foreach ( Reports::DIMENSIONS as $dimension => $label ) {
			$rows = Reports::aggregate( $events, $model, $dimension );
			self::render_dimension_table( $label, $rows );
		}

		echo '</div>';
	}

	private static function summary_tile( string $label, string $value ): void {
		echo '<div style="background:#fff;padding:16px 20px;border:1px solid #c3c4c7;border-radius:4px;min-width:180px">';
		echo '<div style="color:#646970;font-size:13px;margin-bottom:6px">' . esc_html( $label ) . '</div>';
		echo '<div style="font-size:24px;font-weight:600">' . esc_html( $value ) . '</div>';
		echo '</div>';
	}

	private static function render_dimension_table( string $label, array $rows ): void {
		echo '<h2>' . esc_html( $label ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No data.', 'leadstream' ) . '</p>';
			return;
		}
		echo '<table class="wp-list-table widefat striped" style="max-width:800px;margin-bottom:30px">';
		echo '<thead><tr>';
		echo '<th style="width:50%">' . esc_html( $label ) . '</th>';
		echo '<th style="width:15%">' . esc_html__( 'Conversions', 'leadstream' ) . '</th>';
		echo '<th style="width:15%">' . esc_html__( 'Share', 'leadstream' ) . '</th>';
		echo '<th>&nbsp;</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$pct = (float) ( $row['percentage'] ?? 0 );
			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $row['value'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['conversions'] ?? 0 ) ) . '</td>';
			echo '<td>' . esc_html( (string) $pct ) . '%</td>';
			echo '<td><div style="background:#2271b1;height:14px;width:' . esc_attr( (string) $pct ) . '%;border-radius:2px"></div></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public static function render_utm_builder(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'leadstream' ) );
		}

		$single_url   = '';
		$single_input = array();
		$single_warns = array();
		$bulk_input   = '';
		$bulk_output  = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['leadstream_utm_action'] ) ? sanitize_key( wp_unslash( $_POST['leadstream_utm_action'] ) ) : '';

		if ( 'build' === $action || 'bulk' === $action ) {
			check_admin_referer( 'leadstream_utm_builder' );
		}

		if ( 'build' === $action ) {
			$single_input = array(
				'base_url'     => isset( $_POST['base_url'] ) ? esc_url_raw( wp_unslash( $_POST['base_url'] ) ) : '',
				'utm_source'   => isset( $_POST['utm_source'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_source'] ) ) : '',
				'utm_medium'   => isset( $_POST['utm_medium'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_medium'] ) ) : '',
				'utm_campaign' => isset( $_POST['utm_campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ) ) : '',
				'utm_term'     => isset( $_POST['utm_term'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_term'] ) ) : '',
				'utm_content'  => isset( $_POST['utm_content'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_content'] ) ) : '',
			);
			$single_url   = UtmBuilder::build( $single_input['base_url'], $single_input );
			foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $field ) {
				$value = $single_input[ $field ];
				if ( '' === $value ) {
					continue;
				}
				$drift = UtmBuilder::detect_taxonomy_drift( $value, UtmBuilder::known_values( $field ) );
				if ( null !== $drift ) {
					$single_warns[ $field ] = $drift;
				}
			}
		} elseif ( 'bulk' === $action ) {
			$bulk_input  = isset( $_POST['bulk_csv'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bulk_csv'] ) ) : '';
			$rows        = UtmBuilder::parse_bulk_csv( $bulk_input );
			$bulk_output = UtmBuilder::generate_bulk_csv( $rows );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'UTM Builder', 'leadstream' ) . '</h1>';
		echo '<p>' . esc_html__( 'Generate consistently tagged campaign URLs. The plugin remembers values you have used before so you can keep your taxonomy clean (warns when "Google" creeps in next to "google").', 'leadstream' ) . '</p>';

		echo '<h2>' . esc_html__( 'Single URL', 'leadstream' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'leadstream_utm_builder' );
		echo '<input type="hidden" name="leadstream_utm_action" value="build" />';
		echo '<table class="form-table" role="presentation"><tbody>';
		self::utm_input_row( __( 'Base URL', 'leadstream' ), 'base_url', $single_input['base_url'] ?? '', __( 'The page you are tagging, e.g. https://timberbrookmarketing.com/services/', 'leadstream' ), array(), null );
		foreach ( array(
			'utm_source'   => array( __( 'Source', 'leadstream' ), __( 'Where the traffic comes from. Example: google, linkedin, newsletter.', 'leadstream' ) ),
			'utm_medium'   => array( __( 'Medium', 'leadstream' ), __( 'Marketing medium. Example: cpc, social, email, organic.', 'leadstream' ) ),
			'utm_campaign' => array( __( 'Campaign', 'leadstream' ), __( 'Specific promotion or campaign. Example: spring_2026, q2_launch.', 'leadstream' ) ),
			'utm_term'     => array( __( 'Term (optional)', 'leadstream' ), __( 'Keyword, used mostly for paid search.', 'leadstream' ) ),
			'utm_content'  => array( __( 'Content (optional)', 'leadstream' ), __( 'Variant identifier for A/B tests, e.g. cta_top vs cta_bottom.', 'leadstream' ) ),
		) as $field => $meta ) {
			$known = in_array( $field, array( 'utm_source', 'utm_medium', 'utm_campaign' ), true ) ? UtmBuilder::known_values( $field ) : array();
			self::utm_input_row(
				$meta[0],
				$field,
				$single_input[ $field ] ?? '',
				$meta[1],
				$known,
				$single_warns[ $field ] ?? null
			);
		}
		echo '</tbody></table>';
		submit_button( __( 'Generate URL', 'leadstream' ) );
		echo '</form>';

		if ( '' !== $single_url ) {
			echo '<h3>' . esc_html__( 'Generated URL', 'leadstream' ) . '</h3>';
			echo '<p><code style="display:block;padding:10px;background:#f4f4f4;border:1px solid #ddd;word-break:break-all">' . esc_html( $single_url ) . '</code></p>';
		}

		echo '<hr style="margin:40px 0" />';
		echo '<h2>' . esc_html__( 'Bulk CSV', 'leadstream' ) . '</h2>';
		echo '<p>' . esc_html__( 'Paste a CSV with columns: base_url, utm_source, utm_medium, utm_campaign, utm_term (optional), utm_content (optional). Header row required. Output adds a tagged_url column you can paste into your campaign tracking sheet.', 'leadstream' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'leadstream_utm_builder' );
		echo '<input type="hidden" name="leadstream_utm_action" value="bulk" />';
		echo '<p><textarea name="bulk_csv" rows="10" cols="80" class="large-text code" placeholder="base_url,utm_source,utm_medium,utm_campaign&#10;https://example.com/x,google,cpc,spring_2026">' . esc_textarea( $bulk_input ) . '</textarea></p>';
		submit_button( __( 'Generate Bulk', 'leadstream' ) );
		echo '</form>';

		if ( '' !== $bulk_output ) {
			echo '<h3>' . esc_html__( 'Output CSV', 'leadstream' ) . '</h3>';
			echo '<p><textarea rows="10" cols="80" class="large-text code" readonly>' . esc_textarea( $bulk_output ) . '</textarea></p>';
		}

		echo '</div>';
	}

	private static function utm_input_row( string $label, string $name, string $value, string $help, array $datalist, ?string $drift_warning ): void {
		echo '<tr>';
		echo '<th scope="row"><label for="leadstream_utm_' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td>';
		$list_id = 'list_' . $name;
		$type    = 'base_url' === $name ? 'url' : 'text';
		printf(
			'<input type="%1$s" id="leadstream_utm_%2$s" name="%2$s" value="%3$s" class="regular-text" %4$s />',
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $value ),
			! empty( $datalist ) ? 'list="' . esc_attr( $list_id ) . '"' : ''
		);
		if ( ! empty( $datalist ) ) {
			echo '<datalist id="' . esc_attr( $list_id ) . '">';
			foreach ( $datalist as $option ) {
				echo '<option value="' . esc_attr( (string) $option ) . '"></option>';
			}
			echo '</datalist>';
		}
		echo ' <p class="description">' . esc_html( $help ) . '</p>';
		if ( null !== $drift_warning ) {
			printf(
				'<p style="color:#b94a48"><strong>&#9888;</strong> %s <code>%s</code></p>',
				esc_html__( 'You have used this value before with different casing:', 'leadstream' ),
				esc_html( $drift_warning )
			);
		}
		echo '</td></tr>';
	}

	public static function render_uploads(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'leadstream' ) );
		}

		$rows = Uploads::recent( 100 );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'LeadStream Uploads', 'leadstream' ) . '</h1>';
		echo '<p>' . esc_html__( 'Offline conversion upload attempts to ad platforms. Pending rows drain every 15 minutes; failures retry with backoff.', 'leadstream' ) . '</p>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Created', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Platform', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Click ID', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Attempts', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Next retry', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Response', 'leadstream' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No uploads yet. Configure a platform under Settings, then submit a form with a click ID to trigger the first upload.', 'leadstream' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( (string) ( $row['created_at'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['platform'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['status'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['click_id'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['attempts'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['next_retry_at'] ?? '' ) ) . '</td>';
				echo '<td><code style="word-break:break-word">' . esc_html( substr( (string) ( $row['response'] ?? '' ), 0, 200 ) ) . '</code></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
	}

	public static function render_submissions(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'leadstream' ) );
		}

		// Action dispatcher — single page slug serves both list and detail.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'view' === $action ) {
			self::render_submission_detail();
			return;
		}

		$rows  = Events::recent( 100 );
		$total = Events::count_all();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'LeadStream Submissions', 'leadstream' ) . '</h1>';

		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: rows shown, 2: total rows */
					__( 'Showing the %1$d most recent of %2$s total captured submissions.', 'leadstream' ),
					count( $rows ),
					number_format_i18n( $total )
				)
			)
		);

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Time', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Medium', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Campaign', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Click ID', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Form', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Landing page', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Journey', 'leadstream' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No submissions captured yet. Submit a form on the frontend to see it here.', 'leadstream' ) . '</td></tr>';
		} else {
			$base_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
			foreach ( $rows as $row ) {
				$click = trim( ( $row['click_id_type'] ?? '' ) . ' ' . ( $row['click_id'] ?? '' ) );
				$form  = trim( ( $row['form_source'] ?? '' ) . ' ' . ( $row['form_id'] ?? '' ) );
				$id    = isset( $row['id'] ) ? (int) $row['id'] : 0;
				echo '<tr>';
				echo '<td>' . esc_html( (string) ( $row['created_at'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['utm_source'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['utm_medium'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['utm_campaign'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( $click ) . '</td>';
				echo '<td>' . esc_html( $form ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['landing_page'] ?? '' ) ) . '</td>';
				if ( $id > 0 && ! empty( $row['visitor_id'] ) ) {
					$detail_url = add_query_arg(
						array(
							'action' => 'view',
							'id'     => $id,
						),
						$base_url
					);
					echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html__( 'View', 'leadstream' ) . '</a></td>';
				} else {
					echo '<td>—</td>';
				}
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	private static function render_submission_detail(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$event = $id > 0 ? Events::find( $id ) : null;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'LeadStream Submission Detail', 'leadstream' ) . '</h1>';

		$back_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		echo '<p><a href="' . esc_url( $back_url ) . '">&larr; ' . esc_html__( 'Back to all submissions', 'leadstream' ) . '</a></p>';

		if ( ! is_array( $event ) ) {
			echo '<p>' . esc_html__( 'Submission not found.', 'leadstream' ) . '</p></div>';
			return;
		}

		// Conversion summary.
		echo '<h2>' . esc_html__( 'Conversion', 'leadstream' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:700px"><tbody>';
		self::detail_row( __( 'Time', 'leadstream' ), (string) ( $event['created_at'] ?? '' ) );
		self::detail_row( __( 'Source', 'leadstream' ), (string) ( $event['utm_source'] ?? '' ) );
		self::detail_row( __( 'Medium', 'leadstream' ), (string) ( $event['utm_medium'] ?? '' ) );
		self::detail_row( __( 'Campaign', 'leadstream' ), (string) ( $event['utm_campaign'] ?? '' ) );
		self::detail_row( __( 'Click ID', 'leadstream' ), trim( ( $event['click_id_type'] ?? '' ) . ' ' . ( $event['click_id'] ?? '' ) ) );
		self::detail_row( __( 'Form', 'leadstream' ), trim( ( $event['form_source'] ?? '' ) . ' ' . ( $event['form_id'] ?? '' ) ) );
		self::detail_row( __( 'Landing page', 'leadstream' ), (string) ( $event['landing_page'] ?? '' ) );
		self::detail_row( __( 'Visitor ID', 'leadstream' ), (string) ( $event['visitor_id'] ?? '' ) );
		echo '</tbody></table>';

		// Journey.
		$visitor_id = (string) ( $event['visitor_id'] ?? '' );
		$model      = (string) get_option( 'leadstream_attribution_model', 'last' );
		$touches    = '' !== $visitor_id ? Touches::for_visitor( $visitor_id, 50 ) : array();
		$attributed = Attribution::apply( $touches, $model );

		/* translators: %d: number of touches captured for this visitor */
		$count_label = sprintf( _n( '%d touch', '%d touches', count( $touches ), 'leadstream' ), count( $touches ) );
		printf(
			'<h2>%s <small style="color:#777;font-weight:normal">(%s, %s)</small></h2>',
			esc_html__( 'Visitor Journey', 'leadstream' ),
			esc_html( $count_label ),
			esc_html( self::model_label( $model ) )
		);

		if ( empty( $attributed ) ) {
			echo '<p>' . esc_html__( 'No touches recorded for this visitor. Either the form was submitted before LeadStream started recording touches, or the visitor never had attribution-bearing parameters in their URL.', 'leadstream' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="wp-list-table widefat striped">';
		echo '<thead><tr>';
		echo '<th>#</th>';
		echo '<th>' . esc_html__( 'Time', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Medium', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Campaign', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Click ID', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Page', 'leadstream' ) . '</th>';
		echo '<th>' . esc_html__( 'Credit', 'leadstream' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $attributed as $i => $touch ) {
			$credit  = isset( $touch['credit'] ) ? (float) $touch['credit'] : 0.0;
			$winner  = $credit > 0.5 || ( $credit > 0 && count( $attributed ) > 4 && $credit >= ( 1.0 / count( $attributed ) ) );
			$style   = $credit > 0 ? 'background:#fff8e1' : '';
			$click   = trim( ( $touch['click_id_type'] ?? '' ) . ' ' . ( $touch['click_id'] ?? '' ) );
			$percent = $credit > 0 ? round( $credit * 100, 1 ) . '%' : '—';
			echo '<tr style="' . esc_attr( $style ) . '">';
			echo '<td>' . esc_html( (string) ( $i + 1 ) ) . ( $winner ? ' &#9733;' : '' ) . '</td>';
			echo '<td>' . esc_html( (string) ( $touch['created_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $touch['utm_source'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $touch['utm_medium'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $touch['utm_campaign'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( $click ) . '</td>';
			echo '<td><code style="word-break:break-word">' . esc_html( substr( (string) ( $touch['page_url'] ?? '' ), 0, 120 ) ) . '</code></td>';
			echo '<td>' . esc_html( $percent ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	private static function detail_row( string $label, string $value ): void {
		echo '<tr><th style="width:160px;text-align:left">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}

	private static function model_label( string $model ): string {
		$labels = Settings::ATTRIBUTION_MODELS;
		return $labels[ $model ] ?? $model;
	}
}
