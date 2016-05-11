<?php

/**
 * WooCommerce NFe.io - missing SOAPClient notice.
 *
 * @author   Renato Alves
 * @package  Nfe_WooCommerce/Classes/Notices
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit; ?>

<div class="error">
	<p><strong><?php esc_html_e( 'WooCommerce NFe', 'woocommerce-nfe' ); ?></strong> <?php printf( __( 'needs %s to works!', 'woocommerce-nfe' ), '<a href="https://secure.php.net/manual/book.soap.php" target="_blank">' . __( 'SOAP module', 'woocommerce-nfe' ) . '</a>' ); ?></p>
</div>
