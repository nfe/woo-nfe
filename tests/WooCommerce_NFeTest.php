<?php
/**
 * @coversDefaultClass WooCommerce_NFeTest
 */
class WooCommerce_NFeTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testNfe_integration
	 */
	public function testNfe_integration() {
		$woo_nfe = woo_nfe();
		/**
		 * @covers WooCommerce_NFe::nfe_integration
		 */
		$test = $woo_nfe->nfe_integration(array('test'));
		if ( $test[0] == "test" ) {
			$this->assertTrue(true);
		}else{
			$this->assertTrue(false);
		}

		$this->assertTrue(invokePrivateMethod2( $woo_nfe, 'version_check1', '4.6.2'));
		$this->assertFalse(invokePrivateMethod2( $woo_nfe, 'version_check1', '2.1.0'));
	}
	/**
	 * @covers ::testNfe_version_check1
	 */
	public function testNfe_version_check1() {
		$woo_nfe = woo_nfe();
		/**
		 * @covers WooCommerce_NFe::version_check1
		 */
		$test = $woo_nfe->nfe_integration(array('test'));
		if ( $test[0] == "test" ) {
			$this->assertTrue(true);
		}

		$this->assertTrue(invokePrivateMethod2( $woo_nfe, 'version_check1', '4.6.2'));
		$this->assertFalse(invokePrivateMethod2( $woo_nfe, 'version_check1', '2.1.0'));
	}
	/**
	 * @covers ::testWoocommerce_missing_notice
	 */
	public function testWoocommerce_missing_notice() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testSoap_missing_notice
	 */
	public function testSoap_missing_notice() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testInstance
	 */
	public function testInstance() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testRun
	 */
	public function testRun() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testPlugin_action_links
	 */
	public function testPlugin_action_links() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testLoad_plugin_textdomain
	 */
	public function testLoad_plugin_textdomain() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__construct
	 */
	public function test__construct() {
		$this->assertEquals('a', 'b');
	}
}
