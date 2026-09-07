<?php
/**
 * Missing dependencies notice.
 *
 * @author   NFE.io
 * @package  NFEIO_NF_Plugin/Admin/Notices
 * @version  1.0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$nfeio_nf_is_installed = false;

if ( function_exists( 'get_plugins' ) ) {
	$nfeio_nf_all_plugins  = get_plugins();
	$nfeio_nf_is_installed = ! empty( $nfeio_nf_all_plugins['woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php'] );
}
?>

<div class="error">
	<p><strong><?php esc_html_e( 'WooCommerce NFE.io', 'nfe-io-nota-fiscal-for-woocommerce' ); ?></strong> <?php esc_html_e( 'depends on the lastest version of WooCommerce Extra Checkout Fields for Brazil to work!', 'nfe-io-nota-fiscal-for-woocommerce' ); ?></p>

	<?php if ( $nfeio_nf_is_installed && current_user_can( 'activate_plugin' ) ) : ?>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( 'plugins.php?action=activate&amp;plugin=woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php&amp;plugin_status=all', 'activate-plugin_woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Active WooCommerce Extra Checkout Fields for Brazil', 'nfe-io-nota-fiscal-for-woocommerce' ); ?>
			</a>
		</p>
		<?php
	else :
		if ( current_user_can( 'install_plugins' ) ) {
			$nfeio_nf_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce-extra-checkout-fields-for-brazil' ), 'install-plugin_woocommerce_checkout_fields' );
		} else {
			$nfeio_nf_url = 'https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/';
		}
		?>
		<p><a href="<?php echo esc_url( $nfeio_nf_url ); ?>" class="button button-primary">
			<?php esc_html_e( 'Install WooCommerce Extra Checkout Fields for Brazil', 'nfe-io-nota-fiscal-for-woocommerce' ); ?></a>
		</p>
	<?php endif; ?>
</div>
