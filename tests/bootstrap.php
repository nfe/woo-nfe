<?php
require_once 'helpers.php';

//$wordpress_tests_lib =  dirname( dirname( __FILE__ ) ) ."/../../../wordpress-tests-lib";
$wordpress_tests_lib =  dirname( dirname( __FILE__ ) );
$apiKey = "J6aFxW5rTe4uPKb0XtmWtJjJNR0aEtF0E4bq9KAqF8i8fGBgbIkqnW6irZn3pq8YJr1";
$companyId = "54244e0ee340420fdc94ad09";

//$apiKey = "pvzwBy2Ak40b1mN1NOffLh6WFRtMpTpMcOi9ScvHfZpdgrcRZGw4ZShN0uxNqVoUgFi";
//$companyId = "5ee800fd8a31980b9ca0cdb8";

putenv("WP_TESTS_DIR=$wordpress_tests_lib");
putenv("NFE_API_KEY=$apiKey");

define('COMPANY_ID', $companyId);

$_tests_dir = getenv('WP_TESTS_DIR');

//if (!$_tests_dir) {
//	$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
//}

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
	exit(1);
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin()
{
	$plugin_dir = dirname(dirname(__FILE__));

	require $plugin_dir . '/woo-nfe.php';
	require $plugin_dir . '../../woocommerce/woocommerce.php';

	// woocommerce testing framework
//	require $plugin_dir . '/tests/vendors/wc-framework/helpers/class-wc-helper-order.php';
//	require $plugin_dir . '/tests/vendors/wc-framework/helpers/class-wc-helper-product.php';
//	require $plugin_dir . '/tests/vendors/wc-framework/helpers/class-wc-helper-shipping.php';


//	require $plugin_dir . '/includes/utilities/class-wc-wspay-config.php';
//	require $plugin_dir . '/includes/utilities/class-wc-wspay-logger.php';
//
//	require $plugin_dir . '/includes/core/class-wc-wspay-payment-gateway.php';
//	require $plugin_dir . '/includes/core/class-wc-wspay.php';
//
//	require $plugin_dir . '/neuralab-wc-wspay.php';

}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

//require_once('BaseTestCase.php');
require_once('TestCaseNew.php');

//
//
///**
// * PHPUnit bootstrap file
// *
// * @author NFe.io
// * @package Woocommerce_NFe\Tests
// * @since 1.0.0
// */
//
//$wordpress_tests_lib =  dirname( dirname( __FILE__ ) ) ."/../../../wordpress-tests-lib";
//$wordpress_tests_lib =  "/var/www/html/wordpress-tests-lib";
//$apiKey = "J6aFxW5rTe4uPKb0XtmWtJjJNR0aEtF0E4bq9KAqF8i8fGBgbIkqnW6irZn3pq8YJr1";
//
//putenv("WP_TESTS_DIR=$wordpress_tests_lib");
//putenv("NFE_API_KEY=$apiKey");
//
//$_tests_dir = getenv( 'WP_TESTS_DIR' );

//echo 'AQUI1'.$_tests_dir;
//
//if ( ! $_tests_dir ) {
//	$_tests_dir = '/tmp/wordpress-tests-lib';
//}
//
//echo 'AQUI1'.$_tests_dir;
//
//// Give access to tests_add_filter() function.
//require_once $_tests_dir . '/includes/functions.php';
//
//require_once '/var/www/html/wordpress-tests-lib/wp-tests-config.php';
//
////require_once '/var/www/html/wordpress-tests-lib/includes/phpunit7/testcase.php';
//
///**
// * Manually load the plugin being tested.
// */
//function _manually_load_plugin() {
////	echo 'OK:'.dirname( dirname( __FILE__ ) ) . '/woo-nfe.php';
//
//	require dirname( dirname( __FILE__ ) ) . '/woo-nfe.php';
////	require dirname( dirname( __FILE__ ) ) . '../../woocommerce/woocommerce.php';
//	require '/var/www/html/wp-content/plugins/woocommerce/woocommerce.php';
////	require '/var/www/html/wp-content/plugins/woo-nfe/includes/nfe-functions.php';
//
//}
//
//tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
//
//function is_woocommerce_active() {
//	return true;
//}
//
//function woothemes_queue_update($file, $file_id, $product_id) {
//   return true;
//}
//
//
////$wc_tests_framework_base_dir = dirname( dirname( __FILE__ ) ) . '../../woocommerce/tests/framework/';
//
//
//
//
//
////require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-coupon.php'  );
////require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-fee.php'  );
////require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-shipping.php'  );
////require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-customer.php'  );
////require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-order.php'  );
//
//// Start up the WP testing environment.
//require $_tests_dir . '/includes/bootstrap.php';
//
//
////require_once('/var/www/html/wp-content/plugins/woo-nfe/li/client-php/test/NfeTests.php');
//
////echo '<pre>';
////print_r(get_included_files());
////echo '</pre>';
