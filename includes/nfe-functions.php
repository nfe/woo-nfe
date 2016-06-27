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
