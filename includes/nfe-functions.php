<?php

/**
 * WooCommerce NFe.io Custom Functions
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Functions
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Function to fetch field from the NFe WooCommerce Integration
 * 
 * @param  string $value Value to fetch
 * @return string
 */
function nfe_get_field( $value = '' ) {
	$nfe_fields = get_option( 'woocommerce_nfe-woo-integration_settings' );

	if ( empty( $value ) ) {
		$output = $nfe_fields;
	} else {
		$output = $nfe_fields[$value];
	}

	return $output;
}

/**
 * Check to make sure the user has all the fields for a NFe issue
 * 
 * @param  int $order Product Order
 * @return boole true|false
 */
function nfe_user_address_filled( $order ) {
	$order_email = get_post_meta( $order, '_billing_email', true );
	$user        = get_user_by( 'email', $order_email );
	$user_id     = $user->ID;

	$fields = array(
		'neighborhood' => get_user_meta( $user_id, 'billing_neighborhood', true ),
		'address_1'    => get_user_meta( $user_id, 'billing_address_1', true ),
		'number'       => get_user_meta( $user_id, 'billing_number', true ),
		'postcode'     => get_user_meta( $user_id, 'billing_postcode', true ),
		'state'        => get_user_meta( $user_id, 'billing_state', true ),
		'city'         => get_user_meta( $user_id, 'billing_city', true ),
		'country'      => get_user_meta( $user_id, 'billing_country', true ),
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
 * Past Issue Check (Can we issue a past order?)
 * 
 * @param  string $post_date Post date
 * @param  string $past_days Days in the past for time check
 * @return bool true|false
 */
function nfe_issue_past_orders( $order ) {
	$time   = $order->post->post_date;
	$days   = '-' . nfe_get_field( 'issue_past_days' ) . ' days';

	if ( strtotime( $days ) < strtotime( $time ) ) {
		return true;
	}

	return false;
}
