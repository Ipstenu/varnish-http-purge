<?php
/**
Plugin Name: Varnish HTTP Purge
Plugin URI: http://wordpress.org/extend/plugins/varnish-http-purge/
Description: Sends HTTP PURGE requests to URLs of changed posts/pages when they are modified.
Version: 3.8.1
Author: Mika Epstein
Author URI: http://halfelf.org/
License: http://www.apache.org/licenses/LICENSE-2.0
Text Domain: varnish-http-purge
Network: true

Copyright 2013-2015: Mika A. Epstein (email: ipstenu@ipstenu.org)

Original Author: Leon Weidauer ( http:/www.lnwdr.de/ )

	This file is part of Varnish HTTP Purge, a plugin for WordPress.

	Varnish HTTP Purge is free software: you can redistribute it and/or modify
	it under the terms of the Apache License 2.0 license.

	Varnish HTTP Purge is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Purge Varnish Class.
 *
 * @since 2.0
 */
class VarnishPurger {

	/**
	 * Contains all urls to be purged.
	 *
	 * @var array
	 */
	protected $purge_urls = array();

	/**
	 * Init.
	 *
	 * @since 2.0
	 * @access public
	 */
	public function __construct() {
		defined( 'varnish-http-purge' ) || define( 'varnish-http-purge', true );
		defined( 'VHP_VARNISH_IP' ) || define( 'VHP_VARNISH_IP', false );
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'activity_box_end', array( $this, 'varnish_rightnow' ), 100 );
	}

	/**
	 * Plugin Init.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init() {
		global $blog_id;
		load_plugin_textdomain( 'varnish-http-purge' );

		// Get my events.
		$events = $this->get_register_events();

		// Make sure we have events and it's an array.
		if ( ! empty( $events ) ) {

			// Force it to be an array, in case someone's stupid.
			$events = (array) $events;

			// Add the action for each event.
			foreach ( $events as $event ) {
				add_action( $event, array( $this, 'purge_post' ), 10, 2 );
			}
		}
		add_action( 'shutdown', array( $this, 'execute_purge' ) );

		// Success: Admin notice when purging.
		if ( isset( $_GET['vhp_flush_all'] ) && check_admin_referer( 'varnish-http-purge' ) ) {
			add_action( 'admin_notices' , array( $this, 'purge_message' ) );
		}

		// Warning: No Pretty Permalinks!
		if ( '' === get_option( 'permalink_structure' ) && current_user_can( 'manage_options' ) ) {
			add_action( 'admin_notices' , array( $this, 'pretty_permalinks_message' ) );
		}

		if (
			// SingleSite - admins can always purge.
			( ! is_multisite() && current_user_can( 'activate_plugins' ) ) ||
			// Multisite - Network Admin can always purge.
			current_user_can( 'manage_network' ) ||
			// Multisite - Site admins can purge UNLESS it's a subfolder install and we're on site #1.
			( is_multisite() && ! current_user_can( 'manage_network' ) && ( SUBDOMAIN_INSTALL || ( ! SUBDOMAIN_INSTALL && ( absint( BLOG_ID_CURRENT_SITE ) !== absint( $blog_id ) ) ) ) )
			) {
				add_action( 'admin_bar_menu', array( $this, 'varnish_rightnow_adminbar' ), 100 );
		}

	}

	/**
	 * Purge Message.
	 * Informs of a successful purge.
	 *
	 * @since 2.0
	 */
	function purge_message() {
		echo '<div id="message" class="updated fade"><p><strong>' . __( 'Varnish cache purged!', 'varnish-http-purge' ) . '</strong></p></div>';
	}

	/**
	 * Permalinks Message.
	 * Explains you need Pretty Permalinks on to use this plugin.
	 *
	 * @since 2.0
	 */
	function pretty_permalinks_message() {
		echo '<div id="message" class="error"><p>' . __( 'Varnish HTTP Purge requires you to use custom permalinks. Please go to the <a href="options-permalink.php">Permalinks Options Page</a> to configure them.', 'varnish-http-purge' ) . '</p></div>';
	}

	/**
	 * Varnish Purge Button in the Admin Bar
	 *
	 * @param object $admin_bar WP_Admin_Bar Object.
	 * @since 2.0
	 */
	function varnish_rightnow_adminbar( $admin_bar ) {
		$admin_bar->add_menu( array(
			'id'	=> 'purge-varnish-cache-all',
			'title' => __( 'Purge Varnish','varnish-http-purge' ),
			'href'  => wp_nonce_url( add_query_arg( 'vhp_flush_all', 1 ), 'varnish-http-purge' ),
			'meta'  => array( 'title' => __( 'Purge Varnish','varnish-http-purge' ) ),
		));
	}

	/**
	 * Varnish Right Now Information.
	 * This information is put on the Dashboard 'Right now' widget.
	 *
	 * @since 1.0
	 */
	function varnish_rightnow() {
		global $blog_id;
		$url = wp_nonce_url( add_query_arg( 'vhp_flush_all', 1 ), 'varnish-http-purge' );
		$intro = sprintf( __( '<a href="%1$s">Varnish HTTP Purge</a> automatically purges your posts when published or updated. Sometimes you need a manual flush.', 'varnish-http-purge' ), 'http://wordpress.org/plugins/varnish-http-purge/' );
		$button = __( 'Press the button below to force it to purge your entire cache.', 'varnish-http-purge' );
		$button .= '</p><p><span class="button"><a href="' . $url . '"><strong>';
		$button .= __( 'Purge Varnish', 'varnish-http-purge' );
		$button .= '</strong></a></span>';
		$nobutton = __( 'You do not have permission to purge the cache for the whole site. Please contact your administrator.', 'varnish-http-purge' );

		if (
			// SingleSite - admins can always purge.
			( ! is_multisite() && current_user_can( 'activate_plugins' ) ) ||
			// Multisite - Network Admin can always purge.
			current_user_can( 'manage_network' ) ||
			// Multisite - Site admins can purge UNLESS it's a subfolder install and we're on site #1.
			( is_multisite() && ! current_user_can( 'manage_network' ) && ( SUBDOMAIN_INSTALL || ( ! SUBDOMAIN_INSTALL && ( absint( BLOG_ID_CURRENT_SITE ) !== absint( $blog_id ) ) ) ) )
		) {
			$text = $intro.' '.$button;
		} else {
			$text = $intro.' '.$nobutton;
		}
		echo esc_html( "<p class='varnish-rightnow'>$text</p>\n" );
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
			'save_post',            // Save a post.
			'deleted_post',         // Delete a post.
			'trashed_post',         // Empty Trashed post.
			'edit_post',            // Edit a post - includes leaving comments.
			'delete_attachment',    // Delete an attachment - includes re-uploading.
			'switch_theme',         // Change theme.
		);

		// Send back the actions array, filtered.
		// @param array $actions the actions that trigger the purge event.
		return apply_filters( 'varnish_http_purge_events', $actions );
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

		if ( empty( $purge_urls ) ) {
			if ( isset( $_GET['vhp_flush_all'] ) && check_admin_referer( 'varnish-http-purge' ) ) {
				$this->purge_url( home_url() . '/?vhp-regex' );
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
	 * @param array $url the url to be purged.
	 * @access protected
	 */
	public function purge_url( $url ) {
		$p = parse_url( $url );

		if ( isset( $p['query'] ) && ( 'vhp-regex' === $p['query'] ) ) {
			$pregex = '.*';
			$varnish_x_purgemethod = 'regex';
		} else {
			$pregex = '';
			$varnish_x_purgemethod = 'default';
		}

		// Build a varniship.
		if ( VHP_VARNISH_IP !== false ) {
			$varniship = VHP_VARNISH_IP;
		} else {
			$varniship = get_option( 'vhp_varnish_ip' );
		}

		if ( isset( $p['path'] ) ) {
			$path = $p['path'];
		} else {
			$path = '';
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
		if ( isset( $varniship ) && null !== $varniship ) {
			$purgeme = $schema . $varniship . $path . $pregex;
		} else {
			$purgeme = $schema.$p['host'].$path.$pregex;
		}

		// Cleanup CURL functions to be wp_remote_request and thus better.
		// @link http://wordpress.org/support/topic/incompatability-with-editorial-calendar-plugin.
		$response = wp_remote_request( $purgeme, array( 'method' => 'PURGE', 'headers' => array( 'host' => $p['host'], 'X-Purge-Method' => $varnish_x_purgemethod ) ) );

		do_action( 'after_purge_url', $url, $purgeme, $response );
	}

	/**
	 * Purge Post.
	 * Flush the post.
	 *
	 * @since 1.0
	 * @param int $post_id WP_Post.ID to be purged.
	 * @access public
	 */
	public function purge_post( $post_id ) {

		$post_id = absint( $post_id );

		// If this is a valid post we want to purge the post, the home page and any associated tags & cats
		// If not, purge everything on the site.
		$valid_post_status = array( 'publish', 'trash' );
		$this_post_status = get_post_status( $post_id );

		// If this is a revision, stop.
		if ( get_permalink( $post_id ) !== true && ! in_array( $this_post_status, $valid_post_status, true ) ) {
			return;
		} else {
			// Array to collect all our URLs.
			$listofurls = array();

			// Category purge based on Donnacha's work in WP Super Cache.
			$categories = get_the_category( $post_id );
			if ( $categories ) {
				foreach ( $categories as $cat ) {
					array_push( $listofurls, get_category_link( $cat->term_id ) );
				}
			}
			// Tag purge based on Donnacha's work in WP Super Cache.
			$tags = get_the_tags( $post_id );
			if ( $tags ) {
				foreach ( $tags as $tag ) {
					array_push( $listofurls, get_tag_link( $tag->term_id ) );
				}
			}

			// Author URL.
			array_push( $listofurls,
				get_author_posts_url( get_post_field( 'post_author', $post_id ) ),
				get_author_feed_link( get_post_field( 'post_author', $post_id ) )
			);

			// Archives and their feeds.
			$archiveurls = array();
			if ( get_post_type_archive_link( get_post_type( $post_id ) ) === true ) {
				array_push( $listofurls,
					get_post_type_archive_link( get_post_type( $post_id ) ),
					get_post_type_archive_feed_link( get_post_type( $post_id ) )
				);
			}

			// Post URL.
			array_push( $listofurls, get_permalink( $post_id ) );

			// Feeds.
			array_push( $listofurls,
				get_bloginfo_rss( 'rdf_url' ),
				get_bloginfo_rss( 'rss_url' ),
				get_bloginfo_rss( 'rss2_url' ),
				get_bloginfo_rss( 'atom_url' ),
				get_bloginfo_rss( 'comments_rss2_url' ),
				get_post_comments_feed_link( $post_id )
			);

			// Home Page and (if used) posts page.
			array_push( $listofurls, home_url( '/' ) );
			if ( get_option( 'show_on_front' ) === 'page' ) {
				array_push( $listofurls, get_permalink( get_option( 'page_for_posts' ) ) );
			}

			// Now flush all the URLs we've collected.
			foreach ( $listofurls as $url ) {
				array_push( $this->purge_urls, $url );
			}
		}

		// Filter to add or remove urls to the array of purged urls.
		// @param array $purge_urls the urls (paths) to be purged.
		// @param int $post_id the id of the new/edited post.
		$this->purge_urls = apply_filters( 'vhp_purge_urls', $this->purge_urls, $post_id );
	}
}

$purger = new VarnishPurger();

// WP-CLI support.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include( 'wp-cli.php' );
}
