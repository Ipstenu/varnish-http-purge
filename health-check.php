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

	// Check the debug log
	$debug_log     = get_site_option( 'vhp_varnish_debug' );
	$debug_results = array();
	foreach ( $debug_log as $site => $results ) {
		foreach ( $results as $item => $content ) {
			$sitename = ( VarnishPurger::the_home_url() !== $site ) ? 'Site: ' . $site . '<br />' : '';
			// Log cache not working
			if ( 'Cache Service' === $item && 'notice' === $content['icon'] ) {
				$debug_results[ $item ] = $sitename . $content['message'];
			}
			// Log all Criticals
			if ( isset( $content['icon'] ) && 'bad' === $content['icon'] ) {
				$debug_results[ $item ] = $sitename . $content['message'];
			}
		}
	}

	// Defaults, all is good:
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
		$result['label']       = __( 'Proxy Cache Purge is in development mode', 'varnish-http-purge' );
		$result['description'] = sprintf(
			'<p>%s</p>',
			__( 'Proxy Cache Purge is active but in dev mode, which means it will not serve cached content to your users. If this is intentional, carry on. Otherwise you should re-enable caching.', 'varnish-http-purge' )
		);
		$result['actions']     = sprintf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=varnish-page' ) ),
			__( 'Enable Caching' )
		);
	} elseif ( ! empty( $debug_results ) && '' !== $debug_results ) {
		$count = count( $debug_results );
		// Translators: %d is the number of issues reported
		$desc  = sprintf( _n( 'The most recent cache status check reported %d issue.', 'The most recent cache status check reported %d issues.', $count, 'varnish-http-purge' ), $count );

		$result['status']      = 'critical';
		// Translators: %d is the number of issues reported
		$result['label']       = sprintf( __( 'Proxy Cache Purge has reported caching errors (%s)', 'varnish-http-purge' ), $count );
		$result['description'] = sprintf(
			'<p>%s</p>',
			$desc
		);
		$result['description'] .= '<ul>';
		foreach ( $debug_results as $key => $value ) {
			$result['description'] .= '<li><strong>' . $key . '</strong>: ' . $value . '</li>';
		}
		$result['description'] .= '</ul>';
	}

	return $result;
}
