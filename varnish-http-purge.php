<?php
/**
 * Plugin Name: Proxy Cache Purge
 * Plugin URI: https://halfelf.org/plugins/varnish-http-purge/
 * Description: Automatically empty cached pages when content on your site is modified.
 * Version: 4.7.3
 * Author: Mika Epstein
 * Author URI: https://halfelf.org/
 * License: http://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: varnish-http-purge
 * Network: true
 *
 * @package varnish-http-purge
 *
 * Copyright 2016-2018 Mika Epstein (email: ipstenu@halfelf.org)
 *
 * This file is part of Proxy Cache Purge, a plugin for WordPress.
 *
 * Proxy Cache Purge is free software: you can redistribute it and/or modify
 * it under the terms of the Apache License 2.0 license.
 *
 * Proxy Cache Purge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Purge Class
 *
 * @since 2.0
 */
class VarnishPurger {

	/**
	 * Version Number
	 * @var string
	 */
	public static $version = '4.7.2';

	/**
	 * List of URLs to be purged
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access protected
	 */
	protected $purge_urls = array();

	/**
	 * Devmode options
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access public
	 * @static
	 */
	public static $devmode = array();

	/**
	 * Init
	 *
	 * @since 2.0
	 * @access public
	 */
	public function __construct() {
		defined( 'VHP_VARNISH_IP' ) || define( 'VHP_VARNISH_IP', false );
		defined( 'VHP_DEVMODE' ) || define( 'VHP_DEVMODE', false );

		// Development mode defaults to off.
		self::$devmode = array(
			'active' => false,
			'expire' => current_time( 'timestamp' ),
		);
		if ( ! get_site_option( 'vhp_varnish_devmode' ) ) {
			update_site_option( 'vhp_varnish_devmode', self::$devmode );
		}

		// Default URL is home.
		if ( ! get_site_option( 'vhp_varnish_url' ) ) {
			update_site_option( 'vhp_varnish_url', $this->the_home_url() );
		}

		// Default IP is nothing.
		if ( ! get_site_option( 'vhp_varnish_ip' ) && ! VHP_VARNISH_IP ) {
			update_site_option( 'vhp_varnish_ip', '' );
		}

		// Release the hounds!
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );

	}

	/**
	 * Admin Init
	 *
	 * @since 4.1
	 * @access public
	 */
	public function admin_init() {

		// If WordPress.com Master Bar is active, show the activity box.
		if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'masterbar' ) ) {
			add_action( 'activity_box_end', array( $this, 'varnish_rightnow' ), 100 );
		}

		// Failure: Pre WP 4.7.
		if ( version_compare( get_bloginfo( 'version' ), '4.7', '<=' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			add_action( 'admin_notices', array( $this, 'require_wp_version_notice' ) );
			return;
		}

		// Admin notices.
		if ( current_user_can( 'manage_options' ) ) {

			// Warning: Debug is active.
			if ( VarnishDebug::devmode_check() ) {
				add_action( 'admin_notices', array( $this, 'devmode_is_active_notice' ) );
			}

			// Warning: No Pretty Permalinks!
			if ( '' === get_site_option( 'permalink_structure' ) ) {
				add_action( 'admin_notices', array( $this, 'require_pretty_permalinks_notice' ) );
			}
		}
	}

	/**
	 * Plugin Init
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init() {
		global $blog_id;

		// If Dev Mode is true, kill caching.
		if ( VarnishDebug::devmode_check() ) {
			if ( ! is_admin() ) {
				// Sessions to break PHP caching.
				if ( ! is_user_logged_in() ) {
					// @codingStandardsIgnoreStart
					@session_start();
					// @codingStandardsIgnoreEnd
				}

				// Add nocacche to CSS and JS.
				add_filter( 'style_loader_src', array( 'VarnishDebug', 'nocache_cssjs' ), 10, 2 );
				add_filter( 'script_loader_src', array( 'VarnishDebug', 'nocache_cssjs' ), 10, 2 );
			}
		}

		// get my events.
		$events       = $this->get_register_events();
		$no_id_events = $this->get_no_id_events();

		// make sure we have events and they're in an array.
		if ( ! empty( $events ) && ! empty( $no_id_events ) ) {

			// Force it to be an array, in case someone's stupid.
			$events       = (array) $events;
			$no_id_events = (array) $no_id_events;

			// Add the action for each event.
			foreach ( $events as $event ) {
				if ( in_array( $event, $no_id_events, true ) ) {
					// These events have no post ID and, thus, will perform a full purge.
					add_action( $event, array( $this, 'execute_purge_no_id' ) );
				} else {
					add_action( $event, array( $this, 'purge_post' ), 10, 2 );
				}
			}
		}

		add_action( 'shutdown', array( $this, 'execute_purge' ) );

		// Success: Admin notice when purging.
		if ( ( isset( $_GET['vhp_flush_all'] ) && check_admin_referer( 'vhp-flush-all' ) ) ||
			( isset( $_GET['vhp_flush_do'] ) && check_admin_referer( 'vhp-flush-do' ) ) ) {
			if ( 'devmode' === $_GET['vhp_flush_do'] ) {
				$toggle = ( VarnishDebug::devmode_check() ) ? 'deactivate' : 'activate';
				VarnishDebug::devmode_toggle( $toggle );
				add_action( 'admin_notices', array( $this, 'admin_message_devmode' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'admin_message_purge' ) );
			}
		}

		// Add Admin Bar.
		add_action( 'admin_bar_menu', array( $this, 'varnish_rightnow_adminbar' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'custom_css' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'custom_css' ) );
	}

	/**
	 * Purge Message
	 * Informs of a succcessful purge
	 *
	 * @since 4.6
	 */
	public function admin_message_purge() {
		echo '<div id="message" class="notice notice-success fade is-dismissible"><p><strong>' . esc_html__( 'Varnish cache emptied!', 'varnish-http-purge' ) . '</strong></p></div>';
	}

	/**
	 * Devmode Message
	 * Informs of a toggle in Devmode
	 *
	 * @since 4.6
	 */
	public function admin_message_devmode() {
		$message = ( VarnishDebug::devmode_check() ) ? __( 'Development Mode activated for the next 24 hours.', 'varnish-http-purge' ) : __( 'Development Mode deactivated.', 'varnish-http-purge' );
		echo '<div id="message" class="notice notice-success fade is-dismissible"><p><strong>' . wp_kses_post( $message ) . '</strong></p></div>';
	}

	/**
	 * Require: Pretty Permalinks Message
	 * Explains you need Pretty Permalinks enabled to use this plugin
	 *
	 * @since 2.0
	 */
	public function require_pretty_permalinks_notice() {
		// translators: The URL should link to the permalinks page.
		echo wp_kses_post( '<div id="message" class="error"><p>' . sprintf( __( 'Proxy Cache Purge requires you to use custom permalinks. Please go to the <a href="%1$s">Permalinks Options Page</a> to configure them.', 'varnish-http-purge' ), esc_url( admin_url( 'options-permalink.php' ) ) ) . '</p></div>' );
	}

	/**
	 * Require: WP Version Message
	 * Explains you need WordPress 4.7+ to use this plugin
	 *
	 * @since 4.1
	 */
	public function require_wp_version_notice() {
		// translators: The URL should link to the update core page.
		echo "<div id='message' class='error'><p>" . sprintf( esc_html__( 'Proxy Cache Purge requires WordPress 4.7 or greater. Please <a href="%1$s">upgrade WordPress</a>.', 'varnish-http-purge' ), esc_url( admin_url( 'update-core.php' ) ) ) . '</p></div>';
	}

	/**
	 * Warning: Development Mode
	 * Checks if DevMode is active
	 *
	 * @since 4.6.0
	 */
	public function devmode_is_active_notice() {
		if ( VHP_DEVMODE ) {
			$message = __( 'Proxy Cache Purge Development Mode has been activated via wp-config.', 'varnish-http-purge' );
		} else {
			$devmode = get_site_option( 'vhp_varnish_devmode', self::$devmode );
			$time    = human_time_diff( current_time( 'timestamp' ), $devmode['expire'] );
			if ( ! is_multisite() ) {
				// translators: %1$s is the time until dev mode expires.
				// translators: %2$s is a link to the settings pages.
				$message = sprintf( __( 'Proxy Cache Purge Development Mode is active for the next %1$s. You can disable this at the <a href="%2$s">Proxy Settings Page</a>.', 'varnish-http-purge' ), $time, esc_url( admin_url( 'admin.php?page=varnish-page' ) ) );
			} else {
				// translators: %1$s is the time until dev mode expires.
				$message = sprintf( __( 'Proxy Cache Purge Development Mode is active for the next %1$s.', 'varnish-http-purge' ), $time );
			}
		}
		echo '<div class="notice notice-warning"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * The Home URL
	 * Get the Home URL and allow it to be filterable
	 * This is for domain mapping plugins that, for some reason, don't filter
	 * on their own (including WPMU, Ron's, and so on).
	 *
	 * @since 4.0
	 */
	public static function the_home_url() {
		$home_url = apply_filters( 'vhp_home_url', home_url() );
		return $home_url;
	}

	/**
	 * Custom CSS to allow for coloring.
	 *
	 * @since 4.5.0
	 */
	public function custom_css() {
		if ( is_user_logged_in() && is_admin_bar_showing() ) {
			wp_register_style( 'varnish_http_purge', plugins_url( 'style.css', __FILE__ ), false, self::$version );
			wp_enqueue_style( 'varnish_http_purge' );
		}
	}

	/**
	 * Purge Button in the Admin Bar
	 *
	 * @access public
	 * @param mixed $admin_bar - data passed back from admin bar.
	 * @return void
	 */
	public function varnish_rightnow_adminbar( $admin_bar ) {
		global $wp;

		$can_purge    = false;
		$cache_active = ( VarnishDebug::devmode_check() ) ? __( 'Inactive', 'varnish-http-purge' ) : __( 'Active', 'varnish-http-purge' );
		// translators: %s is the state of cache.
		$cache_titled = sprintf( __( 'Cache (%s)', 'varnish-http-purge' ), $cache_active );

		if ( ( ! is_admin() && get_post() !== false && current_user_can( 'edit_published_posts' ) ) || current_user_can( 'activate_plugins' ) ) {
			// Main Array.
			$args      = array(
				array(
					'id'    => 'purge-varnish-cache',
					'title' => '<span class="ab-icon" style="background-image: url(' . self::get_icon_svg() . ') !important;"></span><span class="ab-label">' . $cache_titled . '</span>',
					'meta'  => array(
						'class' => 'varnish-http-purge',
					),
				),
			);
			$can_purge = true;
		}

		// Checking user permissions for who can and cannot use the all flush.
		if (
			// SingleSite - admins can always purge.
			( ! is_multisite() && current_user_can( 'activate_plugins' ) ) ||
			// Multisite - Network Admin can always purge.
			current_user_can( 'manage_network' ) ||
			// Multisite - Site admins can purge UNLESS it's a subfolder install and we're on site #1.
			( is_multisite() && current_user_can( 'activate_plugins' ) && ( SUBDOMAIN_INSTALL || ( ! SUBDOMAIN_INSTALL && ( BLOG_ID_CURRENT_SITE !== $blog_id ) ) ) )
			) {

			$args[] = array(
				'parent' => 'purge-varnish-cache',
				'id'     => 'purge-varnish-cache-all',
				'title'  => __( 'Purge Cache (All Pages)', 'varnish-http-purge' ),
				'href'   => wp_nonce_url( add_query_arg( 'vhp_flush_do', 'all' ), 'vhp-flush-do' ),
				'meta'   => array(
					'title' => __( 'Purge Cache (All Pages)', 'varnish-http-purge' ),
				),
			);

			// If a memcached file is found, we can do this too.
			if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
				$args[] = array(
					'parent' => 'purge-varnish-cache',
					'id'     => 'purge-varnish-cache-db',
					'title'  => __( 'Purge Database Cache', 'varnish-http-purge' ),
					'href'   => wp_nonce_url( add_query_arg( 'vhp_flush_do', 'object' ), 'vhp-flush-do' ),
					'meta'   => array(
						'title' => __( 'Purge Database Cache', 'varnish-http-purge' ),
					),
				);
			}

			// If we're on a front end page and the current user can edit published posts, then they can do this.
			if ( ! is_admin() && get_post() !== false && current_user_can( 'edit_published_posts' ) ) {
				$page_url = esc_url( home_url( $wp->request ) );
				$args[]   = array(
					'parent' => 'purge-varnish-cache',
					'id'     => 'purge-varnish-cache-this',
					'title'  => __( 'Purge Cache (This Page)', 'varnish-http-purge' ),
					'href'   => wp_nonce_url( add_query_arg( 'vhp_flush_do', $page_url . '/' ), 'vhp-flush-do' ),
					'meta'   => array(
						'title' => __( 'Purge Cache (This Page)', 'varnish-http-purge' ),
					),
				);
			}

			// Populate enable/disable cache button.
			$purge_devmode_title = ( VarnishDebug::devmode_check() ) ? __( 'Restart Cache', 'varnish-http-purge' ) : __( 'Pause Cache (24h)', 'varnish-http-purge' );
			$args[]              = array(
				'parent' => 'purge-varnish-cache',
				'id'     => 'purge-varnish-cache-devmode',
				'title'  => $purge_devmode_title,
				'href'   => wp_nonce_url( add_query_arg( 'vhp_flush_do', 'devmode' ), 'vhp-flush-do' ),
				'meta'   => array(
					'title' => $purge_devmode_title,
				),
			);
		}

		if ( $can_purge ) {
			foreach ( $args as $arg ) {
				$admin_bar->add_node( $arg );
			}
		}
	}

	/**
	 * Get the icon as SVG.
	 *
	 * Forked from Yoast SEO
	 *
	 * @access public
	 * @param bool $base64 (default: true) - Use SVG, true/false?
	 * @param string $icon_color - What color to use.
	 * @return string
	 */
	public static function get_icon_svg( $base64 = true, $icon_color = false ) {
		global $_wp_admin_css_colors;

		$fill = ( false !== $icon_color ) ? sanitize_hex_color( $icon_color ) : '#82878c';

		if ( is_admin() && false === $icon_color ) {
			$admin_colors  = json_decode( wp_json_encode( $_wp_admin_css_colors ), true );
			$current_color = get_user_option( 'admin_color' );
			$fill          = $admin_colors[ $current_color ]['icon_colors']['base'];
		}

		// Flat
		$svg = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="100%" height="100%" style="fill:' . $fill . '" viewBox="0 0 36.2 34.39" role="img" aria-hidden="true" focusable="false"><g id="Layer_2" data-name="Layer 2"><g id="Layer_1-2" data-name="Layer 1"><path fill="' . $fill . '" d="M24.41,0H4L0,18.39H12.16v2a2,2,0,0,0,4.08,0v-2H24.1a8.8,8.8,0,0,1,4.09-1Z"/><path fill="' . $fill . '" d="M21.5,20.4H18.24a4,4,0,0,1-8.08,0v0H.2v8.68H19.61a9.15,9.15,0,0,1-.41-2.68A9,9,0,0,1,21.5,20.4Z"/><path fill="' . $fill . '" d="M28.7,33.85a7,7,0,1,1,7-7A7,7,0,0,1,28.7,33.85Zm-1.61-5.36h5V25.28H30.31v-3H27.09Z"/><path fill="' . $fill . '" d="M28.7,20.46a6.43,6.43,0,1,1-6.43,6.43,6.43,6.43,0,0,1,6.43-6.43M26.56,29h6.09V24.74H30.84V21.8H26.56V29m2.14-9.64a7.5,7.5,0,1,0,7.5,7.5,7.51,7.51,0,0,0-7.5-7.5ZM27.63,28V22.87h2.14v2.95h1.81V28Z"/></g></g></svg>';

		if ( $base64 ) {
			return 'data:image/svg+xml;base64,' . base64_encode( $svg );
		}

		return $svg;
	}

	/**
	 * Varnish Right Now Information
	 * This information is put on the Dashboard 'Right now' widget
	 *
	 * @since 1.0
	 */
	public function varnish_rightnow() {
		global $blog_id;
		// translators: %1$s links to the plugin's page on WordPress.org.
		$intro    = sprintf( __( '<a href="%1$s">Proxy Cache Purge</a> automatically deletes your cached posts when published or updated. When making major site changes, such as with a new theme, plugins, or widgets, you may need to manually empty the cache.', 'varnish-http-purge' ), 'http://wordpress.org/plugins/varnish-http-purge/' );
		$url      = wp_nonce_url( add_query_arg( 'vhp_flush_do', 'all' ), 'vhp-flush-do' );
		$button   = __( 'Press the button below to force it to empty your entire Varnish cache.', 'varnish-http-purge' );
		$button  .= '</p><p><span class="button"><strong><a href="' . $url . '">';
		$button  .= __( 'Empty Cache', 'varnish-http-purge' );
		$button  .= '</a></strong></span>';
		$nobutton = __( 'You do not have permission to empty the proxy cache for the whole site. Please contact your administrator.', 'varnish-http-purge' );
		if (
			// SingleSite - admins can always purge.
			( ! is_multisite() && current_user_can( 'activate_plugins' ) ) ||
			// Multisite - Network Admin can always purge.
			current_user_can( 'manage_network' ) ||
			// Multisite - Site admins can purge UNLESS it's a subfolder install and we're on site #1.
			( is_multisite() && current_user_can( 'activate_plugins' ) && ( SUBDOMAIN_INSTALL || ( ! SUBDOMAIN_INSTALL && ( BLOG_ID_CURRENT_SITE !== $blog_id ) ) ) )
		) {
			$text = $intro . ' ' . $button;
		} else {
			$text = $intro . ' ' . $nobutton;
		}
		// @codingStandardsIgnoreStart
		// This is safe to echo as it's controlled and secured above.
		// Using wp_kses will delete the icon.
		echo '<p class="varnish-rightnow">' . $text . '</p>';
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Registered Events
	 * These are when the purge is triggered
	 *
	 * @since 1.0
	 * @access protected
	 */
	protected function get_register_events() {

		// Define registered purge events.
		$actions = array(
			'switch_theme',                    // After a theme is changed.
			'autoptimize_action_cachepurged',  // Compat with https://wordpress.org/plugins/autoptimize/ plugin.
			'save_post',                       // Save a post.
			'deleted_post',                    // Delete a post.
			'trashed_post',                    // Empty Trashed post.
			'edit_post',                       // Edit a post - includes leaving comments.
			'delete_attachment',               // Delete an attachment - includes re-uploading.
		);

		// send back the actions array, filtered.
		// @param array $actions the actions that trigger the purge event.
		return apply_filters( 'varnish_http_purge_events', $actions );
	}

	/**
	 * Events that have no post IDs
	 * These are when a full purge is triggered
	 *
	 * @since 3.9
	 * @access protected
	 */
	protected function get_no_id_events() {

		// Define registered purge events.
		$actions = array(
			'switch_theme',                     // After a theme is changed.
			'autoptimize_action_cachepurged,',  // Compat with https://wordpress.org/plugins/autoptimize/ plugin.
		);

		/**
		 * Send back the actions array, filtered
		 *
		 * @param array $actions the actions that trigger the purge event
		 *
		 * DEVELOPERS! USE THIS SPARINGLY! YOU'RE A GREAT BIG 💩 IF YOU USE IT FLAGRANTLY
		 * Remember to add your action to this AND varnish_http_purge_events due to shenanigans
		 */
		return apply_filters( 'varnish_http_purge_events_full', $actions );
	}

	/**
	 * Execute Purge
	 * Run the purge command for the URLs. Calls $this->purge_url for each URL
	 *
	 * @since 1.0
	 * @access protected
	 */
	public function execute_purge() {
		$purge_urls = array_unique( $this->purge_urls );

		if ( empty( $purge_urls ) && isset( $_GET ) ) {
			if ( isset( $_GET['vhp_flush_all'] ) && check_admin_referer( 'vhp-flush-all' ) ) {
				// Flush Cache recursize.
				$this->purge_url( $this->the_home_url() . '/?vhp-regex' );
			} elseif ( isset( $_GET['vhp_flush_do'] ) && check_admin_referer( 'vhp-flush-do' ) ) {
				if ( 'object' === $_GET['vhp_flush_do'] ) {
					// Flush Object Cache (with a double check).
					if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
						wp_cache_flush();
					}
				} elseif ( 'all' === $_GET['vhp_flush_do'] ) {
					// Flush Cache recursize.
					$this->purge_url( $this->the_home_url() . '/?vhp-regex' );
				} else {
					// Flush the URL we're on.
					$p = wp_parse_url( esc_url_raw( wp_unslash( $_GET['vhp_flush_do'] ) ) );
					if ( ! isset( $p['host'] ) ) {
						return;
					}
					$this->purge_url( esc_url_raw( wp_unslash( $_GET['vhp_flush_do'] ) ) );
				}
			}
		} else {
			foreach ( $purge_urls as $url ) {
				$this->purge_url( $url );
			}
		}
	}

	/**
	 * Purge URL
	 * Parse the URL for proxy proxies
	 *
	 * @since 1.0
	 * @param array $url - The url to be purged.
	 * @access protected
	 */
	public static function purge_url( $url ) {
		$p = wp_parse_url( $url );

		// Bail early if there's no host since some plugins are weird.
		if ( ! isset( $p['host'] ) ) {
			return;
		}

		// Determine if we're using regex to flush all pages or not.
		$pregex         = '';
		$x_purge_method = 'default';

		if ( isset( $p['query'] ) && ( 'vhp-regex' === $p['query'] ) ) {
			$pregex         = '.*';
			$x_purge_method = 'regex';
		}

		// Build a varniship.
		if ( VHP_VARNISH_IP !== false ) {
			$varniship = VHP_VARNISH_IP;
		} else {
			$varniship = get_site_option( 'vhp_varnish_ip' );
		}
		$varniship = apply_filters( 'vhp_varnish_ip', $varniship );

		// Determine the path.
		$path = '';
		if ( isset( $p['path'] ) ) {
			$path = $p['path'];
		}

		/**
		 * Schema filter
		 *
		 * Allows default http:// schema to be changed to https
		 * varnish_http_purge_schema()
		 *
		 * @since 3.7.3
		 */
		$schema = apply_filters( 'varnish_http_purge_schema', 'http://' );

		// If we made varniship, let it sail.
		if ( isset( $varniship ) && ! empty( $varniship ) ) {
			$host = $varniship;
		} else {
			$host = $p['host'];
		}

		/**
		 * Allow setting of ports in host name
		 * Credit: davidbarratt - https://github.com/Ipstenu/varnish-http-purge/pull/38/
		 *
		 * (default value: $p['host'])
		 *
		 * @var string
		 * @access public
		 * @since 4.4.0
		 */
		$host_headers = $p['host'];
		if ( isset( $p['port'] ) ) {
			$host_headers .= ':' . $p['port'];
		}

		$parsed_url = $url;
		// Filter URL based on the Proxy IP for nginx compatibility
		if ( 'localhost' === $varniship ) {
			$parsed_url = str_replace( $p['host'], 'localhost', $parsed_url );
		}

		// Create path to purge.
		$purgeme = $schema . $host . $path . $pregex;

		// Check the queries...
		if ( ! empty( $p['query'] ) && 'vhp-regex' !== $p['query'] ) {
			$purgeme .= '?' . $p['query'];
		}

		/**
		 * Filters the HTTP headers to send with a PURGE request.
		 *
		 * @since 4.1
		 */
		$headers  = apply_filters( 'varnish_http_purge_headers', array(
			'host'           => $host_headers,
			'X-Purge-Method' => $x_purge_method,
		) );
		$response = wp_remote_request( $purgeme, array(
			'method'  => 'PURGE',
			'headers' => $headers,
		) );

		do_action( 'after_purge_url', $parsed_url, $purgeme, $response, $headers );
	}

	/**
	 * Purge - No IDs
	 * Flush the whole cache
	 *
	 * @access public
	 * @param mixed $post_id - the post ID that triggered this (we don't use it yet).
	 * @return void
	 * @since 3.9
	 */
	public function execute_purge_no_id( $post_id ) {
		$listofurls = array();

		array_push( $listofurls, $this->the_home_url() . '/?vhp-regex' );

		// Now flush all the URLs we've collected provided the array isn't empty.
		if ( ! empty( $listofurls ) ) {
			foreach ( $listofurls as $url ) {
				array_push( $this->purge_urls, $url );
			}
		}

		do_action( 'after_full_purge' );
	}

	/**
	 * Generate URLs
	 *
	 * Generates a list of URLs that should be purged, based on the post ID
	 * passed through. Useful for when you're trying to get a post to flush
	 * another post.
	 *
	 * @access public
	 * @param mixed $post_id - The ID of the post to be purged.
	 * @return array()
	 */
	public function generate_urls( $post_id ) {
		$this->purge_post( $post_id );
		return $this->purge_urls;
	}

	/**
	 * Purge Post
	 * Flush the post
	 *
	 * @since 1.0
	 * @param array $post_id - The ID of the post to be purged.
	 * @access public
	 */
	public function purge_post( $post_id ) {

		/**
		 * Future Me: You may need this if you figure out how to use an array
		 * further down with versions of WP and their json versions.
		 * Maybe use global $wp_version;
		 * If this is a valid post we want to purge the post,
		 * the home page and any associated tags and categories
		 */
		$valid_post_status = array( 'publish', 'private', 'trash' );
		$this_post_status  = get_post_status( $post_id );

		// Not all post types are created equal.
		$invalid_post_type   = array( 'nav_menu_item', 'revision' );
		$noarchive_post_type = array( 'post', 'page' );
		$this_post_type      = get_post_type( $post_id );

		/**
		 * Determine the route for the rest API
		 * This will need to be revisted if WP updates the version.
		 * Future me: Consider an array? 4.7-?? use v2, and then adapt from there?
		 */
		if ( version_compare( get_bloginfo( 'version' ), '4.7', '>=' ) ) {
			$rest_api_route = 'wp/v2';
		}

		// array to collect all our URLs.
		$listofurls = array();

		// Verify we have a permalink and that we're a valid post status and a not an invalid post type.
		if ( false !== get_permalink( $post_id ) && in_array( $this_post_status, $valid_post_status, true ) && ! in_array( $this_post_type, $invalid_post_type, true ) ) {

			// Post URL.
			array_push( $listofurls, get_permalink( $post_id ) );

			/**
			 * JSON API Permalink for the post based on type
			 * We only want to do this if the rest_base exists
			 * But we apparently have to force it for posts and pages (seriously?)
			 */
			if ( isset( $rest_api_route ) ) {
				$post_type_object = get_post_type_object( $post_id );
				$rest_permalink   = false;
				if ( isset( $post_type_object->rest_base ) ) {
					$rest_permalink = get_rest_url() . $rest_api_route . '/' . $post_type_object->rest_base . '/' . $post_id . '/';
				} elseif ( 'post' === $this_post_type ) {
					$rest_permalink = get_rest_url() . $rest_api_route . '/posts/' . $post_id . '/';
				} elseif ( 'page' === $this_post_type ) {
					$rest_permalink = get_rest_url() . $rest_api_route . '/pages/' . $post_id . '/';
				}
			}

			if ( $rest_permalink ) {
				array_push( $listofurls, $rest_permalink );
			}

			// Add in AMP permalink for offical WP AMP plugin:
			// https://wordpress.org/plugins/amp/
			if ( function_exists( 'amp_get_permalink' ) ) {
				array_push( $listofurls, amp_get_permalink( $post_id ) );
			}

			// Regular AMP url for posts if ant of the following are active:
			// https://wordpress.org/plugins/accelerated-mobile-pages/
			if ( defined( 'AMPFORWP_AMP_QUERY_VAR' ) ) {
				array_push( $listofurls, get_permalink( $post_id ) . 'amp/' );
			}

			// Also clean URL for trashed post.
			if ( 'trash' === $this_post_status ) {
				$trashpost = get_permalink( $post_id );
				$trashpost = str_replace( '__trashed', '', $trashpost );
				array_push( $listofurls, $trashpost, $trashpost . 'feed/' );
			}

			// Category purge based on Donnacha's work in WP Super Cache.
			$categories = get_the_category( $post_id );
			if ( $categories ) {
				foreach ( $categories as $cat ) {
					array_push( $listofurls,
						get_category_link( $cat->term_id ),
						get_rest_url() . $rest_api_route . '/categories/' . $cat->term_id . '/'
					);
				}
			}

			// Tag purge based on Donnacha's work in WP Super Cache.
			$tags = get_the_tags( $post_id );
			if ( $tags ) {
				$tag_base = get_site_option( 'tag_base' );
				if ( '' === $tag_base ) {
					$tag_base = '/tag/';
				}
				foreach ( $tags as $tag ) {
					array_push( $listofurls,
						get_tag_link( $tag->term_id ),
						get_rest_url() . $rest_api_route . $tag_base . $tag->term_id . '/'
					);
				}
			}
			// Custom Taxonomies: Only show if the taxonomy is public.
			$taxonomies = get_post_taxonomies( $post_id );
			if ( $taxonomies ) {
				foreach ( $taxonomies as $taxonomy ) {
					$features = (array) get_taxonomy( $taxonomy );
					if ( $features['public'] ) {
						$terms = wp_get_post_terms( $post_id, $taxonomy );
						foreach ( $terms as $term ) {
							array_push( $listofurls,
								get_term_link( $term ),
								get_rest_url() . $rest_api_route . '/' . $term->taxonomy . '/' . $term->slug . '/'
							);
						}
					}
				}
			}

			// If the post is a post, we have more things to flush
			// Pages and Woo Things don't need all this.
			if ( $this_post_type && 'post' === $this_post_type ) {
				// Author URLs:
				$author_id = get_post_field( 'post_author', $post_id );
				array_push( $listofurls,
					get_author_posts_url( $author_id ),
					get_author_feed_link( $author_id ),
					get_rest_url() . $rest_api_route . '/users/' . $author_id . '/'
				);

				// Feeds:
				array_push( $listofurls,
					get_bloginfo_rss( 'rdf_url' ),
					get_bloginfo_rss( 'rss_url' ),
					get_bloginfo_rss( 'rss2_url' ),
					get_bloginfo_rss( 'atom_url' ),
					get_bloginfo_rss( 'comments_rss2_url' ),
					get_post_comments_feed_link( $post_id )
				);
			}

			// Archives and their feeds.
			if ( $this_post_type && ! in_array( $this_post_type, $noarchive_post_type, true ) ) {
				array_push( $listofurls,
					get_post_type_archive_link( get_post_type( $post_id ) ),
					get_post_type_archive_feed_link( get_post_type( $post_id ) )
					// Need to add in JSON?
				);
			}

			// Home Pages and (if used) posts page.
			array_push( $listofurls,
				get_rest_url(),
				$this->the_home_url() . '/'
			);
			if ( 'page' === get_site_option( 'show_on_front' ) ) {
				// Ensure we have a page_for_posts setting to avoid empty URL.
				if ( get_site_option( 'page_for_posts' ) ) {
					array_push( $listofurls, get_permalink( get_site_option( 'page_for_posts' ) ) );
				}
			}
		} else {
			// We're not sure how we got here, but bail instead of processing anything else.
			return;
		}

		// If the array isn't empty, proceed.
		if ( ! empty( $listofurls ) ) {
			// Strip off query variables
			foreach ( $listofurls as $url ) {
				$url = strtok( $url, '?' );
			}

			// Make sure each URL only gets purged once, eh?
			$purgeurls = array_unique( $listofurls, SORT_REGULAR );

			// Flush all the URLs
			foreach ( $purgeurls as $url ) {
				array_push( $this->purge_urls, $url );
			}
		}

		/*
		 * Filter to add or remove urls to the array of purged urls
		 * @param array $purge_urls the urls (paths) to be purged
		 * @param int $post_id the id of the new/edited post
		 */
		$this->purge_urls = apply_filters( 'vhp_purge_urls', $this->purge_urls, $post_id );
	}

	// @codingStandardsIgnoreStart
	/*
	 * These have all been name changed to proper names, but just in case...
	 */
	public function getRegisterEvents() {
		self::get_register_events();
	}
	public function getNoIDEvents() {
		self::get_no_id_events();
	}
	public function executePurge() {
		self::execute_purge();
	}
	public function purgeNoID( $post_id ) {
		self::execute_purge_no_id( $post_id );
	}
	public function purgeURL( $url ) {
		self::purge_url( $url );
	}
	public function purgePost( $post_id ) {
		self::purge_post( $post_id );
	}
	// @codingStandardsIgnoreEnd

}

/**
 * Purge via WP-CLI
 *
 * @since 3.8
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include 'wp-cli.php';
}

/*
 * Settings Pages
 *
 * @since 4.0
 */
// The settings PAGES aren't needed on the network admin page
if ( ! is_network_admin() ) {
	require_once 'settings.php';
}
require_once 'debug.php';

$purger = new VarnishPurger();
