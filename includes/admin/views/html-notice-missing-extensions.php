<?php
/**
 * Missing PHP extensions notice.
 *
 * Replaces the old SOAP notice: the NFE.io SDK talks HTTP over cURL and speaks
 * JSON, and uses no SOAP at all.
 *
 * @author   NFE.io
 * @package  NFEIO_NF_Plugin/Admin/Notices
 * @version  1.5.0
 *
 * @var array $nfeio_nf_missing_extensions Names of the extensions that are missing.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$nfeio_nf_missing_extensions = isset( $nfeio_nf_missing_extensions ) && is_array( $nfeio_nf_missing_extensions ) ? $nfeio_nf_missing_extensions : array();
?>

<div class="error">
	<p>
		<strong><?php esc_html_e( 'NFE.io Nota Fiscal for WooCommerce', 'nfe-io-nota-fiscal-for-woocommerce' ); ?></strong>
		<?php
		printf(
			/* translators: %s: comma-separated list of missing PHP extensions. */
			esc_html__( 'needs the following PHP extension(s) to talk to the NFE.io API: %s. Ask your host to enable them.', 'nfe-io-nota-fiscal-for-woocommerce' ),
			esc_html( implode( ', ', $nfeio_nf_missing_extensions ) )
		);
		?>
	</p>
</div>
