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
	
	function purge() {	
		wp_create_nonce('varnish-http-purge-cli');

		$this->varnish_purge->purgeUrl( home_url() .'/?vhp-regex' );

		WP_CLI::success( 'The Varnish cache was purged.' );
	}

}

WP_CLI::add_command( 'varnish', 'WP_CLI_Varnish_Purge_Command' );