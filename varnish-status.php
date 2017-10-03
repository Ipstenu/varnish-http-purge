<?php
/**
	Copyright 2016-2017 Mika Epstein (email: ipstenu@halfelf.org)

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

		?><p><?php _e( 'While it is impossible to detect all possible conflicts, this status page performs a check of the most common issues that prevent Varnish from caching your site properly.', 'varnish-http-purge' ); ?></p>
		
		<p><?php _e( 'This feature is provided to help you in debugging any conflicts on your own. If filing an issue with your webhost, it can be useful to include the output in your ticket.', 'varnish-http-purge' ); ?></p>
		
		<?php
		$icon_awesome	= '<span class="dashicons dashicons-heart" style="color:#008000;"></span>';
		$icon_good 		= '<span class="dashicons dashicons-thumbs-up" style="color:#008000;"></span>';
		$icon_warning 	= '<span class="dashicons dashicons-warning" style="color:#FF9933"></span>';
		$icon_awkward	= '<span class="dashicons dashicons-flag" style="color:#FF9933;">';
		$icon_bad		= '<span class="dashicons dashicons-thumbs-down" style="color:#990000;"></span>';

		$url = esc_url( VarnishPurger::the_home_url() );
		$varnishurl = get_option( 'vhp_varnish_url', $url );

		$args = array(
			'headers' => array( 
				'timeout' 		=> 30,
				'redirection' 	=> 10,
			)
		);
		
		$response = wp_remote_get( $varnishurl, $args );
		$headers = wp_remote_retrieve_headers( $response );
		$preflight = true;
		
		// Basic checks that should stop a scan
		if( is_wp_error($response) ) {
			$preflight = false;
			$failure_to_launch = __( 'This request cannot be performed: ', 'varnish-http-purge' );
			$failure_to_launch .= $response->get_error_message();
		}
		if( wp_remote_retrieve_response_code($response) == '404' ) {
			$preflight = false;
			$failure_to_launch = __( 'This URL is a 404. Please check your typing and try again.', 'varnish-http-purge' );
		}

		// Get the IP address so we can check things later
		if ( isset( $headers['X-Forwarded-For'] ) && filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$remote_ip = $headers['X-Forwarded-For'];
		} elseif ( isset( $headers['HTTP_X_FORWARDED_FOR'] ) && filter_var( $headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
		) {
			$remote_ip = $headers['HTTP_X_FORWARDED_FOR'];
		} elseif ( strpos( $headers['Server'] ,'cloudflare') !== false ) {
			$remote_ip = 'cloudflare';
		} else {
			$remote_ip = false;
		}
		
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

			if ( $preflight == false ) {
				?><tr>
					<td width="40px"><?php echo $icon_bad; ?></td>
					<td><?php echo $failure_to_launch; ?></td>
				</tr><?php
			} else {
				/* Pre Flight Checks */
				
				// VARNISH
				if ( isset( $headers['x-cacheable'] ) && strpos( $headers['x-cacheable'] ,'YES') !== false ) {
				?><tr>
					<td width="40px"><?php echo $icon_good; ?></td>
					<td><?php _e( 'Varnish is running properly and caching is happening.', 'varnish-http-purge' ); ?></td>
				</tr><?php
				} elseif (isset( $headers['x-cacheable'] ) && strpos( $headers['x-cacheable'] ,'NO') !== false ) {
				?><tr>
					<td width="40px"><?php echo $icon_bad; ?></td>
					<td><?php _e( 'Varnish is running but is unable to cache your site.', 'varnish-http-purge' ); ?></td>
				</tr><?php
				} else {
				?><tr>
					<td width="40px"><?php echo $icon_warning; ?></td>
					<td><?php _e( 'We cannot find Varnish active for this domain.', 'varnish-http-purge' ); ?></td>
				</tr><?php
				}

				// Remote IP (i.e. Yo Dawg, I heard you like proxies
				?><tr><?php
					if ( $remote_ip == false && !empty( $varniship) ) {
					?>
						<td width="40px"><?php echo $icon_warning; ?></td>
						<td><?php printf( __( 'You have a Varnish IP set but a proxy like Cloudflare or Sucuri has not been detected. This is mostly harmless, but if you have issues with your cache not emptying when you make a post, you may need to <a href="%s">erase your Varnish IP</a>. Please check with your webhost or server admin before doing so.', 'varnish-http-purge'  ), '#configure' ); ?></td>
					<?php
					} elseif ( $remote_ip !== false && $remote_ip !== $varniship ) {
					?>
						<td width="40px"><?php echo $icon_warning; ?></td>
						<td><?php printf( __( 'You\'re using a Custom Varnish IP that doesn\'t appear to match your server IP address. If you\'re using multiple Varnish servers or IPv6, this is fine. Please make sure you\'ve <a href="%s">properly configured it</a> according to your webhost\'s specifications.', 'varnish-http-purge'  ), '#configure' ); ?></td>
					<?php
					} else {
					?>
						<td width="40px"><?php echo $icon_good; ?></td>
						<td><?php printf( __( 'Your server IP setup looks good. If you are using a proxy (like Sucuri or Cloudflare or Fastly) please double check that configuration and make sure to add a <a href="%s">custom Varnish IP</a> if necessary.', 'varnish-http-purge'  ), '#configure' ); ?></td>
					<?php
					}
				?></tr><?php

				// Cloudflare
				if ( isset( $headers['Server'] ) && strpos( $headers['Server'] ,'cloudflare') !== false ) {
				?><tr>
					<td><?php echo $icon_warning; ?></td>
					<td><?php printf( __( 'CloudFlare has been detected. While this is generally fine, you may experience some cache oddities. Make sure you <a href="%s">configure WordPress for Cloudflare</a>.', 'varnish-http-purge'  ), '#configure' ); ?></td>
				</tr><?php
				}
	
				// HHVM
				if ( isset( $headers['X-Powered-By'] ) ) {
					if ( strpos( $headers['X-Powered-By'], 'HHVM' ) !== false ) {
					?><tr>
						<td><?php echo $icon_good; ?></td>
						<td><?php _e( 'You are running HHVM instead of PHP. While that works fine, you should consider PHP 7. WordPress will be ceasing support for HHVM in 2018.', 'varnish-http-purge' ); ?></td>
					</tr><?php
					}
				}
				
				// GZIP
				if ( isset( $headers['Content-Encoding'] ) ) {
					// Regular gZIP
					if( strpos( $headers['Content-Encoding'] ,'gzip') !== false || ( isset( $headers['Vary'] ) && strpos( $headers['Vary'] ,'gzip' ) !== false ) ) {
					?><tr>
						<td><?php echo $icon_good; ?></td>
						<td><?php _e( 'Your site is compressing content and making the internet faster.', 'varnish-http-purge' ); ?></td>
					</tr><?php						
					}
	
					// Fastly
					if ( strpos( $headers['Content-Encoding'] ,'Fastly') !== false ) {
					?><tr>
						<td><?php echo $icon_good; ?></td>
						<td><?php printf( __( '<a href="%s">Fastly</a> is speeding up your site. Keep in mind, it may cache your CSS and images longer than Varnish does. Remember to empty all caches.', 'varnish-http-purge' ), esc_url( 'https://fastly.com' ) ); ?></td>
					</tr><?php
					} 
				}
	
				/* Things that breaks Varnish */
				
				// SET COOKIE
				if ( isset( $headers['Set-Cookie'] ) ) {
					
					// If cookies are an array, scan the whole thing. Otherwise, we can use strpos.
					if ( is_array( $headers['Set-Cookie'] ) ) {						
						$cookie_phpsessid = in_array( 'PHPSESSID', $headers['Set-Cookie'], true );
						$cookie_edd_wp_session = in_array( 'edd_wp_session', $headers['Set-Cookie'], true );
						$cookie_edd_items_in_cart = in_array( 'edd_items_in_cart', $headers['Set-Cookie'], true );
						$cookie_wfvt = in_array( 'wfvt_', $headers['Set-Cookie'], true );
						$cookie_invite_anyone = in_array( 'invite-anyone', $headers['Set-Cookie'], true );
					} else {
						$cookie_phpsessid = strpos( $headers['Set-Cookie'] , 'PHPSESSID');
						$cookie_edd_wp_session = strpos( $headers['Set-Cookie'], 'edd_wp_session' );
						$cookie_edd_items_in_cart = strpos( $headers['Set-Cookie'], 'edd_items_in_cart' );
						$cookie_wfvt = strpos( $headers['Set-Cookie'], 'wfvt_' );
						$cookie_invite_anyone = strpos( $headers['Set-Cookie'], 'invite-anyone' );
					}

					if ( $cookie_phpsessid !== false ) {
						?><tr>
							<td><?php echo $icon_bad; ?></td>
							<td><?php _e( 'A plugin or theme is setting a PHPSESSID cookie on every pageload. This makes Varnish not deliver cached pages.', 'varnish-http-purge' ); ?></td>
						</tr><?php
					}
					if ( $cookie_edd_wp_session !== false ) {
						?><tr>
							<td><?php echo $icon_bad; ?></td>
							<td><?php printf( __( '<a href="%s">Easy Digital Downloads</a> is being used with cookie sessions. This may cause your cache to misbehave. If you have issues, please set <code>define( "EDD_USE_PHP_SESSIONS", true );</code> in your <code>wp-config.php</code> file.', 'varnish-http-purge'  ), esc_url('https://wordpress.org/plugins/easy-digital-downloads/') ); ?></td>
						</tr><?php
					}
					if ( $cookie_edd_items_in_cart !== false ) {
						?><tr>
							<td><?php echo $icon_warning; ?></td>
							<td><?php printf( __( '<a href="%s">Easy Digital Downloads</a> is putting down a shopping cart cookie on every page load. Make sure Varnish is set up to ignore that when it\'s empty.', 'varnish-http-purge'  ), esc_url('https://wordpress.org/plugins/easy-digital-downloads/') ); ?></td>
						</tr><?php				
					}
					if ( $cookie_wfvt !== false ) {
						?><tr>
							<td><?php echo $icon_bad; ?></td>
							<td><?php printf( __( '<a href="%s">Wordfence</a> is putting down cookies on every page load. Please disable that in your options (available from version 4.0.4 and up).', 'varnish-http-purge'  ), esc_url('https://wordpress.org/plugins/wordfence/') ); ?></td>
						</tr><?php
					}
					if ( $cookie_invite_anyone !== false ) {
						?><tr>
							<td><?php echo $icon_bad; ?></td>
							<td><?php printf( __( '<a href="%s">Invite Anyone</a>, a plugin for BuddyPress, is putting down a cookie on every page load. This will prevent Varnish from caching.', 'varnish-http-purge'  ), esc_url('https://wordpress.org/plugins/invite-anyone/') ); ?></td>
						</tr><?php
					}
				}
				
				// AGE
				if( !isset($headers['Age']) ) {
					?><tr>
						<td><?php echo $icon_bad; ?></td>
						<td><?php _e( 'Your domain does not report an "Age" header, which means we can\'t tell if the page is actually serving from cache.', 'varnish-http-purge' ); ?></td>
					</tr><?php
				} elseif( $headers['Age'] <= 0 || $headers['Age'] == 0 ) {
					if( !isset($headers['Cache-Control']) || strpos($headers['Cache-Control'], 'max-age') === FALSE ) {
						?><tr>
							<td><?php echo $icon_warning; ?></td>
							<td><?php _e( 'The "Age" header is set to less than 1, which means you checked right when Varnish cleared the cache for that url or Varnish is not actually serving the content for that url from cache. Check again (refresh the page) but if it happens again, it could be one of the following reasons:', 'varnish-http-purge' ); ?>
								<ul style=\"text-align: left;\">
									<li><?php _e( 'That url is excluded from the cache on purpose in the Varnish vcl file (in which case everything is working.)', 'varnish-http-purge' ); ?></li>
									<li><?php _e( 'A theme or plugin is sending cache headers that are telling Varnish not to serve that content from cache. This means you will have to fix the cache headers the application is sending to Varnish. A lot of the time those headers are Cache-Control and/or Expires.', 'varnish-http-purge' ); ?></li>
									<li><?php _e( 'A theme or plugin is setting a session cookie, which can prevent Varnish from serving content from cache. You need to make it not send a session cookie for anonymous traffic. ', 'varnish-http-purge' ); ?></li>
								</ul>
							</td>
						</tr><?php			
					}
				}
				
				// CACHE-CONTROL
				if ( isset( $headers['Cache-Control'] ) && strpos( $headers['Cache-Control'] ,'no-cache') !== false ) {
					?><tr>
						<td><?php echo $icon_bad; ?></td>
						<td><?php _e( 'Something is setting the header Cache-Control to "no-cache" which means visitors will never get cached pages.', 'varnish-http-purge' ); ?></td>
					</tr><?php
				}
				
				// MAX AGE
				if ( isset( $headers['Cache-Control'] ) && strpos( $headers['Cache-Control'] ,'max-age=0') !== false ) {
					?><tr>
						<td><?php echo $icon_bad; ?></td>
						<td><?php _e( 'Something is setting the header Cache-Control to "max-age=0" which means a page can be no older than 0 seconds before it needs to regenerate the cache.', 'varnish-http-purge' ); ?></td>
					</tr><?php
				}
				
				// PRAGMA
				if ( isset( $headers['Pragma'] ) && strpos( $headers['Pragma'] ,'no-cache') !== false ) {
					?><tr>
						<td><?php echo $icon_bad; ?></td>
						<td><?php _e( 'Something is setting the header Pragma to "no-cache" which means visitors will never get cached pages.', 'varnish-http-purge' ); ?></td>
					</tr><?php
				}
				
				// X-CACHE (we're not running this)
				if ( isset( $headers['X-Cache-Status'] ) && strpos( $headers['X-Cache-Status'] ,'MISS') !== false ) {
					?><tr>
						<td><?php echo $icon_bad; ?></td>
						<td><?php _e( 'X-Cache missed, which means it was not able to serve this page as cached.', 'varnish-http-purge' ); ?></td>
					</tr><?php
				}
				
				/* Server features */
				
				// PAGESPEED
				if ( isset( $headers['X-Mod-Pagespeed'] ) ) {
					if ( strpos( $headers['X-Cacheable'] , 'YES:Forced') !== false ) {
						?><tr>
							<td><?php echo $icon_good; ?></td>
							<td><?php _e( 'Mod Pagespeed is active and working properly with Varnish.', 'varnish-http-purge' ); ?></td>
						</tr><?php
					} else {
						?><tr>
							<td><?php echo $icon_bad; ?></td>
							<td><?php _e( 'Mod Pagespeed is active but it looks like your caching headers may not be right. This may be a false negative if other parts of your site are overwriting headers. Fix all other errors listed, then come back to this. If you are still having errors, you will need to look into using htaccess or nginx to override the Pagespeed headers.', 'varnish-http-purge' ); ?></td>
						</tr><?php
					}
				}
			}
		?>
		</table>
		<?php
	}

	/**
	 * Varnish URL Callback
	 *
	 * @since 4.0
	 */
	function varnish_url_callback() {
		$url = esc_url( VarnishPurger::the_home_url() );
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
				submit_button( 'Check URL', 'primary');
			?></form>

			<form action="options.php" method="POST" ><?php
				// Only available if _not_ multisite
				if ( !is_multisite() ) {
					settings_fields( 'varnish-http-purge-ip' );
					do_settings_sections( 'varnish-ip-settings' );
					submit_button( 'Save IP', 'secondary');
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
		$set_message = 'You have entered an invalid IP address.';
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

		$baseurl_host = parse_url( esc_url( VarnishPurger::the_home_url() ), PHP_URL_HOST );
		$output = esc_url( VarnishPurger::the_home_url() );
		$set_type = 'error';	

		if ( !empty($input) ) {
			$parsed_input = parse_url($input);
			if ( empty($parsed_input['scheme']) ) {
				$schema_input = 'http://';
				if ( is_ssl() ) {
					$schema_input = 'https://';
				}
				$input = $schema_input . ltrim($input, '/');
			}
		}

		if ( empty($input) ) {
			$set_message = 'You must enter a URL from your own domain to scan.';
		} elseif ( !filter_var( $input, FILTER_VALIDATE_URL) ) {
			$set_message = 'You have entered an invalid URL address.';
		} elseif ( $baseurl_host !== parse_url( $input, PHP_URL_HOST ) ) {
			$set_message = 'You cannot scan URLs on other domains.';
		} else {
			$output = filter_var( $input, FILTER_VALIDATE_URL);
			$set_message = 'Scanning New URL...';
			$set_type = 'updated';
		}
		
		add_settings_error( 'vhp_varnish_url', 'varnish-url', $set_message, $set_type );
		return $output;
	}

}

$status = new VarnishStatus();