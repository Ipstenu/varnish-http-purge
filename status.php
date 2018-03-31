<?php
/**
	Copyright 2016-2018 Mika Epstein (email: ipstenu@halfelf.org)

	This file is part of Varnish HTTP Purge, a plugin for WordPress.

	Varnish HTTP Purge is free software: you can redistribute it and/or modify
	it under the terms of the Apache License 2.0 license.

	Varnish HTTP Purge is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

if ( !defined( 'ABSPATH' ) ) die();

/**
 * Varnish Status Class
 *
 * @since 4.0
 */

class VarnishStatus {

	/**
	 * Construct
	 * Fires when class is constructed, adds init hook
	 *
	 * @since 4.0
	 */
	function __construct() {
		// Admin Init Hooks
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		// Admin Menu
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
	}

	/**
	 * Admin init Callback
	 *
	 * @since 4.0
	 */
	function admin_init() {
		$this->register_settings_url();		
		if ( !is_multisite() ) $this->register_settings_ip();
	}

	/**
	 * Admin Menu Callback
	 *
	 * @since 4.0
	 */
	function admin_menu() {
		add_management_page( __( 'Is Varnish Working?', 'varnish-http-purge' ), __( 'Varnish Status', 'varnish-http-purge' ), 'manage_options', 'varnish-status', array( &$this, 'settings_page' ) );
	}

	/**
	 * Register Admin Settings URL
	 *
	 * @since 4.0
	 */
	function register_settings_url() {
		register_setting( 'varnish-http-purge-url', 'vhp_varnish_url', array( &$this, 'varnish_url_sanitize' ) );
		add_settings_section( 'varnish-url-settings-section', __( 'Check Varnish Status', 'varnish-http-purge' ), array( &$this, 'options_callback_url'), 'varnish-url-settings' );
		add_settings_field( 'varnish_url', __( 'Check A URL On Your Site:', 'varnish-http-purge' ), array( &$this, 'varnish_url_callback' ), 'varnish-url-settings', 'varnish-url-settings-section' );
	}

	/**
	 * Register Admin Settings IP
	 *
	 * @since 4.0.2
	 */
	function register_settings_ip() {
		register_setting( 'varnish-http-purge-ip', 'vhp_varnish_ip', array( &$this, 'varnish_ip_sanitize' ) );
		add_settings_section( 'varnish-ip-settings-section', __('Configure Custom Varnish IP', 'varnish-http-purge'), array( &$this, 'options_callback_ip'), 'varnish-ip-settings' );
		add_settings_field( 'varnish_ip', __('Set Varnish IP', 'varnish-http-purge'), array( &$this, 'varnish_ip_callback'), 'varnish-ip-settings', 'varnish-ip-settings-section' );
	}

	/**
	 * Options Callback - IP Address
	 *
	 * @since 4.0
	 */
	function options_callback_ip() {
		?>
		<p><a name="#configure"></a><?php _e( 'The majority of users will never need to look down here. However there are cases when a custom Varnish IP Address will need to be set, in order to tell the plugin to empty the cache in a specific location. If you\'re using a CDN like Cloudflare or a Firewall Proxy like Sucuri, you will want to set this.', 'varnish-http-purge' ); ?></p>
		<p><?php _e( 'Your Varnish IP the IP address of the server where Varnish is installed. Your Varnish IP must be one of the IPs that Varnish is listening. If you use multiple IPs, or if you\'ve customized your ACLs, you\'ll need to pick one that doesn\'t conflict with your other settings. For example, if you have Varnish listening on a public and private IP, pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1).', 'varnish-http-purge' ); ?></p>
		<p><?php _e( 'If your webhost set up Varnish for you, as is the case with DreamPress or WP Engine, ask them for the specifics if they don\'t have it documented. I\'ve listed the ones I know about here, however you should still check with them if you\'re not sure.', 'varnish-http-purge' ); ?></p>
		<p><strong><?php _e( 'If you aren\'t sure what to do, contact your webhost or server admin before making any changes.', 'varnish-http-purge' ); ?></strong></p>

		<ul>
			<li><?php _e( 'DreamHost - Go into the Panel and click on the DNS settings for the domain. The entry for <em>resolve-to.domain</em> (if set) will be your varnish server. If it\'s not set, then you don\'t need to worry about this at all. Example:', 'varnish-http-purge' ); ?> <code>resolve-to.www A 208.97.157.172</code></li>
		</ul>
		<?php
	}

	/**
	 * Varnish IP Callback
	 *
	 * @since 4.0
	 */
	function varnish_ip_callback() {

		$disabled = false;
		
		if ( VHP_VARNISH_IP != false ) {
			$disabled = true;
			$varniship = VHP_VARNISH_IP;
		} else {
			$varniship = get_option( 'vhp_varnish_ip' );
		}
				
		?>
		<input type="text" id="vhp_varnish_ip" name="vhp_varnish_ip" value="<?php echo $varniship; ?>" size="25" <?php if ( $disabled == true ) { echo 'disabled'; } ?>/>
		<label for="vhp_varnish_ip">
			<?php
			if ( $disabled == true ) { 
				_e( 'A Varnish IP has been defined in your wp-config, so it is not editable here.', 'varnish-http-purge' );
			} else {
				_e( 'Example:', 'varnish-http-purge' ); ?> <code>123.45.67.89</code><?php
			}
			?>
		</label>
		<?php
	}

	/**
	 * Options Callback - URL Scanner
	 *
	 * @since 4.0
	 */
	function options_callback_url() {

		// Include the debug code
		include( 'debug.php' );

		?><p><?php _e( 'While it is impossible to detect all possible conflicts, this status page performs a check of the most common issues that prevent Varnish from caching your site properly.', 'varnish-http-purge' ); ?></p>
		
		<p><?php _e( 'This feature is provided to help you in debugging any conflicts on your own. When filing an issue with your web-host, we recommend you include the output in your ticket.', 'varnish-http-purge' ); ?></p>
		
		<?php
		// Set icons
		$icons = array (
			'awesome' => '<span class="dashicons dashicons-heart" style="color:#008000;"></span>',
			'good'    => '<span class="dashicons dashicons-thumbs-up" style="color:#008000;"></span>',
			'warning' => '<span class="dashicons dashicons-warning" style="color:#FF9933"></span>',
			'awkward' => '<span class="dashicons dashicons-flag" style="color:#FF9933;">',
			'bad'     => '<span class="dashicons dashicons-thumbs-down" style="color:#990000;"></span>',
		);

		// Get the base URL to start
		$url        = esc_url( VarnishPurger::the_home_url() );
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
		?>
		
		<h4><?php printf( __( 'Results for %s', 'varnish-http-purge'  ), $varnishurl ); ?></h4>
		
		<table class="wp-list-table widefat fixed posts">
		<?php

			// If we failed the preflight checks, we fail.
			if ( $preflight['preflight'] == false ) {
				?><tr>
					<td width="40px"><?php echo $icons['bad']; ?></td>
					<td><?php echo $preflight['message']; ?></td>
				</tr><?php
			} else {
				// We passed the checks, let's get the data!

				$output = VarnishDebug::get_all_the_results( $headers, $remote_ip, $varniship );

				foreach ( $output as $item ) {
					if ( $item !== false && is_array( $item ) ) {
						?><tr>
							<td width="40px"><?php echo $icons[ $item['icon'] ]; ?></td>
							<td><?php echo $item['message'] ?></td>
						</tr><?php
					}
				}
			}
		?>
		</table>

		<?php
		if ( $preflight['preflight'] !== false ) {
		?>
			<h4><?php _e( 'Technical Details', 'varnish-http-purge'  ); ?></h4>
			<table class="wp-list-table widefat fixed posts">
				<?php
				if ( !empty( $headers[0] ) ) {
					echo '<tr><td width="200px">&nbsp;</td><td>' . $headers[0] . '</td></tr>';
				}
				foreach ( $headers as $header => $key ) {
					if ( $header !== '0' ) {
						if ( is_array( $key ) ) {
							$content = print_r( $key, true );
						} else {
							$content = wp_kses_post( $key );
						}
						
						echo '<tr><td width="200px" style="text-align:right;">' . $header . ':</td><td>' . $content . '</td></tr>';
					}
				}
				?>
			</table>
		<?php
		}
	}

	/**
	 * Varnish URL Callback
	 *
	 * @since 4.0
	 */
	function varnish_url_callback() {
		$url        = esc_url( VarnishPurger::the_home_url() );
		$varnishurl = get_option( 'vhp_varnish_url', $url );
		?><input type="text" id="vhp_varnish_url" name="vhp_varnish_url" value="<?php echo $varnishurl; ?>" size="50" /><?php
	}

	/*
	 * Call settings page
	 *
	 * @since 4.0
	 */
	function settings_page() {
		?>
		<div class="wrap">

			<h1><?php _e( 'Is Varnish Working?', 'varnish-http-purge' ); ?></h1>
				
			<?php settings_errors(); ?>

			<form action="options.php" method="POST" ><?php
				settings_fields( 'varnish-http-purge-url' );
				do_settings_sections( 'varnish-url-settings' );
				submit_button( __( 'Check URL', 'varnish-http-purge' ), 'primary');
			?></form>

			<form action="options.php" method="POST" ><?php
				// Only available if _not_ multisite
				if ( !is_multisite() ) {
					settings_fields( 'varnish-http-purge-ip' );
					do_settings_sections( 'varnish-ip-settings' );
					submit_button( __( 'Save IP', 'varnish-http-purge' ), 'secondary');
				}
			?></form>

		</div>
		<?php
	}

	/**
	 * Sanitization and validation for IP
	 *
	 * @param $input the input to be sanitized
	 * @since 4.0
	 */
	function varnish_ip_sanitize( $input ) {

		$output = '';
		$set_message = __( 'You have entered an invalid IP address.', 'varnish-http-purge' );
		$set_type = 'error';	

		if ( empty($input) ) {
			return; // do nothing
		} elseif ( filter_var( $input, FILTER_VALIDATE_IP) ) {
			$set_message = 'IP Updated.';
			$set_type = 'updated';
			$output = filter_var( $input, FILTER_VALIDATE_IP);
		}

		add_settings_error( 'vhp_varnish_url', 'varnish-url', $set_message, $set_type );
		return $output;
	}

	/**
	 * Sanitization and validation for URL
	 *
	 * @param $input the input to be sanitized
	 * @since 4.0
	 */
	function varnish_url_sanitize( $input ) {

		// Defaults
		$output       = esc_url( VarnishPurger::the_home_url() );
		$set_type     = 'error';

		if ( !empty( $input ) ) {
			$parsed_input = parse_url( $input );
			if ( empty( $parsed_input['scheme'] ) ) {
				$schema_input = 'http://';
				if ( is_ssl() ) $schema_input = 'https://';
				$input = $schema_input . ltrim( $input, '/' );
			}
		}

		if ( empty( $input ) ) {
			$set_message = __( 'You must enter a URL from your own domain to scan.', 'varnish-http-purge' );
		} elseif ( !filter_var( $input, FILTER_VALIDATE_URL) ) {
			$set_message = __( 'You have entered an invalid URL address.', 'varnish-http-purge' );
		} elseif ( parse_url( $output, PHP_URL_HOST ) !== parse_url( $input, PHP_URL_HOST ) ) {
			$set_message = __( 'You cannot scan URLs on other domains.', 'varnish-http-purge' );
		} else {
			$output = filter_var( $input, FILTER_VALIDATE_URL);
			$set_message = __( 'Scanning New URL...', 'varnish-http-purge' );
			$set_type = 'updated';
		}
		
		add_settings_error( 'vhp_varnish_url', 'varnish-url', $set_message, $set_type );
		return $output;
	}

}


$status = new VarnishStatus();