<?php
/**
 * Missing dependencies notice.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Admin/Notices
 * @version  1.0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; ?>

<div class="error">
	<p><strong><?php esc_html_e( 'The WooCommerce NFe plugin', 'woo-nfe' ); ?></strong> <?php printf( esc_html__( 'needs the %s to work!', 'woo-nfe' ), '<a href="https://secure.php.net/manual/en/class.soapclient.php" target="_blank">' . esc_html__( 'SOAP module', 'woo-nfe' ) . '</a>' ); ?></p>
</div>
