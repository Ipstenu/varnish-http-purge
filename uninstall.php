<?php
/**
 * Uninstall
 * @package varnish-http-purge
*/

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

delete_site_option( 'vhp_varnish_url' );
delete_site_option( 'vhp_varnish_ip' );
delete_site_option( 'vhp_varnish_devmode' );
