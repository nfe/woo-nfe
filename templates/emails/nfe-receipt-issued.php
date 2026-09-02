<?php
/**
 * The Template for displaying NFe issued
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/nfe-receipt-issued.php.
 *
 * @see     https://docs.woothemes.com/document/template-structure/
 * @author  NFe.io
 * @package WooCommerce_NFe/Templates/Emails
 * @version 1.5.0
 *
 * @var WC_Order $order         Order the receipt belongs to.
 * @var string   $email_heading Heading configured for this e-mail.
 * @var WC_Email $email         E-mail object rendering this template.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Email header.
 *
 * @hooked WC_Emails::email_header() Output the email header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'The service receipt (NFS-e) for your order has been issued.', 'woo-nfe' ); ?></p>

<?php
/*
 * The receipt is downloaded from the order page rather than linked to
 * directly. It is a fiscal document carrying the buyer's tax ID and address,
 * and the download is protected by a nonce tied to the logged-in customer --
 * a nonce that cannot be generated here, because this e-mail is sent from a
 * webhook request that has no user session.
 */
if ( isset( $order ) && is_a( $order, 'WC_Order' ) ) :
	$nfe    = nfe_get_order_meta( $order, 'nfe_issued' );
	$number = ( is_array( $nfe ) && ! empty( $nfe['number'] ) ) ? $nfe['number'] : '';
	?>
	<?php if ( '' !== $number ) : ?>
		<p>
			<?php
			printf(
				/* translators: %s: invoice number. */
				esc_html__( 'Receipt number: %s', 'woo-nfe' ),
				esc_html( $number )
			);
			?>
		</p>
	<?php endif; ?>

	<p>
		<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
			<?php esc_html_e( 'View your order and download the receipt', 'woo-nfe' ); ?>
		</a>
	</p>
<?php endif; ?>

<?php
/**
 * Email footer.
 *
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email );
