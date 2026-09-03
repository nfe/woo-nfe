<?php
/**
 * The Template for displaying NFe issued (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/nfe-receipt-issued.php.
 *
 * @see     https://docs.woothemes.com/document/template-structure/
 * @author  NFe.io
 * @package WooCommerce_NFe/Templates/Emails/Plain
 * @version 1.5.0
 *
 * @var WC_Order $order         Order the receipt belongs to.
 * @var string   $email_heading Heading configured for this e-mail.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Plain text context: markup is STRIPPED, never escaped. Running esc_html() here
// would turn & " ' < > into visible HTML entities in a message that is not HTML,
// so wp_strip_all_tags() is the whole of the output boundary for this template.
echo '= ' . wp_strip_all_tags( $email_heading ) . " =\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text e-mail; tags stripped, entities would be visible.

echo wp_strip_all_tags( __( 'The service receipt (NFS-e) for your order has been issued.', 'nota-fiscal-nfe-io-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text e-mail.

/*
 * Points at the order page rather than the file: the download is nonce-guarded
 * for the logged-in customer, and this e-mail is sent from a webhook request
 * that has no session to build such a nonce from.
 */
if ( isset( $order ) && is_a( $order, 'WC_Order' ) ) {
	$nfe    = nfe_get_order_meta( $order, 'nfe_issued' );
	$number = ( is_array( $nfe ) && ! empty( $nfe['number'] ) ) ? $nfe['number'] : '';

	if ( '' !== $number ) {
		// translators: %s: invoice number.
		echo "\n\n" . wp_strip_all_tags( sprintf( __( 'Receipt number: %s', 'nota-fiscal-nfe-io-for-woocommerce' ), $number ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text e-mail.
	}

	echo "\n\n" . wp_strip_all_tags( __( 'View your order and download the receipt:', 'nota-fiscal-nfe-io-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text e-mail.
	echo "\n" . esc_url_raw( $order->get_view_order_url() );
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text e-mail.
