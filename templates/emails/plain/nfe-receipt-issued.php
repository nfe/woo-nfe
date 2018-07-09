<?php
/**
 * The Template for displaying NFe issued (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/nfe-receipt-issued.php.
 *
 * @see 	    https://docs.woothemes.com/document/template-structure/
 * @author		NFe.io
 * @package 	WooCommerce_NFe/Templates/Emails/Plain
 * @version		1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

echo "= " . $email_heading . " =\n\n";

echo sprintf( __('NFe issued successfully. Here follows an email for your backing.', 'woo-nfe') );

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
