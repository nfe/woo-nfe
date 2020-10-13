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
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

require_once('TestCaseNew.php');
