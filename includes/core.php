<?php
/**
 * Core bootstrap for Jejak Journal plugin.
 *
 * @package JejakJournal
 */

namespace JejakJournal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once JEJAK_JOURNAL_PLUGIN_DIR . 'includes/class-jejak-db.php';
require_once JEJAK_JOURNAL_PLUGIN_DIR . 'includes/rest.php';
require_once JEJAK_JOURNAL_PLUGIN_DIR . 'includes/shortcodes.php';
require_once JEJAK_JOURNAL_PLUGIN_DIR . 'includes/admin.php';
require_once JEJAK_JOURNAL_PLUGIN_DIR . 'includes/manifest.php';
require_once JEJAK_JOURNAL_PLUGIN_DIR . 'includes/icon-library.php';

register_activation_hook( JEJAK_JOURNAL_PLUGIN_FILE, __NAMESPACE__ . '\\on_activation' );

add_action( 'plugins_loaded', __NAMESPACE__ . '\\on_plugins_loaded' );

/**
 * Fires on plugin activation.
 */
function on_activation() {
	DB::install();
	manifest_register_rewrite();
	flush_rewrite_rules();
}

/**
 * Fires on plugins_loaded.
 */
function on_plugins_loaded() {
	load_plugin_textdomain( 'jejak-journal', false, dirname( plugin_basename( JEJAK_JOURNAL_PLUGIN_FILE ) ) . '/languages' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_front_scripts' );
	manifest_setup();
}

/**
 * Enqueue frontend scripts and styles.
 */
function enqueue_front_scripts() {
	global $post;
	$has_shortcode     = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'jejak-journal' );
	$is_theme_template = is_a( $post, 'WP_Post' ) && 'templates/jejak.php' === get_page_template_slug( $post->ID );
	if ( ! $has_shortcode && ! $is_theme_template ) {
		return;
	}

	$ver = JEJAK_JOURNAL_VERSION;

	$css_asset_file = JEJAK_JOURNAL_PLUGIN_DIR . 'dist/css/front-style.asset.php';
	if ( file_exists( $css_asset_file ) ) {
		$css_asset = require $css_asset_file;
		$css_deps  = $css_asset['dependencies'] ?? array();
		$css_ver   = $css_asset['version'] ?? $ver;
	} else {
		$css_deps = array();
		$css_ver  = $ver;
	}

	wp_enqueue_style(
		'jejak-journal-front',
		JEJAK_JOURNAL_PLUGIN_URL . 'dist/css/front-style.css',
		$css_deps,
		$css_ver
	);

	$js_asset_file = JEJAK_JOURNAL_PLUGIN_DIR . 'dist/js/front.asset.php';
	if ( file_exists( $js_asset_file ) ) {
		$js_asset = require $js_asset_file;
		$js_deps  = $js_asset['dependencies'] ?? array();
		$js_ver   = $js_asset['version'] ?? $ver;
	} else {
		$js_deps = array();
		$js_ver  = $ver;
	}

	wp_enqueue_script(
		'jejak-journal-front',
		JEJAK_JOURNAL_PLUGIN_URL . 'dist/js/front.js',
		$js_deps,
		$js_ver,
		true
	);

	wp_localize_script(
		'jejak-journal-front',
		'jejakJournalData',
		array(
			'rest_url'      => get_rest_url( null, 'jejak-journal/v1' ),
			'rest_nonce'    => wp_create_nonce( 'wp_rest' ),
			'user_id'       => get_current_user_id(),
			'features'      => get_option( 'jejak_journal_features', array( 'highlights', 'todos', 'journal' ) ),
			'current_year'  => (int) gmdate( 'Y' ),
			'current_month' => (int) gmdate( 'n' ),
			'i18n'          => array(
				'saved'          => __( 'Saved.', 'jejak-journal' ),
				'error'          => __( 'Something went wrong.', 'jejak-journal' ),
				'delete_confirm' => __( 'Delete this item?', 'jejak-journal' ),
				'cancel'         => __( 'Cancel', 'jejak-journal' ),
				'delete'         => __( 'Delete', 'jejak-journal' ),
				'ok'             => __( 'OK', 'jejak-journal' ),
				'add_highlight'  => __( 'Add highlight', 'jejak-journal' ),
				'add_todo'       => __( 'Add to-do', 'jejak-journal' ),
				'no_entry'       => __( 'No journal entry for this month yet.', 'jejak-journal' ),
				'create_entry'   => __( 'Create entry', 'jejak-journal' ),
				'select_icon'    => __( 'Select icon', 'jejak-journal' ),
				'search_icon'    => __( 'Search icons…', 'jejak-journal' ),
				'please_login'   => __( 'Please log in to access your journal.', 'jejak-journal' ),
				'no_permission'  => __( 'You do not have permission to manage journals.', 'jejak-journal' ),
				'loading'        => __( 'Loading…', 'jejak-journal' ),
			),
			'icons'         => get_icon_list(),
			'allIcons'      => jejak_journal_get_full_icon_library(),
		)
	);
}

/**
 * Get available icon list for highlights.
 *
 * Uses saved settings if configured, otherwise the default 20.
 *
 * @return array<string, string>
 */
function get_icon_list() {
	$saved = get_option( 'jejak_journal_default_icons', '' );
	if ( $saved && is_string( $saved ) && '' !== trim( $saved ) ) {
		$library = jejak_journal_get_full_icon_library();
		$slugs   = array_filter( array_map( 'trim', explode( "\n", $saved ) ) );
		$icons   = array();
		foreach ( $slugs as $slug ) {
			if ( isset( $library[ $slug ] ) ) {
				$icons[ $slug ] = $library[ $slug ];
			}
		}
		if ( ! empty( $icons ) ) {
			return $icons;
		}
	}
	return get_default_icon_list();
}

/**
 * Get the hardcoded default icon list (fallback).
 *
 * @return array<string, string>
 */
function get_default_icon_list() {
	return array(
		'star'              => '⭐',
		'red_heart'         => '❤️',
		'dog'               => '🐕',
		'fork_and_knife'    => '🍴',
		'automobile'        => '🚗',
		'cherry_blossom'    => '🌸',
		'laptop'            => '💻',
		'open_book'         => '📖',
		'musical_note'      => '🎵',
		'sun'               => '☀️',
		'crescent_moon'     => '🌙',
		'fire'              => '🔥',
		'rocket'            => '🚀',
		'trophy'            => '🏆',
		'light_bulb'        => '💡',
		'wrapped_gift'      => '🎁',
		'check_mark_button' => '✅',
		'sparkles'          => '✨',
		'rainbow'           => '🌈',
		'hot_beverage'      => '☕',
	);
}
