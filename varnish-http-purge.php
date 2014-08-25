<?php
/*
Plugin Name: Varnish HTTP Purge - Multi
Description: Sends HTTP PURGE requests to URLs of changed posts/pages when they are modified.
Version: 1.1
Author: Oliver Payne
License: http://www.apache.org/licenses/LICENSE-2.0
Text Domain: varnish-http-purge-multi
Network: true

Copyright 2013-2014: Mika A. Epstein (email: ipstenu@ipstenu.org)

Original Author: Leon Weidauer ( http:/www.lnwdr.de/ )
Modified By: Oliver Payne

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

        if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin
            require_once('updater.php');
            $config = array(
                'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
                'proper_folder_name' => 'varnish-http-purge-multi', // this is the name of the folder your plugin lives in
                'api_url' => 'https://api.github.com/repos/olipayne/varnish-http-purge-multi', // the github API url of your github repo
                'raw_url' => 'https://raw.github.com/olipayne/varnish-http-purge-multi/master', // the github raw url of your github repo
                'github_url' => 'https://github.com/olipayne/varnish-http-purge-multi', // the github url of your github repo
                'zip_url' => 'https://github.com/olipayne/varnish-http-purge-multi/zipball/master', // the zip url of the github repo
                'sslverify' => true, // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
                'requires' => '3.0', // which version of WordPress does your plugin require?
                'tested' => '3.9.2', // which version of WordPress is your plugin tested up to?
                'readme' => 'README.md' // which file to use as the readme for the version number
            );
            new WP_GitHub_Updater($config);
        }
        defined('varnish-http-purge') || define('varnish-http-purge', true);
        defined('VHP_VARNISH_IP') || define('VHP_VARNISH_IP', false );
        defined('VHP_LOGGING') || define('VHP_LOGGING', false );
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
            'id'    => 'purge-varnish-cache-all',
            'title' => 'Purge Varnish',
            'href'  => wp_nonce_url('?vhp_flush_all', 'varnish-http-purge'),
            'meta'  => array(
                'title' => __('Purge Varnish','varnish-http-purge'),
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

        // Should we enable logging?
        if ( VHP_LOGGING != false ) {
            $logging = VHP_LOGGING;
        }

        // If we made varniship, let it sail
        if ( isset($varniship) && $varniship != null ) {
            // Explode in case we have a comma seperated list. If it's a single IP, then it will be used in the first (and only) loop iteration
            $varniships = explode(',', $varniship);
            // Trim all array values in case it was a ', ' seperated list.
            $varniships = array_map('trim', $varniships);
            foreach ($varniships as $varniship) {
                $purgeme = $p['scheme'].'://'.$varniship.$path.$pregex;
                if ($logging) {
                    $this->logPurge('Purge request sent - '.$purgeme);
                }
                // Cleanup CURL functions to be wp_remote_request and thus better
                // http://wordpress.org/support/topic/incompatability-with-editorial-calendar-plugin
                if ($logging) {
                    $this->logPurge(json_encode(wp_remote_request($purgeme, array('sslverify' => false, 'method' => 'PURGE', 'headers' => array( 'host' => $p['host'], 'X-Purge-Method' => $varnish_x_purgemethod ) ) ) ) );
                } else {
                    wp_remote_request($purgeme, array('sslverify' => false, 'method' => 'PURGE', 'headers' => array( 'host' => $p['host'], 'X-Purge-Method' => $varnish_x_purgemethod ) ) );
                }
            }
        } else {
            // No $varniship is set, send the request to the host
            $purgeme = $p['scheme'].'://'.$p['host'].$path.$pregex;
            $this->logPurge('Purge request sent - '.$purgeme);
            // Cleanup CURL functions to be wp_remote_request and thus better
            // http://wordpress.org/support/topic/incompatability-with-editorial-calendar-plugin
            if ($logging) {
                $this->logPurge(json_encode(wp_remote_request($purgeme, array('sslverify' => false, 'method' => 'PURGE', 'headers' => array( 'host' => $p['host'], 'X-Purge-Method' => $varnish_x_purgemethod ) ) ) ) );
            } else {
                wp_remote_request($purgeme, array('sslverify' => false, 'method' => 'PURGE', 'headers' => array( 'host' => $p['host'], 'X-Purge-Method' => $varnish_x_purgemethod ) ) );
            }
        }


    }

    protected function logPurge($string) {
        $logfile = plugin_dir_path(__FILE__).'purgelog.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logfile, $timestamp.' - '.$string."\n", FILE_APPEND);
    }

    public function purgePost($postId) {

        // If this is a valid post we want to purge the post, the home page and any associated tags & cats
        // If not, purge everything on the site.
        // We should ignore revisions though

        if ( get_post_type($postId) == 'revision' ) {
            return;
        }

        $validPostStatus = array("publish", "trash");
        $thisPostStatus  = get_post_status($postId);

        if ( get_permalink($postId) == true && in_array($thisPostStatus, $validPostStatus) ) {
            // Category & Tag purge based on Donnacha's work in WP Super Cache
            $categories = get_the_category($postId);
            if ( $categories ) {
                $category_base = get_option( 'category_base');
                if ( $category_base == '' )
                    $category_base = '/category/';
                $category_base = trailingslashit( $category_base );
                foreach ($categories as $cat) {
                    array_push($this->purgeUrls, home_url( $category_base . $cat->slug . '/' ) );
                }
            }
            $tags = get_the_tags($postId);
            if ( $tags ) {
                $tag_base = get_option( 'tag_base' );
                if ( $tag_base == '' )
                    $tag_base = '/tag/';
                $tag_base = trailingslashit( str_replace( '..', '', $tag_base ) );
                foreach ($tags as $tag) {
                    array_push($this->purgeUrls, home_url( $tag_base . $tag->slug . '/' ) );
                }
            }

            // Post URL
            array_push($this->purgeUrls, get_permalink($postId) );

			// Feeds
			$feeds = array(get_bloginfo('rdf_url') , get_bloginfo('rss_url') , get_bloginfo('rss2_url'), get_bloginfo('atom_url'), get_bloginfo('comments_atom_url'), get_bloginfo('comments_rss2_url'), get_post_comments_feed_link($postId) );
			foreach ( $feeds as $feed ) {
				array_push($this->purgeUrls, $feed );
			}

			// Home URL
            array_push($this->purgeUrls, home_url() );

        } else {
            array_push($this->purgeUrls, home_url( '?vhp=regex') );
        }
    }

}

$purger = new VarnishPurger();