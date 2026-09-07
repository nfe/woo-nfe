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
	$nfeio_nf_is_installed = ! empty( $nfeio_nf_all_plugins['woocommerce/woocommerce.php'] );
}
?>

<div class="error">
	<p><strong><?php esc_html_e( 'WooCommerce NFE.io', 'nfe-io-nota-fiscal-for-woocommerce' ); ?></strong> <?php esc_html_e( 'depends on the last version of WooCommerce to work!', 'nfe-io-nota-fiscal-for-woocommerce' ); ?></p>

	<?php if ( $nfeio_nf_is_installed && current_user_can( 'activate_plugin' ) ) : ?>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php&plugin_status=active' ), 'activate-plugin_woocommerce/woocommerce.php' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Active WooCommerce', 'nfe-io-nota-fiscal-for-woocommerce' ); ?>
			</a>
		</p>
		<?php
	else :
		if ( current_user_can( 'install_plugins' ) ) {
			$nfeio_nf_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' );
		} else {
			$nfeio_nf_url = 'https://wordpress.org/plugins/woocommerce/';
		}
		?>
		<p><a href="<?php echo esc_url( $nfeio_nf_url ); ?>" class="button button-primary">
			<?php esc_html_e( 'Install WooCommerce', 'nfe-io-nota-fiscal-for-woocommerce' ); ?></a>
		</p>
	<?php endif; ?>
</div>
