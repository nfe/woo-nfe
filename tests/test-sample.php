<?php
/**
 * NFe Test Class
 *
 * @author   NFe.io
 * @package Woocommerce_NFe\Tests\NFe_Test
 * @since 1.0.0
 */
class NFe_Test extends WP_UnitTestCase {

	/**
	 * NFe Fields
	 */
	public function test_nfe_options_fields() {
		$nfe_fields = nfe_get_field();

		$this->assertNotNull( $nfe_fields );
	}
}
