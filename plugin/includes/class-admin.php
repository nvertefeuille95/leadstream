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
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No submissions captured yet. Submit a form on the frontend to see it here.', 'leadstream' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$click = trim( ( $row['click_id_type'] ?? '' ) . ' ' . ( $row['click_id'] ?? '' ) );
				$form  = trim( ( $row['form_source'] ?? '' ) . ' ' . ( $row['form_id'] ?? '' ) );
				echo '<tr>';
				echo '<td>' . esc_html( (string) ( $row['created_at'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['utm_source'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['utm_medium'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['utm_campaign'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( $click ) . '</td>';
				echo '<td>' . esc_html( $form ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['landing_page'] ?? '' ) ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}
}
