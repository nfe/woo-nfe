<?php

/**
 * WooCommerce NFe.io FrontEnd Class
 *
 * @author   Renato Alves
 * @category Admin
 * @package  WooCommerce_NFe/Classes/WC_NFe_FrontEnd
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
		add_filter( 'woocommerce_my_account_my_address_description', 			array( $this, 'nfe_my_account_description' ) );

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
		if ( nfe_get_field('nfe_enable') == 'yes' ) {
			echo '<div class="woocommerce-message">' . __( 'The following address will <strong>also</strong> be used when issuing a NFe Sales Receipt.', 'woocommerce-nfe' ) . '</div>';
		}
	}

	/**
	 * Notice added in the My Account page
	 * 
	 * @return string
	 */
	public function nfe_my_account_description() {
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
    public function nfe_column_content( $order ) {   	
        $nfe 		= get_post_meta( $order->id, 'nfe_issued', true );
        $actions 	= array();

        if ( $order->post_status == 'wc-completed' ) {
            if ( $this->issue_past_orders( $order ) ) {
                $actions['woo_nfe_expired'] = array(
                    'name'      => __( 'Time Expired', 'woocommerce-nfe' ),
                    'action'    => 'woo_nfe_expired'
                );
            }

            if ( ! $this->issue_past_orders( $order ) && ( nfe_get_field('nfe_enable') == 'yes' && $nfe == false ) ) {
                $actions['woo_nfe_issue'] = array(
                    'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_issue&order_id=' . $order->id ), 'woocommerce_nfe_issue' ),
                    'name'      => __( 'Issue Nfe', 'woocommerce-nfe' ),
                    'action'    => 'woo_nfe_issue'
                );
            }
        }

        if ( $nfe == true ) {
            $actions['woo_nfe_download'] = array(
                'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_download&order_id=' . $order->id ), 'woo_nfe_download' ),
                'name'      => __( 'Download NFe', 'woocommerce-nfe' ),
                'action'    => 'woo_nfe_download'
            );
        }

        if ( current_user_can('manage_woocommerce') && nfe_get_field('nfe_enable') == 'no' ) {
            $actions['woo_nfe_tab'] = array(
                'url'       => WOOCOMMERCE_NFE_SETTINGS_URL,
                'name'      => __( 'Enable NFe', 'woocommerce-nfe' ),
                'action'    => 'woo_nfe_tab'
            );
        } 

        foreach ( $actions as $action ) {
            if ( $action['action'] == 'woo_nfe_expired' ) {
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
     * Past Issue Check
     * 
     * @param  string $post_date Post date
     * @param  string $past_days Days
     * @return bool true|false
     */
    public function issue_past_orders( $order ) {
        $time   = $order->post->post_date;
        $days   = '-' . nfe_get_field( 'issue_past_days' ) . ' days';

        if ( nfe_get_field( 'issue_past_notes' ) == 'yes' && strtotime( $time ) > strtotime( $days ) ) {
            return true;
        }

        return false;
    }

	/**
	 * Removes the WooCommerce fields on the checkout if the user chooses it to.
     *
     * @uses nfe_get_field() Fetch NFe custom settings fields
	 * 
	 * @return void
	 */
	public function removes_fields( $fields ) {
		if ( nfe_get_field('nfe_enable') == 'yes' && nfe_get_field('where_note') == 'after' ) {
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

// That's it! =)
