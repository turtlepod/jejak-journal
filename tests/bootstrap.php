<?php
/**
 * PHPUnit bootstrap.
 *
 * @package JejakJournal
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_TESTS_ABSPATH' ) ) {
	return;
}

// WP_Mock setup for unit tests.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();
