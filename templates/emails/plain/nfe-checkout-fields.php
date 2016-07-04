<?php

/**
 * NFe checkout fields filling email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/nfe-checkout-fields.php.
 *
 * @see 	    https://docs.woothemes.com/document/template-structure/
 * @author		NFe.io
 * @package 	WooCommerce_NFe/Templates/Emails/Plain
 * @version		1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

echo "= " . $email_heading . " =\n\n";

echo sprintf( __('To issue a NFe receipt, you need to fill the NFe information through this link:: <a href="%s">fill information</a>.', 'woocommerce-nfe'), esc_url( wc_get_page_permalink( 'myaccount' ) ) );

echo __('Att.', 'woocommerce-nfe');

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
