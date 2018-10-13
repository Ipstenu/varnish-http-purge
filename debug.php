<?php
/**
 * Debug Code
 * @package varnish-http-purge
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Varnish Debug
 *
 * @since 4.4
 */
class VarnishDebug {

	/**
	 * Devmode Check
	 * See if Dev Mode is active
	 *
	 * @since 4.6.0
	 * @returns true|false
	 */
	public static function devmode_check() {
		$return  = false;
		$newmode = get_site_option( 'vhp_varnish_devmode', VarnishPurger::$devmode );

		if ( VHP_DEVMODE ) {
			$return = true;
		} elseif ( isset( $newmode['active'] ) && $newmode['active'] ) {
			// if expire is less that NOW, it's over.
			if ( $newmode['expire'] <= current_time( 'timestamp' ) ) {
				$newmode['active'] = false;
				update_site_option( 'vhp_varnish_devmode', $newmode );
			} else {
				$return = true;
			}
		}
		return $return;
	}

	/**
	 * Toggle devmode on or off.
	 *
	 * @access public
	 * @static
	 * @param string $state (default: 'deactivate').
	 * @return true|false
	 */
	public static function devmode_toggle( $state = 'deactivate' ) {
		$newmode = get_site_option( 'vhp_varnish_devmode', VarnishPurger::$devmode );

		// Weirdly this doesn't actually matter.
		$newmode['expire'] = current_time( 'timestamp' ) + DAY_IN_SECONDS;

		switch ( sanitize_text_field( $state ) ) {
			case 'activate':
				$newmode['active'] = true;
				break;
			case 'toggle':
				$newmode['active'] = ( self::devmode_check() ) ? false : true;
				break;
			case 'deactivate':
			default:
				$newmode['active'] = false;
				break;
		}

		update_site_option( 'vhp_varnish_devmode', $newmode );

		return $newmode['active'];
	}

	/**
	 * Append the ?nocache parameter to JS and CSS files
	 *
	 * @access public
	 * @static
	 * @param mixed $src - URL of CSS or JS file.
	 * @return url
	 * @since 4.6.0
	 */
	public static function nocache_cssjs( $src ) {
		$src = remove_query_arg( 'ver', $src );
		$src = add_query_arg( 'nocache', '', $src );
		return $src;
	}

	/**
	 * Validate URL.
	 *
	 * @access public
	 * @static
	 * @param mixed $input - The URL to validate.
	 * @return string
	 * @since 4.6.0
	 */
	public static function is_url_valid( $input ) {

		$default = esc_url( VarnishPurger::the_home_url() );

		if ( ! empty( $input ) ) {
			$parsed_input = wp_parse_url( $input );
			if ( empty( $parsed_input['scheme'] ) ) {
				$schema_input = 'http://';
				if ( is_ssl() ) {
					$schema_input = 'https://';
				}
				$input = $schema_input . ltrim( $input, '/' );
			}
		}

		if ( empty( $input ) ) {
			$output = 'empty';
		} elseif ( wp_parse_url( $default, PHP_URL_HOST ) !== wp_parse_url( $input, PHP_URL_HOST ) ) {
			$output = 'domain';
		} elseif ( ! filter_var( $input, FILTER_VALIDATE_URL ) ) {
			$output = 'invalid';
		} else {
			$output = 'valid';
		}

		return $output;
	}

	/**
	 * Get Remote URL.
	 *
	 * @access public
	 * @static
	 * @param string $url (default: '').
	 * @return array
	 * @since 4.4.0
	 */
	public static function remote_get( $url = '' ) {

		// Make sure it's not a stupid URL.
		$url = esc_url( $url );

		$args = array(
			'headers' => array(
				'timeout'     => 30,
				'redirection' => 10,
			),
		);

		// Lazy run twice to make sure we get a primed cache page.
		$response1 = wp_remote_get( $url, $args );

		// Because the 'Age' header is an important check, wait a second before fetching again.
		sleep( 1 );

		$response2 = wp_remote_get( $url, $args );

		return $response2;
	}

	/**
	 * Basic checks that should stop a scan
	 *
	 * @since 4.4.0.
	 *
	 * @access public
	 * @static
	 * @param mixed $response - Message for if the URL is scannable.
	 * @return array
	 */
	public static function preflight( $response ) {

		// Defaults.
		$preflight = true;
		$message   = __( 'Success', 'varnish-http-purge' );

		if ( is_wp_error( $response ) ) {
			$preflight = false;
			$message   = __( 'This request cannot be performed: ', 'varnish-http-purge' );
			$message  .= $response->get_error_message();
		} elseif ( '404' === wp_remote_retrieve_response_code( $response ) ) {
			$preflight = false;
			$message   = __( 'This URL does not resolve properly. Either it\'s was not found or it redirects incorrectly.', 'varnish-http-purge' );
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
	 *
	 * @access public
	 * @static
	 * @param mixed $headers - headers from wp_remote_get.
	 * @return string
	 */
	public static function remote_ip( $headers ) {

		if ( isset( $headers['X-Forwarded-For'] ) && filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$remote_ip = $headers['X-Forwarded-For'];
		} elseif ( isset( $headers['HTTP_X_FORWARDED_FOR'] ) && filter_var( $headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
		) {
			$remote_ip = $headers['HTTP_X_FORWARDED_FOR'];
		} elseif ( isset( $headers['Server'] ) && strpos( $headers['Server'], 'cloudflare' ) !== false ) {
			$remote_ip = 'cloudflare';
		} else {
			$remote_ip = false;
		}

		return $remote_ip;
	}

	/**
	 * Results on the Varnish calls
	 *
	 * @since 4.4.0
	 *
	 * @access public
	 * @static
	 * @param string $headers (default: false) - headers from wp_remote_get.
	 * @return array
	 */
	public static function varnish_results( $headers = false ) {

		$return = array();

		// If we have headers...
		if ( ! $headers ) {
			$kronk = false;
		} else {
			$kronk = true;

			// Get some basic truthy/falsy from the headers.
			// Headers used by both.
			$x_varnish = ( isset( $headers['X-Varnish'] ) ) ? true : false;
			$x_date    = ( isset( $headers['Date'] ) && strtotime( $headers['Date'] ) !== false ) ? true : false;
			$x_age     = ( isset( $headers['Age'] ) ) ? true : false;

			// Is this Nginx or not?
			$x_nginx = ( isset( $headers['server'] ) && ( strpos( $headers['server'], 'nginx' ) !== false || strpos( $headers['server'], 'openresty' ) !== false ) ) ? true : false;

			// Headers used by Nginx.
			$x_varn_hit  = ( $x_varnish && strpos( $headers['X-Varnish'], 'HIT' ) !== false ) ? true : false;
			$x_age_nginx = ( $x_varn_hit || ( $x_age && $x_date && ( strtotime( $headers['Age'] ) < strtotime( $headers['Date'] ) ) ) ) ? true : false;
			$x_pragma    = ( ! isset( $headers['Pragma'] ) || ( isset( $headers['Pragma'] ) && strpos( $headers['Pragma'], 'no-cache' ) === false ) ) ? true : false;

			// Headers used ONLY by Apache/Varnish.
			$x_cachable = ( isset( $headers['X-Cacheable'] ) && strpos( $headers['X-Cacheable'], 'YES' ) !== false ) ? true : false;
			$x_age_vapc = ( $x_age && $headers['Age'] > 0 ) ? true : false;

			// Optional Headers.
			$x_via     = ( is_numeric( strpos( $headers['Via'], 'arnish' ) ) ) ? true : false;
			$x_cache   = ( isset( $headers['x-cache-status'] ) && strpos( $headers['x-cache-status'], 'HIT' ) !== false ) ? true : false;
			$x_p_cache = ( isset( $headers['X-Proxy-Cache'] ) && strpos( $headers['X-Proxy-Cache'], 'HIT' ) !== false ) ? true : false;

			// Are cache HEADERS set?
			$cacheheaders_set = ( isset( $headers['X-Cacheable'] ) || $x_varnish || isset( $headers['X-Cache'] ) || $x_via ) ? true : false;

			// Is Cacheable?
			$is_cachable    = ( $x_varnish && $x_age ) ? true : false;
			$still_cachable = true;

			// Which service are we?
			$cache_service = false;
			if ( $x_varnish && $x_nginx ) {
				$cache_service  = __( 'Nginx', 'varnish-http-purge' );
				$still_cachable = ( $is_cachable && $x_age_nginx && $x_varn_hit && $x_pragma ) ? true : false;
			} elseif ( $x_varnish && ! $x_nginx ) {
				$cache_service  = __( 'Varnish', 'varnish-http-purge' );
				$still_cachable = ( $is_cachable && $x_cachable && $x_age_vapc ) ? true : false;
			}

			// Determine the default message.
			if ( false !== $cache_service ) {
				// translators: %1 is the type of caching service detected (i.e. nginx or varnish).
				$return['message'] = sprintf( __( 'Your %1 caching service appears to be running properly.', 'varnish-http-purge' ), $cache_service );
				$return['icon']    = 'good';
			}
		}

		if ( ! $cache_service ) {
			$return['icon']    = 'bad';
			$return['message'] = __( 'No known cache service has been detected on your site.', 'varnish-http-purge' );
		} elseif ( ! $kronk ) {
			$return['icon']    = 'bad';
			$return['message'] = __( 'Your site is not responding. If this happens again, please contact your webhost.', 'varnish-http-purge' );
		} elseif ( ! $cacheheaders_set ) {
			$return['icon']    = 'notice';
			$return['message'] = __( 'We were unable find a caching service active for this domain. This may occur if you use a proxy service (such as CloudFlare or Sucuri) or if you\'re in the middle of a DNS move.', 'varnish-http-purge' );
		} elseif ( $is_cachable && $still_cachable ) {
			$return['icon'] = 'awesome';
		} else {
			// translators: %1 is the type of caching service detected (i.e. nginx or varnish).
			$return['message'] = sprintf( __( '%s caching service is running but is unable to cache your site.', 'varnish-http-purge' ), $cache_service );
			$return['icon']    = 'warning';
		}

		return $return;
	}

	/**
	 * Remote IP
	 *
	 * Results on if we have a proxy going on and what that means
	 *
	 * @since 4.4.0
	 *
	 * @access public
	 * @static
	 * @param mixed $remote_ip - IP detected.
	 * @param mixed $varniship - Varnish IP.
	 * @return array
	 */
	public static function remote_ip_results( $remote_ip, $varniship, $headers ) {
		$return  = false;
		$x_nginx = ( isset( $headers['server'] ) && ( strpos( $headers['server'], 'nginx' ) !== false || strpos( $headers['server'], 'openresty' ) !== false ) ) ? true : false;

		if ( $x_nginx && 'localhost' === $varniship ) {
			$return = array(
				'icon'    => 'awesome',
				'message' => __( 'Your Nginx Proxy is set up correctly.', 'varnish-http-purge' ),
			);
		} elseif ( false === $remote_ip && ! empty( $varniship ) ) {
			$return = array(
				// translators: %s is an IP address.
				'message' => sprintf( __( 'Your Varnish IP address is set to %s but a proxy (like Cloudflare or Sucuri) has not been detected. This is mostly harmless, but if you have issues with your cache not emptying when you make a post, you may need to remove your Varnish IP. Please check with your webhost or server admin before doing so.', 'varnish-http-purge' ), $varniship ),
				'icon'    => 'warning',
			);
		} elseif ( false !== $remote_ip && $remote_ip !== $varniship ) {
			$return = array(
				'icon'    => 'warning',
				'message' => __( 'You\'re using a custom Varnish IP that doesn\'t appear to match your server IP address. If you\'re using multiple caching servers or IPv6, this is fine. Please make sure you\'ve properly configured it according to your webhost\'s specifications.', 'varnish-http-purge' ),
			);
		} else {
			$return = array(
				'icon'    => 'awesome',
				'message' => __( 'Your server IP setup looks good.', 'varnish-http-purge' ),
			);
		}

			return $return;
	}

	/**
	 * Server Details
	 *
	 * Includes nginx, hhvm, cloudflare, and more
	 *
	 * @since 4.4.0.
	 *
	 * @access public
	 * @static
	 * @param mixed $headers - headers from wp_remote_get
	 * @return array
	 */
	public static function server_results( $headers ) {

		$return = array();

		if ( isset( $headers['Server'] ) ) {
			// Apache.
			if ( strpos( $headers['Server'], 'Apache' ) !== false && strpos( $headers['Server'], 'cloudflare' ) === false ) {
				$return['Apache'] = array(
					'icon'    => 'awesome',
					'message' => __( 'Your server is running Apache.', 'varnish-http-purge' ),
				);
			}

			// nginx.
			if ( strpos( $headers['Server'], 'nginx' ) !== false && strpos( $headers['Server'], 'cloudflare' ) === false ) {
				$return['Nginx'] = array(
					'icon'    => 'awesome',
					'message' => __( 'Your server is running Nginx.', 'varnish-http-purge' ),
				);
			}

			// Cloudflare.
			if ( strpos( $headers['Server'], 'cloudflare' ) !== false ) {
				$return['CloudFlare'] = array(
					'icon'    => 'warning',
					'message' => __( 'CloudFlare has been detected. Make sure you configure WordPress properly by adding your Varnish IP and to flush the CloudFlare cache if you see inconsistencies.', 'varnish-http-purge' ),
				);
			}

			// HHVM: Note, WP is dropping support.
			if ( isset( $headers['X-Powered-By'] ) && strpos( $headers['X-Powered-By'], 'HHVM' ) !== false ) {
				$return['HHVM'] = array(
					'icon'    => 'notice',
					'message' => __( 'You are running HHVM instead of PHP. While that is compatible with Varnish, you should consider PHP 7. WordPress will cease support for HHVM in 2018.', 'varnish-http-purge' ),
				);
			}

			if ( strpos( $headers['Server'], 'Pagely' ) !== false ) {
				$return['Pagely'] = array(
					'icon'    => 'good',
					'message' => __( 'This site is hosted on Pagely. The results of this scan may not be accurate.', 'varnish-http-purge' ),
				);
			}
		}

		if ( isset( $headers['X-hacker'] ) ) {
			$return['WordPress.com'] = array(
				'icon'    => 'bad',
				'message' => __( 'This site is hosted on WordPress.com. The results of this scan may not be accurate.', 'varnish-http-purge' ),
			);
		}

		if ( isset( $headers['X-Backend'] ) && strpos( $headers['X-Backend'], 'wpaas_web_' ) !== false ) {
			$return['GoDaddy'] = array(
				'icon'    => 'good',
				'message' => __( 'This site is hosted on GoDaddy. The results of this scan may not be accurate.', 'varnish-http-purge' ),
			);
		}

		return $return;
	}

	/**
	 * Results on GZIP
	 *
	 * @since 4.4.0
	 *
	 * @access public
	 * @static
	 * @param mixed $headers - headers from wp_remote_get.
	 * @return array
	 */
	public static function gzip_results( $headers ) {

		$return = false;

		// GZip.
		if ( strpos( $headers['Content-Encoding'], 'gzip' ) !== false || ( isset( $headers['Vary'] ) && strpos( $headers['Vary'], 'gzip' ) !== false ) ) {
			$return = array(
				'icon'    => 'good',
				'message' => __( 'Your site is compressing content and making the internet faster.', 'varnish-http-purge' ),
			);
		}

		// Fastly.
		if ( strpos( $headers['Content-Encoding'], 'Fastly' ) !== false ) {
			$return = array(
				'icon'    => 'good',
				'message' => __( 'Fastly is speeding up your site. Remember to empty all caches in all locations when necessary.', 'varnish-http-purge' ),
			);
		}

		return $return;
	}

	/**
	 * Cookies break Varnish. Sometimes.
	 *
	 * @since 4.4.0
	 *
	 * @access public
	 * @static
	 * @param mixed $headers - headers from wp_remote_get.
	 * @return array
	 */
	public static function cookie_results( $headers ) {

		$return = array();
		$almost = array();

		// Early check. If there are no cookies, skip!
		if ( ! isset( $headers['Set-Cookie'] ) ) {
			$return['No Cookies'] = array(
				'icon'    => 'awesome',
				'message' => __( 'No active cookies have been detected on your site. You may safely ignore any warnings about cookies set by plugins or themes, as your server has properly accounted for them.', 'varnish-http-purge' ),
			);
		} else {
			// We have at least one cookie, so let's set this now.
			$return['Cookies Found'] = array(
				'icon'    => 'warning',
				'message' => __( 'Cookies have been detected. Unless your caching service is configured properly for the specific cookies, it may not cache properly. Please contact your webhost or administrator with information about the cookies found.', 'varnish-http-purge' ),
			);

			// Let's check our known bad cookies.
			$request = wp_remote_get( 'https://varnish-http-purge.objects-us-east-1.dream.io/cookies.json' );

			if ( is_wp_error( $request ) ) {
				return $return; // Bail if we can't hit the server.
			}

			$body    = wp_remote_retrieve_body( $request );
			$cookies = json_decode( $body );

			if ( empty( $cookies ) ) {
				if ( WP_DEBUG ) {
					$return['cookie-error'] = array(
						'icon'    => 'warning',
						'message' => __( 'Error: Cookie data cannot be loaded.', 'varnish-http-purge' ),
					);
				}
				return $return; // Bail if the data was empty for some reason.
			}

			foreach ( $cookies as $cookie => $info ) {
				$has_cookie = false;

				// If cookies are an array, scan the whole thing. Otherwise, we can use strpos.
				if ( is_array( $headers['Set-Cookie'] ) ) {
					if ( in_array( $info->cookie, $headers['Set-Cookie'], true ) ) {
						$has_cookie = true;
					}
				} else {
					$strpos = strpos( $headers['Set-Cookie'], $info->cookie );
					if ( false !== $strpos ) {
						$has_cookie = true;
					}
				}

				if ( $has_cookie ) {
					$return[ 'Cookie: ' . $cookie ] = array(
						'icon'    => $info->type,
						'message' => $info->message,
					);
				}
			}
		}

		return $return;
	}

	/**
	 * Cache
	 *
	 * Checking Age, Max Age, Cache Control, Pragma and more
	 *
	 * @since 4.4.0
	 *
	 * @access public
	 * @static
	 * @param mixed $headers - headers from wp_remote_get.
	 * @return array
	 */
	public static function cache_results( $headers ) {

		$return = array();

		// Cache Control.
		if ( isset( $headers['Cache-Control'] ) ) {

			if ( is_array( $headers['Cache Control'] ) ) {
				$no_cache = array_search( 'no-cache', $headers['Cache-Control'], true );
				$max_age  = array_search( 'max-age=0', $headers['Cache-Control'], true );
			} else {
				$no_cache = strpos( $headers['Cache-Control'], 'no-cache' );
				$max_age  = strpos( $headers['Cache-Control'], 'max-age=0' );
			}

			// No-Cache Set.
			if ( $no_cache ) {
				$return['No Cache Header'] = array(
					'icon'    => 'bad',
					'message' => __( 'The header Cache-Control is returning "no-cache", which means visitors will never get cached pages.', 'varnish-http-purge' ),
				);
			}

			// Max-Age is 0.
			if ( $max_age ) {
				$return['max_age'] = array(
					'icon'    => 'bad',
					'message' => __( 'The header Cache-Control is returning "max-age=0", which means a page can be no older than 0 seconds before it needs to regenerate the cache.', 'varnish-http-purge' ),
				);
			}
		}

		// Age Headers.
		if ( ! isset( $headers['Age'] ) ) {
			$return['Age Headers'] = array(
				'icon'    => 'bad',
				'message' => __( 'Your domain does not report an "Age" header, making it impossible to determine if the page is actually serving from cache.', 'varnish-http-purge' ),
			);
		} elseif ( ( $headers['Age'] <= 0 || 0 === $headers['Age'] ) && (bool) strtotime( $headers['Age'] ) === false ) {
			$age_header            = (int) $headers['Age']; // a number from 0 to infinity.
			$return['Age Headers'] = array(
				// translators: %s is a number indicating how many seconds old the content is.
				'message' => sprintf( __( 'The "Age" header is returning %s, which means it is not properly caching. Either this URL is intentionally excluded from caching, or a theme or plugin is instructing WordPress not to cache.', 'varnish-http-purge' ), $age_header ),
				'icon'    => 'warning',
			);
		} elseif ( (bool) strtotime( $headers['Age'] ) && time() <= strtotime( $headers['Age'] ) ) {
			$return['Age Headers'] = array(
				'icon'    => 'bad',
				'message' => __( 'The "Age" header is set to an invalid time, which will result in incorrect caching.', 'varnish-http-purge' ),
			);
		} else {
			$return['Age Headers'] = array(
				'icon'    => 'awesome',
				'message' => __( 'Your site is returning proper "Age" headers.', 'varnish-http-purge' ),
			);
		}

		// Pragma.
		if ( isset( $headers['Pragma'] ) && strpos( $headers['Pragma'], 'no-cache' ) !== false ) {
			$return['Pragma Headers'] = array(
				'icon'    => 'bad',
				'message' => __( 'The header Pragma is set to to "no-cache" which means visitors will never get cached content.', 'varnish-http-purge' ),
			);
		}

		// X-Cache.
		if ( isset( $headers['X-Cache-Status'] ) && strpos( $headers['X-Cache-Status'], 'MISS' ) !== false ) {
			$return['X-Cache Satus'] = array(
				'icon'    => 'bad',
				'message' => __( 'X-Cache missed, which means your site was not able to serve this page as cached.', 'varnish-http-purge' ),
			);
		}

		// Mod-PageSpeed.
		if ( isset( $headers['X-Mod-Pagespeed'] ) ) {
			if ( strpos( $headers['X-Cacheable'], 'YES:Forced' ) !== false ) {
				$return['Mod Pagespeed'] = array(
					'icon'    => 'good',
					'message' => __( 'Mod Pagespeed is active and configured to work properly with caching services.', 'varnish-http-purge' ),
				);
			} else {
				$return['Mod Pagespeed'] = array(
					'icon'    => 'bad',
					'message' => __( 'Mod Pagespeed is active but your caching headers may not be right. This may be a false negative if other parts of your site are overwriting headers. Fix all other errors listed, then come back to this. If you are still having errors, you will need to look into using .htaccess or Nginx to override the Pagespeed headers.', 'varnish-http-purge' ),
				);
			}
		}

		return $return;
	}

	/**
	 * Bad Themes
	 *
	 * Themes known to be problematic
	 *
	 * @since 4.5.0
	 *
	 * @access public
	 * @static
	 * @return array
	 */
	public static function bad_themes_results() {

		$return  = array();
		$request = wp_remote_get( 'https://varnish-http-purge.objects-us-east-1.dream.io/themes.json' );

		if ( is_wp_error( $request ) ) {
			if ( WP_DEBUG ) {
				$return['Theme Check'] = array(
					'icon'    => 'warning',
					'message' => __( 'Error: Theme data cannot be loaded.', 'varnish-http-purge' ),
				);
			}
			return $return; // Bail early.
		}

		$body   = wp_remote_retrieve_body( $request );
		$themes = json_decode( $body );

		if ( empty( $themes ) ) {
			if ( WP_DEBUG ) {
				$return['Theme Check'] = array(
					'icon'    => 'warning',
					'message' => __( 'Error: Theme data was empty.', 'varnish-http-purge' ),
				);
			}
			return $return; // Bail early.
		}

		// Check all the themes. If one of the questionable ones are active, warn.
		foreach ( $themes as $theme => $info ) {
			$my_theme = wp_get_theme( $theme );
			if ( $my_theme->exists() ) {
				$active  = ( get_template() === $theme ) ? true : false;
				$message = $info->message . ' (';
				$warning = $info->type;

				if ( $active ) {
					$message .= __( 'Active', 'varnish-http-purge' );
				} else {
					$message .= __( 'Inactive', 'varnish-http-purge' );
					$warning  = 'notice';
				}
				$message .= ')';

				$return[ 'Theme: ' . ucfirst( $theme ) ] = array(
					'icon'    => $warning,
					'message' => $message,
				);
			}
		}

		// If no questionable themes are found, let the user know with a success message.
		if ( empty( $return ) ) {
			$return['Theme Check'] = array(
				'icon'    => 'good',
				'message' => __( 'No installed themes were found on the known conflicts list.', 'varnish-http-purge' ),
			);
		}

		return $return;
	}

	/**
	 * Bad Plugins
	 *
	 * Plugins known to be problematic
	 *
	 * @since 4.5.0
	 *
	 * @access public
	 * @static
	 * @return array
	 */
	public static function bad_plugins_results() {

		$return   = array();
		$messages = array(
			'incompatible' => __( 'This plugin has unexpected results with caching, making not function properly.', 'varnish-http-purge' ),
			'translation'  => __( 'Translation plugins that use cookies and/or sessions prevent most server side caching from running properly.', 'varnish-http-purge' ),
			'sessions'     => __( 'This plugin uses sessions, which conflicts with server side caching.', 'varnish-http-purge' ),
			'cookies'      => __( 'This plugin uses cookies, which may prevent server side caching.', 'varnish-http-purge' ),
			'cache'        => __( 'This type of caching plugin does not work well with server side caching.', 'varnish-http-purge' ),
			'ancient'      => __( 'This plugin is not up to date with WordPress best practices and breaks caching.', 'varnish-http-purge' ),
			'removed'      => __( 'This plugin was removed from WordPress.org and we do not recommend it\'s use.', 'varnish-http-purge' ),
			'maybe'        => __( 'This plugin is usually fine, but may be configured in a way that breaks caching. Please resolve all other errors. If this is the only one left, and caching is running, you may safely ignore this message.', 'varnish-http-purge' ),
		);

		$request = wp_remote_get( 'https://varnish-http-purge.objects-us-east-1.dream.io/plugins.json' );
		if ( is_wp_error( $request ) ) {
			if ( WP_DEBUG ) {
				$return['Plugin Check'] = array(
					'icon'    => 'warning',
					'message' => __( 'Error: Plugin data cannot be loaded.', 'varnish-http-purge' ),
				);
			}
			return $return; // Bail early.
		}

		$body    = wp_remote_retrieve_body( $request );
		$plugins = json_decode( $body );

		if ( empty( $plugins ) ) {
			if ( WP_DEBUG ) {
				$return['Plugin Check'] = array(
					'icon'    => 'warning',
					'message' => __( 'Error: Plugin data was empty.', 'varnish-http-purge' ),
				);
			}
			return $return; // Bail early.
		}

		// Check all the plugins. If one of the questionable ones are active, warn.
		foreach ( $plugins as $plugin => $info ) {
			if ( file_exists( plugin_dir_path( __DIR__ ) . $info->path ) ) {
				$message = $messages[ $info->reason ];
				$warning = 'notice';
				$active  = __( 'Inactive', 'varnish-http-purge' );

				// If the plugin is inactive, change the warning.
				if ( is_plugin_active( $info->path ) ) {
					$warning = $info->type;
					$active  = __( 'Active', 'varnish-http-purge' );
				}

				$return[ 'Plugin: ' . ucfirst( $plugin ) ] = array(
					'icon'    => $warning,
					'message' => $message . ' (' . $active . ')',
				);
			}
		}

		// If no questionable plugins are found, let the user know with a success message.
		if ( empty( $return ) ) {
			$return['Plugin Check'] = array(
				'icon'    => 'good',
				'message' => __( 'No installed plugins were found on the known conflicts list.', 'varnish-http-purge' ),
			);
		}

		return $return;
	}

	/**
	 * Get all the results
	 *
	 * Collect everything, get all the data spit it out.
	 *
	 * @since 4.4.0
	 *
	 * @access public
	 * @static
	 * @param mixed $headers - results from wp_remote_get.
	 * @param mixed $remote_ip - IP address detected.
	 * @param mixed $varniship - IP address defined.
	 * @return array
	 */
	public static function get_all_the_results( $headers, $remote_ip, $varniship ) {
		$output = array();

		// Preface with Debugging Warning.
		if ( self::devmode_check() ) {
			$output['Development Mode'] = array(
				'icon'    => 'notice',
				'message' => __( 'NOTICE: Caching is disabled while Development Mode is active.', 'varnish-http-purge' ),
			);
		}

		// Basic Checks.
		$output['Cache Service'] = self::varnish_results( $headers );
		$output['Remote IP']     = self::remote_ip_results( $remote_ip, $varniship, $headers );

		// Server Results.
		$server_results = self::server_results( $headers, $remote_ip, $varniship );
		$output         = array_merge( $output, $server_results );

		// Cache Results.
		$cache_results = self::cache_results( $headers );
		$output        = array_merge( $output, $cache_results );

		// Cookies.
		$cookie_results = self::cookie_results( $headers );
		$output         = array_merge( $output, $cookie_results );

		// Plugins that don't play nicely with Varnish.
		$bad_plugins_results = self::bad_plugins_results();
		$output              = array_merge( $output, $bad_plugins_results );

		// Themes that don't play nicely with Varnish.
		$bad_themes_results = self::bad_themes_results();
		$output             = array_merge( $output, $bad_themes_results );

		return $output;
	}
}

$varnish_debug = new VarnishDebug();
