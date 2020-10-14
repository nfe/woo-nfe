<?php
/**
 * NFe Test Class
 *
 * @author   NFe.io
 * @package Woocommerce_NFe\Tests\NFe_Test
 * @since 1.0.0
 */
//class NFe_Test extends UnitTestCase {
//class NFe_Test extends NFe_TestCaseNew {

//class NFe_Test extends WP_UnitTestCase {
//declare(strict_types=1);
//use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass NFe_Extension_Test
 */
//class NFe_Extension_Test extends PHPUnit_Framework_TestCase {
class NFe_Extension_Test extends NFe_TestCaseNew {

//	/**
//	 * @covers \<Namespace>\Container::register
//	 * @covers \<Namespace>\Container::extend
//	 * @covers \<Namespace>\Container::get
//	 */

//	public function test_basic_functions_are_available() {
////		$this->assertEquals(['foo', 'bar'], (new Container([woo_nfe()]))->get('value'));
//
//		$this->assertTrue( function_exists( 'woo_nfe' ) );
////		$this->assertTrue( function_exists( 'wc_ee_instance' ) );
//
//		$test = woo_nfe();
//		var_dump(class_exists('WooCommerce_NFe'));
////
//		print_r($test);
//
//
//		$o = woo_nfe();
//		$o->nfe_integration(array());
//
//		print_r($o);
//
//		$this->assertTrue( function_exists( 'woo_nfe' ) );
//
//	}


	/**
	 * @covers ::testSomething
	 */
	public function testSomething()
	{
		$o = woo_nfe();

		/**
		 * @covers WooCommerce_NFe::nfe_integration
		 */
		$o->nfe_integration(array());

		print_r($o);

		$this->assertTrue( function_exists( 'woo_nfe' ) );
	}

	/**
	 * @covers ::test_nfe_options_fields
	 */
	public function test_nfe_options_fields() {

		/**
		 * @covers ::nfe_get_field
		 */
		$nfe_fields = nfe_get_field('issue_when');
////		$nfe_fields = nfe_get_field();
//		$nfe_fields = get_option('woocommerce_woo-nfe_settings');


//		$test = new WooCommerce_NFe();
////		var_dump($test);
//
//		print_r($test);
		echo 'ai';
		print_r($nfe_fields);
		echo $nfe_fields;
		echo 'ai';

		$this->assertNotNull( true );
	}


	/**
	 * @covers ::test_nfe_options_fields
	 */
	public function test_api_key() {
		$apiKey = getenv( 'NFE_API_KEY' );
//		echo 'aqui'.$apiKey;
		$this->assertEquals('J6aFxW5rTe4uPKb0XtmWtJjJNR0aEtF0E4bq9KAqF8i8fGBgbIkqnW6irZn3pq8YJr1', $apiKey);
	}


	/**
	 * @covers ::test_nfe_options_fields
	 */
	public function test_get_conn() {

//		echo '<pre>';
//		print_r(get_included_files());
//		echo '</pre>';

//		$apiKey = getenv( 'NFE_API_KEY' );
//		$db = DB_NAME;
//		$nfe_fields = nfe_get_field();
//
//		echo '<pre>test_nfe_options_fields';
//		echo 'DB_NAME'.$db ."\n";
//		echo '$apiKey:'.$apiKey ."\n";
//		echo '</pre>';


//		echo '<pre>';



//		$apiKey = getenv('NFE_API_KEY');
//		NFe_io::setApiKey($apiKey);

//		print_r($GLOBALS);
//		echo '</pre>';

//		$this->assertEquals('J6aFxW5rTe4uPKb0XtmWtJjJNR0aEtF0E4bq9KAqF8i8fGBgbIkqnW6irZn3pq8YJr1', $apiKey);
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
		$this->assertNotNull(true);
	}
}
