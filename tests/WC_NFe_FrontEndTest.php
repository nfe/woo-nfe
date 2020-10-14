<?php
/**
 * @coversDefaultClass WC_NFe_FrontEndTest
 */
class WC_NFe_FrontEndTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testAccount_desc
	 */
	public function testAccount_desc() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testNfe_column
	 */
	public function testNfe_column() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testColumn_content
	 */
	public function testColumn_content() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__construct
	 */
	public function test__construct() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testBilling_notice
	 */
	public function testBilling_notice() {$woo_nfe = woo_nfe();
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
