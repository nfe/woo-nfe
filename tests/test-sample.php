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

//		$db = getenv( 'DB_NAME' );
		$nfe_fields = nfe_get_field();

//		echo '<pre>test_nfe_options_fields';
////		echo 'db'.$db;
//		echo 'a:'.$nfe_fields;
//		print_r($nfe_fields);
//		echo '<pre>';


//echo '<pre>';
//print_r(get_included_files());
//echo '</pre>';


		$this->assertNotNull($nfe_fields);
	}
}
