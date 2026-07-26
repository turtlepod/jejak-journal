<?php
/**
 * Plugin Name: Jejak Journal
 * Description: Private monthly journal with highlights, todos, and notes. Self-hosted on your WordPress site.
 * Plugin URI: https://pandaplugin.com/jejak/
 * Version: 0.3.0
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author: David Chandra Purnama
 * Author URI: https://turtlepod.xyz
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: jejak-journal
 * Domain Path: /languages/
 *
 * @author David Chandra Purnama <turtlepod.xyz@gmail.com>
 * @copyright Copyright (c) 2026, David Chandra Purnama
 * @package JejakJournal
 */

namespace JejakJournal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JEJAK_JOURNAL_VERSION', '0.3.0' );
define( 'JEJAK_JOURNAL_PLUGIN_FILE', __FILE__ );
define( 'JEJAK_JOURNAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JEJAK_JOURNAL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once JEJAK_JOURNAL_PLUGIN_DIR . 'includes/core.php';
