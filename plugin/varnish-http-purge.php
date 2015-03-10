<?php
/*
Plugin Name: Varnish HTTP Purge
Plugin URI: http://wordpress.org/extend/plugins/varnish-http-purge/
Description: Sends HTTP PURGE requests to URLs of changed posts/pages when they are modified.
Version: 3.7-beta
Author: Mika Epstein
Author URI: http://halfelf.org/
License: http://www.apache.org/licenses/LICENSE-2.0
Text Domain: varnish-http-purge
Network: true

Copyright 2013-2014: Mika A. Epstein (email: ipstenu@ipstenu.org)

Original Author: Leon Weidauer ( http:/www.lnwdr.de/ )

	This file is part of Varnish HTTP Purge, a plugin for WordPress.

	Varnish HTTP Purge is free software: you can redistribute it and/or modify
	it under the terms of the Apache License 2.0 license.

	Varnish HTTP Purge is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

*/

class VarnishPurger {
	protected $purgeUrls = array();

	public function __construct() {
		defined('varnish-http-purge') ||define('varnish-http-purge', true);
		defined('VHP_VARNISH_IP') || define('VHP_VARNISH_IP', false );
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'activity_box_end', array( $this, 'varnish_rightnow' ), 100 );
	}

	public function init() {
		global $blog_id;
		load_plugin_textdomain( 'varnish-http-purge' );

		foreach ($this->getRegisterEvents() as $event) {
			add_action( $event, array($this, 'purgePost'), 10, 2 );
		}
		add_action( 'shutdown', array($this, 'executePurge') );

		if ( isset($_GET['vhp_flush_all']) && check_admin_referer('varnish-http-purge') ) {
			add_action( 'admin_notices' , array( $this, 'purgeMessage'));
		}

		if ( '' == get_option( 'permalink_structure' ) && current_user_can('manage_options') ) {
			add_action( 'admin_notices' , array( $this, 'prettyPermalinksMessage'));
		}

		if (
			// SingleSite - admins can always purge
			( !is_multisite() && current_user_can('activate_plugins') ) ||
			// Multisite - Network Admin can always purge
			current_user_can('manage_network') ||
			// Multisite - Site admins can purge UNLESS it's a subfolder install and we're on site #1
			( is_multisite() && !current_user_can('manage_network') && ( SUBDOMAIN_INSTALL || ( !SUBDOMAIN_INSTALL && ( BLOG_ID_CURRENT_SITE != $blog_id ) ) ) )
			) {
				add_action( 'admin_bar_menu', array( $this, 'varnish_rightnow_adminbar' ), 100 );
		}

	}

	function purgeMessage() {
		echo "<div id='message' class='updated fade'><p><strong>".__('Varnish cache purged!', 'varnish-http-purge')."</strong></p></div>";
	}

	function prettyPermalinksMessage() {
		echo "<div id='message' class='error'><p>".__( 'Varnish HTTP Purge requires you to use custom permalinks. Please go to the <a href="options-permalink.php">Permalinks Options Page</a> to configure them.', 'varnish-http-purge' )."</p></div>";
	}

	function varnish_rightnow_adminbar($admin_bar){
		$admin_bar->add_menu( array(
			'id'	=> 'purge-varnish-cache-all',
			'title' => 'Purge Varnish Cache',
			'href'  => wp_nonce_url(add_query_arg('vhp_flush_all', 1), 'varnish-http-purge'),
			'meta'  => array(
				'title' => __('Purge your entire Varnish Cache','varnish-http-purge'),
			),
		));
	}

	function varnish_rightnow() {
		global $blog_id;
		$url = wp_nonce_url(admin_url('?vhp_flush_all'), 'varnish-http-purge');
		$intro = sprintf( __('<a href="%1$s">Varnish HTTP Purge</a> automatically purges your posts when published or updated. Sometimes you need a manual flush.', 'varnish-http-purge' ), 'http://wordpress.org/plugins/varnish-http-purge/' );
		$button =  __('Press the button below to force it to purge your entire cache.', 'varnish-http-purge' );
		$button .= '</p><p><span class="button"><a href="'.$url.'"><strong>';
		$button .= __('Purge Varnish Cache', 'varnish-http-purge' );
		$button .= '</strong></a></span>';
		$nobutton =  __('You do not have permission to purge the cache for the whole site. Please contact your adminstrator.', 'varnish-http-purge' );
		if (
			// SingleSite - admins can always purge
			( !is_multisite() && current_user_can('activate_plugins') ) ||
			// Multisite - Network Admin can always purge
			current_user_can('manage_network') ||
			// Multisite - Site admins can purge UNLESS it's a subfolder install and we're on site #1
			( is_multisite() && !current_user_can('manage_network') && ( SUBDOMAIN_INSTALL || ( !SUBDOMAIN_INSTALL && ( BLOG_ID_CURRENT_SITE != $blog_id ) ) ) )
		) {
			$text = $intro.' '.$button;
		} else {
			$text = $intro.' '.$nobutton;
		}
		echo "<p class='varnish-rightnow'>$text</p>\n";
	}

	protected function getRegisterEvents() {
		return array(
			'save_post',
			'deleted_post',
			'trashed_post',
			'edit_post',
			'delete_attachment',
			'switch_theme',
		);
	}

	public function executePurge() {
		$purgeUrls = array_unique($this->purgeUrls);
		
		if (empty($purgeUrls)) {
			if ( isset($_GET['vhp_flush_all']) && current_user_can('manage_options') && check_admin_referer('varnish-http-purge') ) { 
				$this->purgeUrl( home_url() .'/?vhp=regex' );
			   // wp_cache_flush();
			} 
		} else {
			foreach($purgeUrls as $url) {
				$this->purgeUrl($url);
			}
		}
	}

	protected function purgeUrl($url) {
		// Parse the URL for proxy proxies
		$p = parse_url($url);
		
		
		if ( isset($p['query']) && ( $p['query'] == 'vhp=regex' ) ) {
			$pregex = '.*';
			$varnish_x_purgemethod = 'regex';
		} else {
			$pregex = '';
			$varnish_x_purgemethod = 'default';
		}

		// Build a varniship
		if ( VHP_VARNISH_IP != false ) {
			$varniship = VHP_VARNISH_IP;
		} else {
			$varniship = get_option('vhp_varnish_ip');
		}

		if (isset($p['path'] ) ) { 
			$path = $p['path'];
		} else {
			$path = '';
		}

		// If we made varniship, let it sail
		if ( isset($varniship) && $varniship != null ) {
			$purgeme = 'http://'.$varniship.$path.$pregex;
		} else {
			$purgeme = 'http://'.$p['host'].$path.$pregex;
		}

		// Cleanup CURL functions to be wp_remote_request and thus better
		// http://wordpress.org/support/topic/incompatability-with-editorial-calendar-plugin
		wp_remote_request($purgeme, array('method' => 'PURGE', 'headers' => array( 'host' => $p['host'], 'X-Purge-Method' => $varnish_x_purgemethod ) ) );
		
		do_action('after_purge_url', $url, $purgeme);
	}

	public function purgePost($postId) {

		// If this is a revision, stop.
		if( get_post_type($postId) == 'revision' ) {
			return;
		}

		// If this is a valid post we want to purge the post, the home page and any associated tags & cats
		// If not, purge everything on the site.
	
		$validPostStatus = array("publish", "trash");
		$thisPostStatus  = get_post_status($postId);
	
		if ( get_permalink($postId) == true && in_array($thisPostStatus, $validPostStatus) ) {

			// Category purge based on Donnacha's work in WP Super Cache
			$categories = get_the_category($postId);
			if ( $categories ) {
				foreach ($categories as $cat) {
					array_push($this->purgeUrls, get_category_link( $cat->term_id ) );
				}
			}
			// Tag purge based on Donnacha's work in WP Super Cache
			$tags = get_the_tags($postId);
			if ( $tags ) {
				foreach ($tags as $tag) {
					array_push($this->purgeUrls, get_tag_link( $tag->term_id ) );
				}
			}

			// Author URL
			$authorurls = array (
				get_author_posts_url( get_the_author( $postId ) ),
				get_author_feed_link( get_the_author( $postId ) ),
			);
			array_push($this->purgeUrls, $authorurls ) ;
			
			// Archives and their feeds
			$archiveurls = array();
			if ( get_post_type_archive_link($postID) == true ) {
				array_push( $archiveurls, get_post_type_archive_link( get_post_type( $postId ) ) );
				array_push( $archiveurls, get_post_type_archive_feed_link( get_post_type( $postId ) ) );
			}
			array_push($this->purgeUrls, $archiveurls ) ;

			// Post URL Purge
			array_push($this->purgeUrls, get_permalink($postId) );

			// Feed Purge
			$feeds = array(
				get_bloginfo_rss('rdf_url') , 
				get_bloginfo_rss('rss_url') , 
				get_bloginfo_rss('rss2_url'), 
				get_bloginfo_rss('atom_url'), 
				get_bloginfo_rss('comments_rss2_url'), 
				get_post_comments_feed_link($postId) 
			);
			foreach ( $feeds as $feed ) {
				array_push($this->purgeUrls, $feed );
			}

			// Home Page:
			// Flush the front page IF we're showing posts, otherwise fush page used for posts 
			if( get_option('show_on_front') == 'posts' ) {
				array_push($this->purgeUrls, home_url('/') );
			} elseif ( get_option('show_on_front') == 'page' ) {
				array_push($this->purgeUrls, get_permalink( get_option('page_for_posts') ) );
			}

		} else {
			array_push($this->purgeUrls, home_url( '/?vhp=regex') );
		}

        // Filter to add or remove urls to the array of purged urls
        // @param array $purgeUrls the urls (paths) to be purged
        // @param int $postId the id of the new/edited post
        $this->purgeUrls = apply_filters( 'vhp_purge_urls', $this->purgeUrls, $postId );
	}

}

$purger = new VarnishPurger();
