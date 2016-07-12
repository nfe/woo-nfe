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
	 * NFe Issue Test
	 */
	public function test_issue() {
		$order   = WC_Helper_Order::create_order();
		$invoice = NFe_Woo()->issue_invoice( array( $order->id ) );

		$this->assertNotNull($invoice->id);
		$this->assertEquals($invoice->servicesAmount, 0.01);
		$this->assertEquals($invoice->cityServiceCode, '2690');
	}

	/**
	 * NFe Download Test
	 */
	public function test_download() {
		$order = WC_Helper_Order::create_order();
		$pdf   = NFe_Woo()->down_invoice( array( $order->id ) );

		$this->assertNotNull($pdf);
		$this->assertFileExists($pdf);
	}
}
