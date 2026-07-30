<?php
/**
 * Web App Manifest support for PWA installability.
 *
 * @package JejakJournal
 */

namespace JejakJournal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register manifest hooks.
 */
function manifest_setup() {
	add_action( 'init', __NAMESPACE__ . '\\manifest_register_rewrite' );
	add_filter( 'query_vars', __NAMESPACE__ . '\\manifest_query_vars' );
	add_action( 'template_redirect', __NAMESPACE__ . '\\manifest_serve', 1 );
	add_action( 'wp_head', __NAMESPACE__ . '\\manifest_head_tags', 1 );
}

/**
 * Register rewrite rule for manifest.json.
 */
function manifest_register_rewrite() {
	add_rewrite_rule( '^manifest\\.json$', 'index.php?jejak_manifest=1', 'top' );
}

/**
 * Add query vars.
 *
 * @param array<string> $vars Query vars.
 * @return array<string>
 */
function manifest_query_vars( $vars ) {
	$vars[] = 'jejak_manifest';
	$vars[] = 'jejak_sw';
	return $vars;
}

/**
 * Serve manifest JSON or service worker when requested.
 */
function manifest_serve() {
	$serve_manifest = (bool) get_query_var( 'jejak_manifest' );
	$serve_sw       = (bool) get_query_var( 'jejak_sw' );

	if ( ! $serve_manifest && ! $serve_sw ) {
		return;
	}

	if ( ! get_option( 'jejak_pwa_enabled', false ) ) {
		status_header( 404 );
		nocache_headers();
		echo '';
		exit;
	}

	if ( $serve_sw ) {
		status_header( 200 );
		header( 'Content-Type: application/javascript; charset=' . get_bloginfo( 'charset' ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Service-Worker-Allowed: /' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo manifest_get_service_worker_script();
		exit;
	}

	$manifest = manifest_get_data();
	status_header( 200 );
	header( 'Content-Type: application/manifest+json; charset=' . get_bloginfo( 'charset' ) );
	header( 'X-Content-Type-Options: nosniff' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}

/**
 * Output manifest link and theme-color meta in head.
 */
function manifest_head_tags() {
	if ( ! get_option( 'jejak_pwa_enabled', false ) ) {
		return;
	}

	$manifest_url = home_url( '/manifest.json' );
	printf(
		"<link rel=\"manifest\" href=\"%s\">\n",
		esc_url( $manifest_url )
	);

	$theme_color = get_option( 'jejak_pwa_theme_color', '#1a1a1b' );
	if ( $theme_color ) {
		printf(
			"<meta name=\"theme-color\" content=\"%s\">\n",
			esc_attr( $theme_color )
		);
	}

	$sw_url = add_query_arg( 'jejak_sw', '1', home_url( '/' ) );
	printf(
		"<script>(function(){if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register(%s).catch(function(){});});}})();</script>\n",
		wp_json_encode( esc_url_raw( $sw_url ) )
	);
}

/**
 * Get service worker source.
 *
 * @return string
 */
function manifest_get_service_worker_script() {
	return "const CACHE='jejak-pwa-v1';\n"
		. "self.addEventListener('install',event=>{self.skipWaiting();event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(['/',self.location.href])).catch(()=>{}));});\n"
		. "self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim()));});\n"
		. "self.addEventListener('fetch',event=>{if(event.request.method!=='GET'){return;}event.respondWith(fetch(event.request).catch(()=>caches.match(event.request)));});\n";
}

/**
 * Build manifest data array.
 *
 * @return array<string, mixed>
 */
function manifest_get_data() {
	$app_name   = get_option( 'jejak_pwa_app_name', '' );
	$short_name = get_option( 'jejak_pwa_short_name', '' );
	if ( empty( $app_name ) ) {
		$app_name = get_bloginfo( 'name' );
	}
	if ( empty( $short_name ) ) {
		$short_name = function_exists( 'mb_substr' ) ? mb_substr( $app_name, 0, 12 ) : substr( $app_name, 0, 12 );
	}

	$data = array(
		'name'             => $app_name,
		'short_name'       => $short_name,
		'start_url'        => home_url( '/' ),
		'display'          => 'standalone',
		'background_color' => '#ffffff',
		'theme_color'      => get_option( 'jejak_pwa_theme_color', '#1a1a1b' ),
	);

	$icons = manifest_get_icons();
	if ( ! empty( $icons ) ) {
		$data['icons'] = $icons;
	}

	return $data;
}

/**
 * Get icon entries for manifest.
 *
 * @return array<int, array{src: string, sizes: string, type: string}>
 */
function manifest_get_icons() {
	$icon_192 = get_site_icon_url( 192 );
	$icon_512 = get_site_icon_url( 512 );

	if ( $icon_192 && $icon_512 ) {
		return array(
			array(
				'src'   => $icon_192,
				'sizes' => '192x192',
				'type'  => 'image/png',
			),
			array(
				'src'   => $icon_512,
				'sizes' => '512x512',
				'type'  => 'image/png',
			),
		);
	}

	// Fallback to plugin default icons.
	$base = JEJAK_JOURNAL_PLUGIN_URL . 'assets/images/';
	return array(
		array(
			'src'   => $base . 'icon-192.png',
			'sizes' => '192x192',
			'type'  => 'image/png',
		),
		array(
			'src'   => $base . 'icon-512.png',
			'sizes' => '512x512',
			'type'  => 'image/png',
		),
	);
}
