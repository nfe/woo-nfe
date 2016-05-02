<?php

/**
 * WooCommerce Nfe.io plugin
 *
 * @link              https://github.com/nfe/nfe-wordpress-woocommerce
 * @since             1.0.0
 * @package           WooCommerce Nfe.io
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Nfe.io
 * Plugin URI:        https://github.com/nfe/nfe-wordpress-woocommerce
 * Description:       WooCommerce extension for the Nfe.io API
 * Version:           1.0.0
 * Author:            Nfe.io
 * Author URI:        https://nfe.io
 * Developer:         Renato Alves
 * Developer URI:     http://ralv.es
 * Text Domain:       nfe-woocommerce
 * Domain Path:       /languages
 *
 * Copyright: © 2016 Nfe.io
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
 
// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Nfe_WooCommerce' ) ) :

    /**
    * WooCommerce Nfe.io Main Class
    *
    * @since 1.0.0
    */
    class Nfe_WooCommerce {

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
                $instance = new Nfe_WooCommerce;
                $instance->setup_globals();
                $instance->includes();
                $instance->setup_hooks();
            }

            // Always return the instance
            return $instance;
        }

        /**
         * A dummy constructor to prevent Nfe_WooCommerce from being loaded more than once.
         *
         * @since 1.0.0
         * 
         * @see Nfe_WooCommerce::instance()
         */
        private function __construct() { /* Do nothing here */ }

        /**
         * Sets some globals for the plugin
         *
         * @since 1.0.0
         */
        private function setup_globals() {
            /** Plugin globals ********************************************/
            $this->version       = '1.0.0';
            $this->domain        = 'nfe-wordpress-woocommerce';
            $this->name          = 'WooCommerce Nfe.io';
            $this->file          = __FILE__;
            $this->basename      = plugin_basename( $this->file                     );
            $this->plugin_dir    = plugin_dir_path( $this->file                     );
            $this->plugin_url    = plugin_dir_url( $this->file                      );
            $this->includes_dir  = trailingslashit( $this->plugin_dir . 'includes'  );
            $this->admin         = trailingslashit( $this->includes_dir . 'admin'   );
            $this->lang_dir      = trailingslashit( $this->plugin_dir . 'languages' );
        }

        /**
         * Include needed files.
         *
         * @since 1.0.0
         */
        private function includes() {
            require( $this->admin . 'class-wc-integration.php'              );
        }

        /**
         * Set hooks.
         *
         * @since 1.0.0
         */
        private function setup_hooks() {
            add_action( 'init', array( $this, 'load_textdomain' ) );

            // Checks if WooCommerce is installed.
            if ( ! class_exists( 'WooCommerce' ) ) {
                add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
                return;
            }

            // Register the integration.
            add_filter( 'woocommerce_integrations', array( $this, 'add_nfe_integration' ) );
        }

        /**
         * Adds our custom WC_Nfe_Integration integration to WooCommerce.
         *
         * @since 1.0.0
         */
        public function add_nfe_integration( $integrations ) {
            $integrations[] = 'WC_Nfe_Integration';

            return $integrations;
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
            $mofile_global = WP_LANG_DIR . '/nfe-wordpress-woocommerce/' . $mofile;

            // Look in global /wp-content/languages/nfe-wordpress-woocommerce folder
            load_textdomain( $this->domain, $mofile_global );

            // Look in local /wp-content/plugins/nfe-wordpress-woocommerce/languages/ folder
            load_textdomain( $this->domain, $mofile_local );
        }
    }

endif;

/**
 * The main function responsible for returning the one true Nfe_WooCommerce Instance.
 *
 * @since 1.0.0
 *
 * @return Nfe_WooCommerce The one true Nfe_WooCommerce Instance.
 */
function Nfe_WooCommerce() {
    return Nfe_WooCommerce::instance();
}
add_action( 'plugins_loaded', 'Nfe_WooCommerce');

// That's it! =)
