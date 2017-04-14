<?php
/*
Plugin Name: Varnish HTTP Purge
Plugin URI: https://halfelf.org/plugins/varnish-http-purge/
Description: Automatically empty pages cached by Varnish when content on your site is modified.
Version: 4.1-beta
Author: Mika Epstein
Author URI: https://halfelf.org/
License: http://www.apache.org/licenses/LICENSE-2.0
Text Domain: varnish-http-purge
Network: true

	Copyright 2013-2017: Mika A. Epstein (email: ipstenu@halfelf.org)

	Original Author: Leon Weidauer ( http:/www.lnwdr.de/ )

	This file is part of Varnish HTTP Purge, a plugin for WordPress.

	Varnish HTTP Purge is free software: you can redistribute it and/or modify
	it under the terms of the Apache License 2.0 license.

	Varnish HTTP Purge is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

*/

/**
 * Purge Varnish Class
 *
 * @since 2.0
 */

class VarnishPurger {
	protected $purgeUrls = array();

	/**
	 * Init
	 *
	 * @since 2.0
	 * @access public
	 */
	public function __construct( ) {
		defined( 'VHP_VARNISH_IP' ) || define( 'VHP_VARNISH_IP' , false );
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'activity_box_end', array( $this, 'varnish_rightnow' ), 100 );
	}

	/**
	 * Admin Init
	 *
	 * @since 4.1
	 * @access public
	 */
	public function admin_init() {

		// Failure: Pre WP 4.7		
		if ( version_compare( get_bloginfo( 'version' ), '4.7', '<=' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			add_action( 'admin_notices' , array( $this, 'require_wp_version_notice'));
			return;
		}

		// Warning: No Pretty Permalinks!
		if ( '' == get_option( 'permalink_structure' ) && current_user_can( 'manage_options' ) ) {
			add_action( 'admin_notices' , array( $this, 'require_pretty_permalinks_notice' ) );
			return;
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

		// get my events
		$events = $this->getRegisterEvents();
		$noIDevents = $this->getNoIDEvents();

		// make sure we have events and they're in an array
		if ( !empty( $events ) && !empty( $noIDevents ) ) {

			// Force it to be an array, in case someone's stupid
			$events = (array) $events;
			$noIDevents = (array) $noIDevents;

			// Add the action for each event
			foreach ( $events as $event) {
				if ( in_array($event, $noIDevents ) ) {
					// These events have no post ID and, thus, will perform a full purge
					add_action( $event, array( $this, 'purgeNoID' ) );
				} else {
					add_action( $event, array( $this, 'purgePost' ), 10, 2 );
				}
			}
		}
		
		add_action( 'shutdown', array( $this, 'executePurge' ) );

		// Success: Admin notice when purging
		if ( isset( $_GET['vhp_flush_all'] ) && check_admin_referer( 'vhp-flush-all' ) ) {
			add_action( 'admin_notices' , array( $this, 'purgeMessage'));
		}

		// Checking user permissions for who can and cannot use the admin button
		if (
			// SingleSite - admins can always purge
			( !is_multisite() && current_user_can( 'activate_plugins' ) ) ||
			// Multisite - Network Admin can always purge
			current_user_can( 'manage_network' ) ||
			// Multisite - Site admins can purge UNLESS it's a subfolder install and we're on site #1
			( is_multisite() && current_user_can( 'activate_plugins' ) && ( SUBDOMAIN_INSTALL || ( !SUBDOMAIN_INSTALL && ( BLOG_ID_CURRENT_SITE != $blog_id ) ) ) )
			) {
				add_action( 'admin_bar_menu', array( $this, 'varnish_rightnow_adminbar' ), 100 );
		}

	}

	/**
	 * Purge Message
	 * Informs of a succcessful purge
	 *
	 * @since 2.0
	 */
	function purgeMessage() {
		echo "<div id='message' class='notice notice-success fade is-dismissible'><p><strong>".__('Varnish cache emptied!', 'varnish-http-purge')."</strong></p></div>";
	}

	/**
	 * Require: Pretty Permalinks Message
	 * Explains you need Pretty Permalinks enabled to use this plugin
	 *
	 * @since 2.0
	 */
	function require_pretty_permalinks_notice() {
		echo "<div id='message' class='error'><p>" . sprintf( __( 'Varnish HTTP Purge requires you to use custom permalinks. Please go to the <a href="%1$s">Permalinks Options Page</a> to configure them.', 'varnish-http-purge' ), admin_url( 'options-permalink.php' ) ) . "</p></div>";
	}

	/**
	 * Require: WP Version Message
	 * Explains you need WordPress 4.7+ to use this plugin
	 *
	 * @since 4.1
	 */
	function require_wp_version_notice() {
		echo "<div id='message' class='error'><p>" . sprintf( __( 'Varnish HTTP Purge requires WordPress 4.7 or greater. Please <a href="%1$s">upgrade WordPress</a>.', 'varnish-http-purge' ), admin_url( 'update-core.php' ) ) . "</p></div>";
	}

	/**
	 * The Home URL
	 * Get the Home URL and allow it to be filterable
	 * This is for domain mapping plugins that, for some reason, don't filter
	 * on their own (including WPMU, Ron's, and so on).
	 *
	 * @since 4.0
	 */
	static public function the_home_url(){
		$home_url = apply_filters( 'vhp_home_url', home_url() );
		return $home_url;
	}

	/**
	 * Varnish Purge Button in the Admin Bar
	 *
	 * @since 2.0
	 */
	function varnish_rightnow_adminbar( $admin_bar ){
		$admin_bar->add_menu( array(
			'id'	=> 'purge-varnish-cache-all',
			'title' => __( 'Empty Cache', 'varnish-http-purge' ),
			'href'  => wp_nonce_url( add_query_arg('vhp_flush_all', 1), 'vhp-flush-all'),
			'meta'  => array(
				'title' => __( 'Empty Cache', 'varnish-http-purge' ),
			),
		));
	}

	/**
	 * Varnish Right Now Information
	 * This information is put on the Dashboard 'Right now' widget
	 *
	 * @since 1.0
	 */
	function varnish_rightnow() {
		global $blog_id;
		$url = wp_nonce_url( add_query_arg( 'vhp_flush_all', 1 ), 'vhp-flush-all' );
		$intro = sprintf( __( '<a href="%1$s">Varnish HTTP Purge</a> automatically deletes your cached posts when published or updated. When making major site changes, such as with a new theme, plugins, or widgets, you may need to manually empty the cache.', 'varnish-http-purge' ), 'http://wordpress.org/plugins/varnish-http-purge/' );
		$button =  __( 'Press the button below to force it to empty your entire Varnish cache.', 'varnish-http-purge' );
		$button .= '</p><p><span class="button"><a href="'.$url.'"><strong>';
		$button .= __( 'Empty Cache', 'varnish-http-purge' );
		$button .= '</strong></a></span>';
		$nobutton =  __( 'You do not have permission to empty the Varnish cache for the whole site. Please contact your administrator.', 'varnish-http-purge' );

		if (
			// SingleSite - admins can always purge
			( !is_multisite() && current_user_can( 'activate_plugins' ) ) ||
			// Multisite - Network Admin can always purge
			current_user_can( 'manage_network' ) ||
			// Multisite - Site admins can purge UNLESS it's a subfolder install and we're on site #1
			( is_multisite() && current_user_can( 'activate_plugins' ) && ( SUBDOMAIN_INSTALL || ( !SUBDOMAIN_INSTALL && ( BLOG_ID_CURRENT_SITE != $blog_id ) ) ) )
		) {
			$text = $intro.' '.$button;
		} else {
			$text = $intro.' '.$nobutton;
		}
		echo "<p class='varnish-rightnow'>$text</p>\n";
	}

	/**
	 * Registered Events
	 * These are when the purge is triggered
	 *
	 * @since 1.0
	 * @access protected
	 */
	protected function getRegisterEvents() {

		// Define registered purge events
		$actions = array(
			'switch_theme',						// After a theme is changed
			'autoptimize_action_cachepurged',	// Compat with https://wordpress.org/plugins/autoptimize/
			'save_post',							// Save a post
			'deleted_post',						// Delete a post
			'trashed_post',						// Empty Trashed post
			'edit_post',							// Edit a post - includes leaving comments
			'delete_attachment',					// Delete an attachment - includes re-uploading
		);

		// send back the actions array, filtered
		// @param array $actions the actions that trigger the purge event
		return apply_filters( 'varnish_http_purge_events', $actions );
	}

	/**
	 * Events that have no post IDs
	 * These are when a full purge is triggered
	 *
	 * @since 3.9
	 * @access protected
	 */
	protected function getNoIDEvents() {

		// Define registered purge events
		$actions = array(
			'switch_theme',						// After a theme is changed
			'autoptimize_action_cachepurged,'	// Compat with https://wordpress.org/plugins/autoptimize/
		);

		// send back the actions array, filtered
		// @param array $actions the actions that trigger the purge event
		// DEVELOPERS! USE THIS SPARINGLY! YOU'RE A GREAT BIG 💩 IF YOU USE IT FLAGRANTLY
		// Remember to add your action to this AND varnish_http_purge_events due to shenanigans
		return apply_filters( 'varnish_http_purge_events_full', $actions );
	}

	/**
	 * Execute Purge
	 * Run the purge command for the URLs. Calls $this->purgeUrl for each URL
	 *
	 * @since 1.0
	 * @access protected
	 */
	public function executePurge() {
		$purgeUrls = array_unique( $this->purgeUrls );

		if ( empty( $purgeUrls ) ) {
			if ( isset( $_GET['vhp_flush_all'] ) && check_admin_referer( 'vhp-flush-all' ) ) {
				$this->purgeUrl( $this->the_home_url() . '/?vhp-regex' );
			}
		} else {
			foreach( $purgeUrls as $url ) {
				$this->purgeUrl( $url );
			}
		}
	}

	/**
	 * Purge URL
	 * Parse the URL for proxy proxies
	 *
	 * @since 1.0
	 * @param array $url the url to be purged
	 * @access protected
	 */
	public function purgeUrl( $url ) {
		$p = parse_url( $url );

		// Determine if we're using regex to flush all pages or not
		$pregex = '';
		$x_purge_method = 'default';

		if ( isset($p['query']) && ( $p['query'] == 'vhp-regex' ) ) {
			$pregex = '.*';
			$x_purge_method = 'regex';
		}

		// Build a varniship
		if ( VHP_VARNISH_IP != false ) {
			$varniship = VHP_VARNISH_IP;
		} else {
			$varniship = get_option( 'vhp_varnish_ip' );
		}
		$varniship = apply_filters( 'vhp_varnish_ip' , $varniship );
                $varniships = explode(',', $varniship);

		// Determine the path
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
                foreach ($varniships as $host) {

		        // If we made varniship, let it sail
		        // if ( isset( $varniship ) && $varniship != null ) {
		        // 	$host = $varniship;
		        // } else {
		        // 	$host = $p['host'];
		        // }

		        $purgeme = $schema.$host.$path.$pregex;

		        if ( !empty( $p['query'] ) && $p['query'] != 'vhp-regex' ) {
		        	$purgeme .= '?' . $p['query'];
		        }

		        /**
		         * Filters the HTTP headers to send with a PURGE request.
		         *
		         * @since 4.1
		         */
		        $headers = apply_filters( 'varnish_http_purge_headers', array( 'host' => $p['host'], 'X-Purge-Method' => $x_purge_method ) );
		        
		        // Cleanup CURL functions to be wp_remote_request and thus better
		        // http://wordpress.org/support/topic/incompatability-with-editorial-calendar-plugin
		        $response = wp_remote_request( $purgeme, array( 'method' => 'PURGE', 'headers' => $headers ) );

		        do_action( 'after_purge_url', $url, $purgeme, $response, $headers );
                }
	}

	/**
	 * Purge - No IDs
	 * Flush the whole cache
	 *
	 * @since 3.9
	 * @access public
	 */
	public function purgeNoID( $postId ) {
		$listofurls = array();

		array_push( $listofurls, $this->the_home_url() . '/?vhp-regex' );
	
		// Now flush all the URLs we've collected provided the array isn't empty
		if ( !empty( $listofurls ) ) {
			foreach ( $listofurls as $url ) {
				array_push( $this->purgeUrls, $url ) ;
			}
		}
	}

	/**
	 * Purge Post
	 * Flush the post
	 *
	 * @since 1.0
	 * @param array $postId the ID of the post to be purged
	 * @access public
	 */
	public function purgePost( $postId ) {
		
		// Future Me: You may need this if you figure out how to use an array
		// further down with versions of WP and their json versions.
		// global $wp_version;
		
		// If this is a valid post we want to purge the post, 
		// the home page and any associated tags and categories
		$valid_post_status = array( 'publish', 'private', 'trash' );
		$this_post_status  = get_post_status( $postId );

		// Not all post types are created equal
		$invalid_post_type = array( 'nav_menu_item', 'revision' );
		$noarchive_post_type = array( 'post', 'page' );
		$this_post_type = get_post_type( $postId );
				
		// Determine the route for the rest API
		// This will need to be revisted if WP updates the version.
		// Future me: Consider an array? 4.7-4.7.3 use v2, and then adapt from there?
		$rest_api_route  = 'wp/v2'; 

		// array to collect all our URLs
		$listofurls = array();

		// Verify we have a permalink and that we're a valid post status and a not an invalid post type
		if( get_permalink( $postId ) == true && in_array( $this_post_status, $valid_post_status ) && !in_array( $this_post_type, $invalid_post_type ) ) {

			// Post URL
			array_push( $listofurls, get_permalink( $postId ) );

			// JSON API Permalink for the post based on type
			// We only want to do this if the rest_base exists
			// But we apparently have to force it for posts and pages (seriously?)
			$post_type_object = get_post_type_object( $postId );	
			if ( isset( $post_type_object->rest_base ) ) {
				$rest_permalink = get_rest_url() . $rest_api_route . '/' . $post_type_object->rest_base . '/' . $postId . '/';
			} elseif ( $this_post_type == 'post' ) {
				$rest_permalink = get_rest_url() . $rest_api_route . '/posts/' . $postId . '/';
			} elseif ( $this_post_type == 'page' ) {
				$rest_permalink = get_rest_url() . $rest_api_route . '/pages/' . $postId . '/';
			}
			array_push( $listofurls, $rest_permalink );

			// Add in AMP permalink if Automattic's AMP is installed
			if ( function_exists( 'amp_get_permalink' ) ) {
				array_push( $listofurls, amp_get_permalink( $postId ) );
			}
			
			// Regular AMP url for posts
			array_push( $listofurls, get_permalink( $postId ) . 'amp/' );

			// Also clean URL for trashed post.
			if ( $this_post_status == 'trash' ) {
				$trashpost = get_permalink( $postId );
				$trashpost = str_replace( '__trashed', '', $trashpost );
				array_push( $listofurls, $trashpost, $trashpost . 'feed/' );
			}

			// Category purge based on Donnacha's work in WP Super Cache
			$categories = get_the_category( $postId) ;
			if ( $categories ) {
				foreach ( $categories as $cat ) {
					array_push( $listofurls, 
						get_category_link( $cat->term_id ),
						get_rest_url() . $rest_api_route . '/categories/' . $cat->term_id . '/'
					);
				}
			}
			// Tag purge based on Donnacha's work in WP Super Cache
			$tags = get_the_tags( $postId );
			if ( $tags ) {
				foreach ( $tags as $tag ) {
					array_push( $listofurls, 
						get_tag_link( $tag->term_id ),
						get_rest_url() . $rest_api_route . '/tags/' . $tag->term_id . '/'
					);
				}
			}
			
			// Author URL
			$author_id = get_post_field( 'post_author', $postId );
			array_push( $listofurls,
				get_author_posts_url( $author_id ),
				get_author_feed_link( $author_id ),
				get_rest_url() . $rest_api_route . '/users/' . $author_id . '/'
			);

			// Archives and their feeds
			if ( $this_post_type && !in_array( $this_post_type, $noarchive_post_type ) ) {
				array_push( $listofurls,
					get_post_type_archive_link( get_post_type( $postId ) ),
					get_post_type_archive_feed_link( get_post_type( $postId ) )
					// Need to add in JSON?
				);
			}
			
			// Feeds
			array_push( $listofurls,
				get_bloginfo_rss( 'rdf_url' ),
				get_bloginfo_rss( 'rss_url' ),
				get_bloginfo_rss( 'rss2_url' ),
				get_bloginfo_rss( 'atom_url' ),
				get_bloginfo_rss( 'comments_rss2_url' ),
				get_post_comments_feed_link( $postId )
			);

			// Home Pages and (if used) posts page
			array_push( $listofurls, 
				get_rest_url(),
				$this->the_home_url() . '/'
				);
			if ( get_option( 'show_on_front' ) == 'page' ) {
				// Ensure we have a page_for_posts setting to avoid empty URL
				if ( get_option('page_for_posts') ) {
					array_push( $listofurls, get_permalink( get_option( 'page_for_posts' ) ) );
				}
			}
			
		} else {
			// We're not sure how we got here, but bail instead of processing anything else.
			return;
		}
		
		// Now flush all the URLs we've collected provided the array isn't empty
		// And make sure each URL only gets purged once, eh?
		if ( !empty( $listofurls ) ) {
			$purgeurls = array_unique( $listofurls, SORT_REGULAR );
			foreach ( $purgeurls as $url ) {
				array_push( $this->purgeUrls, $url ) ;
			}
		}

        // Filter to add or remove urls to the array of purged urls
        // @param array $purgeUrls the urls (paths) to be purged
        // @param int $postId the id of the new/edited post
        $this->purgeUrls = apply_filters( 'vhp_purge_urls', $this->purgeUrls, $postId );
	}

}

$purger = new VarnishPurger();

/**
 * Purge Varnish via WP-CLI
 *
 * @since 3.8
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include( 'wp-cli.php' );
}

/* Varnish Status Page
 * 
 * @since 4.0
 */
include_once( 'varnish-status.php' );
