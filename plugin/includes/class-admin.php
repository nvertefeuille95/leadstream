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
