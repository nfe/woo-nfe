<?php

/**
 * Missing dependencies notice.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Admin/Notices
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit; ?>

<div class="error">
	<p><strong><?php _e( 'The WooCommerce NFe plugin', 'woocommerce-nfe' ); ?></strong> <?php printf( __( 'needs the %s to work!', 'woocommerce-nfe' ), '<a href="https://secure.php.net/manual/book.soap.php" target="_blank">' . __( 'SOAP module', 'woocommerce-nfe' ) . '</a>' ); ?></p>
</div>
