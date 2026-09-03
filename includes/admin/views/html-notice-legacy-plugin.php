<?php
/**
 * Previous release still active notice.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Admin/Notices
 * @version  1.5.0
 *
 * @var string $legacy_plugin Plugin basename of the old installation.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$legacy_plugin = isset( $legacy_plugin ) ? (string) $legacy_plugin : '';
$legacy_name   = '';

if ( '' !== $legacy_plugin && function_exists( 'get_plugin_data' ) ) {
	$legacy_file = WP_PLUGIN_DIR . '/' . $legacy_plugin;

	if ( file_exists( $legacy_file ) ) {
		$legacy_data = get_plugin_data( $legacy_file, false, false );
		$legacy_name = isset( $legacy_data['Name'] ) ? $legacy_data['Name'] : '';
	}
}

if ( '' === $legacy_name ) {
	$legacy_name = $legacy_plugin;
}
?>

<div class="notice notice-error">
	<p>
		<strong><?php esc_html_e( 'Nota Fiscal NFe.io for WooCommerce', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
		<?php
		printf(
			/* translators: %s: name of the previously installed plugin. */
			esc_html__( 'did not start because an older version of it is still active: %s.', 'nota-fiscal-nfe-io-for-woocommerce' ),
			'<strong>' . esc_html( $legacy_name ) . '</strong>'
		);
		?>
	</p>
	<p>
		<?php esc_html_e( 'This plugin was renamed, so WordPress sees the two installations as different plugins and would run both at once. Two copies issuing invoices means two NFS-e for the same order, which has to be cancelled by hand.', 'nota-fiscal-nfe-io-for-woocommerce' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Deactivate the older plugin and this one starts on its own. Your settings and the invoices already recorded on your orders are kept: both versions read the same data.', 'nota-fiscal-nfe-io-for-woocommerce' ); ?>
	</p>

	<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
		<p>
			<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Go to Plugins', 'nota-fiscal-nfe-io-for-woocommerce' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
