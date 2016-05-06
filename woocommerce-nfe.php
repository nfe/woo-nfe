<?php

/**
 * WooCommerce NFe plugin
 *
 * @link              https://github.com/nfe/nfe-wordpress-woocommerce
 * @since             1.0.0
 * @package           WooCommerce NFe
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce NFe
 * Plugin URI:        https://github.com/nfe/nfe-wordpress-woocommerce
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

if ( ! class_exists( 'NFe_WooCommerce' ) ) :

    /**
    * WooCommerce NFe.io Main Class
    *
    * @since 1.0.0
    */
    class NFe_WooCommerce {

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
                $instance = new NFe_WooCommerce;
                $instance->setup_globals();
                $instance->includes();
                $instance->setup_hooks();
            }

            // Always return the instance
            return $instance;
        }

        /**
         * A dummy constructor to prevent NFe_WooCommerce from being loaded more than once.
         *
         * @since 1.0.0
         * 
         * @see NFe_WooCommerce::instance()
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
            $this->name          = 'WooCommerce NFe.io';
            $this->file          = __FILE__;
            $this->basename      = plugin_basename( $this->file                     );
            $this->plugin_dir    = plugin_dir_path( $this->file                     );
            $this->plugin_url    = plugin_dir_url( $this->file                      );
            $this->includes_dir  = trailingslashit( $this->plugin_dir . 'includes'  );
            $this->admin         = trailingslashit( $this->includes_dir . 'admin'   );
            $this->frontend      = trailingslashit( $this->includes_dir . 'frontend');
            $this->lang_dir      = trailingslashit( $this->plugin_dir . 'languages' );
            $this->assets        = trailingslashit( $this->plugin_url . 'assets'    );
        }

        /**
         * Include needed files.
         *
         * @since 1.0.0
         */
        private function includes() {
            // Admin
            require( $this->admin . 'class-wc-nfe-integration.php'      );
            require( $this->admin . 'class-wc-admin.php'                );

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

            // Checks if WooCommerce is installed.
            if ( ! class_exists( 'WooCommerce' ) ) {
                add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
                return;
            }

            // Checks if WooCommerce Extra Checkout Fields for Brazil is installed.
            // if ( ! class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
               //  add_action( 'admin_notices', array( $this, 'extra_checkout_fields_missing_notice' ) );
                // return;
            //}

            // Filters
            add_filter( 'woocommerce_integrations', array( $this, 'add_nfe_integration' ) );
            add_filter( 'plugin_action_links_' . $this->basename , array( $this, 'plugin_action_links' ) );

            // Actions
            add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_scripts' ) );
        }

        /**
         * Adds the admin script
         *
         * @since 1.0.0
         */
        public function enqueue_scripts() {
            // Get admin screen id
            $screen = get_current_screen();

            $is_woocommerce_screen = ( in_array( $screen->id, array( 'product' ) ) ) ? true : false;

            if ( $is_woocommerce_screen ) {
                wp_enqueue_script( 'nfe-woo-metabox', 
                    $this->assets . 'js/admin/admin.js', 
                    array( 'jquery' ),
                    $this->assets . 'js/admin/admin.js' 
                );
            }
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
            include $this->includes_dir . 'admin/views/html-notice-missing-woocommerce-checkout-fields.php';
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
            $plugin_links = array();
            $plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=integration' ) ) . '">' . __( 'Settings', 'nfe-wordpress-woocommerce' ) . '</a>';
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
            $mofile_global = WP_LANG_DIR . '/nfe-wordpress-woocommerce/' . $mofile;

            // Look in global /wp-content/languages/nfe-wordpress-woocommerce folder
            load_textdomain( $this->domain, $mofile_global );

            // Look in local /wp-content/plugins/nfe-wordpress-woocommerce/languages/ folder
            load_textdomain( $this->domain, $mofile_local );
        }
    }

endif;

/**
 * The main function responsible for returning the one true NFe_WooCommerce Instance.
 *
 * @since 1.0.0
 *
 * @return NFe_WooCommerce The one true NFe_WooCommerce Instance.
 */
function NFe_WooCommerce() {
    return NFe_WooCommerce::instance();
}
add_action( 'plugins_loaded', 'NFe_WooCommerce');

// That's it! =)
