<?php
/**
 * @coversDefaultClass WC_NFe_EmailsTest
 */
class WC_NFe_EmailsTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testInit
	 */
	public function testInit() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testAdd_emails
	 */
	public function testAdd_emails() {$woo_nfe = woo_nfe();
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
