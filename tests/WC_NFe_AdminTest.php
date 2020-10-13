<?php
/**
 * @coversDefaultClass WC_NFe_AdminTest
 */
class WC_NFe_AdminTest extends NFe_TestCaseNew {
	/**
	 * @covers ::testDownload_and_issue_actions
	 */
	public function testDownload_and_issue_actions() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testNfe_admin_order_preview_details
	 */
	public function testNfe_admin_order_preview_details() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testDisplay_order_data_preview_in_admin
	 */
	public function testDisplay_order_data_preview_in_admin() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testIssue_trigger
	 */
	public function testIssue_trigger() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::test__construct
	 */
	public function test__construct() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testOrder_status_column_content
	 */
	public function testOrder_status_column_content() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testProduct_data_tab
	 */
	public function testProduct_data_tab() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testOrder_status_column_header
	 */
	public function testOrder_status_column_header() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testNfe_admin_order_preview
	 */
	public function testNfe_admin_order_preview() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testNfe_status_widget_order_rows
	 */
	public function testNfe_status_widget_order_rows() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testSave_variations_fields
	 */
	public function testSave_variations_fields() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testProduct_data_fields
	 */
	public function testProduct_data_fields() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testRegister_enqueue_css
	 */
	public function testRegister_enqueue_css() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testDownload_issue_action
	 */
	public function testDownload_issue_action() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testGet_instance
	 */
	public function testGet_instance() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testVariation_fields
	 */
	public function testVariation_fields() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testIssue_order_action
	 */
	public function testIssue_order_action() {
		$this->assertTrue(true);
	}
	/**
	 * @covers ::testProduct_data_fields_save
	 */
	public function testProduct_data_fields_save() {$woo_nfe = woo_nfe();
		/**
		 * @covers WooCommerce_NFe::nfe_integration
		 */
		$test = $woo_nfe->nfe_integration(array('test'));
		if ( $test[0] == "test" ) {
			$this->assertTrue(true);
		}else{
			$this->assertTrue(false);
		}
        $this->assertEquals('a', 'b');
	}
}
