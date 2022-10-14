<?php
/**
 * Plugin Name: Proxy Cache Purge
 * Plugin URI: https://github.com/ipstenu/varnish-http-purge/
 * Description: Automatically empty cached pages when content on your site is modified.
 * Version: 5.1.3
 * Author: Mika Epstein
 * Author URI: https://halfelf.org/
 * License: http://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: varnish-http-purge
 * Network: true
 *
 * @package varnish-http-purge
 *
 * Copyright 2016-2022 Mika Epstein (email: ipstenu@halfelf.org)
 *
 * This file is part of Proxy Cache Purge (formerly Varnish HTTP Purge), a
 * plugin for WordPress.
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
	public static $version = '6.x';

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

	public static $purge_strategy;

	/**
	 * Init
	 *
	 * @since 2.0
	 * @access public
	 */
	public function __construct() {
		defined( 'VHP_VARNISH_IP' ) || define( 'VHP_VARNISH_IP', false );
		defined( 'VHP_DEVMODE' ) || define( 'VHP_DEVMODE', false );
		defined( 'VHP_DOMAINS' ) || define( 'VHP_DOMAINS', false );
		defined( 'VHP_STRATEGY' ) || define( 'VHP_STRATEGY', false );

		if ( defined( 'VHP_STRATEGY' ) && ! empty(VHP_STRATEGY) ) {
			$purge_strategy = VHP_STRATEGY;
		} else {
			require_once __DIR__ . '/purge-strategy.php';
			$purge_strategy = VarnishPurgeStrategy::class;
		}

		/**
		 * Filter the strategy class.
		 */
		$purge_strategy = apply_filters( 'vhp_purge_strategy_class', $purge_strategy );
		self::$purge_strategy = new $purge_strategy($this);

		// Development mode defaults to off.
		self::$devmode = array(
			'active' => false,
			'expire' => time(),
		);
		if ( ! get_site_option( 'vhp_varnish_devmode' ) ) {
			update_site_option( 'vhp_varnish_devmode', self::$devmode );
		}

		// Default URL is home.
		if ( ! get_site_option( 'vhp_varnish_url' ) ) {
			update_site_option( 'vhp_varnish_url', self::$purge_strategy->the_home_url() );
		}

		// Default IP is nothing.
		if ( ! get_site_option( 'vhp_varnish_ip' ) && ! VHP_VARNISH_IP ) {
			update_site_option( 'vhp_varnish_ip', '' );
		}

		// Default Debug is the home.
		if ( ! get_site_option( 'vhp_varnish_debug' ) ) {
			update_site_option( 'vhp_varnish_debug', array( self::$purge_strategy->the_home_url() => array() ) );
		}

		// Default Max posts to purge before purge all happens instead.
		if ( ! get_site_option( 'vhp_varnish_max_posts_before_all' ) ) {
			update_site_option( 'vhp_varnish_max_posts_before_all', 50 );
		}

		// Release the hounds!
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'import_start', array( &$this, 'import_start' ) );
		add_action( 'import_end', array( &$this, 'import_end' ) );

		// Check if there's an upgrade
		add_action( 'upgrader_process_complete', array( &$this, 'check_upgrades' ), 10, 2 );
	}

	/**
	 * Admin Init
	 *
	 * @since 4.1
	 * @access public
	 */
	public function admin_init() {
		global $pagenow;

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
		if ( 'site-health.php' !== $pagenow && current_user_can( 'manage_options' ) ) {

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
		global $blog_id, $wp_db_version;

		// If the DB version we detect isn't the same as the version core thinks
		// we will flush DB cache. This may cause double dumping in some cases but
		// should not be harmful.
		if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) && (int) get_option( 'db_version' ) !== $wp_db_version ) {
			wp_cache_flush();
		}

		// If Dev Mode is true, kill caching.
		if ( VarnishDebug::devmode_check() ) {
			if ( ! is_admin() ) {
				// Sessions used to break PHP caching.
				// @codingStandardsIgnoreStart
				if ( ! is_user_logged_in() && session_status() != PHP_SESSION_ACTIVE ) {
					@session_start();
				}
				// @codingStandardsIgnoreEnd

				// Add nocache to CSS and JS.
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
					add_action( $event, array( self::$purge_strategy, 'purge_all' ) );
				} else {
					add_action( $event, array( self::$purge_strategy, 'purge_post' ), 10, 2 );
				}
			}
		}

		add_action( 'shutdown', array( self::$purge_strategy, 'execute_purge' ) );

		// Success: Admin notice when purging.
		if ( ( isset( $_GET['vhp_flush_all'] ) && check_admin_referer( 'vhp-flush-all' ) ) ||
			( isset( $_GET['vhp_flush_do'] ) && check_admin_referer( 'vhp-flush-do' ) ) ) {
			if ( 'devmode' === $_GET['vhp_flush_do'] && isset( $_GET['vhp_set_devmode'] ) ) {
				VarnishDebug::devmode_toggle( esc_attr( $_GET['vhp_set_devmode'] ) );
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
	 * Check if something has upgraded and try to flush the DB cache.
	 * This runs for ALL upgrades (theme, plugin, and core) to account for
	 * the complex nature that are upgrades.
	 *
	 * @param  array $object of upgrade data
	 * @param  array $options picked for upgrade
	 * @since 4.8
	 */
	public function check_upgrades( $object, $options ) {
		if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Pause caching if Importer was started
	 * @since 4.8
	 */
	public function import_start() {
		VarnishDebug::devmode_toggle( 'activate' );
	}

	/**
	 * Resume caching if Importer has ended
	 * @since 4.8
	 */
	public function import_end() {
		VarnishDebug::devmode_toggle( 'deactivate' );
	}

	/**
	 * Purge Message
	 * Informs of a successful purge
	 *
	 * @since 4.6
	 */
	public function admin_message_purge() {
		echo '<div id="message" class="notice notice-success fade is-dismissible"><p><strong>' . esc_html__( 'Cache emptied!', 'varnish-http-purge' ) . '</strong></p></div>';
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
			$time    = human_time_diff( time(), $devmode['expire'] );

			if ( ! $devmode['active'] ) {
				if ( ! is_multisite() ) {
					// translators: %1$s is the time until dev mode expires.
					// translators: %2$s is a link to the settings pages.
					$message = sprintf( __( 'Proxy Cache Purge Development Mode is active for the next %1$s. You can disable this at the <a href="%2$s">Proxy Settings Page</a>.', 'varnish-http-purge' ), $time, esc_url( admin_url( 'admin.php?page=varnish-page' ) ) );
				} else {
					// translators: %1$s is the time until dev mo^de expires.
					$message = sprintf( __( 'Proxy Cache Purge Development Mode is active for the next %1$s.', 'varnish-http-purge' ), $time );
				}
			}
		}

		// Only echo if there's actually a message
		if ( isset( $message ) ) {
			echo '<div class="notice notice-warning"><p>' . wp_kses_post( $message ) . '</p></div>';
		}
	}

	/**
	 * Custom CSS to allow for colouring.
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

			// If Devmode is in the config, don't allow it to be disabled.
			if ( ! VHP_DEVMODE ) {
				// Populate enable/disable cache button.
				if ( VarnishDebug::devmode_check() ) {
					$purge_devmode_title = __( 'Restart Cache', 'varnish-http-purge' );
					$vhp_add_query_arg   = array(
						'vhp_flush_do'    => 'devmode',
						'vhp_set_devmode' => 'dectivate',
					);
				} else {
					$purge_devmode_title = __( 'Pause Cache (24h)', 'varnish-http-purge' );
					$vhp_add_query_arg   = array(
						'vhp_flush_do'    => 'devmode',
						'vhp_set_devmode' => 'activate',
					);
				}

				$args[] = array(
					'parent' => 'purge-varnish-cache',
					'id'     => 'purge-varnish-cache-devmode',
					'title'  => $purge_devmode_title,
					'href'   => wp_nonce_url( add_query_arg( $vhp_add_query_arg ), 'vhp-flush-do' ),
					'meta'   => array(
						'title' => $purge_devmode_title,
					),
				);
			}
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

		if ( is_admin() && false === $icon_color && get_user_option( 'admin_color' ) ) {
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
		$button   = __( 'Press the button below to force it to empty your entire cache.', 'varnish-http-purge' );
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
			'autoptimize_action_cachepurged', // Compat with https://wordpress.org/plugins/autoptimize/ plugin.
			'delete_attachment',              // Delete an attachment - includes re-uploading.
			'deleted_post',                   // Delete a post.
			'edit_post',                      // Edit a post - includes leaving comments.
			'import_start',                   // When importer starts
			'import_end',                     // When importer ends
			'save_post',                      // Save a post.
			'switch_theme',                   // After a theme is changed.
			'customize_save_after',           // After Customizer is updated.
			'trashed_post',                   // Empty Trashed post.
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
			'autoptimize_action_cachepurged', // Compat with https://wordpress.org/plugins/autoptimize/ plugin.
			'import_start',                   // When importer starts
			'import_end',                     // When importer ends
			'switch_theme',                   // After a theme is changed.
			'customize_save_after',           // After Customizer is updated.
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

	/*
	 * These have all been refactored into the VarnishPurgeStrategy class, but just in case...
	 */
	public function purge_post( $post_id ) {
		self::$purge_strategy->purge_post( $post_id );
	}
	public function execute_purge_no_id() {
		self::$purge_strategy->purge_all();
	}
	public static function the_home_url() {
		return self::$purge_strategy->the_home_url();
	}
	public static function purge_url( $url ) {
		self::$purge_strategy->purge_url($url);
	}

	// @codingStandardsIgnoreStart
	/*
	 * These have all been name changed to proper names, but just in case...
	 */
	public function getRegisterEvents() {
		$this->get_register_events();
	}
	public function getNoIDEvents() {
		$this->get_no_id_events();
	}
	public function executePurge() {
		self::$purge_strategy->execute_purge();
	}
	public function purgeNoID() {
		self::$purge_strategy->purge_all();
	}
	public function purgeURL( $url ) {
		self::purge_url( $url );
	}
	public function purgePost( $post_id ) {
		$this->purge_post( $post_id );
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

// Preventing people from forking this and hurting themselve by having two versions, though it may not work.
if ( ! class_exists( 'VarnishStatus' ) ) {
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
	require_once 'health-check.php';

	$purger = new VarnishPurger();
}
