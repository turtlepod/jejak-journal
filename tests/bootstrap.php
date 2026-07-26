<?php
/**
 * PHPUnit bootstrap.
 *
 * @package JejakJournal
 */

if ( ! defined( 'ABSPATH' ) ) {
	if ( defined( 'WP_TESTS_ABSPATH' ) ) {
		define( 'ABSPATH', WP_TESTS_ABSPATH );
	} elseif ( PHP_SAPI !== 'cli' ) {
		exit;
	} else {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}
}

// WP_Mock setup for unit tests.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();
