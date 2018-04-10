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
 * Varnish Debug
 *
 * @since 4.4
 */

class VarnishDebug {

	/**
	 * Remote Get Varnish URL
	 *
	 * @since 4.4.0
	 */
	static function remote_get( $url = '' ) {

		// Make sure it's not a stupid URL
		$url = esc_url( $url );

		$args = array(
			'headers' => array( 
				'timeout'     => 30,
				'redirection' => 10,
			)
		);
		
		$response = wp_remote_get( $url, $args );
	
		return $response;
	}

	/**
	 * Basic checks that should stop a scan
	 *
	 * @since 4.4.0
	 */
	static function preflight( $response ) {

		// Defaults
		$preflight = true;
		$message   = __( 'Success', 'varnish-http-purge' );

		if ( is_wp_error( $response ) ) {
			$preflight = false;
			$message   = __( 'This request cannot be performed: ', 'varnish-http-purge' );
			$message  .= $response->get_error_message();
		} elseif ( wp_remote_retrieve_response_code( $response ) == '404' ) {
			$preflight = false;
			$message   = __( 'This URL is a 404. Please check your typing and try again.', 'varnish-http-purge' );
		}

		$return = array( 
			'preflight' => $preflight,
			'message'   => $message,
		);
		
		return $return;
	}

	/**
	 * Check for remote IP
	 *
	 * @since 4.4.0
	 */
	static function remote_ip( $headers ) {

		if ( isset( $headers['X-Forwarded-For'] ) && filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$remote_ip = $headers['X-Forwarded-For'];
		} elseif ( isset( $headers['HTTP_X_FORWARDED_FOR'] ) && filter_var( $headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
		) {
			$remote_ip = $headers['HTTP_X_FORWARDED_FOR'];
		} elseif ( isset( $headers['Server'] ) && strpos( $headers['Server'] ,'cloudflare') !== false ) {
			$remote_ip = 'cloudflare';
		} else {
			$remote_ip = false;
		}
		
		return $remote_ip;
	}

	/**
	 * Varnish
	 *
	 * Results on the Varnish calls
	 *
	 * @since 4.4.0
	 */
	static function varnish_results( $headers = '' ) {

		// Set the defaults
		$return = array( 
			'icon'    => 'good',
			'message' => __( 'Varnish is running properly and caching is happening.', 'varnish-http-purge' ),
		);

		if ( $headers == '' ) {
			$kronk = false;
		} else {
			$kronk = true;

			// Check if the headers are set AND if the values are valid
			$x_cachable = ( isset( $headers['X-Cacheable'] ) && strpos( $headers['X-Cacheable'] ,'YES') !== false )? true : false;
			$x_varnish  = ( isset( $headers['X-Varnish'] ) )? true : false;
			$x_via      = ( is_numeric( strpos( $headers['Via'], 'arnish' ) ) )? true : false;
			$x_age      = ( isset( $headers[ 'Age' ] ) && $headers[ 'Age' ] > 0 )?  true : false;
	
			// If this is TRUE it's NOT Cachable
			$not_cachable     = ( ( isset( $headers['X-Cacheable'] ) && strpos( $headers['X-Cacheable'] ,'NO') !== false ) || ( isset( $headers['Pragma'] ) && strpos( $headers['Pragma'] ,'no-cache') !== false ) || !$x_age )? true : false;
			$cacheheaders_set = ( isset( $headers['X-Cacheable'] ) || isset( $headers['X-Varnish'] ) || isset( $headers['X-Cache'] ) || $x_via )? true : false;
		}

		if ( !$kronk ) {
			$return['icon']    = 'bad';
			$return['message'] = __( 'Something went very wrong with this request. Please try again.', 'varnish-http-purge' );
		} elseif ( !$cacheheaders_set ) {
			$return['icon']    = 'warning';
			$return['message'] = __( 'We were unable find Varnish active for this domain. Please review the output below to understand why.', 'varnish-http-purge' );
		} elseif ( !$not_cachable && ( $x_cachable || $x_varnish ) ) {
			$return['icon']    = 'awesome';
			$return['message'] = __( 'Varnish is running properly and caching is happening.', 'varnish-http-purge' );
		} else {
			$return['icon']    = 'warning';
			$return['message'] = __( 'Varnish is running but is unable to cache your site. Please review the following output to diagnose the issue.', 'varnish-http-purge' );
		}

		return $return;
	}
	
	/**
	 * Remote IP
	 *
	 * Results on if we have a proxy going on and what that means
	 *
	 * @since 4.4.0
	 */
	static function remote_ip_results( $remote_ip, $varniship ) {

		// Set the defaults
		$return = false;

		if ( $remote_ip == false && !empty( $varniship) ) {
			$return = array( 
				'icon'    => 'warning',
				'message' => sprintf( __( 'You have a Varnish IP set but a proxy like Cloudflare or Sucuri has not been detected. This is mostly harmless, but if you have issues with your cache not emptying when you make a post, you may need to <a href="%s">erase your Varnish IP</a>. Please check with your webhost or server admin before doing so.', 'varnish-http-purge'  ), '#configure' )
			);
		} elseif ( $remote_ip !== false && $remote_ip !== $varniship ) {
			$return = array( 
				'icon'    => 'warning',
				'message' => sprintf( __( 'You\'re using a Custom Varnish IP that doesn\'t appear to match your server IP address. If you\'re using multiple Varnish servers or IPv6, this is fine. Please make sure you\'ve <a href="%s">properly configured it</a> according to your webhost\'s specifications.', 'varnish-http-purge'  ), '#configure' ),
			);
		} else {
			$return = array( 
				'icon'    => 'awesome',
				'message' => __( 'Your server IP setup looks good.', 'varnish-http-purge'  ),
			);
		}

			return $return;
		}

	/**
	 * Server Details
	 *
	 * Includes nginx, hhvm, cloudflare, and more
	 *
	 * @since 4.4.0
	 */
	static function server_results( $headers ) {

		// Set the defaults
		$return = array();

		if ( isset( $headers['Server'] ) ) {
			// nginx
			if ( strpos( $varnish_headers['Server'] ,'nginx') !== false && strpos( $varnish_headers['Server'] ,'cloudflare') == false ) {
				$return['nginx'] = array( 
					'icon'    => 'awkward',
					'message' => __( 'Your server is running nginx and Apache was expected. This may be fine, especially if you use a passthrough proxy, but keep it in mind.', 'varnish-http-purge'  )
				);
			}
			
			// Cloudflare
			if ( strpos( $headers['Server'] ,'cloudflare') !== false ) {
				$return['cloudflare'] = array( 
					'icon'    => 'warning',
					'message' => sprintf( __( 'CloudFlare has been detected. While this is generally fine, you may experience some cache oddities. Make sure you <a href="%s">configure WordPress for Cloudflare</a>.', 'varnish-http-purge'  ), '#configure' ),
				);
			}

			// HHVM: Note, WP is dropping support so ...
			if ( isset( $headers['X-Powered-By'] ) && strpos( $headers['X-Powered-By'] ,'HHVM') !== false ) {
				$return['hhvm'] = array( 
					'icon'    => 'awkward',
					'message' => __( 'You are running HHVM instead of PHP. While that is compatible with Varnish, you should consider PHP 7. WordPress will cease support for HHVM in 2018.', 'varnish-http-purge' ),
				);
			}

			// Pagely
			if ( strpos( $varnish_headers['Server'] ,'Pagely') !== false ) {
				$return['pagely'] = array( 
					'icon'    => 'good',
					'message' => __( 'This site is hosted on Pagely.', 'varnish-http-purge' ),
				);
			}
		}

		if ( isset( $varnish_headers['X-hacker'] ) ) {
			$return['wordpresscom'] = array( 
				'icon'    => 'bad',
				'message' => __( 'This site is hosted on WordPress.com, which is cool but, last we checked, they don\'t use Varnish.', 'varnish-http-purge' ),
			);
		}
		
		if ( isset( $varnish_headers['X-Backend'] ) && strpos( $varnish_headers['X-Backend'] ,'wpaas_web_') !== false ) {
			$return['godaddy'] = array( 
				'icon'    => 'good',
				'message' => __( 'This site is hosted on GoDaddy.', 'varnish-http-purge' ),
			);
		}

		return $return;
	}

	/**
	 * GZIP
	 *
	 * Results on GZIP
	 *
	 * @since 4.4.0
	 */
	static function gzip_results( $headers ) {

		// Set the defaults
		$return = false;

		// GZip
		if( strpos( $headers['Content-Encoding'] ,'gzip') !== false || ( isset( $headers['Vary'] ) && strpos( $headers['Vary'] ,'gzip' ) !== false ) ) {
			$return = array( 
				'icon'    => 'good',
				'message' => __( 'Your site is compressing content and making the internet faster.', 'varnish-http-purge' ),
			);
		}

		// Fastly
		if ( strpos( $headers['Content-Encoding'] ,'Fastly') !== false ) {
			$return = array( 
				'icon'    => 'good',
				'message' => sprintf( __( '<a href="%s">Fastly</a> is speeding up your site. Keep in mind, it may cache your CSS and images longer than Varnish does. Remember to empty all caches in all locations.', 'varnish-http-purge' ), esc_url( 'https://fastly.com' ) ),
			);
		}

		return $return;
	}

	/**
	 * Cookies
	 *
	 * Cookies break Varnish. Sometimes.
	 *
	 * @since 4.4.0
	 */
	static function cookie_results( $headers ) {

		// Defaults
		$return = $cookies = array();

		// Set the default returns
		$cookie_warning = array(
			'icon'    => 'warning',
			'message' => __( 'Cookies have been detected on your site. This can cause Varnish to not properly cache unless it\'s configured specially to accommodate. Since it\'s impossible to cover all possible situations, please take the following alerts with a grain of salt. If you know certain cookies are safe on your server, this is fine. If you aren\'t sure, pass the details on to your webhost.', 'varnish-http-purge' ),
		);
		$default = array(
			'phpsessid'           => array( 'icon' => 'bad', 'message' => __( 'A plugin or theme is setting a PHPSESSID cookie on every pageload. This makes Varnish not deliver cached pages.', 'varnish-http-purge' ) ),
			'edd_wp_session'      => array( 'icon' => 'bad', 'message' => sprintf( __( '<a href="%s">Easy Digital Downloads</a> is being used with cookie sessions. This may cause your cache to misbehave. If you have issues, please set <code>define( "EDD_USE_PHP_SESSIONS", true );</code> in your <code>wp-config.php</code> file.', 'varnish-http-purge'  ), esc_url('https://wordpress.org/plugins/easy-digital-downloads/') ) ),
			'edd_items_in_cart'   => array( 'icon' => 'warning', 'message' => sprintf( __( '<a href="%s">Easy Digital Downloads</a> is putting down a shopping cart cookie on every page load. Make sure Varnish is set up to ignore that when it\'s empty.', 'varnish-http-purge'  ), esc_url('https://wordpress.org/plugins/easy-digital-downloads/') ) ),
			'wfvt'                => array( 'icon' => 'bad', 'message' => sprintf( __( '<a href="%s">Wordfence</a> is putting down cookies on every page load. Please check "Disable WordFence Cookies" under <a href="%s">General Wordfence Options</a> to resolve.', 'varnish-http-purge'  ), esc_url('https://wordpress.org/plugins/wordfence/'), admin_url( 'admin.php?page=WordfenceOptions' ) ) ),
			'invite_anyone'       => array( 'icon' => 'bad', 'message' => sprintf( __( '<a href="%s">Invite Anyone</a>, a plugin for BuddyPress, is putting down a cookie on every page load.', 'varnish-http-purge'  ), esc_url('https://wordpress.org/plugins/invite-anyone/') ) ),
			'charitable_sessions' => array( 'icon' => 'bad', 'message' => sprintf( __( '<a href="%s">Charitable</a> is putting down a cookie on every page load. This has been fixed as of version 1.5.0, so please upgrade to the latest version.', 'varnish-http-purge'  ), esc_url('https://wordpress.org/plugins/charitable/') ) ),
		);

		if ( isset( $headers['Set-Cookie'] ) ) {
			// If cookies are an array, scan the whole thing. Otherwise, we can use strpos.
			if ( is_array( $headers['Set-Cookie'] ) ) {
				$cookies = array(
					'phpsessid'           => in_array( 'PHPSESSID', $headers['Set-Cookie'], true ),
					'edd_wp_session'      => in_array( 'edd_wp_session', $headers['Set-Cookie'], true ),
					'edd_items_in_cart'   => in_array( 'edd_items_in_cart', $headers['Set-Cookie'], true ),
					'wfvt'                => in_array( 'wfvt_', $headers['Set-Cookie'], true ),
					'invite_anyone'       => in_array( 'invite-anyone', $headers['Set-Cookie'], true ),
					'charitable_sessions' => in_array( 'charitable_sessions', $headers['Set-Cookie'], true ),
				);
			} else {
				$cookies = array(
					'phpsessid'           => strpos( $headers['Set-Cookie'], 'PHPSESSID'),
					'edd_wp_session'      => strpos( $headers['Set-Cookie'], 'edd_wp_session' ),
					'edd_items_in_cart'   => strpos( $headers['Set-Cookie'], 'edd_items_in_cart' ),
					'wfvt'                => strpos( $headers['Set-Cookie'], 'wfvt_' ),
					'invite_anyone'       => strpos( $headers['Set-Cookie'], 'invite-anyone' ),
					'charitable_sessions' => strpos( $varnish_headers['Set-Cookie'], 'charitable_sessions' ),
				);
			}

			foreach ( $cookies as $name => $value ) {
				if ( $value !== false ) {
					$return[ $name ] = array( 'icon' => $default[ $name]['icon'], 'message' => $default[ $name]['message'] );
				}
			}
		}

		// If there's data in the array, add a Cookie Monster Warning to the top
		if ( !empty( $return ) ) array_unshift( $return, $cookie_warning );

		return $return;
	}


	/**
	 * Cache
	 *
	 * Checking Age, Max Age, Cache Control, Pragma and more
	 *
	 * @since 4.4.0
	 */
	static function cache_results( $headers ) {

		$return = array();

		// Cache Control
		if ( isset( $headers['Cache-Control'] ) ) {

			// No-Cache Set
			if ( strpos( $headers['Cache-Control'], 'no-cache' ) !== false ) {
				$return['no_cache'] = array(
					'icon'    => 'bad',
					'message' => __( 'Something is setting the header Cache-Control to "no-cache" which means visitors will never get cached pages.', 'varnish-http-purge' ),
				);
			}

			// Max-Age is 0
			if ( strpos( $headers['Cache-Control'], 'max-age=0' ) !== false ) {
				$return['max_age'] = array(
					'icon'    => 'bad',
					'message' => __( 'Something is setting the header Cache-Control to "max-age=0" which means a page can be no older than 0 seconds before it needs to regenerate the cache.', 'varnish-http-purge' ),
				);
			}
		}

		// Age Headers
		if ( !isset( $headers['Age'] ) ) {
			$return['age'] = array(
				'icon'    => 'bad',
				'message' => __( 'Your domain does not report an "Age" header, which means we can\'t tell if the page is actually serving from cache.', 'varnish-http-purge' ),
			);
		} elseif( $headers['Age'] <= 0 || $headers['Age'] == 0 ) {
			$return['age'] = array(
				'icon'    => 'warning',
				'message' => __( 'The "Age" header is set to less than 1, which means you checked right when Varnish cleared the cache for that url or Varnish is not serving cached content for that url. Check again but if it happens again, then either the URL is intentionally excluded from caching, or a theme or plugin is sending cache headers or cookies that instruct varnish not to cache.', 'varnish-http-purge' ),
			);
		} else {
			$return['age'] = array(
				'icon'    => 'good',
				'message' => __( 'Your site is returning proper "Age" headers.', 'varnish-http-purge' ),
			);
		}

		// Pragma
		if ( isset( $headers['Pragma'] ) && strpos( $headers['Pragma'] ,'no-cache') !== false ) {
			$return['pragma'] = array(
				'icon'    => 'bad',
				'message' =>  __( 'Something is setting the header Pragma to "no-cache" which means visitors will never get cached pages. This is usually done by plugins.', 'varnish-http-purge' ),
			);
		}

		// X-Cache
		if ( isset( $headers['X-Cache-Status'] ) && strpos( $headers['X-Cache-Status'] ,'MISS') !== false ) {
			$return['X-Cache'] = array(
				'icon'    => 'bad',
				'message' =>  __( 'X-Cache missed, which means it was not able to serve this page as cached. This may be resolved by rerunning the scan. If not, then a plugin or theme is forcing this setting.', 'varnish-http-purge' ),
			);
		}

		// Mod-PageSpeed
		if ( isset( $headers['X-Mod-Pagespeed'] ) ) {
			if ( strpos( $headers['X-Cacheable'] , 'YES:Forced') !== false ) {
				$return['mod_pagespeed'] = array(
					'icon'    => 'good',
					'message' =>  __( 'Mod Pagespeed is active and configured to work properly with Varnish.', 'varnish-http-purge' ),
				);
			} else {
				$return['mod_pagespeed'] = array(
					'icon'    => 'bad',
					'message' =>  __( 'Mod Pagespeed is active but it looks like your caching headers may not be right. This may be a false negative if other parts of your site are overwriting headers. Fix all other errors listed, then come back to this. If you are still having errors, you will need to look into using htaccess or nginx to override the Pagespeed headers.', 'varnish-http-purge' ),
				);
			}
		}

		return $return;
	}

	/**
	 * Bad Actors
	 *
	 * Plugins and themes known to be problematic
	 *
	 * @since 4.4.0
	 */
	static function bad_actors_results( ) {

		$return = array();

		$themes = array( 
			'divi' => __( 'Divi themes use sessions in their headers for many of their themes. To check, change your theme and re-run this test. If this warning goes away, it\'s your theme.', 'varnish-http-purge' ),
			'enfold' => __( 'The Enfold theme uses sessions for every call of shortcodes in certain situations. To check, change your theme and re-run this test. If this warning goes away, it\'s your theme.', 'varnish-http-purge' ),
			'prophoto6' => __( 'Prophoto version 6 requires you to be on version 6.21.8 or higher to work properly with Varnish. Please make sure you site is up to date.', 'varnish-http-purge' ),
		);

		$plugins = array( 
			'bad-behavior' => array(
				'path'    => 'bad-behavior/bad-behavior.php',
				'message' => sprintf( __( '<a href="%s">Bad Behavior</a> may cause unexpected results with Varnish and not function properly.', 'varnish-http-purge' ), 'https://wordpress.org/plugins/bad-behavior/' ),
			),
			'pie-register' => array(
				'path'    => 'pie-register/pie-register.php',
				'message' => sprintf( __( '<a href="%s">Pie Register</a> sets output buffering in the header of every page load, which enforces sessions. There is no known fix at this time.', 'varnish-http-purge' ), 'https://wordpress.org/plugins/pie-register/' ),
			),
			'quick-cache' => array(
				'path'    => 'quick-cache/quick-cache.php',
				'message' => __( 'Quick Cache does not play well with Varnish.', 'varnish-http-purge' )
			),
			'simple-session-support' => array(
				'path'    => 'simple-session-support/simple-session-support.php',
				'message' => sprintf( __( '<a href="%s">Simple Session Support</a> forces PHP Sessions. It\'s also no longer updated and not recommended for use.', 'varnish-http-purge' ), 'https://wordpress.org/plugins/simple-session-support/' ),
			),
			'tweet-blender' => array(
				'path'    => 'tweet-blender/tweet-blender.php',
				'message' => sprintf( __( '<a href="%s">Tweet Blender</a> conflicts with most server based caching. It also has not been updated since 2014.', 'varnish-http-purge' ), 'https://wordpress.org/plugins/tweet-blender/' ),
			),
			'wp-cache' => array(
				'path'    => 'wp-cache/wp-cache.php',
				'message' => sprintf( __( '<a href="%s">WP Cache</a> is not necessary when using a Varnish based caching, and can cause redundancy in caches, resulting in unexpected data load.', 'varnish-http-purge' ), 'https://wordpress.org/plugins/wp-cache/' ),
			),
			'wp-file-cache' => array(
				'path'    => 'wp-file-cache/file-cache.php',
				'message' => sprintf( __( '<a href="%s">WP Files Cache</a> is not necessary when using a Varnish based caching, and can cause redundancy in caches, resulting in unexpected data load.', 'varnish-http-purge' ), 'https://wordpress.org/plugins/wp-file-cache/' ),
			),
			'wp-super-cache' => array(
				'path'    => 'wp-super-cache/wp-cache.php',
				'message' => sprintf( __( '<a href="%s">WP Super Cache</a> is not necessary when using a Varnish based caching, and can cause redundancy in caches, resulting in unexpected data load.', 'varnish-http-purge' ), 'https://wordpress.org/plugins/wp-super-cache/' ),
			),
		);

		// Check all the themes. If one of the questionable ones are active, warn
		foreach ( $themes as $theme => $message ) {
			$my_theme = wp_get_theme( $theme );
			if ( $my_theme->exists() ) {
				$return[ $theme ] = array( 'icon' => 'warning', 'message' => $message );
			}
		}

		// Check the plugins
		foreach ( $plugins as $plugin => $data ) {
			if ( is_plugin_active( $data['path'] ) ) {
				$return[ $plugin ] = array( 'icon' => 'warning', 'message' => $data['message'] );
			}
		}

		return $return;
	}

	/**
	 * Get all the results
	 *
	 * Collect everything, get all the data spit it out.
	 * 
	 * @since 4.4.0
	 */
	static function get_all_the_results( $headers, $remote_ip, $varniship ) {
		$output = array();
		$output['varnish']   = self::varnish_results( $headers );
		$output['remote_ip'] = self::remote_ip_results( $remote_ip, $varniship );

		// Server Results
		$sever_results       = self::server_results( $remote_ip, $varniship );
		$output              = array_merge( $output, $sever_results );

		// Cache Results
		$cache_results       = self::cache_results( $headers );
		$output              = array_merge( $output, $cache_results );

		// Cookies
		$cookie_results      = self::cookie_results( $headers );
		$output              = array_merge( $output, $cookie_results );

		// Bad Actors (plugins and themes that don't play nicely with Varnish)
		$bad_actors_results  = self::bad_actors_results( $headers );
		$output              = array_merge( $output, $bad_actors_results );
		
		return $output;
	}

}

if ( class_exists( 'VarnishDebug' ) ) $varnish_debug = new VarnishDebug();