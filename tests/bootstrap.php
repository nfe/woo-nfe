<?php

/**
 * PHPUnit bootstrap file
 *
 * @author NFe.io
 * @package Woocommerce_NFe\Tests
 * @since 1.0.0
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/woocommerce-nfe.php';
	require dirname( dirname( __FILE__ ) ) . '../../woocommerce/woocommerce.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

function is_woocommerce_active() {
	return true;
}

function woothemes_queue_update($file, $file_id, $product_id) {
   return true;
}

$wc_tests_framework_base_dir = dirname( dirname( __FILE__ ) ) . '../../woocommerce/tests/framework/';
require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-product.php'  );
require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-coupon.php'  );
require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-fee.php'  );
require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-shipping.php'  );
require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-customer.php'  );
require_once( $wc_tests_framework_base_dir . 'helpers/class-wc-helper-order.php'  );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
