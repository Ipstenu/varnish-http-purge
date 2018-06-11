<?php
/**
	Copyright 2015-2018 Mika Epstein (email: ipstenu@halfelf.org)
	
	This file is part of Varnish HTTP Purge, a plugin for WordPress.

	Varnish HTTP Purge is free software: you can redistribute it and/or modify
	it under the terms of the Apache License 2.0 license.

	Varnish HTTP Purge is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

if ( !defined( 'ABSPATH' ) ) die();

// Bail if WP-CLI is not present
if ( !defined( 'WP_CLI' ) ) return;

/**
 * WP-CLI Commands
 */
 
if ( !class_exists( 'WP_CLI_Varnish_Command' ) ) {
	class WP_CLI_Varnish_Command extends WP_CLI_Command {
	
		private $wildcard = false;
	
		public function __construct() {
			$this->varnish_purge = new VarnishPurger();
		}
		
		/**
		 * Forces a full Varnish Purge of the entire site (provided
		 * regex is supported). Alternately you can fluxh the cache
		 * for specific pages or folders (using the --wildcard param)
		 * 
		 * ## EXAMPLES
		 * 
		 *		wp varnish purge
		 *
		 *		wp varnish purge http://example.com/wp-content/themes/twentyeleventy/style.css
		 *
		 *		wp varnish purge http://example.com/wp-content/themes/ --wildcard
		 *
		 */
		
		function purge( $args, $assoc_args ) {	
			
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
	
		/**
		 * Runs a debug check of the site to see if there are any known 
		 * issues that would stop Varnish from caching.
		 * 
		 * ## EXAMPLES
		 * 
		 *		wp varnish debug
		 *
		 *		wp varnish debug http://example.com/wp-content/themes/twentyeleventy/style.css
		 *
		 */
		
		function debug( $args, $assoc_args ) {
	
			// Set the URL/path
			if ( !empty($args) ) list( $url ) = $args;
	
			$default_url = esc_url( $this->varnish_purge->the_home_url() );
	
			if ( !empty( $url ) ) {
				$parsed_input = parse_url($url);
				if ( empty($parsed_input['scheme']) ) {
					$schema_input = 'http://';
					if ( is_ssl() ) $schema_input = 'https://';
					$url = $schema_input . ltrim($url, '/');
				}
			} else {
				$url = $default_url;
			}
	
			if ( empty( $url ) || parse_url( $default_url, PHP_URL_HOST ) !== parse_url( $url, PHP_URL_HOST ) ) {
				WP_CLI::error( __( 'You must enter a URL from your own domain to scan.', 'varnish-http-purge' ) );
			} elseif ( !filter_var( $url, FILTER_VALIDATE_URL) ) {
				WP_CLI::error( __( 'You have entered an invalid URL address.', 'varnish-http-purge' ) );
			} else {
	
				// Include the debug code
				if ( !class_exists( 'VarnishDebug' ) ) include( 'debug.php' );
	
				$varnishurl = get_option( 'vhp_varnish_url', $url );
	
				// Get the response and headers
				$remote_get = VarnishDebug::remote_get( $varnishurl );
				$headers    = wp_remote_retrieve_headers( $remote_get );

				// Preflight checklist
				$preflight = VarnishDebug::preflight( $remote_get );
		
				// Check for Remote IP
				$remote_ip = VarnishDebug::remote_ip( $headers );

				// Get the Varnish IP
				if ( VHP_VARNISH_IP != false ) {
					$varniship = VHP_VARNISH_IP;
				} else {
					$varniship = get_option('vhp_varnish_ip');
				}

				if ( $preflight['preflight'] == false ) {
					WP_CLI::error( $preflight['message'] );
				} else {
					$results = VarnishDebug::get_all_the_results( $headers, $remote_ip, $varniship );
	
					// Generate array
					foreach ( $results as $type => $content ) { 
						$items[] = array(
							'name'    => $type,
							'status'  => ucwords( $content['icon'] ),
							'message' => $content['message'],
						);
					}

					$format = ( isset( $assoc_args['format'] ) )? $assoc_args['format'] : 'table';

					// Output the data
					WP_CLI\Utils\format_items( $format, $items, array( 'name', 'status', 'message' ) );
				}
			}
		}
	}
}

WP_CLI::add_command( 'varnish', 'WP_CLI_Varnish_Command' );