<?php

/**
 * WooCommerce NFe.io FrontEnd Class
 *
 * @author   Renato Alves
 * @category Admin
 * @package  NFe_WooCommerce/Classes/WC_NFe_FrontEnd
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * WC_NFe_FrontEnd
 */
class WC_NFe_FrontEnd {

	/**
	 * Constructor
	 */
	public function __construct() {

		// Filters
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'user_account_actions'), 10, 2 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'removes_fields' ) );
	}

	/**
	 * Adds the NFe actions on the My Orders
	 * 
	 * @return array
	 */
	public function user_account_actions( $actions, $order ) {
		if ( $order->has_status( 'completed' ) && strtotime( $order->post_date ) < strtotime('-1 year') ) {
			$actions['nfe-issue'] = array(
				'url'  => '', // todo
				'name' => __( 'Issue NFe', 'woocommerce-nfe' )
			);

			$actions['nfe-download'] = array(
				'url'  => '', // todo
				'name' => __( 'Download Issue', 'woocommerce-nfe' )
			);
		}
		return $actions;
	}

	/**
	 * Removes the WooCommerce fields on checkout
	 * 
	 * @return void
	 */
	public function removes_fields( $fields ) {
		$where_note = get_option( 'where_note' );

		if ( $where_note === 'after' ) {
	        unset($fields['billing']['billing_phone']);
	        unset($fields['billing']['billing_number']);
	        unset($fields['billing']['billing_country']);
	        unset($fields['billing']['billing_address_1']);
	        unset($fields['billing']['billing_address_2']);
	        unset($fields['billing']['billing_city']);
	        unset($fields['billing']['billing_state']);
	        unset($fields['billing']['billing_neighborhood']);
	        unset($fields['billing']['billing_postcode']);
	    }
        
        return $fields;
	}
}

new WC_NFe_FrontEnd();
