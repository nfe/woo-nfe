<?php
/**
 * Missing Composer autoloader notice.
 *
 * The NFE.io SDK ships inside vendor/. Reaching this notice means the plugin
 * was installed from a package built without `composer install --no-dev`, or
 * from a git checkout where dependencies were never installed.
 *
 * @author   NFE.io
 * @package  NFEIO_NF_Plugin/Admin/Notices
 * @version  1.5.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>

<div class="error">
	<p>
		<strong><?php esc_html_e( 'NFE.io Nota Fiscal for WooCommerce', 'nfe-io-nota-fiscal-for-woocommerce' ); ?></strong>
		<?php esc_html_e( 'could not find its dependencies (vendor/autoload.php). The plugin stayed inactive. Reinstall it from the official package, or run "composer install --no-dev" in the plugin folder.', 'nfe-io-nota-fiscal-for-woocommerce' ); ?>
	</p>
</div>
