<?php
/**
 * Missing dependencies notice.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Admin/Notices
 * @version  1.0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$is_installed = false;

if ( function_exists( 'get_plugins' ) ) {
	$all_plugins  = get_plugins();
	$is_installed = ! empty( $all_plugins['woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php'] );
}
?>

<div class="error">
	<p><strong><?php esc_html_e( 'WooCommerce NFe.io', 'woo-nfe' ); ?></strong> <?php esc_html_e( 'depends on the lastest version of WooCommerce Extra Checkout Fields for Brazil to work!', 'woo-nfe' ); ?></p>

	<?php if ( $is_installed && current_user_can( 'activate_plugin' ) ) : ?>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( 'plugins.php?action=activate&amp;plugin=woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php&amp;plugin_status=all', 'activate-plugin_woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Active WooCommerce Extra Checkout Fields for Brazil', 'woo-nfe' ); ?>
			</a>
		</p>
		<?php
	else :
		if ( current_user_can( 'install_plugins' ) ) {
			$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce-extra-checkout-fields-for-brazil' ), 'install-plugin_woocommerce_checkout_fields' );
		} else {
			$url = 'https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/';
		}
		?>
		<p><a href="<?php echo esc_url( $url ); ?>" class="button button-primary">
			<?php esc_html_e( 'Install WooCommerce Extra Checkout Fields for Brazil', 'woo-nfe' ); ?></a>
		</p>
	<?php endif; ?>
</div>
