<?php
/**
 * Missing PHP extensions notice.
 *
 * Replaces the old SOAP notice: the NFe.io SDK talks HTTP over cURL and speaks
 * JSON, and uses no SOAP at all.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Admin/Notices
 * @version  1.5.0
 *
 * @var array $missing_extensions Names of the extensions that are missing.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$missing_extensions = isset( $missing_extensions ) && is_array( $missing_extensions ) ? $missing_extensions : array();
?>

<div class="error">
	<p>
		<strong><?php esc_html_e( 'NFe for WooCommerce', 'woo-nfe' ); ?></strong>
		<?php
		printf(
			/* translators: %s: comma-separated list of missing PHP extensions. */
			esc_html__( 'needs the following PHP extension(s) to talk to the NFe.io API: %s. Ask your host to enable them.', 'woo-nfe' ),
			esc_html( implode( ', ', $missing_extensions ) )
		);
		?>
	</p>
</div>
