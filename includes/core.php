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
	$has_shortcode = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'jejak-journal' );
	$is_theme_template = is_a( $post, 'WP_Post' ) && 'templates/jejak.php' === get_page_template_slug( $post->ID );
	if ( ! $has_shortcode && ! $is_theme_template ) {
		return;
	}

	$ver = JEJAK_JOURNAL_VERSION;

	wp_enqueue_style(
		'jejak-journal-front',
		JEJAK_JOURNAL_PLUGIN_URL . 'assets/css/front.css',
		array(),
		$ver
	);

	wp_enqueue_script(
		'jejak-journal-front',
		JEJAK_JOURNAL_PLUGIN_URL . 'assets/js/front.js',
		array(),
		$ver,
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
		)
	);
}

/**
 * Get available icon list for highlights.
 *
 * @return array<string, string>
 */
function get_icon_list() {
	return array(
		'star'    => '⭐',
		'heart'   => '❤️',
		'dog'     => '🐕',
		'fork'    => '🍴',
		'car'     => '🚗',
		'flower'  => '🌸',
		'laptop'  => '💻',
		'book'    => '📖',
		'music'   => '🎵',
		'sun'     => '☀️',
		'moon'    => '🌙',
		'fire'    => '🔥',
		'rocket'  => '🚀',
		'trophy'  => '🏆',
		'bulb'    => '💡',
		'gift'    => '🎁',
		'check'   => '✅',
		'sparkle' => '✨',
		'rainbow' => '🌈',
		'coffee'  => '☕',
	);
}
