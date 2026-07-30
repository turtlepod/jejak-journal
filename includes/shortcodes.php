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
	add_shortcode( 'jejak_install_button', __NAMESPACE__ . '\\shortcode_install_button' );
}

/**
 * [jejak-journal]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function shortcode_render_journal( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	if ( ! is_user_logged_in() ) {
		$login_redirect_url = get_permalink();
		if ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$login_redirect_url = wp_validate_redirect(
				esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				get_permalink()
			);
		}
		$login_url = wp_login_url( $login_redirect_url );

		// Use the theme login-prompt partial if the companion theme is active.
		$partial = get_theme_file_path( 'templates/partials/login-prompt.php' );
		if ( function_exists( 'JejakTheme\\get_account_page_url' ) && file_exists( $partial ) ) {
			$heading = __( 'Jejak Journal', 'jejak-journal' );
			$desc    = __( 'Please log in to access your journal.', 'jejak-journal' );
			ob_start();
			require $partial;
			return ob_get_clean();
		}

		$account_page_url = function_exists( 'JejakTheme\\get_account_page_url' ) ? \JejakTheme\get_account_page_url() : '';
		if ( $account_page_url ) {
			$login_url = add_query_arg( 'redirect_to', $login_redirect_url, $account_page_url );
		}
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
/**
 * [jejak_install_button]
 *
 * Usage:
 * [jejak_install_button]
 * [jejak_install_button label="Install App" tag="a"]
 *
 * @param array<string, string> $atts Shortcode attrs.
 * @return string
 */
function shortcode_install_button( $atts ) {
	if ( ! get_option( 'jejak_pwa_enabled', false ) ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'label' => __( 'Install App', 'jejak-journal' ),
			'tag'   => 'button',
			'class' => '',
		),
		$atts,
		'jejak_install_button'
	);

	$tag   = 'a' === strtolower( $atts['tag'] ) ? 'a' : 'button';
	$label = wp_strip_all_tags( (string) $atts['label'] );
	$class = 'jejak-install-app-btn' . ( $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '' );

	if ( 'a' === $tag ) {
		return sprintf(
			'<a href="#" class="%s" data-jejak-install-app role="button">%s</a>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	return sprintf(
		'<button type="button" class="%s" data-jejak-install-app>%s</button>',
		esc_attr( $class ),
		esc_html( $label )
	);
}
