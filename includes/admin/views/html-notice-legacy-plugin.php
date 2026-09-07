<?php
/**
 * Previous release still active notice.
 *
 * @author   NFE.io
 * @package  NFEIO_NF_Plugin/Admin/Notices
 * @version  1.5.0
 *
 * @var string $nfeio_nf_legacy_plugin Plugin basename of the old installation.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$nfeio_nf_legacy_plugin = isset( $nfeio_nf_legacy_plugin ) ? (string) $nfeio_nf_legacy_plugin : '';
$nfeio_nf_legacy_name   = '';

if ( '' !== $nfeio_nf_legacy_plugin && function_exists( 'get_plugin_data' ) ) {
	$nfeio_nf_legacy_file = WP_PLUGIN_DIR . '/' . $nfeio_nf_legacy_plugin;

	if ( file_exists( $nfeio_nf_legacy_file ) ) {
		$nfeio_nf_legacy_data = get_plugin_data( $nfeio_nf_legacy_file, false, false );
		$nfeio_nf_legacy_name = isset( $nfeio_nf_legacy_data['Name'] ) ? $nfeio_nf_legacy_data['Name'] : '';
	}
}

if ( '' === $nfeio_nf_legacy_name ) {
	$nfeio_nf_legacy_name = $nfeio_nf_legacy_plugin;
}
?>

<div class="notice notice-error">
	<p>
		<strong><?php esc_html_e( 'NFE.io Nota Fiscal for WooCommerce', 'nfe-io-nota-fiscal-for-woocommerce' ); ?></strong>
		<?php
		printf(
			/* translators: %s: name of the previously installed plugin. */
			esc_html__( 'did not start because an older version of it is still active: %s.', 'nfe-io-nota-fiscal-for-woocommerce' ),
			'<strong>' . esc_html( $nfeio_nf_legacy_name ) . '</strong>'
		);
		?>
	</p>
	<p>
		<?php esc_html_e( 'This plugin was renamed, so WordPress sees the two installations as different plugins and would run both at once. Two copies issuing invoices means two NFS-e for the same order, which has to be cancelled by hand.', 'nfe-io-nota-fiscal-for-woocommerce' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Deactivate the older plugin and this one starts on its own. Your settings and the invoices already recorded on your orders are kept: both versions read the same data.', 'nfe-io-nota-fiscal-for-woocommerce' ); ?>
	</p>

	<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
		<p>
			<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Go to Plugins', 'nfe-io-nota-fiscal-for-woocommerce' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
