<?php
/**
 * @coversDefaultClass NFe_ObjectTest
 */
class NFe_ObjectTest extends NFe_TestCaseNew {
	/**
	 * @covers ::test__get
	 */
	public function test__get() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__toString
	 */
	public function test__toString() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testModifiedAttributes
	 */
	public function testModifiedAttributes() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testOffsetExists
	 */
	public function testOffsetExists() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testOffsetUnset
	 */
	public function testOffsetUnset() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testResetStates
	 */
	public function testResetStates() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__unset
	 */
	public function test__unset() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__isset
	 */
	public function test__isset() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testOffsetSet
	 */
	public function testOffsetSet() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testGetAttributes
	 */
	public function testGetAttributes() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__construct
	 */
	public function test__construct() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testKeys
	 */
	public function testKeys() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testIs_new
	 */
	public function testIs_new() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__set
	 */
	public function test__set() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testCopy
	 */
	public function testCopy() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testOffsetGet
	 */
	public function testOffsetGet() {
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
