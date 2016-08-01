<?php

/**
 * WooCommerce NFe plugin
 * 
 * @author 			  NFe.io
 * @link              https://github.com/nfe/woo-nfe
 * @since             1.0.0
 * @package           WooCommerce_NFe
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce NFe
 * Plugin URI:        https://github.com/nfe/woo-nfe
 * Description:       WooCommerce extension for the NFe API
 * Version:           1.0.0
 * Author:            NFe.io
 * Author URI:        https://nfe.io
 * Developer:         Renato Alves
 * Developer URI:     http://ralv.es
 * Text Domain:       woo-nfe
 * Domain Path:       /languages
 * Network:     	  false
 *
 * Copyright: © 2016 NFe.io
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
 
// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce_NFe' ) ) :

	/**
	* WooCommerce NFe.io Main Class
	*
	* @since 1.0.0
	*/
	final class WooCommerce_NFe {

		/**
		 * Main instance
		 *
		 * @since 1.0.0
		 * 
		 * @return instance
		 */
		public static function instance() {

			// Store the instance locally to avoid private static replication
			static $instance = null;

			// Only run these methods if they haven't been run previously
			if ( null === $instance ) {
				$instance = new WooCommerce_NFe;
				$instance->setup_globals();
				$instance->includes();
				$instance->setup_hooks();
			}

			// Always return the instance
			return $instance;
		}

		/**
		 * A dummy constructor to prevent WooCommerce_NFe from being loaded more than once.
		 *
		 * @since 1.0.0
		 * 
		 * @see WooCommerce_NFe::instance()
		 */
		private function __construct() { /* Do nothing here */ }

		/**
		 * Sets some globals for the plugin
		 *
		 * @since 1.0.0
		 */
		private function setup_globals() {
			$this->domain        = 'woo-nfe';
			$this->name          = 'WooCommerce NFe';
			$this->file          = __FILE__;
			$this->basename      = plugin_basename( $this->file                     );
			$this->plugin_dir    = plugin_dir_path( $this->file                     );
			$this->plugin_url    = plugin_dir_url( $this->file                      );
			$this->includes_dir  = trailingslashit( $this->plugin_dir . 'includes'  );

			// WooCommerce Webhook Callback
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
			
			// NFe Client-PHP API - Composer Support
			$composer_path = $this->plugin_dir . 'vendor/autoload.php';

			if ( ! file_exists( $composer_path ) ) {
				require( $this->plugin_dir . 'lib/client-php/lib/init.php' );
			}
			else {
				require( $composer_path );
			}

			// Admin
			require( $this->includes_dir . 'nfe-functions.php'           );
			require( $this->includes_dir . 'admin/class-settings.php'    );
			require( $this->includes_dir . 'admin/class-ajax.php'        );
			require( $this->includes_dir . 'admin/class-admin.php'       );
			require( $this->includes_dir . 'admin/class-api.php'         );
			require( $this->includes_dir . 'admin/class-emails.php'      );
			require( $this->includes_dir . 'admin/class-webhook.php' 	 );

			// Front-end
			require( $this->includes_dir . 'frontend/class-frontend.php' );
		}

		/**
		 * Set hooks.
		 *
		 * @since 1.0.0
		 */
		private function setup_hooks() {
			load_plugin_textdomain( 'woo-nfe', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

			// Check for SOAP.
			if ( ! class_exists( 'SoapClient' ) ) {
				add_action( 'admin_notices', array( $this, 'soap_missing_notice' ) );
				return;
			}

			// Checks if WooCommerce is installed.
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
				return;
			}

			// Checks if WooCommerce Extra Checkout Fields for Brazil is installed.
			if ( ! class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
				add_action( 'admin_notices', array( $this, 'extra_checkout_fields_missing_notice' ) );
			   return;
			}

			/******************************************************************************/

			global $woocommerce;
			
			$settings_url = admin_url( 'admin.php?page=woocommerce_settings&tab=integration&section=woo-nfe' );
			
			if ( $woocommerce->version >= '2.1' ) {
				$settings_url = admin_url( 'admin.php?page=wc-settings&tab=integration&section=woo-nfe' );
			}

			if ( ! defined( 'WOOCOMMERCE_NFE_SETTINGS_URL' ) ) {
				define( 'WOOCOMMERCE_NFE_SETTINGS_URL', $settings_url );
			}

			if ( ! defined( 'WOOCOMMERCE_NFE_PATH' ) ) {
				define( 'WOOCOMMERCE_NFE_PATH', plugin_dir_path( $this->file ) );
			}

			// Filters
			add_filter( 'woocommerce_integrations',                array( $this, 'nfe_integration' ) );
			add_filter( 'plugin_action_links_' . $this->basename , array( $this, 'plugin_action_links' ) );
		}

		/**
		 * Adds our custom WC_NFe_Integration integration to WooCommerce.
		 *
		 * @since 1.0.0
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
		 *
		 * @return string
		 */
		public function soap_missing_notice() {
			include $this->includes_dir . 'admin/views/html-notice-missing-soap-client.php';
		}

		/**
		 * WooCommerce missing notice.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function woocommerce_missing_notice() {
			include $this->includes_dir . 'admin/views/html-notice-missing-woocommerce.php';
		}

		/**
		 * WooCommerce Extra Checkout Fields for Brazil missing notice.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function extra_checkout_fields_missing_notice() {
			include $this->includes_dir . 'admin/views/html-notice-missing-woocommerce-extra-checkout-fields.php';
		}

		/**
		 * Action links.
		 *
		 * @since 1.0.0
		 *
		 * @param array $links
		 * @return array
		 */
		public function plugin_action_links( $links ) {
			$plugin_links   = array(
				'<a href="' . esc_url( WOOCOMMERCE_NFE_SETTINGS_URL ) . '">' . __( 'Settings', 'woo-nfe' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}
	}

endif;

/**
 * The main function responsible for returning the one true WooCommerce_NFe Instance.
 *
 * @since 1.0.0
 *
 * @return WooCommerce_NFe
 */
function WooCommerce_NFe() {
	return WooCommerce_NFe::instance();
}
add_action( 'plugins_loaded', 'WooCommerce_NFe');

// That's it! =)
