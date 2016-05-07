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
		add_filter( 'woocommerce_checkout_fields', array( $this, 'removes_fields' ) );
		add_filter( 'woocommerce_my_account_my_orders_columns', array( $this, 'nfe_column' ) );

		// Actions
		add_action( 'woocommerce_my_account_my_orders_column_sales-receipt', array( $this, 'nfe_column_content' ) );
	}

	/**
     * NFe Column Header on Recent Orders
     * 
     * @return array
     */
	public function nfe_column( $columns ) {
		$new_column = array();

        foreach ( $columns as $column_name => $column_info ) {
            $new_columns[ $column_name ] = $column_info;

            if ( 'order-total' == $column_name ) {
                $new_columns['sales-receipt'] = __( 'Sales Receipt', 'woocommerce-nfe' );
            }
        }

        return $new_columns;
	}

	/**
     * NFe Sales Receipt Column Content on Recent Orders
     * 
     * @return string
     */
    public function nfe_column_content( $order ) {   	
        $nfe = get_post_meta( $order->ID, 'nfe_issued', true );

     	if ( $order->has_status('completed') && strtotime( $order->post->post_date ) < strtotime('-1 year') ) {
            echo '<div class="nfe_woo">' . __( 'Issue Time Expired', 'woocoomerce-nfe' ) . '</div>';

        } elseif ( $order->has_status('completed') && $nfe == false ) {
            echo '<a href="#" class="button view">' . __( 'Issue NFe', 'woocoomerce-nfe' ) . '</a>';

        } elseif ( $nfe == true ) {
            echo '<a href="#" class="button view">' . __( 'Download NFe', 'woocoomerce-nfe' ) . '</a>';

        } else {
            echo '<span class="nfe_woo_none">-</span>';
        }
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
