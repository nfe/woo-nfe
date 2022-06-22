<?php
/**
 * WooCommerce NFe Custom Functions.
 *
 * @author   NFe.io
 *
 * @version  1.0.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Function to fetch fields from the NFe WooCommerce Integration.
 *
 * @param string $value value to fetch
 *
 * @return string
 */
function nfe_get_field( $value = '' ) {
	$nfe_fields = get_option( 'woocommerce_woo-nfe_settings' );

	if ( empty( $value ) ) {
		$output = $nfe_fields;
	} else {
		$output = $nfe_fields[ $value ];
	}

	return $output;
}

/**
 * Make sure address is required and if all the fields are available.
 *
 * @param int $order_id order ID
 *
 * @return bool
 */
function nfe_order_address_filled( $order_id ) {
	// If address is not required, go along.
	if ( nfe_require_address() === false ) {
		return true;
	}

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

			break;
		}
	}

	// If there is one or more fields missing, flag it.
	if ( $count >= 1 ) {
		return false;
	}

	return true;
}

/**
 * Past Issue Check (It answers the question: Can we issue a past order?).
 *
 * @param WC_Order $order order object
 *
 * @return bool
 */
function nfe_issue_past_orders( $order ) {
	$past_days = nfe_get_field( 'issue_past_days' );

	if ( empty( $past_days ) ) {
		return false;
	}

	$days = '-' . $past_days . ' days';

	if ( strtotime( $days ) < strtotime( $order->post->post_date ) ) {
		return true;
	}

	return false;
}

/**
 * WooCommerce 2.2 support for wc_get_order.
 *
 * @param int $order_id order ID
 *
 * @return WC_Order order object
 */
function nfe_wc_get_order( $order_id ) {
	return ( function_exists( 'wc_get_order' ) )
		? wc_get_order( $order_id )
		: WC_Order( $order_id );
}

/**
 * Get order information.
 *
 * @since 1.2.2
 *
 * @param string $value value to search against
 *
 * @return WP_Query
 */
function nfe_get_order_by_nota_value( $value ) {
	$query_args = array(
		'post_type'              => 'shop_order',
		'cache_results'          => true,
		'update_post_term_cache' => false,
		'post_status'            => 'any',
		'meta_query'             => array( // WPCS: slow query ok.
			array(
				'key'     => 'nfe_issued',
				'value'   => sprintf( ':"%s";', $value ),
				'compare' => 'LIKE',
			),
		),
	);

	return new WP_Query( $query_args );
}

/**
 * Status when the NFe in being processed.
 *
 * @since 1.2.4
 *
 * @return array
 */
function nfe_processing_status() {
	return array( 'WaitingCalculateTaxes', 'WaitingDefineRpsNumber', 'WaitingSend', 'WaitingSendCancel', 'WaitingReturn', 'WaitingDownload' );
}

/**
 * Does an address is required?
 *
 * @return bool
 */
function nfe_require_address() {
	$result = nfe_get_field( 'require_address' );

	if ( empty( $result ) ) {
		return true;
	}

	if ( 'no' === $result ) {
		return false;
	}

	return true;
}

/**
 * Get NFe status label.
 *
 * @param string $status status
 *
 * @return string
 */
function nfe_status_label( $status ) {
	// Check processing status first.
	if ( in_array( $status, nfe_processing_status(), true ) ) {
		return __( 'Processing NFe', 'woo-nfe' );
	}

	$valid_status = array(
		'Issued'          => __( 'NFe Issued', 'woo-nfe' ),
		'Cancelled'       => __( 'NFe Cancelled', 'woo-nfe' ),
		'CancelledFailed' => __( 'NFe Cancelling Failed', 'woo-nfe' ),
		'IssueFailed'     => __( 'NFe Issuing Failed', 'woo-nfe' ),
		'Processing'      => __( 'NFe Processing', 'woo-nfe' ),
	);

	foreach ( $valid_status as $key => $title ) {
		if ( $status === $key ) {
			return $title;
		}
	}

	return '';
}
