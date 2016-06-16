<?php

/**
 * NFe checkout fields filling email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/nfe-checkout-fields.php.
 *
 * @see 	    https://docs.woothemes.com/document/template-structure/
 * @author 		NFe.io
 * @package 	WooCommerce_NFe/Templates/Emails
 * @version     1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

$link = '';

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php printf( __('Para a emissão da sua NFe, é preciso preencher os dados do contribuinte, através deste link: <a href="%s">preencher informações</a>', 'woocommerce-nfe'), $link ); ?></p>

<p><?php _e('Atenciosamente.', 'woocommerce-nfe'); ?></p>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
