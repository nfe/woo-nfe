<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('WC_NFe_FrontEnd') ) :

/**
 * FrontEnd Class.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_FrontEnd
 * @version  1.0.0
 */
class WC_NFe_FrontEnd {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Filters
		add_filter( 'woocommerce_checkout_fields', 							 array( $this, 'removes_fields' ) );
		add_filter( 'woocommerce_my_account_my_orders_columns', 			 array( $this, 'nfe_column' ) );
		add_filter( 'woocommerce_my_account_my_address_description', 		 array( $this, 'my_account_description' ) );
        add_filter( 'woocommerce_thankyou_order_received_text',              array( $this, 'thankyou_text' ) );

		// Actions
		add_action( 'woocommerce_my_account_my_orders_column_sales-receipt', array( $this, 'column_content' ) );
		add_action( 'woocommerce_order_details_after_order_table', 			 array( $this, 'column_content' ) );
		add_action( 'woocommerce_before_edit_address_form_billing', 		 array( $this, 'billing_notice' ) );
	}

    /**
     * NFe custom note for Order Received page
     * 
     * @return string
     */
    public function thankyou_text( $message ) {
        if ( $this->where_note() ) {
            $message = sprintf( __( 'Thank you. Your order has been received. Now you need %s.', 'woocommerce-nfe' ), '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . __( 'update your NFe information', 'woocommerce-nfe' ) . '</a>' );
        }

        return $message;
    }

	/**
	 * Notice added on the WooCommerce edit-address page
	 * 
	 * @return string
	 */
	public function billing_notice() {
		if ( nfe_get_field('nfe_enable') == 'yes' ) {
			echo '<div class="woocommerce-message">' . __( 'The following address will <strong>also</strong> be used when issuing a NFe Sales Receipt.', 'woocommerce-nfe' ) . '</div>';
		}
	}

	/**
	 * Notice added in the My Account page
	 * 
	 * @return string
	 */
	public function my_account_description() {
		return __( 'The following address(es) will be used on the checkout page by default and also when issuing a NFe sales receipt.', 'woocommerce-nfe' );
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
    public function column_content( $order ) {   	
        $nfe 		= get_post_meta( $order->id, 'nfe_issued', true );
        $actions 	= array();

        if ( nfe_get_field('nfe_enable') == 'yes' && $order->has_status( 'completed' ) ) {
            if ( ! nfe_issue_past_orders( $order ) ) {
                $actions['woo_nfe_expired'] = array(
                    'name'      => __( 'Issue Expired', 'woocommerce-nfe' ),
                    'action'    => 'woo_nfe_expired'
                );
            }

            if ( $nfe['status'] == 'Cancelled' ) {
                $actions['woo_nfe_cancelled'] = array(
                    'name'      => __( 'Issue Cancelled', 'woocommerce-nfe' ),
                    'action'    => 'woo_nfe_cancelled'
                );
            }
            else {
                if ( nfe_user_address_filled( $order->id ) ) {
                    $actions['woo_nfe_pending_address'] = array(
                        'name'      => __( 'Pending Address', 'woocommerce-nfe' ),
                        'action'    => 'woo_nfe_pending_address'
                    );
                }
                else {
                    if ( nfe_issue_past_orders( $order ) && $nfe == false ) {
                        $actions['woo_nfe_issue'] = array(
                            'url'       => wp_nonce_url( add_query_arg( 'nfe_issue', $order->id ) , 'woocommerce_nfe_issue' ),
                            'name'      => __( 'Issue NFe', 'woocommerce-nfe' ),
                            'action'    => 'woo_nfe_issue'
                        );
                    }

                    if ( $nfe == true ) {
                        $actions['woo_nfe_download'] = array(
                            'url'       => wp_nonce_url( add_query_arg( 'nfe_download', $order->id ) , 'woocommerce_nfe_download' ),
                            'name'      => __( 'Download NFe', 'woocommerce-nfe' ),
                            'action'    => 'woo_nfe_download'
                        );
                    }
                }
            }
        }

        if ( current_user_can('manage_woocommerce') && nfe_get_field('nfe_enable') == 'no' ) {
            $actions['woo_nfe_tab'] = array(
                'url'       => WOOCOMMERCE_NFE_SETTINGS_URL,
                'name'      => __( 'Enable NFe', 'woocommerce-nfe' ),
                'action'    => 'woo_nfe_tab'
            );
        } 

        foreach ( $actions as $action ) {
            if ( $action['action'] == 'woo_nfe_expired' || $action['action'] == 'woo_nfe_pending_address' ||
            $action['action'] == 'woo_nfe_pending_address' ) {
                printf( '<span class="button view %s" data-tip="%s">%s</span>', 
                    esc_attr( $action['action'] ), 
                    esc_attr( $action['name'] ), 
                    esc_attr( $action['name'] ) 
                );
            } else {
                printf( '<a class="button view %s" href="%s" data-tip="%s">%s</a>', 
                    esc_attr( $action['action'] ), 
                    esc_url( $action['url'] ), 
                    esc_attr( $action['name'] ), 
                    esc_attr( $action['name'] ) 
                );
            }
        }
    }

	/**
	 * Removes the WooCommerce fields on the checkout if the admin chooses it to.
	 * 
	 * @return void
	 */
	public function removes_fields( $fields ) {
		if ( $this->where_note() == true ) {
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

    /**
     * Custom check for Where Note usage
     * 
     * @return bool true|false
     */
    private function where_note() {
        if ( nfe_get_field('nfe_enable') == 'yes' && nfe_get_field('where_note') == 'after' ) {
            return true;
        }

        return false;
    }
}

endif;

$run = new WC_NFe_FrontEnd();

// That's it! =)
