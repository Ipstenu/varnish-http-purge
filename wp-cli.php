<?php
/**
 * WP-CLI code
 * @package varnish-http-purge
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

// Bail if WP-CLI is not present.
if ( ! defined( 'WP_CLI' ) ) {
	return;
}

if ( ! class_exists( 'WP_CLI_Varnish_Command' ) ) {

	/**
	 * WP CLI Commands for Varnish.
	 *
	 * @extends WP_CLI_Command
	 */
	class WP_CLI_Varnish_Command extends WP_CLI_Command {


		/**
		 * wildcard
		 *
		 * (default value: false)
		 *
		 * @var bool
		 * @access private
		 */
		private $wildcard = false;


		/**
		 * __construct function.
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {
			$this->varnish_purge = new VarnishPurger();
		}

		/**
		 * Forces cache to purge.
		 *
		 * ## OPTIONS
		 *
		 * [<url>]
		 * : Specify a URL
		 *
		 * [--wildcard]
		 * : Include include all subfolders and files.
		 *
		 * ## EXAMPLES
		 *
		 *      wp varnish purge
		 *      wp varnish purge http://example.com/wp-content/themes/twentyeleventy/style.css
		 *      wp varnish purge http://example.com/wp-content/themes/ --wildcard
		 */
		public function purge( $args, $assoc_args ) {

			$wp_version  = get_bloginfo( 'version' );
			$cli_version = WP_CLI_VERSION;

			// Set the URL/path.
			if ( ! empty( $args ) ) {
				list( $url ) = $args; }

			// If wildcard is set, or the URL argument is empty then treat this as a full purge.
			$pregex = '';
			$wild   = '';
			if ( isset( $assoc_args['wildcard'] ) || empty( $url ) ) {
				$pregex = '/?vhp-regex';
				$wild   = '.*';
			}

			wp_create_nonce( 'vhp-flush-cli' );

			// If the URL is not empty, sanitize. Else use home URL.
			if ( ! empty( $url ) ) {
				$url = esc_url( $url );

				// If it's a regex, let's make sure we don't have a trailing slash.
				if ( isset( $assoc_args['wildcard'] ) ) {
					$url = rtrim( $url, '/' );
				}
			} else {
				$url = $this->varnish_purge->the_home_url();
			}

			if ( version_compare( $wp_version, '4.6', '>=' ) && ( version_compare( $cli_version, '0.25.0', '<' ) || version_compare( $cli_version, '0.25.0-alpha', 'eq' ) ) ) {

				// translators: %1$s is the version of WP-CLI.
				// translators: %2$s is the version of WordPress.
				WP_CLI::log( sprintf( __( 'This plugin does not work on WP 4.6 and up, unless WP-CLI is version 0.25.0 or greater. You\'re using WP-CLI %1$s and WordPress %2$s.', 'varnish-http-purge' ), $cli_version, $wp_version ) );
				WP_CLI::log( __( 'To flush your cache, please run the following command:', 'varnish-http-purge' ) );
				WP_CLI::log( sprintf( '$ curl -X PURGE "%s"', $url . $wild ) );
				WP_CLI::error( __( 'Your cache must be purged manually.', 'varnish-http-purge' ) );
			}

			$this->varnish_purge->purge_url( $url . $pregex );

			if ( WP_DEBUG === true ) {
				// translators: %1$s is the URL being flushed.
				// translators: %2$s are the params being flushed.
				WP_CLI::log( sprintf( __( 'Proxy Cache Purge is flushing the URL %1$s with params %2$s.', 'varnish-http-purge' ), $url, $pregex ) );
			}

			WP_CLI::success( __( 'Proxy Cache Purge has flushed your cache.', 'varnish-http-purge' ) );
		}

		/**
		 * Activate, deactivate, or toggle Development Mode.
		 *
		 * ## OPTIONS
		 *
		 * [<state>]
		 * : Change the state of Development Mode
		 * ---
		 * options:
		 *   - activate
		 *   - deactivate
		 *   - toggle
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *      wp varnish devmode activate
		 *      wp varnish devmode deactivate
		 *      wp varnish devmode toggle
		 */
		public function devmode( $args, $assoc_args ) {

			$valid_modes = array( 'activate', 'deactivate', 'toggle' );
			$devmode     = get_site_option( 'vhp_varnish_devmode', VarnishPurger::$devmode );

			// Check for valid arguments.
			if ( empty( $args[0] ) ) {
				// No params, echo state.
				$state = ( $devmode['active'] ) ? __( 'activated', 'varnish-http-purge' ) : __( 'deactivated', 'varnish-http-purge' );
				// translators: %s is the state of dev mode.
				WP_CLI::log( sprintf( __( 'Proxy Cache Purge development mode is currently %s.', 'varnish-http-purge' ), $state ) );
			} elseif ( ! in_array( $args[0], $valid_modes, true ) ) {
				// Invalid Params, warn.
				// translators: %s is the bad command.
				WP_CLI::error( sprintf( __( '%s is not a valid subcommand for development mode.', 'varnish-http-purge' ), sanitize_text_field( $args[0] ) ) );
			} else {
				// Run the toggle!
				$result = VarnishDebug::devmode_toggle( sanitize_text_field( $args[0] ) );
				$state  = ( $result ) ? __( 'activated', 'varnish-http-purge' ) : __( 'deactivated', 'varnish-http-purge' );
				// translators: %s is the state of dev mode.
				WP_CLI::success( sprintf( __( 'Proxy Cache Purge development mode has been %s.', 'varnish-http-purge' ), $state ) );
			}
		} // End devmode.

		/**
		 * Runs a debug check of the site to see if there are any known issues.
		 *
		 * ## OPTIONS
		 *
		 * [<url>]
		 * : Specify a URL for testing against. Default is the home URL.
		 *
		 * [--include-headers]
		 * : Include headers in debug check output.
		 *
		 * [--include-grep]
		 * : Also grep active theme and plugin directories for common issues.
		 *
		 * [--format=<format>]
		 * : Render output in a particular format.
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - csv
		 *   - json
		 *   - yaml
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *      wp varnish debug
		 *
		 *      wp varnish debug http://example.com/wp-content/themes/twentyeleventy/style.css
		 */
		public function debug( $args, $assoc_args ) {

			// Set the URL/path.
			if ( ! empty( $args ) ) {
				list( $url ) = $args;
			}

			if ( empty( $url ) ) {
				$url = esc_url( $this->varnish_purge->the_home_url() );
			}

			WP_CLI::log( __( 'Robots are scanning your site for possible issues with caching... ', 'varnish-http-purge' ) );

			if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'include-grep' ) ) {
				$pattern = '(PHPSESSID|session_start|start_session|$cookie|setCookie)';
				// translators: %s is the pattern string.
				WP_CLI::log( sprintf( __( 'Grepping for: %s.', 'varnish-http-purge' ), $pattern ) );
				WP_CLI::log( '' );
				$paths = array(
					get_template_directory(),
					get_stylesheet_directory(),
				);
				foreach ( wp_get_active_and_valid_plugins() as $plugin_path ) {
					// We don't care about our own plugin.
					if ( false !== stripos( $plugin_path, 'varnish-http-purge/varnish-http-purge.php' ) ) {
						continue;
					}
					$paths[] = dirname( $plugin_path );
				}
				$paths = array_unique( $paths );
				foreach ( $paths as $path ) {
					$cmd = sprintf(
						// Greps for matches and removes ABSPATH from filepath.
						"grep --include=*.php -RE '%s' %s | cut -d '/' -f %d-",
						$pattern,
						escapeshellarg( $path ),
						substr_count( ABSPATH, '/' ) + 1
					);
					system( $cmd );
				}
				WP_CLI::log( '' );
				WP_CLI::log( __( 'Grep complete. If no data was output, you\'re good!', 'varnish-http-purge' ) );
			}

			// Include the debug code.
			if ( ! class_exists( 'VarnishDebug' ) ) {
				include 'debug.php';
			}

			// Validate the URL.
			$valid_url = VarnishDebug::is_url_valid( $url );

			if ( 'valid' !== $valid_url ) {
				switch ( $valid_url ) {
					case 'empty':
					case 'domain':
						WP_CLI::error( __( 'You must provide a URL on your own domain to scan.', 'varnish-http-purge' ) );
						break;
					case 'invalid':
						WP_CLI::error( __( 'You have entered an invalid URL address.', 'varnish-http-purge' ) );
						break;
					default:
						WP_CLI::error( __( 'An unknown error has occurred.', 'varnish-http-purge' ) );
						break;
				}
			}
			$varnishurl = get_site_option( 'vhp_varnish_url', $url );

			// Get the response and headers.
			$remote_get = VarnishDebug::remote_get( $varnishurl );
			$headers    = wp_remote_retrieve_headers( $remote_get );

			if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'include-headers' ) ) {
				WP_CLI::log( 'Headers:' );
				foreach ( $headers as $key => $value ) {
					if ( is_array( $value ) ) {
						$value = implode( ', ', $value );
					}
					WP_CLI::log( " - {$key}: {$value}" );
				}
			}

			// Preflight checklist.
			$preflight = VarnishDebug::preflight( $remote_get );

			// Check for Remote IP.
			$remote_ip = VarnishDebug::remote_ip( $headers );

			// Get the IP.
			if ( false !== VHP_VARNISH_IP ) {
				$varniship = VHP_VARNISH_IP;
			} else {
				$varniship = get_site_option( 'vhp_varnish_ip' );
			}

			if ( false === $preflight['preflight'] ) {
				WP_CLI::error( $preflight['message'] );
			} else {
				$results = VarnishDebug::get_all_the_results( $headers, $remote_ip, $varniship );

				// Generate array.
				foreach ( $results as $type => $content ) {
					$items[] = array(
						'name'    => $type,
						'status'  => ucwords( $content['icon'] ),
						'message' => $content['message'],
					);
				}

				$format = ( isset( $assoc_args['format'] ) ) ? $assoc_args['format'] : 'table';

				// Output the data.
				WP_CLI\Utils\format_items( $format, $items, array( 'name', 'status', 'message' ) );
			}
		} // End Debug.
	}
}

WP_CLI::add_command( 'varnish', 'WP_CLI_Varnish_Command' );
