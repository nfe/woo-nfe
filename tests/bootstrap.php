<?php
/**
 * PHPUnit bootstrap file
 *
 * @author NFe.io
 * @package Woocommerce_NFe\Tests
 * @since 1.0.0
 */

$wordpress_tests_lib =  dirname( dirname( __FILE__ ) ) ."/../../../wordpress-tests-lib";

putenv("WP_TESTS_DIR=$wordpress_tests_lib");

$_tests_dir = getenv( 'WP_TESTS_DIR' );

//echo 'AQUI1'.$_tests_dir;

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

//echo 'AQUI1'.$_tests_dir;

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

//require_once '/var/www/html/wordpress-tests-lib/includes/phpunit7/testcase.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
//	echo 'OK:'.dirname( dirname( __FILE__ ) ) . '/woo-nfe.php';
	require dirname( dirname( __FILE__ ) ) . '/woo-nfe.php';
	require dirname( dirname( __FILE__ ) ) . '../../woocommerce/woocommerce.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

function is_woocommerce_active() {
	return true;
}

function woothemes_queue_update($file, $file_id, $product_id) {
   return true;
}


//$wc_tests_framework_base_dir = dirname( dirname( __FILE__ ) ) . '../../woocommerce/tests/framework/';


//require_once('/var/www/html/wp-content/plugins/woo-nfe/li/client-php/test/Nfe.php');

//require_once('/var/www/html/wp-content/plugins/woo-nfe/li/client-php/test/NfeTests.php');


//require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-coupon.php'  );
//require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-fee.php'  );
//require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-shipping.php'  );
//require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-customer.php'  );
//require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-order.php'  );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';


//echo '<pre>';
//print_r(get_included_files());
//echo '</pre>';
