<?php
/**
 * The Template for displaying NFe issued
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/nfe-receipt-issued.php.
 *
 * @see 	    https://docs.woothemes.com/document/template-structure/
 * @author 		NFe.io
 * @package 	WooCommerce_NFe/Templates/Emails
 * @version     1.0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'NFe issued successfully. Here follows an email for your backing.', 'woo-nfe' ); ?></p>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
