<?php

/**
 * NFe Test Class
 *
 * @author   NFe.io
 * @package Woocommerce_NFe\Tests\NFe_Test
 * @since 1.0.0
 */
class NFe_Test extends WP_UnitTestCase {

	/**
	 * NFe Download Test
	 */
	public function test_download() {
		// Fetch an ID in the Orders page - /wp-admin/edit.php?post_type=shop_order
		$order = nfe_wc_get_order( 102 );
		$pdf   = NFe_Woo()->down_invoice( array( $order ) );

		$this->assertNotNull($pdf);
	}

	/**
	 * NFe Fields
	 */
	public function test_nfe_options_fields() {
		$nfe_fields = nfe_get_field();

		$this->assertNotNull($nfe_fields);
	}
}
