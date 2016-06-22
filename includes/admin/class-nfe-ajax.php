<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('WC_NFe_Ajax') ) :

/**
 * WooCommerce NFe Ajax Class
 * 
 * @package 	WooCommerce_NFe/Class/WC_NFe_Ajax
 * @author  	NFe.io
 * @version     1.0.0
 */
class WC_NFe_Ajax {

	/**
	 * Bootstraps the class and hooks required actions
	 */
	public static function init() {
		$ajax_events = array(
            'nfe_issue'    => false,
            'nfe_download' => false,
        );
        foreach ( $ajax_events as $ajax_event => $nopriv ) {
            add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
            if ( $nopriv ) {
                add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
            }
        }
	}

	/**
     * Issue a NFe via AJAX
     */
    public static function nfe_issue() {
        if ( current_user_can( 'edit_shop_orders' ) && check_admin_referer( 'woocommerce_nfe_issue' ) ) {
            $order_id = absint( $_GET['order_id'] );

            if ( $order_id ) {
                NFe_Woo()->issue_invoice( array( $order_id ) );
            }
        }
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
        die();
    }

    /**
     * Download a NFe via AJAX
     */
    public static function nfe_download() {
        if ( current_user_can( 'edit_shop_orders' ) && check_admin_referer( 'woocommerce_nfe_download' ) ) {
            $order_id = absint( $_GET['order_id'] );

            if ( $order_id ) {
                NFe_Woo()->down_invoice( array( $order_id ) );
            }
        }
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
        die();
    }
}

endif;

WC_NFe_Ajax::init();
