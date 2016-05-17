<?php

/**
 * WooCommerce NFe plugin
 *
 * @link              https://github.com/nfe/woocommerce-nfe
 * @since             1.0.0
 * @package           WooCommerce NFe
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce NFe
 * Plugin URI:        https://github.com/nfe/woocommerce-nfe
 * Description:       WooCommerce extension for the NFe API
 * Version:           1.0.0
 * Author:            NFe.io
 * Author URI:        https://nfe.io
 * Developer:         Renato Alves
 * Developer URI:     http://ralv.es
 * Text Domain:       woocommerce-nfe
 * Domain Path:       /languages
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
    class WooCommerce_NFe {

        /**
         * Plugin version.
         *
         * @var string
         */
        const VERSION = '1.0.0';

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
            $this->domain        = 'woocommerce-nfe';
            $this->name          = 'WooCommerce NFe';
            $this->file          = __FILE__;
            $this->basename      = plugin_basename( $this->file                     );
            $this->plugin_dir    = plugin_dir_path( $this->file                     );
            $this->plugin_url    = plugin_dir_url( $this->file                      );
            $this->includes_dir  = trailingslashit( $this->plugin_dir . 'includes'  );
            $this->admin         = trailingslashit( $this->includes_dir . 'admin'   );
            $this->frontend      = trailingslashit( $this->includes_dir . 'frontend');
            $this->lang_dir      = trailingslashit( $this->plugin_dir . 'languages' );
            $this->assets        = trailingslashit( $this->plugin_url . 'assets'    );
            $this->templates     = trailingslashit( $this->plugin_url . 'templates' );
        }

        /**
         * Include needed files.
         *
         * @since 1.0.0
         */
        private function includes() {
            // NFe.io API
            require( $this->includes_dir . 'client-php/Nfe.php'         );

            // Admin
            require( $this->admin . 'class-wc-nfe-integration.php'      );
            require( $this->admin . 'class-wc-admin.php'                );
            require( $this->admin . 'class-wc-nfe.php'                  );
            require( $this->includes_dir . 'nfe-functions.php'          );

            // Front-end
            require( $this->frontend . 'class-wc-frontend.php'          );
        }

        /**
         * Set hooks.
         *
         * @since 1.0.0
         */
        private function setup_hooks() {
            add_action( 'init', array( $this, 'load_textdomain' ) );

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

            // Filters
            add_filter( 'woocommerce_integrations',                array( $this, 'add_nfe_integration' ) );
            add_filter( 'plugin_action_links_' . $this->basename , array( $this, 'plugin_action_links' ) );
        }

        /**
         * Adds our custom WC_NFe_Integration integration to WooCommerce.
         *
         * @since 1.0.0
         *
         * @return array
         */
        public function add_nfe_integration( $integrations ) {
            $integrations[] = 'WC_NFe_Integration';

            return $integrations;
        }

        /**
        * SOAPClient missing notice.
        *
        * @return string
        */ 
        public function soap_missing_notice() {
            include $this->admin .  'views/html-notice-missing-soap-client.php';
        }

        /**
         * WooCommerce missing notice.
         *
         * @since 1.0.0
         *
         * @return string
         */
        public function woocommerce_missing_notice() {
            include $this->admin . 'views/html-notice-missing-woocommerce.php';
        }

        /**
         * WooCommerce Extra Checkout Fields for Brazil missing notice.
         *
         * @since 1.0.0
         *
         * @return string
         */
        public function extra_checkout_fields_missing_notice() {
            include $this->admin . 'views/html-notice-missing-woocommerce-extra-checkout-fields.php';
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
            $plugin_links   = array();
            $plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=integration' ) ) . '">' . __( 'Settings', 'woocommerce-nfe' ) . '</a>';

            return array_merge( $plugin_links, $links );
        }

        /**
         * Loads the translation files.
         *
         * @since 1.0.0
         */
        public function load_textdomain() {
            // Traditional WordPress plugin locale filter
            $locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
            $mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

            // Setup paths to current locale file
            $mofile_local  = $this->lang_dir . $mofile;
            $mofile_global = WP_LANG_DIR . '/woocommerce-nfe/' . $mofile;

            // Look in global /wp-content/languages/woocommerce-nfe folder
            load_textdomain( $this->domain, $mofile_global );

            // Look in local /wp-content/plugins/woocommerce-nfe/languages/ folder
            load_textdomain( $this->domain, $mofile_local );
        }
    }

endif;

/**
 * The main function responsible for returning the one true WooCommerce_NFe Instance.
 *
 * @since 1.0.0
 *
 * @return WooCommerce_NFe The one true WooCommerce_NFe Instance.
 */
function WooCommerce_NFe() {
    return WooCommerce_NFe::instance();
}
add_action( 'plugins_loaded', 'WooCommerce_NFe');

// That's it! =)
