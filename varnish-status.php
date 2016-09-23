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

		// Calling Varnish Purger!
		$this->varnish_purge = new VarnishPurger();

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
		// Register Settings
		$this->register_settings();
	}

	/**
	 * Admin Menu Callback
	 *
	 * @since 4.0
	 */
	function admin_menu() {
		add_management_page( __('Is Varnish Working?', 'varnish-http-purge'), __('Varnish Status', 'varnish-http-purge'), 'manage_options', 'varnish-status', array( &$this, 'settings_page' ) );
	}

	/**
	 * Register Admin Settings
	 *
	 * @since 4.0
	 */
	function register_settings() {
		register_setting( 'varnish-http-purge', 'vhp_varnish_ip', array( &$this, 'varnish_sanitize' ) );
		
		add_settings_section( 'varnish-settings-section', __('Configure Custom Varnish IP', 'varnish-http-purge'), array( &$this, 'options_callback'), 'varnish-settings' );
		
		add_settings_field( 'varnish_ip', __('Set Varnish IP', 'varnish-http-purge'), array( &$this, 'varnish_ip_callback'), 'varnish-settings', 'varnish-settings-section' );
	}

	/**
	 * Options Callback
	 *
	 * @since 4.0
	 */
	function options_callback() {
	    ?>
	    <p><a name="#configure"></a><?php _e('The majority of users will never need to so much as look down here. However there are cases when a custom Varnish IP Address will need to be set, in order to tell the plugin to flush cache in a specific location. If you\'re using a CDN like Cloudflare or a Firewall Proxy like Sucuri, you will want to set this.', 'varnish-http-purge'); ?></p>
	    <p><?php _e('Your Varnish IP is just the IP address of the server where Varnish is installed. Your Varnish IP must be one of the IPs that Varnish is listening on. If you use multiple IPs, or if you\'ve customized your ACLs, you\'ll need to pick on that doesn\'t conflict with your other settings. For example, if you have Varnish listening on a public and private IP, pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1).', 'varnish-http-purge'); ?></p>
	    <p><?php _e('If your webhost set up Varnish for you, you may need to ask them for the specifics if they don\'t have it documented. I\'ve listed the ones I know about here, however you should still check with them if you\'re not sure.', 'varnish-http-purge'); ?></p>

		<ul>
		    <li><?php _e('DreamHost - Go into the Panel and click on the DNS settings for the domain. The entry for <em>resolve-to.domain</em> (if set) will your varnish server. If it\'s not set, then you don\'t need to worry about this at all. Example:', 'varnish-http-purge'); ?> <code>resolve-to.www A 208.97.157.172</code></li>
		</ul>

	    <?php
	}

	/**
	 * Colour-Coded Post Statuses Callback
	 *
	 * @since 2.0
	 */
	function varnish_ip_callback() {

		$disabled = false;
		
		if ( VHP_VARNISH_IP != false ) {
			$disabled = true;
			$varniship = VHP_VARNISH_IP;
		} else {
			$varniship = get_option('vhp_varnish_ip');
		}
		?>
		<input type="text" id="vhp_varnish_ip" name="vhp_varnish_ip" value="<?php echo $varniship; ?>" size="25" <?php if ( $disabled == true ) { echo 'disabled'; } ?>/>
		<label for="vhp_varnish_ip">
			<?php
			if ( $disabled == true ) { 
				_e('The Varnish IP has been defined in your wp-config, so it is not editable here.', 'varnish-http-purge');
			} else {
				_e('Example:', 'varnish-http-purge'); ?> <code>123.45.67.89</code><?php
			}
			?>
		</label>
	<?php
	}

	/*
	 * Call settings page
	 *
	 * @since 4.0
	 */
	function settings_page() {

		$icon_awesome	= '<span class="dashicons dashicons-heart" style="color:#008000;"></span>';
		$icon_good 		= '<span class="dashicons dashicons-thumbs-up" style="color:#008000;"></span>';
		$icon_warning 	= '<span class="dashicons dashicons-warning" style="color:#FF9933"></span>';
		$icon_awkward	= '<span class="dashicons dashicons-flag" style="color:#FF9933;">';
		$icon_bad		= '<span class="dashicons dashicons-thumbs-down" style="color:#990000;"></span>';


		$url = esc_url($this->varnish_purge->the_home_url());
		$url = 'https://plugins.breakwpdh.com';	
			
		$args = array(
			'headers' => array( 
				'timeout' 		=> 30,
				'redirection' 	=> 10,
			)
		);
		
		$response = wp_remote_get( $url, $args );
		$headers = wp_remote_retrieve_headers( $response );

		?>
		<div class="wrap">

			<h1><?php _e( 'Is Varnish Working?', 'varnish-http-purge' ); ?></h1>
			
			<p>Obviously we can't predict everything but...</p>
			
			<h2>Overview</h2>
			<table class="wp-list-table widefat fixed posts">
			<?php
				
				/* Pre Flight Checks */
				
				// VARNISH
				if ( isset( $headers['x-cacheable'] ) && strpos( $headers['x-cacheable'] ,'YES') !== false ) {
				?><tr>
					<td width="40px"><?php echo $icon_good; ?></td>
					<td>Varnish is running properly so caching is happening.</td>
				</tr><?php
				} elseif (isset( $headers['x-cacheable'] ) && strpos( $headers['x-cacheable'] ,'NO') !== false ) {
				?><tr>
					<td width="40px"><?php echo $icon_bad; ?></td>
					<td>Varnish is running but can't cache.</td>
				</tr><?php
				} else {
				?><tr>
					<td width="40px"><?php echo $icon_warning; ?></td>
					<td>We can't find Varnish on this server.</td>
				</tr><?php
				}

				// Cloudflare
				if ( strpos( $headers['Server'] ,'cloudflare') !== false ) {
				?><tr>
					<td><?php echo $icon_warning; ?></td>
					<td>Because CloudFlare is running, you <em>may</em> experience some cache oddities. Make sure you <a href="#configure">configure WordPress for Cloudflare</a>.</td>
				</tr><?php
				}

				// HHVM
				if ( isset( $headers['X-Powered-By'] ) ) {
					if ( strpos( $headers['X-Powered-By'] ,'HHVM') !== false ) {
					?><tr>
						<td><?php echo $icon_good; ?></td>
						<td>You are so awesome! You're on HHVM!</td>
					</tr><?php
					}
				}
				
				// GZIP
				if ( isset( $headers['Content-Encoding'] ) ) {
					// Regular gZIP
					if( strpos( $headers['Content-Encoding'] ,'gzip') !== false || ( isset( $headers['Vary']) && strpos( $headers['Vary'] ,'gzip') !== false ) ) {
					?><tr>
						<td><?php echo $icon_good; ?></td>
						<td>Your site is compressing content and making the internet faster.</td>
					</tr><?php						
					}

					// Fastly
					if ( strpos( $headers['Content-Encoding'] ,'Fastly') !== false ) {
					?><tr>
						<td><?php echo $icon_good; ?></td>
						<td>You're using <a href="https://fastly.com">Fastly</a> to speed up your site.</td>
					</tr><?php
					} 
				}

				/* Things that breaks Varnish */
				
				// SET COOKIE
				if ( isset( $headers['Set-Cookie'] ) ) {
				
					if ( strpos( $headers['Set-Cookie'] , 'PHPSESSID') !== false ) {
						?><tr>
							<td><?php echo $icon_bad; ?></td>
							<td>You're setting a PHPSESSID cookie. This makes Varnish not deliver cached pages.</td>
						</tr><?php
					}
					if ( strpos( $headers['Set-Cookie'], 'edd_wp_session' ) !== false ) {
						?><tr>
							<td><?php echo $icon_bad; ?></td>
							<td><a href="https://wordpress.org/plugins/easy-digital-downloads/">Easy Digital Downloads</a> is being used with cookie sessions. This may cause your cache to misbehave. If you have issues, please set <code>define( 'EDD_USE_PHP_SESSIONS', true );</code> in your <code>wp-config.php</code> file.</td>
						</tr><?php
					}
					if ( strpos( $headers['Set-Cookie'], 'edd_items_in_cart' ) !== false ) {
						?><tr>
							<td><?php echo $icon_warning; ?></td>
							<td><a href="https://wordpress.org/plugins/easy-digital-downloads/">Easy Digital Downloads</a> is putting down a shopping cart cookie on every page load. Make sure Varnish is set up to ignore that when it's empty.</td>
						</tr><?php				
					}
					if ( strpos( $headers['Set-Cookie'], 'wfvt_' ) !== false ) {
						?><tr>
							<td><?php echo $icon_bad; ?></td>
							<td>The plugin <a href="https://wordpress.org/plugins/wordfence">WordFence</a> is putting down cookies on every page load. Please disable that in your options (available from version 4.0.4 and up)</td>
						</tr><?php
					}
					if ( strpos( $headers['Set-Cookie'], 'invite-anyone' ) !== false ) {
						?><tr>
							<td><?php echo $icon_bad; ?></td>
							<td><a href="https://wordpress.org/plugins/invite-anyone/">Invite Anyone</a>, a plugin for BuddyPress, is putting down a cookie on every page load. This will prevent Varnish from caching.</td>
						</tr><?php
					}
				}
				
				// AGE
				if( !isset($headers['Age']) ) {
					?><tr>
						<td><?php echo $icon_bad; ?></td>
						<td>There's no "Age" header, which means we can't tell if the page is actually serving from cache.</td>
					</tr><?php
				} elseif( $headers['Age'] <= 0 || $headers['Age'] == 0 ) {
					if( !isset($headers['Cache-Control']) || strpos($headers['Cache-Control'], 'max-age') === FALSE ) {
						?><tr>
							<td><?php echo $icon_warning; ?></td>
							<td>The "Age" header is set to less than 1, which means you checked right when Varnish cleared it's cache for that url or Varnish is not actually serving the content for that url from cache. Check again (refresh the page) but if it happens again, it could be one of the following reasons:
								<ul style=\"text-align: left;\">
									<li>That url is excluded from the cache on purpose in the Varnish vcl file (in which case, yay! It's working.)</li>
									<li>A theme or plugin is sending cache headers that are telling Varnish not to serve that content from cache. This means you'll have to fix the cache headers the application is sending to Varnish. A lot of the time those headers are Cache-Control and/or Expires.</li>
									<li>A theme or plugin is setting a session cookie, which can prevent Varnish from serving content from cache. This means you'll have to update the application and make it not send a session cookie for anonymous traffic. </li>
								</ul>
							</td>
						</tr><?php			
					}
				}
				
				// CACHE-CONTROL
				if ( isset( $headers['Cache-Control'] ) && strpos( $headers['Cache-Control'] ,'no-cache') !== false ) {
					?><tr>
						<td><?php echo $icon_bad; ?></td>
						<td>Something is setting the header Cache-Control to 'no-cache' which means visitors will never get cached pages.</td>
					</tr><?php
				}
				
				// MAX AGE
				if ( isset( $headers['Cache-Control'] ) && strpos( $headers['Cache-Control'] ,'max-age=0') !== false ) {
					?><tr>
						<td><?php echo $icon_bad; ?></td>
						<td>Something is setting the header Cache-Control to 'max-age=0' which means a page can be no older than 0 seconds before it needs to regenerate the cache.</td>
					</tr><?php
				}
				
				// PRAGMA
				if ( isset( $headers['Pragma'] ) && strpos( $headers['Pragma'] ,'no-cache') !== false ) {
					?><tr>
						<td><?php echo $icon_bad; ?></td>
						<td>Something is setting the header Pragma to 'no-cache' which means visitors will never get cached pages.</td>
					</tr><?php
				}
				
				// X-CACHE (we're not running this)
				if ( isset( $headers['X-Cache-Status'] ) && strpos( $headers['X-Cache-Status'] ,'MISS') !== false ) {
					?><tr>
						<td><?php echo $icon_bad; ?></td>
						<td>X-Cache missed, which means it's not able to serve this page as cached.</td>
					</tr><?php
				}
				
				/* Server features */
				
				// PAGESPEED
				if ( isset( $headers['X-Mod-Pagespeed'] ) ) {
					if ( strpos( $headers['X-Cacheable'] , 'YES:Forced') !== false ) {
						?><tr>
							<td><?php echo $icon_good; ?></td>
							<td>Mod Pagespeed is active and working properly with Varnish.</td>
						</tr><?php
					} else {
						?><tr>
							<td><?php echo $icon_bad; ?></td>
							<td>Mod Pagespeed is active but it looks like your caching headers may not be right. DreamPress doesn't support Pagespeed anymore, so this may be a false negative if other parts of your site are overwriting headers. Fix all other errors <em>first</em>, then come back to this. If you're still having errors, you'll need to look into using htaccess or nginx to override the Pagespeed headers.</td>
						</tr><?php
					}
				}
			?>
			</table>
	
			<?php settings_errors(); ?>
	
			<form action="options.php" method="POST" ><?php
				settings_fields( 'varnish-http-purge' );
				do_settings_sections( 'varnish-settings' );
				submit_button( '', 'primary', 'update');
			?></form>

		</div>
		<?php
	}

	/**
	 * Sanitization and validation
	 *
	 * @param $input the input to be sanitized
	 * @since 4.0
	 */
	function varnish_sanitize( $input ) {
		
		if ( filter_var( $input, FILTER_VALIDATE_IP) ) {
			$output = filter_var( $input, FILTER_VALIDATE_IP);
		} else {
			add_settings_error( 'vhp_varnish_ip', 'invalid-ip', 'You have entered an invalid IP address.' );
		}	
		return $output;
	}
}

$status = new VarnishStatus();