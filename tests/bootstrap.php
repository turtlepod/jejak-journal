<?php
/**
 * PHPUnit bootstrap.
 *
 * @package JejakJournal
 */

// WP_Mock setup for unit tests.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();
