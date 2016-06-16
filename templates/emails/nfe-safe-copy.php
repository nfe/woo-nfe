<?php

/**
 * NFe safe copy email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/nfe-safe-copy.php.
 *
 * @see 	    https://docs.woothemes.com/document/template-structure/
 * @author 		NFe.io
 * @package 	WooCommerce_NFe/Templates/Emails
 * @version     1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php __('This is a safe copy email.', 'woocommerce-nfe'); ?></p>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
