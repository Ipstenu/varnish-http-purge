<?php
/**
	Copyright 2015-2017 Mika Epstein (email: ipstenu@halfelf.org)
	
	This file is part of Varnish HTTP Purge, a plugin for WordPress.

	Varnish HTTP Purge is free software: you can redistribute it and/or modify
	it under the terms of the Apache License 2.0 license.

	Varnish HTTP Purge is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

if ( !defined( 'ABSPATH' ) ) {
    die();
}

// Bail if WP-CLI is not present
if ( !defined( 'WP_CLI' ) ) return;

/**
 * Purges Varnish Cache
 */
class WP_CLI_Varnish_Purge_Command extends WP_CLI_Command {

	private $wildcard = false;

	public function __construct() {
		$this->varnish_purge = new VarnishPurger();
	}
	
    /**
     * Forces a full Varnish Purge of the entire site (provided
     * regex is supported).
     * 
     * ## EXAMPLES
     * 
     *		wp varnish purge
     *
     *		wp varnish purge http://example.com/wp-content/themes/twentyeleventy/style.css
     *
	 *		wp varnish purge "/wp-content/themes/twentysixty/style.css"
	 *
     *		wp varnish purge http://example.com/wp-content/themes/ --wildcard
     *
     *		wp varnish purge "/wp-content/themes/" --wildcard
     *
     */
	
	function purge( $args , $assoc_args ) {	
		
		$wp_version = get_bloginfo( 'version' );
		$cli_version = WP_CLI_VERSION;
		
		// Set the URL/path
		if ( !empty($args) ) { list( $url ) = $args; }

		// If wildcard is set, or the URL argument is empty
		// then treat this as a full purge
		$pregex = $wild = '';
		if ( isset( $assoc_args['wildcard'] ) || empty($url) ) {
			$pregex = '/?vhp-regex';
			$wild = ".*";
		}

		wp_create_nonce('vhp-flush-cli');

		// If the URL is not empty, sanitize. Else use home URL.
		if ( !empty( $url ) ) {
			$url = esc_url( $url );
			
			// If it's a regex, let's make sure we don't have //
			if ( isset( $assoc_args['wildcard'] ) ) $url = rtrim( $url, '/' );
		} else {
			$url = $this->varnish_purge->the_home_url();
		}
		
		if ( version_compare( $wp_version, '4.6', '>=' ) && ( version_compare( $cli_version, '0.25.0', '<' ) || version_compare( $cli_version, '0.25.0-alpha', 'eq' ) ) ) {
			
			WP_CLI::log( sprintf( 'This plugin does not work on WP 4.6 and up, unless WP-CLI is version 0.25.0 or greater. You\'re using WP-CLI %s and WordPress %s.', $cli_version, $wp_version ) );
			WP_CLI::log( 'To flush your cache, please run the following command:');
			WP_CLI::log( sprintf( '$ curl -X PURGE "%s"' , $url . $wild ) );
			WP_CLI::error( 'Varnish Cache must be purged manually.' );
		}

		$this->varnish_purge->purgeUrl( $url . $pregex );
		
		if ( WP_DEBUG == true ) {
			WP_CLI::log( sprintf( 'Flushing URL: %s with params: %s.', $url, $pregex ) );
		}

		WP_CLI::success( 'The Varnish cache was purged.' );
	}

}

WP_CLI::add_command( 'varnish', 'WP_CLI_Varnish_Purge_Command' );