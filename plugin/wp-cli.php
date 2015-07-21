<?php
	
/**
	This file is part of Varnish HTTP Purge, a plugin for WordPress.

	Varnish HTTP Purge is free software: you can redistribute it and/or modify
	it under the terms of the Apache License 2.0 license.

	Varnish HTTP Purge is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
	
*/

if (!defined('ABSPATH')) {
    die();
}

// Bail if WP-CLI is not present
if ( !defined( 'WP_CLI' ) ) return;

/**
 * Purges Varnish Cache
 */
class WP_CLI_Varnish_Purge_Command extends WP_CLI_Command {

	public function __construct() {
		$this->varnish_purge = new VarnishPurger();
	}
	
    /**
     * Forces a Varnish Purge
     * 
     * ## EXAMPLES
     * 
     *     wp varnish purge
     *
     */
	
	function purge( ) {	
			
		wp_create_nonce('varnish-http-purge-cli');

		$this->varnish_purge->purgeUrl( home_url() .'/?vhp-regex' );
		
		WP_CLI::success( "Testing." );
	}
	
	function oldpurge( ) {
		
		$url = parse_url( home_url() );
	
		// Build a varniship
		if ( VHP_VARNISH_IP != false ) {
			$varniship = VHP_VARNISH_IP;
		} else {
			$varniship = get_option('vhp_varnish_ip');
		}

		// If we have a varnish ip, use that
		if ( isset($varniship) && $varniship != null ) {
			$purgeme = 'http://'.$varniship.'/.*';
		} else {
			$purgeme = 'http://'.$url['host'].'/.*';
		}
	
		$response = wp_remote_request($purgeme, array('method' => 'PURGE', 'headers' => array( 'host' => $url['host'], 'X-Purge-Method' => 'regex' ) ) );	
		WP_CLI::success( 'The Varnish cache was purged.' );
	}

}

WP_CLI::add_command( 'varnish', 'WP_CLI_Varnish_Purge_Command' );