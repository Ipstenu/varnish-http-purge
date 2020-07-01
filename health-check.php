<?php
/**
 * Health Check Code
 * @package varnish-http-purge
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

// Health Check Test
add_filter( 'site_status_tests', 'vhp_add_site_status_tests' );

function vhp_add_site_status_tests( $tests ) {
	$tests['direct']['proxy_cache_purge_caching'] = array(
		'label' => __( 'Proxy Cache Purge Status', 'varnish-http-purge' ),
		'test'  => 'vhp_site_status_caching_test',
	);
	return $tests;
}

function vhp_site_status_caching_test() {
	$result = array(
		'label'       => __( 'Proxy Cache Purge is working', 'varnish-http-purge' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'varnish-http-purge' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'Caching can help load your site more quickly for visitors. You\'re doing great!', 'varnish-http-purge' )
		),
		'actions'     => sprintf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=varnish-check-caching' ) ),
			__( 'Check Caching Status', 'varnish-http-purge' )
		),
		'test'        => 'caching_plugin',
	);

	// If we're in dev mode....
	if ( VarnishDebug::devmode_check() ) {
		$result['status']      = 'recommended';
		$result['label']       = __( 'Proxy Cache Purge is in development mode' );
		$result['description'] = sprintf(
			'<p>%s</p>',
			__( 'Proxy Cache Purge is active but in dev mode, which means it will not serve cached content to your users. If this is intentional, carry on. Otherwise you should enable caching.', 'varnish-http-purge' )
		);
		$result['actions']     = sprintf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=varnish-page' ) ),
			__( 'Enable Caching' )
		);
	}

	return $result;
}
