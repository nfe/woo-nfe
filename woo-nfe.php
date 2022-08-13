<?php
/**
 * NFe for Woocommerce plugin.
 *
 * @author            NFe.io
 *
 * @see              https://github.com/nfe/woo-nfe
 * @since             1.0.8
 * @package          Woo_Nfe
 *
 * @wordpress-plugin
 * Plugin Name:       NFe for Woocommerce
 * Plugin URI:        https://github.com/nfe/woo-nfe
 * Description:       Extension for connecting to NFe.io API
 * Version:           1.3.1
 * Author:            NFe.io
 * Author URI:        https://nfe.io
 * Developer:         Project contributors
 * Developer URI:     https://github.com/nfe/woo-nfe/graphs/contributors
 * Text Domain:       woo-nfe
 * Domain Path:       /languages
 * Network:           false
 *
 * WC requires at least: 4.7.0
 * WC tested up to: 6.1.0
 *
 * Copyright: © 2022 NFe.io
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce_NFe' ) ) {
	/**
	 * WooCommerce NFe.io Main Class.
	 *
	 * @since 1.0.0
	 */
	final class WooCommerce_NFe {
		// **
		// * The full path and filename of the file of plugin's main file.
		// *
		// * @var string
		// */
		// public $file;
		//
		// **
		// * The full path and filename of the file of plugin's main file.
		// *
		// * @var string
		// */
		// public $version;
		//
		// **
		// * Flag to indicate whether this extension is running already.
		// *
		// * @var bool
		// */
		private $is_running = false;

		/**
		 * A dummy constructor to prevent WooCommerce_NFe from being loaded more than once.
		 *
		 * @since 1.0.0
		 * @see WooCommerce_NFe::instance()
		 */
		public function __construct() {
			// Do nothing here
		}

		/**
		 * Main instance.
		 *
		 * @since 1.0.0
		 *
		 * @return instance
		 */
		public static function instance() {
			// Store the instance locally to avoid private static replication.
			static $instance = null;

			// Only run these methods if they haven't been run previously.
			if ( null === $instance ) {
				$instance = new WooCommerce_NFe();
				// $instance = new WooCommerce_NFe(__FILE__, '1.0.0');
				$instance->setup_globals();
				$instance->dependencies();
				$instance->includes();
				$instance->setup_hooks();
			}

			// Always return the instance.
			return $instance;
		}

		//
		// **
		// * Constructor.
		// *
		// * @param string $file    The full path and filename of the file of plugin's
		// *                        main file.
		// * @param string $version The full path and filename of the file of plugin's
		// *                        main file.
		// */
		// public function __construct( $file, $version ) {
		// $this->file    = $file;
		// $this->version = $version;
		// }

		/**
		 * Run the extension.
		 *
		 * @return bool Returns true when it's running
		 */
		public function run() {
			if ( $this->is_running ) {
				return false;
			}
			//
			// $this->includes();
			// $this->wc_hooks();

			$this->is_running = true;

			return $this->is_running;
		}

		/**
		 * Load Localisation files.
		 *
		 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
		 *
		 * Locales found in:
		 *      - WP_LANG_DIR/woo-nfe/woo-nfe-LOCALE.mo
		 *      - WP_LANG_DIR/plugins/woo-nfe-LOCALE.mo
		 */
		public function load_plugin_textdomain() {
			$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$locale = apply_filters( 'plugin_locale', $locale, 'woo-nfe' );

			unload_textdomain( 'woo-nfe' );
			load_textdomain( 'woo-nfe', WP_LANG_DIR . '/woo-nfe/woo-nfe-' . $locale . '.mo' );
			load_plugin_textdomain( 'woo-nfe', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Adds our custom WC_NFe_Integration integration to WooCommerce.
		 *
		 * @since 1.0.0
		 *
		 * @param array $integrations wooCommerce Integrations
		 *
		 * @return array
		 */
		public function nfe_integration( $integrations ) {
			$integrations[] = 'WC_NFe_Integration';

			return $integrations;
		}

		/**
		 * SOAPClient missing notice.
		 *
		 * @since 1.0.0
		 */
		public function soap_missing_notice() {
			include $this->includes_dir . 'admin/views/html-notice-missing-soap-client.php';
		}

		/**
		 * WooCommerce missing notice.
		 *
		 * @since 1.0.0
		 */
		public function woocommerce_missing_notice() {
			include $this->includes_dir . 'admin/views/html-notice-missing-woocommerce.php';
		}

		/**
		 * Action links.
		 *
		 * @since 1.0.0
		 *
		 * @param array $links links
		 *
		 * @return array
		 */
		public function plugin_action_links( $links ) {
			return array_merge(
				array(
					'<a href="' . esc_url( WOOCOMMERCE_NFE_SETTINGS_URL ) . '">' . __( 'Settings', 'woo-nfe' ) . '</a>',
				),
				$links
			);
		}

		/**
		 * Sets some globals for the plugin.
		 *
		 * @since 1.0.0
		 */
		private function setup_globals() {
			$this->domain       = 'woo-nfe';
			$this->name         = 'WooCommerce NFe';
			$this->file         = __FILE__;
			$this->basename     = plugin_basename( $this->file );
			$this->plugin_dir   = plugin_dir_path( $this->file );
			$this->plugin_url   = plugin_dir_url( $this->file );
			$this->includes_dir = trailingslashit( $this->plugin_dir . 'includes' );

			// WooCommerce Webhook Callback.
			if ( ! defined( 'WC_API_CALLBACK' ) ) {
				define( 'WC_API_CALLBACK', 'nfe_webhook' );
			}
		}

		/**
		 * Include needed files.
		 *
		 * @since 1.0.0
		 */
		private function includes() {
			// NFe Client-PHP API.
			require $this->plugin_dir . 'li/client-php/lib/init.php';

			// Admin.
			require $this->includes_dir . 'nfe-functions.php';

			require $this->includes_dir . 'admin/class-settings.php';

			require $this->includes_dir . 'admin/class-ajax.php';

			require $this->includes_dir . 'admin/class-admin.php';

			require $this->includes_dir . 'admin/class-api.php';

			require $this->includes_dir . 'admin/class-emails.php';

			require $this->includes_dir . 'admin/class-webhook.php';

			// Front-end.
			require $this->includes_dir . 'frontend/class-frontend.php';
		}

		/**
		 * Class dependencies.
		 */
		private function dependencies() {
			// Check for SOAP.
			if ( ! class_exists( 'SoapClient' ) ) {
				add_action( 'admin_notices', array( $this, 'soap_missing_notice' ) );

				return;
			}

			// Checks if WooCommerce is installed and with the proper version.
			if ( ! $this->version_check() ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );

				return;
			}
		}

		/**
		 * Set hooks.
		 *
		 * @since 1.0.0
		 */
		private function setup_hooks() {
			// Set up localisation.
			$this->load_plugin_textdomain();

			$settings_url = admin_url( 'admin.php?page=woocommerce_settings&tab=integration&section=woo-nfe' );
			if ( $this->version_check( '2.1' ) ) {
				$settings_url = admin_url( 'admin.php?page=wc-settings&tab=integration&section=woo-nfe' );
			}

			if ( ! defined( 'WOOCOMMERCE_NFE_SETTINGS_URL' ) ) {
				define( 'WOOCOMMERCE_NFE_SETTINGS_URL', $settings_url );
			}

			if ( ! defined( 'WOOCOMMERCE_NFE_PATH' ) ) {
				define( 'WOOCOMMERCE_NFE_PATH', plugin_dir_path( $this->file ) );
			}

			// Filters.
			add_filter( 'woocommerce_integrations', array( $this, 'nfe_integration' ) );
			add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'plugin_action_links' ) );
		}

		/**
		 * Version check.
		 *
		 * @param string $version version to check against
		 *
		 * @return bool
		 */
		private function version_check( $version = '3.5.1' ) {
			if ( class_exists( 'WooCommerce' ) ) {
				global $woocommerce;
				if ( version_compare( $woocommerce->version, $version, '>=' ) ) {
					return true;
				}
			}

			return false;
		}

		private function version_check1( $version ) {
			if ( class_exists( 'WooCommerce' ) ) {
				global $woocommerce;
				if ( version_compare( $version, $woocommerce->version, '>=' ) ) {
					return true;
				}
			}

			return false;
		}
	}

	/**
	 * The main function responsible for returning the one true WooCommerce_NFe Instance.
	 *
	 * @since 1.0.0
	 *
	 * @return WooCommerce_NFe
	 */
	function woo_nfe() {
		return WooCommerce_NFe::instance();
	}

	add_action( 'plugins_loaded', 'woo_nfe' );

	/**
	 * Plugin Name: WooCommerce example extension
	 * Plugin URI: https://github.com/Automattic/wc-extensions-code-test-guide
	 * Description: WooCommerce example extension as a guide to write tests.
	 * Version: 1.0.0
	 * Author: Akeda Bagus <admin@gedex.web.id>
	 * Author URI: http://gedex.web.id.
	 */

	/**
	 * Run the plugin during `woocommerce_init`.
	 *
	 * @return bool
	 */
	function wc_ee_run() {
		return wc_ee_instance()->run();
	}

	add_action( 'woocommerce_init', 'wc_ee_run' );

	// **
	// * Get instance of WC_Example_Extension.
	// *
	// * @return WC_Example_Extension Instance of WC_Example_Extension
	// */
	function wc_ee_instance() {
		static $extension;

		if ( ! isset( $extension ) ) {
			// require_once('includes/class-wc-example-extension.php');
			// $extension = new WC_Example_Extension(__FILE__, '1.0.0');ILE__, '1.0.0');
			$extension = WooCommerce_NFe::instance();
			// $extension = new WooCommerce_NFe(__FILE__, '1.0.0');
		}

		return $extension;
	}
}
