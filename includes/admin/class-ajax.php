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
			'nfe_issue'          => false,
			'nfe_download'       => false,
			'front_nfe_issue'    => false,
			'front_nfe_download' => false,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}

		add_action( 'wp_loaded', array( __CLASS__, 'front_nfe_download' ), 20 );
		add_action( 'wp_loaded', array( __CLASS__, 'front_nfe_issue' ), 20 );
	}

	/**
	 * Issue a NFe via AJAX
	 */
	public static function nfe_issue() {
		if ( ! current_user_can( 'edit_shop_orders' ) && ! check_admin_referer( 'woo_nfe_issue' ) ) {
			return;
		}

		$order = wc_get_order( absint( $_GET['order_id'] ) );

		// Bail if there is no order id
		if ( empty( $order->id ) ) {
			return;
		}

		// Bail if not completed
		if ( ! $order->has_status( 'completed' ) ) {
			return;
		}

		if ( nfe_user_address_filled( $order->id ) ) {
			wc_add_notice( __( 'User Address Missing. User needs to update NFe information before issuing.', 'woocommerce-nfe' ), 'error' );
		}
		else {
			NFe_Woo()->issue_invoice( array( $order->id ) );
			wc_add_notice( __( 'NFe issued successfully.', 'woocommerce-nfe' ) );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		die();
	}

	/**
	 * Download a NFe via AJAX
	 */
	public static function nfe_download() {
		if ( ! current_user_can( 'edit_shop_orders' ) && ! check_admin_referer( 'woo_nfe_download' ) ) {
			return;
		}

		$order = wc_get_order( absint( $_GET['order_id'] ) );

		// Bail if there is no order id
		if ( empty( $order->id ) ) {
			return;
		}

		$pdf = NFe_Woo()->down_invoice( array( $order->id ) );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		die();
	}

	/**
	 * NFe issue from the front-end
	 */
	public static function front_nfe_issue() {
		// Nothing to do
		if ( ! isset( $_GET['nfe_issuue'] ) || ! is_user_logged_in() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'woocommerce_nfe_issue' ) ) {
			return;
		}

		$order = wc_get_order( absint( $_GET['nfe_issue'] ) );

		// Bail if there is no order id
		if ( empty( $order->id ) ) {
			return;
		}

		// Bail if not completed
		if ( ! $order->has_status( 'completed' ) ) {
			return;
		}

		if ( nfe_user_address_filled( $order->id ) ) {
			wc_add_notice( __( 'You need to update your NFe information before issuing a receipt.', 'woocommerce-nfe' ), 'error' );
		}
		else {
			NFe_Woo()->issue_invoice( array( $order->id ) );

			wc_add_notice( __( 'NFe issued successfully.', 'woocommerce-nfe' ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	/**
	 * Download NFe from the Front-end
	 *
	 * @todo Add notice if there is error
	 */
	public static function front_nfe_download() {
		// Nothing to do
		if ( ! isset( $_GET['nfe_download'] ) || ! is_user_logged_in() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'woocommerce_nfe_download' ) ) {
			return;
		}

		$order = wc_get_order( absint( $_GET['nfe_download'] ) );

		// Bail if there is no order id
		if ( empty( $order->id ) ) {
			return;
		}

		$pdf = NFe_Woo()->down_invoice( array( $order->id ) );

		wc_add_notice( __( 'NFe receipt downloaded successfully.', 'woocommerce-nfe' ) );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}
}

endif;

WC_NFe_Ajax::init();
