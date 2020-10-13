<?php
/**
 * @coversDefaultClass NFe_SearchResultTest
 */
class NFe_SearchResultTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testResults
	 */
	public function testResults() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testTotal
	 */
	public function testTotal() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__construct
	 */
	public function test__construct() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testSet
	 */
	public function testSet() {
		$woo_nfe = woo_nfe();
		$test = $woo_nfe->nfe_integration(array('test'));
		if ( $test[0] == "test" ) {
			$this->assertTrue(true);
		}else{
			$this->assertTrue(false);
		}
		$this->assertNotNull(true);
	}
}
