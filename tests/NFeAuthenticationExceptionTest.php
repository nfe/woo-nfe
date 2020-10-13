<?php
/**
 * @coversDefaultClass NFeAuthenticationExceptionTest
 */
class NFeAuthenticationExceptionTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testGetCode
	 */
	public function testGetCode() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testGetMessage
	 */
	public function testGetMessage() {
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
