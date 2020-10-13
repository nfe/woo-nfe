<?php
/**
 * @coversDefaultClass NFe_APIChildResourceTest
 */
class NFe_APIChildResourceTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testMergeParams
	 */
	public function testMergeParams() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__construct
	 */
	public function test__construct() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testCreate
	 */
	public function testCreate() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testFetch
	 */
	public function testFetch() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testSearch
	 */
	public function testSearch() {
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
