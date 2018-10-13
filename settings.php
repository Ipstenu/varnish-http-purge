<?php
/**
 * Settings Code
 * @package varnish-http-purge
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Status Class
 *
 * @since 4.0
 */
class VarnishStatus {
	/**
	 * Construct
	 * Fires when class is constructed, adds init hook
	 *
	 * @since 4.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_filter( 'admin_footer_text', array( &$this, 'admin_footer' ), 1, 2 );
	}

	/**
	 * Admin init Callback
	 *
	 * @since 4.0
	 */
	public function admin_init() {
		$this->register_settings();
		$this->register_check_caching();
	}

	/**
	 * Admin Menu Callback
	 *
	 * @since 4.0
	 */
	public function admin_menu() {
		add_menu_page( __( 'Proxy Cache Purge', 'varnish-http-purge' ), __( 'Proxy Cache', 'varnish-http-purge' ), 'manage_options', 'varnish-page', array( &$this, 'settings_page' ), VarnishPurger::get_icon_svg( true, '#82878c' ), 75 );
		add_submenu_page( 'varnish-page', __( 'Proxy Cache Purge', 'varnish-http-purge' ), __( 'Settings', 'varnish-http-purge' ), 'manage_options', 'varnish-page', array( &$this, 'settings_page' ) );
		add_submenu_page( 'varnish-page', __( 'Check Caching', 'varnish-http-purge' ), __( 'Check Caching', 'varnish-http-purge' ), 'manage_options', 'varnish-check-caching', array( &$this, 'check_caching_page' ) );
	}

	/**
	 * Register Settings
	 *
	 * @since 4.0.2
	 */
	public function register_settings() {
		// Development Mode Settings.
		register_setting( 'vhp-settings-devmode', 'vhp_varnish_devmode', array( &$this, 'settings_devmode_sanitize' ) );
		add_settings_section( 'vhp-settings-devmode-section', __( 'Development Mode Settings', 'varnish-http-purge' ), array( &$this, 'options_settings_devmode' ), 'varnish-devmode-settings' );
		add_settings_field( 'varnish_devmode', __( 'Development Mode', 'varnish-http-purge' ), array( &$this, 'settings_devmode_callback' ), 'varnish-devmode-settings', 'vhp-settings-devmode-section' );

		// IP Settings.
		register_setting( 'vhp-settings-ip', 'vhp_varnish_ip', array( &$this, 'settings_ip_sanitize' ) );
		add_settings_section( 'vhp-settings-ip-section', __( 'Configure Custom IP', 'varnish-http-purge' ), array( &$this, 'options_settings_ip' ), 'varnish-ip-settings' );
		add_settings_field( 'varnish_ip', __( 'Set Custom IP', 'varnish-http-purge' ), array( &$this, 'settings_ip_callback' ), 'varnish-ip-settings', 'vhp-settings-ip-section' );
	}

	/**
	 * Options Settings - Dev Mode
	 *
	 * @since 4.6
	 */
	public function options_settings_devmode() {
		?>
		<p><a name="#configuredevmode"></a><?php esc_html_e( 'In Development Mode, WordPress will prevent visitors from seeing cached content on your site. You can enable this for 24 hours, after which it will automatically disable itself. This will make your site run slower, so please use with caution.', 'varnish-http-purge' ); ?></p>
		<p><?php echo wp_kses_post( __( 'If you need to activate development mode for extended periods of time, you can add <code>define( \'VHP_DEVMODE\', true );</code> in your wp-config file.', 'varnish-http-purge' ) ); ?></p>
		<?php
	}

	/**
	 * Settings Dev Mode Callback
	 *
	 * @since 4.0
	 */
	public function settings_devmode_callback() {

		$devmode = get_site_option( 'vhp_varnish_devmode', VarnishPurger::$devmode );
		$active  = ( isset( $devmode['active'] ) ) ? $devmode['active'] : false;
		$active  = ( VHP_DEVMODE ) ? true : $active;
		$expire  = current_time( 'timestamp' ) + DAY_IN_SECONDS;
		?>
		<input type="hidden" name="vhp_varnish_devmode[expire]" value="<?php $expire; ?>" />
		<input type="checkbox" name="vhp_varnish_devmode[active]" value="true" <?php disabled( VHP_DEVMODE ); ?> <?php checked( $active, true ); ?> />
		<label for="vhp_varnish_devmode['active']">
			<?php
			if ( $active && isset( $devmode['expire'] ) && ! VHP_DEVMODE ) {
				$timestamp = date_i18n( get_site_option( 'date_format' ), $devmode['expire'] ) . ' @ ' . date_i18n( get_site_option( 'time_format' ), $devmode['expire'] );
				// translators: %s is the time (in hours) until Development Mode expires.
				echo sprintf( esc_html__( 'Development Mode is active until %s. It will automatically disable after that time.', 'varnish-http-purge' ), esc_html( $timestamp ) );
			} elseif ( VHP_DEVMODE ) {
				esc_attr_e( 'Development Mode has been activated via wp-config and cannot be deactivated here.', 'varnish-http-purge' );
			} else {
				esc_attr_e( 'Activate Development Mode', 'varnish-http-purge' );
			}
			?>
		</label>
		<?php
	}

	/**
	 * Sanitization and validation for Dev Mode
	 *
	 * @param mixed $input - the input to be sanitized.
	 * @since 4.6.0
	 */
	public function settings_devmode_sanitize( $input ) {

		$output      = array();
		$expire      = current_time( 'timestamp' ) + DAY_IN_SECONDS;
		$set_message = __( 'Something has gone wrong!', 'varnish-http-purge' );
		$set_type    = 'error';

		if ( empty( $input ) ) {
			return; // do nothing.
		} else {
			$output['active'] = ( isset( $input['active'] ) || $input['active'] ) ? true : false;
			$output['expire'] = ( isset( $input['expire'] ) && is_int( $input['expire'] ) ) ? $input['expire'] : $expire;
			$set_message      = ( $output['active'] ) ? __( 'Development Mode activated for the next 24 hours.', 'varnish-http-purge' ) : __( 'Development Mode dectivated.', 'varnish-http-purge' );
			$set_type         = 'updated';
		}

		// If it's true then we're activating so let's kill the cache.
		if ( $output['active'] ) {
			VarnishPurger::purge_url( VarnishPurger::the_home_url() . '/?vhp-regex' );
		}

		add_settings_error( 'vhp_varnish_devmode', 'varnish-devmode', $set_message, $set_type );
		return $output;
	}

	/**
	 * Options Settings - IP Address
	 *
	 * @since 4.0
	 */
	public function options_settings_ip() {
		?>
		<p><a name="#configureip"></a><?php esc_html_e( 'There are cases when a custom IP Address is needed to for the plugin to properly communicate with the cache service. If you\'re using a CDN like Cloudflare or a Firewall Proxy like Sucuri, or your cache is Nginx based, you may need to customize this setting.', 'varnish-http-purge' ); ?></p>
		<p><?php esc_html_e( 'Normally your Proxy Cache IP is the IP address of the server where your caching service (i.e. Varnish or Nginx) is installed. It must an address used by your cache service. If you use multiple IPs, or have customized your ACLs, you\'ll need to pick one that doesn\'t conflict with your other settings. For example, if you have Varnish listening on a public and private IP, pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1 or localhost).', 'varnish-http-purge' ); ?></p>
		<p><?php esc_html_e( 'If your webhost set the service up for you, as is the case with DreamPress or WP Engine, ask them for the specifics.', 'varnish-http-purge' ); ?></p>
		<p><strong><?php esc_html_e( 'If you aren\'t sure what to do, contact your webhost or server admin before making any changes.', 'varnish-http-purge' ); ?></strong></p>
		<?php
	}

	/**
	 * Settings IP Callback
	 *
	 * @since 4.0
	 */
	public function settings_ip_callback() {

		$disabled = false;
		if ( false !== VHP_VARNISH_IP ) {
			$disabled  = true;
			$varniship = VHP_VARNISH_IP;
		} else {
			$varniship = get_site_option( 'vhp_varnish_ip' );
		}

		echo '<input type="text" id="vhp_varnish_ip" name="vhp_varnish_ip" value="' . esc_attr( $varniship ) . '" size="25" ' . disabled( $disabled, true ) . '/>';
		echo '<label for="vhp_varnish_ip">';

		if ( $disabled ) {
			esc_html_e( 'A Proxy Cache IP has been defined in your wp-config file, so it is not editable in settings.', 'varnish-http-purge' );
		} else {
			esc_html_e( 'Example: ', 'varnish-http-purge' );
			echo '<code>123.45.67.89</code> or <code>localhost</code>';
		}

		echo '</label>';
	}

	/**
	 * Sanitization and validation for IP
	 *
	 * @param mixed $input - the input to be sanitized.
	 * @since 4.0
	 */
	public function settings_ip_sanitize( $input ) {

		$output      = '';
		$set_message = __( 'You have entered an invalid IP address.', 'varnish-http-purge' );
		$set_type    = 'error';

		if ( empty( $input ) ) {
			return; // do nothing.
		} elseif ( 'localhost' === $input || filter_var( $input, FILTER_VALIDATE_IP ) ) {
			$set_message = 'Proxy Cache IP Updated.';
			$set_type    = 'updated';
			$output      = filter_var( $input, FILTER_VALIDATE_IP );
		}

		add_settings_error( 'vhp_varnish_ip', 'varnish-ip', $set_message, $set_type );
		return $output;
	}

	/**
	 * Register Check Caching
	 *
	 * @since 4.0
	 */
	public function register_check_caching() {
		register_setting( 'varnish-http-purge-url', 'vhp_varnish_url', array( &$this, 'varnish_url_sanitize' ) );
		add_settings_section( 'varnish-url-settings-section', __( 'Check Caching Status', 'varnish-http-purge' ), array( &$this, 'options_check_caching_scan' ), 'varnish-url-settings' );
		add_settings_field( 'varnish_url', __( 'Check A URL On Your Site: ', 'varnish-http-purge' ), array( &$this, 'check_caching_callback' ), 'varnish-url-settings', 'varnish-url-settings-section' );
	}

	/**
	 * Options Callback - URL Scanner
	 *
	 * @since 4.0
	 */
	public function options_check_caching_scan() {
		?>
		<p><?php esc_html_e( 'This feature performs a check of the most common issues that prevents your site from caching properly. This feature is provided to help you in resolve potential conflicts on your own. When filing an issue with your web-host, we recommend you include the output in your ticket.', 'varnish-http-purge' ); ?></p>
		<h4><?php esc_html_e( 'Privacy Note', 'varnish-http-purge' ); ?></h4>
		<p>
		<?php
			// translators: %s is a link to the readme for the detection service.
			printf( wp_kses_post( __( '<strong>This check uses <a href="%s">a remote service hosted on DreamObjects</a></strong>.', 'varnish-http-purge' ) ), 'https://varnish-http-purge.objects-us-east-1.dream.io/readme.txt' );
		?>
		</p>
		<p><?php esc_html_e( 'The service used only for providing up to date compatibility checks on plugins and themes that may conflict with running a server based cache. No personally identifying information regarding persons running this check, nor the plugins and themes in use on this site will be transmitted. The bare minimum of usage information is collected, concerning only IPs and domains making requests of the service. If you do not wish to use this service, please do not use this feature.', 'varnish-http-purge' ); ?></p>
		<?php

		// If there's no post made, let's not...
		// @codingStandardsIgnoreStart
		if ( ! isset( $_REQUEST['settings-updated'] ) || ! $_REQUEST['settings-updated'] ) {
			return;
		}
		// @codingStandardsIgnoreEnd

		// Set icons.
		$icons = array(
			'awesome' => '<span class="dashicons dashicons-heart" style="color:#46B450;"></span>',
			'good'    => '<span class="dashicons dashicons-thumbs-up" style="color:#00A0D2;"></span>',
			'warning' => '<span class="dashicons dashicons-warning" style="color:#FFB900"></span>',
			'notice'  => '<span class="dashicons dashicons-flag" style="color:#826EB4;">',
			'bad'     => '<span class="dashicons dashicons-thumbs-down" style="color:#DC3232;"></span>',
		);

		// Get the base URL to start.
		$url        = esc_url( VarnishPurger::the_home_url() );
		$varnishurl = get_site_option( 'vhp_varnish_url', $url );

		// Is this a good URL?
		$valid_url = VarnishDebug::is_url_valid( $varnishurl );
		if ( 'valid' === $valid_url ) {
			// Get the response and headers.
			$remote_get = VarnishDebug::remote_get( $varnishurl );
			$headers    = wp_remote_retrieve_headers( $remote_get );

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
			?>

			<h4>
			<?php
				// translators: %s is the URL someone asked to scan.
				printf( esc_html__( 'Results for %s ', 'varnish-http-purge' ), esc_url_raw( $varnishurl ) );
			?>
			</h4>

			<table class="wp-list-table widefat fixed posts">

			<?php
			// If we failed the preflight checks, we fail.
			if ( ! $preflight['preflight'] ) {
				?>
				<tr>
					<td width="40px"><?php echo wp_kses_post( $icons['bad'] ); ?></td>
					<td><?php echo wp_kses_post( $preflight['message'] ); ?></td>
				</tr>
				<?php
			} else {
				// We passed the checks, let's get the data!
				$output = VarnishDebug::get_all_the_results( $headers, $remote_ip, $varniship );

				foreach ( $output as $subject => $item ) {
					if ( $item && is_array( $item ) ) {
						?>
							<tr>
								<td width="20px"><?php echo wp_kses_post( $icons[ $item['icon'] ] ); ?></td>
								<td width="180px"><strong><?php echo wp_kses_post( $subject ); ?></strong></td>
								<td><?php echo wp_kses_post( $item['message'] ); ?></td>
							</tr>
							<?php
					}
				}
			}
			?>
			</table>

			<?php
			if ( false !== $preflight['preflight'] ) {
				?>
				<h4><?php esc_html_e( 'Technical Details', 'varnish-http-purge' ); ?></h4>
				<table class="wp-list-table widefat fixed posts">
					<?php
					if ( ! empty( $headers[0] ) ) {
						echo '<tr><td width="200px">&nbsp;</td><td>' . wp_kses_post( $headers[0] ) . '</td></tr>';
					}
					foreach ( $headers as $header => $key ) {
						if ( '0' !== $header ) {
							if ( is_array( $key ) ) {
								$content = print_r( $key, true );
							} else {
								$content = wp_kses_post( $key );
							}
							echo '<tr><td width="200px" style="text-align:right;">' . wp_kses_post( ucfirst( $header ) ) . ':</td><td>' . wp_kses_post( $content ) . '</td></tr>';
						}
					}
					?>
				</table>
				<?php
			}
		}
	}

	/**
	 * URL Callback
	 *
	 * @since 4.0
	 */
	public function check_caching_callback() {
		$url        = esc_url( VarnishPurger::the_home_url() );
		$varnishurl = get_site_option( 'vhp_varnish_url', $url );
		echo '<input type="text" id="vhp_varnish_url" name="vhp_varnish_url" value="' . esc_url( $varnishurl ) . '" size="50" />';
	}

	/**
	 * Sanitization and validation for URL
	 *
	 * @param mixed $input - the input to be sanitized.
	 * @since 4.0
	 */
	public function varnish_url_sanitize( $input ) {

		// Defaults values.
		$output   = esc_url( VarnishPurger::the_home_url() );
		$set_type = 'error';

		if ( empty( $input ) ) {
			$set_message = __( 'You must enter a URL from your own domain to scan.', 'varnish-http-purge' );
		} else {
			$valid_url = VarnishDebug::is_url_valid( esc_url( $input ) );

			switch ( $valid_url ) {
				case 'empty':
				case 'domain':
					$set_message = __( 'You must provide a URL on your own domain to scan.', 'varnish-http-purge' );
					break;
				case 'invalid':
					$set_message = __( 'You have entered an invalid URL address.', 'varnish-http-purge' );
					break;
				case 'valid':
					$set_type    = 'updated';
					$set_message = __( 'URL Scanned.', 'varnish-http-purge' );
					$output      = esc_url( $input );
					break;
				default:
					$set_message = __( 'An unknown error has occurred.', 'varnish-http-purge' );
					break;
			}
		}

		if ( isset( $set_message ) ) {
			add_settings_error( 'vhp_varnish_url', 'varnish-url', $set_message, $set_type );
		}
		return $output;
	}

	/**
	 * Call settings page
	 *
	 * @since 4.0
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<?php settings_errors(); ?>
			<h1><?php esc_html_e( 'Proxy Cache Purge Settings', 'varnish-http-purge' ); ?></h1>

			<p><?php esc_html_e( 'Proxy Cache Purge can empty the cache for different server based caching systems, including Varnish and nginx. For most users, there should be no configuration necessary as the plugin is intended to work silently, behind the scenes.', 'varnish-http-purge' ); ?></p>

			<?php
			if ( ! is_multisite() ) {
				?>
				<form action="options.php" method="POST" >
				<?php
					settings_fields( 'vhp-settings-devmode' );
					do_settings_sections( 'varnish-devmode-settings' );
					submit_button( __( 'Save Settings', 'varnish-http-purge' ), 'primary' );
				?>
				</form>

				<form action="options.php" method="POST" >
				<?php
					settings_fields( 'vhp-settings-ip' );
					do_settings_sections( 'varnish-ip-settings' );
					submit_button( __( 'Save IP', 'varnish-http-purge' ), 'secondary' );
				?>
				</form>
				<?php
			} else {
				?>
				<p><?php esc_html_e( 'Editing these settings via the Dashboard is disabled on Multisite as incorrect edits can prevent your network from loading entirely. You can toggle debug mode globally using the admin toolbar option, and you should define your Proxy IP directly into your wp-config file for best results.', 'varnish-http-purge' ); ?></p>
				<p><?php esc_html_e( 'The cache check page remains available to assist you in determining if pages on your site are properly cached by your server.', 'varnish-http-purge' ); ?></p>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Call the Check Caching
	 *
	 * @since 4.6.0
	 */
	public function check_caching_page() {
		?>
		<div class="wrap">

			<?php settings_errors(); ?>
			<h1><?php esc_html_e( 'Is Caching Working?', 'varnish-http-purge' ); ?></h1>

			<form action="options.php" method="POST" >
			<?php
				settings_fields( 'varnish-http-purge-url' );
				do_settings_sections( 'varnish-url-settings' );
				submit_button( __( 'Check URL', 'varnish-http-purge' ), 'primary' );
			?>
			</form>

		</div>
		<?php
	}

	/**
	 * When user is on one of our admin pages, display footer text
	 * that graciously asks them to rate us.
	 *
	 * @since 4.6.4
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function admin_footer( $text ) {

		global $current_screen;

		if ( ! empty( $current_screen->parent_base ) && strpos( $current_screen->parent_base, 'varnish-page' ) !== false ) {
			$review_url  = 'https://wordpress.org/support/plugin/varnish-http-purge/reviews/?filter=5#new-post';
			$dream_url   = 'https://dreamhost.com/dreampress/';
			$footer_text = sprintf(
				wp_kses(
					/* translators: $1$s - DreamHost URL; $2$s - plugin name; $3$s - WP.org review link; $4$s - WP.org review link. */
					__( 'Brought to you <a href="%1$s" target="_blank" rel="noopener noreferrer">DreamHost</a>. Please rate %2$s <a href="%3$s" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733;</a> on <a href="%4$s" target="_blank" rel="noopener">WordPress.org</a> to help us spread the word.', 'varnish-http-purge' ),
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
					)
				),
				$dream_url,
				'<strong>Proxy Cache Purge</strong>',
				$review_url,
				$review_url
			);
			$text = $footer_text;
		}

		return $text;
	}
}

new VarnishStatus();
