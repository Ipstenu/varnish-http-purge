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

/**
 * Purges Varnish Cache
 */
class WP_CLI_Varnish_Purge_Command extends WP_CLI_Command {
	
	function purge( ) {
		
		$url = parse_url( site_url() );
	
		// Build a varniship
		if ( VHP_VARNISH_IP != false ) {
			$varniship = VHP_VARNISH_IP;
		} else {
			$varniship = get_option('vhp_varnish_ip');
		}

		// If we have a varnish ip, use that
		if ( isset($varniship) && $varniship != null ) {
			$purgeme = 'http://'.$varniship.'/\.*';
		} else {
			$purgeme = 'http://'.$url['host'].'/\.*';
		}
	
		$response = wp_remote_request($purgeme, array('method' => 'PURGE', 'headers' => array( 'host' => $p['host'], 'X-Purge-Method' => 'regex' ) ) );	
		WP_CLI::success( 'The Varnish cache was purged.' );
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp varnish

	purge    purges the entire Varnish cache

EOB
		);
	}

}

WP_CLI::add_command( 'varnish', 'WP_CLI_Varnish_Purge_Command' );