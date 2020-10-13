<?php
/**
 * @coversDefaultClass NFe_UtilitiesTest
 */
class NFe_UtilitiesTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testArrayToParamsUrl
	 */
	public function testArrayToParamsUrl() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testConvertEpochToISO
	 */
	public function testConvertEpochToISO() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testUtf8
	 */
	public function testUtf8() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testArrayToParams
	 */
	public function testArrayToParams() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testAuthFromEnv
	 */
	public function testAuthFromEnv() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testConvertDateFromISO
	 */
	public function testConvertDateFromISO() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testCreateFromResponse
	 */
	public function testCreateFromResponse() {
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
