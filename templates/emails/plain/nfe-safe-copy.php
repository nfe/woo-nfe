<?php

/**
 * NFe safe copy email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/nfe-safe-copy.php.
 *
 * @see 	    https://docs.woothemes.com/document/template-structure/
 * @author		NFe.io
 * @package 	WooCommerce_NFe/Templates/Emails/Plain
 * @version		1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

echo "= " . $email_heading . " =\n\n";

echo sprintf( __('This is a safe copy email.', 'woocommerce-nfe') );

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
