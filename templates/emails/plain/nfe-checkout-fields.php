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

echo sprintf( __('Para a emissão da sua NFe, é preciso preencher os dados do contribuinte, através deste link: <a href="%s">preencher informações</a>', 'woocommerce-nfe'), esc_url( wc_get_page_permalink( 'myaccount' ) ) );

echo __('Atenciosamente.', 'woocommerce-nfe');

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
