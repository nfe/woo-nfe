<?php
/**
 * @coversDefaultClass WC_NFe_Email_Receipt_IssuedTest
 */
class WC_NFe_Email_Receipt_IssuedTest extends NFe_TestCaseNew {
	/**
	 * @covers ::test__construct
	 */
	public function test__construct() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testInit_form_fields
	 */
	public function testInit_form_fields() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testGet_content_plain
	 */
	public function testGet_content_plain() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testGet_content_html
	 */
	public function testGet_content_html() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testTrigger
	 */
	public function testTrigger() {$woo_nfe = woo_nfe();
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
