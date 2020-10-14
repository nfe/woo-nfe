<?php
/**
 * @coversDefaultClass WC_NFe_AjaxTest
 */
class WC_NFe_AjaxTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testOutput_pdf
	 */
	public function testOutput_pdf() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testInit
	 */
	public function testInit() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testFront_issue
	 */
	public function testFront_issue() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testDownload_pdf
	 */
	public function testDownload_pdf() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testFront_download_pdf
	 */
	public function testFront_download_pdf() {$woo_nfe = woo_nfe();
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
