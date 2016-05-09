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
		add_filter( 'woocommerce_checkout_fields', 								array( $this, 'removes_fields' ) );
		add_filter( 'woocommerce_my_account_my_orders_columns', 				array( $this, 'nfe_column' ) );
		add_filter( 'woocommerce_my_account_my_address_description', 			array( $this, 'my_account_nfe_description' ) );

		// Actions
		add_action( 'woocommerce_my_account_my_orders_column_sales-receipt', 	array( $this, 'nfe_column_content' ) );
		add_action( 'woocommerce_order_details_after_order_table', 				array( $this, 'nfe_column_content' ) );
		add_action( 'woocommerce_before_edit_address_form_billing', 			array( $this, 'nfe_billing_notice' ) );
	}

	/**
	 * Notice added on the WooCommerce edit-address page
	 * 
	 * @return string
	 */
	public function nfe_billing_notice() {
		if ( nfe_get_field('nfe_enable') === 'yes' ) {
			echo '<div class="woocommerce-message">' . __( 'The following addresses will <strong>also</strong> be used when issuing a NFe Sales Receipt.', 'woocommerce-nfe' ) . '</div>';
		}
	}

	/**
	 * Notice added in the My Account page
	 * 
	 * @return string
	 */
	public function my_account_nfe_description() {
		return __( 'The following addresses will be used on the checkout page by default and also when issuing a NFe Sales Receipt.', 'woocommerce-nfe' );
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

     	if ( $order->has_status('completed') && nfe_get_field( 'issue_past_notes' ) === 'no' && strtotime( $order->post->post_date ) < strtotime('-1 year') ) {
            echo '<div class="nfe_woo">' . __( 'Issue Time Expired', 'woocoomerce-nfe' ) . '</div>';
        }

        if ( nfe_get_field('nfe_enable') === 'yes' && $order->has_status('completed') && $nfe == false ) {
			echo '<a href="#" class="button view">' . __( 'Issue NFe', 'woocoomerce-nfe' ) . '</a>';
        } 

        if ( current_user_can('manage_woocommerce') && nfe_get_field('nfe_enable') === 'no' ) {
         	echo '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=integration' ) . '" class="button view">' . __( 'Enable NFe', 'woocoomerce-nfe' ) . '</a>';
        } 

        if ( $nfe == true ) {
            echo '<a href="#" class="button view">' . __( 'Download NFe', 'woocoomerce-nfe' ) . '</a>';
        }
    }

	/**
	 * Removes the WooCommerce fields on the checkout
	 * 
	 * @return void
	 */
	public function removes_fields( $fields ) {
		if ( nfe_get_field('nfe_enable') === 'yes' && nfe_get_field('where_note') === 'after' ) {
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

$run = new WC_NFe_FrontEnd();
