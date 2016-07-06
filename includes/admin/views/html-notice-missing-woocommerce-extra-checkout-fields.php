<?php

/**
 * Missing dependencies notice.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Admin/Notices
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

$is_installed = false;

if ( function_exists( 'get_plugins' ) ) {
	$all_plugins  = get_plugins();
	$is_installed = ! empty( $all_plugins['woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php'] );
}
?>

<div class="error">
	<p><strong><?php esc_html_e( 'WooCommerce Nfe.io', 'woocommerce-nfe' ); ?></strong> <?php esc_html_e( 'depends on the last version of WooCommerce Extra Checkout Fields for Brazil to work!', 'woocommerce-nfe' ); ?></p>

	<?php if ( $is_installed && current_user_can( 'install_plugins' ) ) : ?>
		<p><a href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php&plugin_status=active' ), 'activate-plugin_woocommerce_checkout_fields/woocommerce-extra-checkout-fields-for-brazil.php' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Active WooCommerce Extra Checkout Fields for Brazil', 'woocommerce-nfe' ); ?></a></p>
	<?php else : ?>
	<?php
	if ( current_user_can( 'install_plugins' ) ) {
		$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce-extra-checkout-fields-for-brazil' ), 'install-plugin_woocommerce_checkout_fields' );
	} else {
		$url = 'https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/';
	}
	?>
		<p><a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Install WooCommerce Extra Checkout Fields for Brazil', 'woocommerce-nfe' ); ?></a></p>
	<?php endif; ?>
</div>
