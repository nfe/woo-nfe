<?php
/**
 * @coversDefaultClass NFe_class_toolsTest
 */
class NFe_class_toolsTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testGet_called_class
	 */
	public function testGet_called_class() {
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
