<?php
/**
 * @coversDefaultClass NFe_APIRequestTest
 */
class NFe_APIRequestTest extends NFe_TestCaseNew {
	/**
	 * @covers ::test__construct
	 */
	public function test__construct() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testRequest
	 */
	public function testRequest() {
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
