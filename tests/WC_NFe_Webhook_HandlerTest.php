<?php
/**
 * @coversDefaultClass WC_NFe_Webhook_HandlerTest
 */
class WC_NFe_Webhook_HandlerTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testHandle
	 */
	public function testHandle() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testLogger
	 */
	public function testLogger() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__construct
	 */
	public function test__construct() {$woo_nfe = woo_nfe();
		/**
		 * @covers WooCommerce_NFe::nfe_integration
		 */
		$test = $woo_nfe->nfe_integration(array('test'));
		if ( $test[0] == "test" ) {
			$this->assertTrue(true);
		}else{
			$this->assertTrue(false);
		}
		$this->assertEquals('a', 'b');
	}
}
