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
		// Settings page
		$this->register_settings();

		// Check Caching page
		$this->register_check_caching();
	}

	/**
	 * Admin Menu Callback
	 *
	 * @since 4.0
	 */
	function admin_menu() {
		// Main Menu Page
		add_menu_page( __( 'Varnish HTTP Purge', 'varnish-http-purge' ), __( 'Varnish', 'varnish-http-purge' ), 'manage_options', 'varnish-page', array( &$this, 'settings_page' ), 'dashicons-carrot', 75 );
		add_submenu_page( 'varnish-page', __( 'Varnish HTTP Purge', 'varnish-http-purge' ), __( 'Settings', 'varnish-http-purge' ), 'manage_options', 'varnish-page', array( &$this, 'settings_page' ) );
		// Debug Subpage
		add_submenu_page( 'varnish-page', __( 'Check Caching', 'varnish-http-purge' ), __( 'Check Caching', 'varnish-http-purge' ), 'manage_options', 'varnish-check-caching', array( &$this, 'check_caching_page' ) );
	}

	/**
	 * Register Settings 
	 *
	 * @since 4.0.2
	 */
	function register_settings() {
		if ( !is_multisite() || current_user_can( 'manage_network' ) ) {
			// Development Mode Settings
			register_setting( 'vhp-settings-devmode', 'vhp_varnish_devmode', array( &$this, 'settings_devmode_sanitize' ) );
			add_settings_section( 'vhp-settings-devmode-section', __( 'Development Mode Settings', 'varnish-http-purge' ), array( &$this, 'options_settings_devmode'), 'varnish-devmode-settings' );
			add_settings_field( 'varnish_devmode', __( 'Development Mode', 'varnish-http-purge' ), array( &$this, 'settings_devmode_callback' ), 'varnish-devmode-settings', 'vhp-settings-devmode-section' );

			// IP Settings
			register_setting( 'vhp-settings-ip', 'vhp_varnish_ip', array( &$this, 'settings_ip_sanitize' ) );
			add_settings_section( 'vhp-settings-ip-section', __( 'Configure Custom IP', 'varnish-http-purge' ), array( &$this, 'options_settings_ip'), 'varnish-ip-settings' );
			add_settings_field( 'varnish_ip', __( 'Set Custom IP', 'varnish-http-purge' ), array( &$this, 'settings_ip_callback' ), 'varnish-ip-settings', 'vhp-settings-ip-section' );
		}
	}

	/**
	 * Options Settings - Dev Mode
	 *
	 * @since 4.6
	 */
	function options_settings_devmode() {
		?>
		<p><a name="#configuredevmode"></a><?php _e( 'In Development Mode, WordPress will prevent visitors from seeing cached content on your site. You can enable this for 24 hours, after which it will automatically disable itself. This will make your site run slower, so please use with caution.', 'varnish-http-purge' ); ?></p>
		<p><?php _e( 'If you need to activate development mode for extended periods of time, you can add <code>define( \'VHP_DEVMODE\', true );</code> in your wp-config file.', 'varnish-http-purge' ); ?></p>
		<?php
	}

	/**
	 * Settings Dev Mode Callback
	 *
	 * @since 4.0
	 */
	function settings_devmode_callback() {

		$devmode = get_option( 'vhp_varnish_devmode', VarnishPurger::$devmode );
		$active  = ( isset( $devmode['active'] ) )? $devmode['active'] : false;
		$expire  = current_time( 'timestamp' ) + DAY_IN_SECONDS;

		?>
		<input type="hidden" name="vhp_varnish_devmode[expire]" value="<?php $expire; ?>" />
		<input type="checkbox" name="vhp_varnish_devmode[active]" value="true" <?php checked( $active, true ); ?> />
		<label for="vhp_varnish_devmode['active']">
			<?php
			if ( $active && isset( $devmode['expire'] ) ) { 
				$timestamp = date_i18n( get_option( 'date_format' ), $devmode['expire'] ) .' @ '. date_i18n( get_option( 'time_format' ), $devmode['expire'] );
				echo sprintf( __( 'Development Mode is active until %s. After that, it will automatically disable the next time someone visits your site.', 'varnish-http-purge' ), $timestamp );
			} else {
				_e( 'Activate Development Mode', 'varnish-http-purge' );
			}
			?>
		</label>
		<?php
	}

	/**
	 * Sanitization and validation for Dev Mode
	 *
	 * @param $input the input to be sanitized
	 * @since 4.6.0
	 */
	function settings_devmode_sanitize( $input ) {

		$output      = array();
		$expire      = current_time( 'timestamp' ) + DAY_IN_SECONDS;
		$set_message = __( 'Something has gone wrong!', 'varnish-http-purge' );
		$set_type    = 'error';

		if ( empty( $input ) ) {
			return; // do nothing
		} else {
			$output['active'] = ( isset( $input['active'] ) || $input['active'] )? true : false;
			$output['expire'] = ( isset( $input['expire'] ) && is_int( $input['expire'] ) )? $input['expire'] : $expire;
			$set_message      = ( $output['active'] )? __( 'Development Mode Activated', 'varnish-http-purge' ) : __( 'Development Mode Dectivated', 'varnish-http-purge' );
			$set_type         = 'updated';
		}

		// If it's true then we're activating so let's kill the cache.
		if ( $output['active'] ) {
			VarnishPurger::purgeUrl( VarnishPurger::the_home_url() . '/?vhp-regex' );
		}
		
		add_settings_error( 'vhp_varnish_devmode', 'varnish-devmode', $set_message, $set_type );
		return $output;
	}

	/**
	 * Options Settings - IP Address
	 *
	 * @since 4.0
	 */
	function options_settings_ip() {
		?>
		<p><a name="#configureip"></a><?php _e( 'There are cases when a custom Varnish IP Address will need to be set, in order to tell the plugin to empty the cache in a specific location. If you\'re using a CDN like Cloudflare or a Firewall Proxy like Sucuri, you will want to set this.', 'varnish-http-purge' ); ?></p>
		<p><?php _e( 'Your Varnish IP is the IP address of the server where your caching service (i.e. Varnish or Nginx) is installed. It must be one of the IPs used by your cache service. If you use multiple IPs, or have customized your ACLs, you\'ll need to pick one that doesn\'t conflict with your other settings. For example, if you have Varnish listening on a public and private IP, pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1).', 'varnish-http-purge' ); ?></p>
		<p><?php _e( 'If your webhost set the service up for you, as is the case with DreamPress or WP Engine, ask them for the specifics if they don\'t have it documented. I\'ve listed the ones I know about here, however you should still check with them if you\'re not sure.', 'varnish-http-purge' ); ?></p>
		<p><strong><?php _e( 'If you aren\'t sure what to do, contact your webhost or server admin before making any changes.', 'varnish-http-purge' ); ?></strong></p>
		<ul>
			<li><?php _e( 'DreamHost - Go into the Panel and click on the DNS settings for the domain. The entry for <em>resolve-to.domain</em> (if set) will be your cache server. If it\'s not set, then you don\'t need to worry about this at all. Example:', 'varnish-http-purge' ); ?> <code>resolve-to.www A 208.97.157.172</code></li>
		</ul>
		<?php
	}

	/**
	 * Settings IP Callback
	 *
	 * @since 4.0
	 */
	function settings_ip_callback() {

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
				_e( 'A Varnish IP has been defined in your wp-config file, so it is not editable here.', 'varnish-http-purge' );
			} else {
				_e( 'Example:', 'varnish-http-purge' ); ?> <code>123.45.67.89</code><?php
			}
			?>
		</label>
		<?php
	}

	/**
	 * Sanitization and validation for IP
	 *
	 * @param $input the input to be sanitized
	 * @since 4.0
	 */
	function settings_ip_sanitize( $input ) {

		$output      = '';
		$set_message = __( 'You have entered an invalid IP address.', 'varnish-http-purge' );
		$set_type    = 'error';

		if ( empty($input) ) {
			return; // do nothing
		} elseif ( filter_var( $input, FILTER_VALIDATE_IP) ) {
			$set_message = 'IP Updated.';
			$set_type = 'updated';
			$output = filter_var( $input, FILTER_VALIDATE_IP);
		}

		add_settings_error( 'vhp_varnish_ip', 'varnish-ip', $set_message, $set_type );
		return $output;
	}

	/**
	 * Register Check Caching
	 *
	 * @since 4.0
	 */
	function register_check_caching() {
		register_setting( 'varnish-http-purge-url', 'vhp_varnish_url', array( &$this, 'varnish_url_sanitize' ) );
		add_settings_section( 'varnish-url-settings-section', __( 'Check Caching Status', 'varnish-http-purge' ), array( &$this, 'options_check_caching_scan' ), 'varnish-url-settings' );
		add_settings_field( 'varnish_url', __( 'Check A URL On Your Site:', 'varnish-http-purge' ), array( &$this, 'check_caching_callback' ), 'varnish-url-settings', 'varnish-url-settings-section' );
	}

	/**
	 * Options Callback - URL Scanner
	 *
	 * @since 4.0
	 */
	function options_check_caching_scan() {
		?><p><?php _e( 'While it is impossible to detect all possible conflicts, this status page performs a check of the most common issues that prevents your site from caching properly. This feature is provided to help you in resolve potential conflicts on your own. When filing an issue with your web-host, we recommend you include the output in your ticket.', 'varnish-http-purge' ); ?></p>
		
		<p><?php printf ( __( '<strong>This check uses <a href="%s">a remote service hosted on DreamObjects</a></strong>. The service used only for providing up to date compatibility checks on plugins and themes that may conflict with running a server based cache (such as Varnish or Nginx). No personally identifying information regarding persons running this check, nor the plugins and themes in use on this site will be transmitted. The bare minimum of usage information is collected, concerning only IPs and domains making requests of the service. If you do not wish to use this service, please do not use this service.', 'varnish-http-purge' ), 'https://varnish-http-purge.objects-us-east-1.dream.io/readme.txt' ); ?></p>

		<?php

		// If there's no post made, let's not...
		if ( !isset( $_REQUEST['settings-updated'] ) || !$_REQUEST['settings-updated'] ) return; 

		// Set icons
		$icons = array (
			'awesome' => '<span class="dashicons dashicons-heart" style="color:#46B450;"></span>',
			'good'    => '<span class="dashicons dashicons-thumbs-up" style="color:#00A0D2;"></span>',
			'warning' => '<span class="dashicons dashicons-warning" style="color:#FFB900"></span>',
			'awkward' => '<span class="dashicons dashicons-flag" style="color:#826EB4;">',
			'bad'     => '<span class="dashicons dashicons-thumbs-down" style="color:#DC3232;"></span>',
		);

		// Get the base URL to start
		$url        = esc_url( VarnishPurger::the_home_url() );
		$varnishurl = get_option( 'vhp_varnish_url', $url );

		// Is this a good URL?
		$valid_url = VarnishDebug::is_url_valid( $varnishurl );
		if ( $valid_url == 'valid' ) {
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
				$varniship = get_option( 'vhp_varnish_ip' );
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
							echo '<tr><td width="200px" style="text-align:right;">' . ucfirst( $header ) . ':</td><td>' . $content . '</td></tr>';
						}
					}
					?>
				</table>
			<?php
			}
		}
	}

	/**
	 * Varnish URL Callback
	 *
	 * @since 4.0
	 */
	function check_caching_callback() {
		$url        = esc_url( VarnishPurger::the_home_url() );
		$varnishurl = get_option( 'vhp_varnish_url', $url );
		?><input type="text" id="vhp_varnish_url" name="vhp_varnish_url" value="<?php echo $varnishurl; ?>" size="50" /><?php
	}

	/**
	 * Sanitization and validation for URL
	 *
	 * @param $input the input to be sanitized
	 * @since 4.0
	 */
	function varnish_url_sanitize( $input ) {

		// Defaults
		$output   = esc_url( VarnishPurger::the_home_url() );
		$set_type = 'error';

		if ( empty( $input ) ) {
			$set_message = __( 'You must enter a URL from your own domain to scan.', 'varnish-http-purge' );
		} else {
			$valid_url = VarnishDebug::is_url_valid( esc_url( $input ) );

			switch ( $valid_url ) {
				case 'empty':
				case 'domain':
					$set_message = __( 'You must provide a URL on your own domain to scan.', 'varnish-http-purge' );
					break;
				case 'invalid':
					$set_message = __( 'You have entered an invalid URL address.', 'varnish-http-purge' );
					break;
				case 'valid':
					$set_type    = 'updated';
					$set_message = __( 'URL Scanned.', 'varnish-http-purge' );
					$output      = esc_url( $input );
					break;
				default:
					$set_message = __( 'An unknown error has occurred.', 'varnish-http-purge' );
					break;
			}
		}

		if ( isset( $set_message ) ) add_settings_error( 'vhp_varnish_url', 'varnish-url', $set_message, $set_type );
		return $output;
	}

	/*
	 * Call settings page
	 *
	 * @since 4.0
	 */
	function settings_page() {
		?>
		<div class="wrap">
			<?php settings_errors(); ?>
			<h1><?php _e( 'Varnish HTTP Purge Settings', 'varnish-http-purge' ); ?></h1>

			<p><?php _e( 'Varnish HTTP Purge can empty the cache for different server based caching systems, including Varnish and nginx. For most users, there should be no configuration necessary as the plugin is intended to work silently, behind the scenes.', 'varnish-http-purge' ); ?></p>

			<?php
			if ( !is_multisite() || current_user_can( 'manage_network' ) ) {
				?><form action="options.php" method="POST" ><?php
					settings_fields( 'vhp-settings-devmode' );
					do_settings_sections( 'varnish-devmode-settings' );
					submit_button( __( 'Save Settings', 'varnish-http-purge' ), 'primary');
				?></form>
	
				<form action="options.php" method="POST" ><?php
					settings_fields( 'vhp-settings-ip' );
					do_settings_sections( 'varnish-ip-settings' );
					submit_button( __( 'Save IP', 'varnish-http-purge' ), 'secondary');
				?></form>
				<?php
			} else {
				?><p><?php _e( 'Your account does not have access to configure settings. Please contact your site administrator.', 'varnish-http-purge' ); ?></p><?php
			}
			?>

		</div>
		<?php
	}

	/*
	 * Call the Check Caching
	 *
	 * @since 4.6.0
	 */
	function check_caching_page() {
		?>
		<div class="wrap">

			<?php settings_errors(); ?>
			<h1><?php _e( 'Is Caching Working?', 'varnish-http-purge' ); ?></h1>

			<form action="options.php" method="POST" ><?php
				settings_fields( 'varnish-http-purge-url' );
				do_settings_sections( 'varnish-url-settings' );
				submit_button( __( 'Check URL', 'varnish-http-purge' ), 'primary');
			?></form>

		</div>
		<?php
	}

}

$status = new VarnishStatus();