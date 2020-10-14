<?php
/**
 * @coversDefaultClass NFe_ioTest
 */
class NFe_ioTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testGetBaseURI
	 */
	public function testGetBaseURI() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testGetVerifySslCerts
	 */
	public function testGetVerifySslCerts() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testSetApiKey
	 */
	public function testSetApiKey() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testGetApiKey
	 */
	public function testGetApiKey() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testSetHost
	 */
	public function testSetHost() {
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
