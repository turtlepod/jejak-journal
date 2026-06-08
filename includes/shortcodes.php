<?php
/**
 * Shortcodes for Jejak Journal.
 *
 * @package JejakJournal
 */

namespace JejakJournal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', __NAMESPACE__ . '\\shortcodes_setup' );

/**
 * Register shortcodes.
 */
function shortcodes_setup() {
	add_shortcode( 'jejak-journal', __NAMESPACE__ . '\\shortcode_render_journal' );
}

/**
 * [jejak-journal]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function shortcode_render_journal( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	if ( ! is_user_logged_in() ) {
		$login_url = wp_login_url( get_permalink() );
		return sprintf(
			'<div class="jejak-login-notice"><p>%s</p><a class="jejak-login-btn" href="%s">%s</a></div>',
			esc_html__( 'Please log in to access your journal.', 'jejak-journal' ),
			esc_url( $login_url ),
			esc_html__( 'Log in', 'jejak-journal' )
		);
	}

	if ( ! DB::user_can_manage() ) {
		return '<div class="jejak-login-notice"><p>' . esc_html__( 'You do not have permission to manage journals.', 'jejak-journal' ) . '</p></div>';
	}

	$enabled_features = get_option( 'jejak_journal_features', array( 'highlights', 'todos', 'journal' ) );

	$now           = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	$current_year  = (int) gmdate( 'Y', $now );
	$current_month = (int) gmdate( 'n', $now );

	ob_start();
	require JEJAK_JOURNAL_PLUGIN_DIR . 'templates/journal.php';
	return ob_get_clean();
}
