<?php
/**
 * Purge Strategy Class
 *
 * Extend this class to customize the default behaviour
 * and define it in the VHP_STRATEGY constant
 * or the vhp_purge_strategy_class filter.
 *
 * @property VarnishPurger $plugin
 * @since
 */
class VarnishPurgeStrategy {
	/**
	 * The plugin class
	 *
	 * @var VarnishPurger
	 */
	private VarnishPurger $plugin;
	/**
	 * List of URLs to be purged
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access protected
	 */
	protected array $purge_urls = array();
	/**
	 * @var string[]
	 */
	private array $noarchive_post_type;
	/**
	 * @var string[]
	 */
	private array $invalid_post_type;
	/**
	 * @var string[]
	 */
	private array $valid_post_status;
	/**
	 * @var string
	 */
	private string $rest_api_route = 'wp/v2';
	/**
	 * @var bool
	 */
	private bool $json_disabled = true;
	/**
	 * @var string[]
	 */
	private array $json_disablers;

	public function __construct( VarnishPurger $plugin ) {
		$this->plugin = $plugin;

		$this->noarchive_post_type = array(
			'post',
			'page'
		);
		$this->invalid_post_type   = array(
			'nav_menu_item',
			'revision'
		);
		$this->valid_post_status   = array(
			'publish',
			'private',
			'trash',
			'pending',
			'draft'
		);
		$this->json_disablers      = array(
			'disable-json-api/disable-json-api.php',
		);
		$this->check_rest_api();
	}

	/**
	 * Execute Purge
	 * Run the purge command for the URLs. Calls $this->purge_url for each URL
	 *
	 * @since  1.0
	 * @access protected
	 */
	public function execute_purge() {
		$purge_urls = array_unique( $this->purge_urls );

		// If there are URLs to purge and it's an array, we'll likely purge.
		if ( ! empty( $purge_urls ) && is_array( $purge_urls ) ) {

			// Number of URLs to purge.
			$count = count( $purge_urls );

			// Max posts
			if ( defined( 'VHP_VARNISH_MAXPOSTS' ) && false !== VHP_VARNISH_MAXPOSTS ) {
				$max_posts = VHP_VARNISH_MAXPOSTS;
			} else {
				$max_posts = get_option( 'vhp_varnish_max_posts_before_all' );
			}

			// If there are more than vhp_varnish_max_posts_before_all URLs to purge (default 50),
			// do a purge ALL instead. Else, do the normal.
			if ( $max_posts <= $count ) {
				// Too many URLs, purge all instead.
				$this->purge_all();
			} else {
				// Purge each URL.
				foreach ( $purge_urls as $url ) {
					$this->purge_url( $url );
				}
			}
		} elseif ( isset( $_GET ) ) {
			// Otherwise, if we've passed a GET call...
			if ( isset( $_GET['vhp_flush_all'] ) && check_admin_referer( 'vhp-flush-all' ) ) {
				// Flush Cache recursive.
				$this->purge_all();
			} elseif ( isset( $_GET['vhp_flush_do'] ) && check_admin_referer( 'vhp-flush-do' ) ) {
				if ( 'object' === $_GET['vhp_flush_do'] ) {
					// Flush Object Cache (with a double check).
					if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
						wp_cache_flush();
					}
				} elseif ( 'all' === $_GET['vhp_flush_do'] ) {
					// Flush Cache recursive.
					$this->purge_all();
				} else {
					// Flush the URL we're on.
					$p = wp_parse_url( esc_url_raw( wp_unslash( $_GET['vhp_flush_do'] ) ) );
					if ( ! isset( $p['host'] ) ) {
						return;
					}
					$this->purge_url( esc_url_raw( wp_unslash( $_GET['vhp_flush_do'] ) ) );
				}
			}
		}
	}

	/**
	 * Purge and flush the whole cache
	 *
	 * @access public
	 * @return void
	 * @since  3.9
	 */
	public function purge_all() {
		$this->purge_url( $this->the_home_url() . '/?vhp-regex' );

		// Reset the urls to purge
		$this->purge_urls = [];

		do_action( 'after_full_purge', $this );
	}

	/**
	 * Purge URL
	 * Parse the URL for proxy proxies
	 *
	 * @param string $url - The url to be purged.
	 *
	 * @access protected
	 * @since  1.0
	 */
	public function purge_url( $url ) {
		// Bail early if someone sent a non-URL
		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return;
		}

		$p = wp_parse_url( $url );

		// Bail early if there's no host since some plugins are weird.
		if ( ! isset( $p['host'] ) ) {
			return;
		}

		// Determine if we're using regex to flush all pages or not.
		$pregex         = '';
		$bregex         = '';
		$x_purge_method = 'default';

		$query = array();
		if ( isset( $p['query'] ) ) {
			parse_str( $p['query'], $query );
			if ( isset( $query['vhp-ban-regex'] ) ) {
				$bregex         = ! empty( $query['vhp-ban-regex'] ) ? $query['vhp-ban-regex'] : '.*';
				$x_purge_method = 'ban-regex';
			} elseif ( isset( $query['vhp-regex'] ) ) {
				$pregex         = '.*';
				$x_purge_method = 'regex';
			}
			unset( $query['vhp-ban-regex'], $query['vhp-regex'] );
		}

		// Build a varniship to sail. ⛵️
		$varniship = ( VHP_VARNISH_IP !== false ) ? VHP_VARNISH_IP : get_site_option( 'vhp_varnish_ip' );

		// If there are commas, and for whatever reason this didn't become an array
		// properly, force it.
		if ( ! is_array( $varniship ) && strpos( $varniship, ',' ) !== false ) {
			$varniship = array_map( 'trim', explode( ',', $varniship ) );
		}

		// Now apply filters
		if ( is_array( $varniship ) ) {
			// To each ship:
			for ( $i = 0; $i++; $i < count( $varniship ) ) {
				$varniship[ $i ] = apply_filters( 'vhp_varnish_ip', $varniship[ $i ] );
			}
		} else {
			// To the only ship:
			$varniship = apply_filters( 'vhp_varnish_ip', $varniship );
		}

		// Determine the path.
		$path = ( isset( $p['path'] ) ) ? $p['path'] : '';

		/**
		 * Schema filter
		 *
		 * Allows default http:// schema to be changed to https
		 * varnish_http_purge_schema()
		 *
		 * @since 3.7.3
		 */

		// This is a very annoying check for DreamHost who needs to default to HTTPS without breaking
		// people who've been around before.
		$server_hostname = gethostname();
		switch ( substr( $server_hostname, 0, 3 ) ) {
			case 'dp-':
				$schema_type = 'https://';
				break;
			default:
				$schema_type = 'http://';
				break;
		}
		$schema = apply_filters( 'varnish_http_purge_schema', $schema_type );

		// When we have Varnish IPs, we use them in lieu of hosts.
		if ( isset( $varniship ) && ! empty( $varniship ) ) {
			$all_hosts = ( ! is_array( $varniship ) ) ? array( $varniship ) : $varniship;
		} else {
			// The default is the main host, converted into an array.
			$all_hosts = array( $p['host'] );
		}

		// Since the ship is always an array now, let's loop.
		foreach ( $all_hosts as $one_host ) {

			/**
			 * Allow setting of ports in host name
			 * Credit: davidbarratt - https://github.com/Ipstenu/varnish-http-purge/pull/38/
			 *
			 * (default value: $p['host'])
			 *
			 * @var string
			 * @access public
			 * @since  4.4.0
			 */
			$host_headers = $p['host'];

			// If the URL to be purged has a port, we're going to re-use it.
			if ( isset( $p['port'] ) ) {
				$host_headers .= ':' . $p['port'];
			}

			$parsed_url = $url;

			// Filter URL based on the Proxy IP for nginx compatibility
			if ( 'localhost' === $one_host ) {
				$parsed_url = str_replace( $p['host'], 'localhost', $parsed_url );
			}

			// Create path to purge.
			$purgeme = $schema . $one_host . $path . $pregex;

			// Check the queries...
			if ( ! empty( $query ) ) {
				$purgeme = add_query_arg( $query, $purgeme );
			}

			/**
			 * Filter the purge path
			 *
			 * Allows dynamically changing the purge cache for custom purge location
			 * or systems not supporting .* regex purge for example
			 *
			 * @since 5.1.1
			 */
			$purgeme = apply_filters( 'vhp_purgeme_path', $purgeme, $schema, $one_host, $path, $pregex, $p, $bregex );

			$headers = array(
				'host'           => $host_headers,
				'X-Purge-Method' => $x_purge_method,
			);

			if ( ! empty( $bregex ) ) {
				$headers['X-Ban-Regex'] = $bregex;
			}

			/**
			 * Filters the HTTP headers to send with a PURGE request.
			 *
			 * @since 4.1
			 */
			$headers = apply_filters( 'varnish_http_purge_headers', $headers );

			// Send response.
			// SSL Verify is required here since Varnish is HTTP only, but proxies are a thing.
			$response = wp_remote_request(
				$purgeme,
				array(
					'sslverify' => false,
					'method'    => 'PURGE',
					'headers'   => $headers,
				)
			);

			do_action( 'after_purge_url', $parsed_url, $purgeme, $response, $headers );
		}
	}

	/**
	 * Purge Post
	 * Flush the post
	 *
	 * @param int|string $post_id - The ID of the post to be purged.
	 *
	 * @access public
	 * @since  1.0
	 */
	public function purge_post( $post_id ) {
		$post = get_post( $post_id );

		/**
		 * If this is a valid post we want to purge the post,
		 * the home page and any associated tags and categories
		 */
		$post_status          = get_post_status( $post );
		$is_valid_post_status = apply_filters(
			'vhp_is_valid_post_status',
			in_array( $post_status, $this->valid_post_status, true ),
			$post_status,
			$this->valid_post_status,
			$post,
			$this,
		);

		$post_type          = get_post_type( $post );
		$is_valid_post_type = apply_filters(
			'vhp_is_valid_post_type',
			! in_array( $post_type, $this->invalid_post_type, true ),
			$post_type,
			$this->invalid_post_type,
			$post,
			$this,
		);

		$is_valid_post = apply_filters(
			'vhp_varnish_is_valid_post',
			$is_valid_post_status && $is_valid_post_type,
			$post,
			$this,
		);

		if ( ! $is_valid_post ) {
			return [];
		}

		// multi-dimensional array to collect all our URLs.
		$urls_to_purge = [];

		/**
		 * Post permalink
		 */
		$urls_to_purge['permalink'] = $this->purge_post_permalink( $post );

		/**
		 * API endpoints
		 */
		$urls_to_purge['rest_endpoints'] = $this->purge_post_rest_endpoints( $post );

		/**
		 * Taxonomies
		 */
		$urls_to_purge['taxonomies'] = $this->purge_post_taxonomies( $post );

		/**
		 * Author URLs
		 */
		$urls_to_purge['author_archives'] = $this->purge_author_archive( $post );

		/**
		 * Feeds
		 */
		$urls_to_purge['feeds'] = $this->purge_post_feeds( $post );

		/**
		 * Archives and their feeds
		 */
		$urls_to_purge['post_archives'] = $this->purge_post_archives( $post );

		/**
		 * Home Pages and (if used) posts page
		 */
		$urls_to_purge['posts_pages'] = $this->purge_posts_pages( $post );

		$urls_to_purge = apply_filters(
			'vhp_urls_to_purge',
			$urls_to_purge,
			$post,
			$this,
		);

		$urls_to_purge = apply_filters(
			'vhp_urls_to_purge_flattened',
			$this->flatten_urls( $urls_to_purge ),
			$post,
			$this,
		);

		// Bail early if the array is empty.
		if ( empty( $urls_to_purge ) ) {
			return [];
		}

		// If the DOMAINS setup is defined, we duplicate the URLs
		if ( false !== VHP_DOMAINS ) {
			// Split domains into an array
			$domains           = explode( ',', VHP_DOMAINS );
			$new_urls          = array();
			$filtered_home_url = $this->the_home_url();
			$raw_home_url      = home_url();

			// Loop through all the domains
			foreach ( $domains as $a_domain ) {
				foreach ( $urls_to_purge as $url ) {
					// If the URL contains the filtered home_url, and is NOT equal to the domain we're trying to replace, we will add it to the new urls
					if ( $filtered_home_url !== $a_domain && false !== strpos( $filtered_home_url, $url ) ) {
						$new_urls[] = str_replace( $filtered_home_url, $a_domain, $url );
					}
					// If the URL contains the raw home_url, and is NOT equal to the domain we're trying to replace, we will add it to the new urls
					if ( $raw_home_url !== $a_domain && false !== strpos( $raw_home_url, $url ) ) {
						$new_urls[] = str_replace( $raw_home_url, $a_domain, $url );
					}
				}
			}

			// Merge all the URLs
			array_push( $urls_to_purge, ...$new_urls );
		}

		// Make sure each URL only gets purged once, eh?
		$unique_urls_to_purge = array_unique( $urls_to_purge, SORT_REGULAR );

		array_push( $this->purge_urls, ...$unique_urls_to_purge );

		/*
		 * Filter to add or remove urls to the array of purged urls
		 * @param array $purge_urls the urls (paths) to be purged
		 * @param int $post_id the id of the new/edited post
		 */
		$this->purge_urls = apply_filters( 'vhp_purge_urls', $this->purge_urls, $post_id );

		return $this->purge_urls;
	}

	private function purge_post_permalink( $post ) {
		$post        = get_post( $post );
		$post_status = get_post_status( $post );
		$permalink   = get_permalink( $post );

		$urls = [
			$permalink,
		];

		// Also clean URL for trashed post.
		if ( 'trash' === $post_status ) {
			$previous_permalink = str_replace( '__trashed', '', $permalink );
			$urls[]             = $previous_permalink;
			$urls[]             = trailingslashit( $previous_permalink . 'feed/' );
		}

		// Add in AMP permalink for official WP AMP plugin:
		// https://wordpress.org/plugins/amp/
		if ( function_exists( 'amp_get_permalink' ) ) {
			$urls[] = amp_get_permalink( $post );
		}

		// Regular AMP url for posts if ant of the following are active:
		// https://wordpress.org/plugins/accelerated-mobile-pages/
		if ( defined( 'AMPFORWP_AMP_QUERY_VAR' ) ) {
			$urls[] = trailingslashit( $permalink ) . 'amp/';
		}

		return apply_filters(
			'vhp_purge_post_permalink',
			$urls,
			$post,
			$this,
		);
	}

	private function purge_post_rest_endpoints( $post ) {
		$urls      = [];
		$post      = get_post( $post );
		$post_type = get_post_type( $post );

		/**
		 * JSON API Permalink for the post based on type
		 * We only want to do this if the rest_base exists
		 * But we apparently have to force it for posts and pages (seriously?)
		 */
		if ( ! $this->json_disabled && ! empty( $this->rest_api_route ) ) {
			$post_type_object = get_post_type_object( $post_type );

			if ( null !== $post_type_object && ! empty( $post_type_object->rest_base ) ) {
				$rest_base = $post_type_object->rest_base;
			} elseif ( 'post' === $post_type ) {
				$rest_base = 'posts';
			} elseif ( 'page' === $post_type ) {
				$rest_base = 'pages';
			}

			if ( ! empty( $rest_base ) ) {
				$urls[] = apply_filters(
					'vhp_purge_post_single_rest_endpoint',
					rest_url( $this->rest_api_route . '/' . $rest_base . '/' . $post->ID . '/' ),
					$post,
					$this,
				);
				$urls[] = apply_filters(
					'vhp_purge_post_index_rest_endpoint',
					rest_url( $this->rest_api_route . '/' . $rest_base . '/' ),
					$post,
					$this,
				);
			}
		}

		return apply_filters(
			'vhp_purge_post_rest_endpoints',
			$urls,
			$post,
			$this,
		);
	}

	private function purge_post_taxonomies( $post ) {
		$post = get_post( $post );

		$urls = [];

		// Category purge based on Donnacha's work in WP Super Cache.
		$categories = get_the_category( $post );
		if ( ! empty( $categories ) ) {
//			$category_base = get_site_option( 'category_base' );
//			if ( empty($category_base) ) {
//				$category_base = '/categories/';
//			}
			foreach ( $categories as $cat ) {
				$urls[] = get_category_link( $cat->term_id );
				$urls[] = rest_url( "{$this->rest_api_route}/categories/{$cat->term_id}/" );
			}
		}

		// Tag purge based on Donnacha's work in WP Super Cache.
		$tags = get_the_tags( $post );
		if ( ! empty( $tags ) ) {
			$tag_base = get_site_option( 'tag_base' );
			if ( empty( $tag_base ) ) {
				$tag_base = 'tag';
			}
			$tag_base = self::unslashit( $tag_base );
			foreach ( $tags as $tag ) {
				$urls[] = get_tag_link( $tag->term_id );
				$urls[] = rest_url( "{$this->rest_api_route}/{$tag_base}/{$tag->term_id}/" );
			}
		}

		// Custom Taxonomies: Only show if the taxonomy is public.
		$taxonomies = get_post_taxonomies( $post );
		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$features = (array) get_taxonomy( $taxonomy );
				if ( isset( $features['public'] ) && $features['public'] ) {
					$terms = wp_get_post_terms( $post, $taxonomy );
					foreach ( $terms as $term ) {
						$urls[] = get_term_link( $term );
						$urls[] = rest_url( "{$this->rest_api_route}/{$term->taxonomy}/{$term->slug}/" );
					}
				}
			}
		}

		return apply_filters(
			'vhp_purge_post_taxonomies',
			$urls,
			$post,
			$this,
		);
	}

	private function purge_author_archive( $post ) {
		$author_id = get_post_field( 'post_author', $post );
		$urls      = [
			get_author_posts_url( $author_id ),
			get_author_feed_link( $author_id ),
			rest_url( "{$this->rest_api_route}/users/{$author_id}/" ),
		];

		return apply_filters(
			'vhp_purge_author_archive',
			$urls,
			$post,
			$this,
			$author_id,
		);
	}

	private function purge_post_feeds( $post ) {
		$post = get_post( $post );
		$urls = [
			get_bloginfo_rss( 'rdf_url' ),
			get_bloginfo_rss( 'rss_url' ),
			get_bloginfo_rss( 'rss2_url' ),
			get_bloginfo_rss( 'atom_url' ),
			get_bloginfo_rss( 'comments_rss2_url' ),
			get_post_comments_feed_link( $post->ID )
		];

		return apply_filters(
			'vhp_purge_author_archive',
			$urls,
			$post,
			$this,
		);
	}

	private function purge_post_archives( $post ) {
		$post      = get_post( $post );
		$post_type = get_post_type( $post );
		$urls      = [];

		if ( $post_type && ! in_array( $post_type, $this->noarchive_post_type, true ) ) {
			$urls[] = get_post_type_archive_link( $post_type );
			$urls[] = get_post_type_archive_feed_link( $post_type );
		}

		return apply_filters(
			'vhp_purge_post_archives',
			$urls,
			$post,
			$this,
		);
	}

	private function purge_posts_pages( $post ) {
		$post = get_post( $post );
		$urls = [
			$this->the_home_url(),
		];

		// Ensure we have a page_for_posts setting to avoid empty URL.
		if ( 'page' === get_site_option( 'show_on_front' ) ) {
			$page_for_posts = get_site_option( 'page_for_posts' );
			if ( ! empty( $page_for_posts ) ) {
				$urls[] = get_post_type_archive_feed_link( get_permalink( $page_for_posts ) );
			}
		}

		return apply_filters(
			'vhp_purge_posts_pages',
			$urls,
			$post,
			$this,
		);
	}

	/***************************************************************************
	 * Helpers
	 **************************************************************************/

	public static function unslashit( $string ) {
		return trim( $string, '/\\' );
	}

	/**
	 * @param $array
	 *
	 * @return array
	 */
	private function flatten_urls( $array ) {
		$output = [];

		$values = array_values( (array) $array );
		foreach ( $values as $value ) {
			if ( empty( $value ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				array_push(
					$output,
					...$this->flatten_urls( $value )
				);
				continue;
			}
			if ( is_string( $value ) ) {
				$output[] = $value;
				continue;
			}
		}

		return $output;
	}

	/**
	 * Generate a purge url that bans the url itself (with and without trailing slash)
	 * and any query string, but the sub-resources (more path fragments).
	 *
	 * Adds the query parameter "vhp-ban-regex" with an urlencoded regex to the input url.
	 * In VarnishPurger::purge_url(), the value will be sent as an additional header
	 * "X-Ban-Regex", along with the header "X-Purge-Method: ban-regex".
	 * The query parameter itself is removed. The VCL should contain the following config:
	 *
	 * if (req.http.X-Purge-Method == "ban-regex" && req.http.X-Ban-Regex) {
	 *     ban("obj.http.x-url ~ " + req.http.X-Ban-Regex + " && obj.http.x-host ~ " + req.http.host);
	 *     return (synth(200, "Banned"));
	 * }
	 *
	 * If the VCL does not support this functionality, only the url itself is purged.
	 *
	 * Example of urls to be banned using the default regex:
	 * - /the/path
	 * - /the/path/
	 * - /the/path?
	 * - /the/path/?
	 * - /the/path?foo=bar
	 * - /the/path/?foo=bar
	 * Sub-resources are not banned:
	 * - /the/path/foo
	 * - /the/path/foo/bar
	 *
	 * Useful for REST endpoints which can contain query parameters.
	 *
	 * @param string|null $url   The url to be banned. Only the path will be used in the final regex.
	 * @param string      $regex The regex to be appended to the url.
	 *
	 * @return string
	 *
	 * @see purge_url()
	 */
	public function ban_url_with_any_query_string( $url, $regex = '($|/$|\?.*|/\?.*)' ) {
		return add_query_arg(
			'vhp-ban-regex',
			urlencode(
				'^'
				. untrailingslashit( str_replace( $this->the_home_url(), '', $url ) )
				. $regex
			),
			$url
		);
	}

	/**
	 * The Home URL
	 * Get the Home URL and allow it to be filterable
	 * This is for domain mapping plugins that, for some reason, don't filter
	 * on their own (including WPMU, Ron's, and so on).
	 *
	 * @since 4.0
	 */
	public function the_home_url() {
		return apply_filters( 'vhp_home_url', home_url() );
	}

	/**
	 * Determine the route for the rest API
	 * This will need to be revisited if WP updates the version.
	 * Future me: Consider an array? 4.7-?? use v2, and then adapt from there?
	 *
	 * @return void
	 */
	private function check_rest_api() {
		if ( version_compare( get_bloginfo( 'version' ), '4.7', '>=' ) ) {
			$this->json_disabled = false;

			foreach ( $this->json_disablers as $json_plugin ) {
				if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $json_plugin ) ) {
					$this->json_disabled = true;
				}
			}

			// If json is NOT disabled...
			if ( ! $this->json_disabled ) {
				$this->rest_api_route = 'wp/v2';
			}
		}
	}
}
