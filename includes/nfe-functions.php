<?php

/**
 * WooCommerce NFe Custom Functions
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Functions
 * @version  1.0.1
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Function to fetch fields from the NFe WooCommerce Integration
 *
 * @param  string $value Value to fetch
 * @return string
 */
function nfe_get_field( $value = '' ) {
	$nfe_fields = get_option( 'woocommerce_woo-nfe_settings' );

	if ( empty( $value ) ) {
		$output = $nfe_fields;
	}
	else {
		$output = $nfe_fields[$value];
	}

	return $output;
}

/**
 * Check to make sure the order has all the fields for a NFe issue
 *
 * @param  int $order Product Order
 * @return bool true|false
 */
function nfe_order_address_filled( $order_id ) {
	$fields = array(
		'neighborhood' => get_post_meta( $order_id, '_billing_neighborhood', true ),
		'address_1'    => get_post_meta( $order_id, '_billing_address_1', true ),
		'number'       => get_post_meta( $order_id, '_billing_number', true ),
		'postcode'     => get_post_meta( $order_id, '_billing_postcode', true ),
		'state'        => get_post_meta( $order_id, '_billing_state', true ),
		'city'         => get_post_meta( $order_id, '_billing_city', true ),
		'country'      => get_post_meta( $order_id, '_billing_country', true ),
	);

	$count = 0;
	foreach ( $fields as $field => $value ) {
		if ( empty( $value ) ) {
			$count = 1;
		}
	}

	if ( $count >= 1 ) {
		return true;
	}

	return false;
}

/**
 * Past Issue Check (It answers the question: Can we issue a past order?)
 *
 * @param  string $post_date Post date
 * @param  string $past_days Days in the past for time check
 * @return bool true|false
 */
function nfe_issue_past_orders( $order ) {
	$time = $order->post->post_date;
	$days = '-' . nfe_get_field( 'issue_past_days' ) . ' days';

	if ( strtotime( $days ) < strtotime( $time ) ) {
		return true;
	}

	return false;
}

/**
 * WooCommerce 2.2 support for wc_get_order.
 *
 * @param int $order_id
 * @return void
 */
function nfe_wc_get_order( $order_id ) {
	if ( function_exists( 'wc_get_order' ) ) {
		return wc_get_order( $order_id );
	}
	else {
		return new WC_Order( $order_id );
	}
}
