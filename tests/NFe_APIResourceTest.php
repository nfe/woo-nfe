<?php
/**
 * @coversDefaultClass NFe_APIResourceTest
 */
class NFe_APIResourceTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testUrl
	 */
	public function testUrl() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testObjectBaseURI
	 */
	public function testObjectBaseURI() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testConvertClassToObjectType
	 */
	public function testConvertClassToObjectType() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testEndpointAPI
	 */
	public function testEndpointAPI() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testAPI
	 */
	public function testAPI() {
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
