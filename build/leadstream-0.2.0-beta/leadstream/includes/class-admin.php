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

	public static function add_menu(): void {
		add_menu_page(
			__( 'LeadStream', 'leadstream' ),
			__( 'LeadStream', 'leadstream' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_submissions' ),
			'dashicons-filter',
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
