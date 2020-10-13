<?php
/**
 * @coversDefaultClass NFe_ServiceInvoiceTest
 */
class NFe_ServiceInvoiceTest extends NFe_TestCaseNew {
//	private static $id = "5f80770e7990af0ba068d078";
	private static $id = NULL;
//	/**
//	 * @covers ::testCreate
//	 */
//	public function testCreate() {
//		NFe_TestCaseNew::getConn();
//		$this->invoice = (object) NFe_ServiceInvoice::create("5f74fd838e9d0929c843416e",
//			array(
//				'cityServiceCode' => '2660',
//				'description'     => 'TESTE EMISSAO 1',
//				'servicesAmount'  => 0.01,
//				'borrower' => array(
//					'federalTaxNumber' => 191,
//					'name' => 'TEST BANCO DO BRASIL SA',
//					'email' => 'exemplo@company.com.br',
//						'address' => array(
//							'country' => 'BRA',
//							'postalCode' => '01001001',
//							'street' => 'Praça da Sé',
//							'number' => '1',
//							'additionalInformation' => '',
//							'district' => 'Centro',
//								'city'  => array(
//									'code' => '3550308',
//									'name' => 'Sao Paulo'
//								),
//							'state' => 'SP'
//						),
//				)
//			)
//		);
//
//		$this->assertNotNull($this->invoice);
//		$this->assertNotNull($this->invoice->id);
//		$this->assertEquals(0.01, $this->invoice->servicesAmount);
//		$this->assertEquals('2660', $this->invoice->cityServiceCode);
//		self::$id = $this->invoice->id;
//	}
//	/**
//	 * @covers ::testFetch
//	 */
//	public function testFetch() {
//		NFe_TestCaseNew::getConn();
//		$fetched_invoice = (object) NFe_ServiceInvoice::fetch("5f74fd838e9d0929c843416e", self::$id);
//		$this->assertNotNull($fetched_invoice);
//		$this->assertNotNull($fetched_invoice->id);
//		$this->assertNotNull($fetched_invoice->borrower);
//		$this->assertEquals("TEST BANCO DO BRASIL SA", $fetched_invoice->borrower->name);
//	}
//	/** Todo
//	 * @covers ::testXml
//	 */
//	public function testXml() {
//		NFe_TestCaseNew::getConn();
//		$url = (object) NFe_ServiceInvoice::xml("5f74fd838e9d0929c843416e", self::$id);
//		print_r($url);
//		$this->assertTrue( strpos($url, "xml") );
//	}
//	/**
//	 * @covers ::testPdf
//	 */
	public function testPdf() {
		NFe_TestCaseNew::getConn();
		$url = (object) NFe_ServiceInvoice::pdf("5f74fd838e9d0929c843416e", "5f80ae77a675240e502641bc");
		print_r($url);
		$this->assertTrue( strpos($url, "pdf") );
	}
//	/**
//	 * @covers ::testCancel
//	 */
//	public function testCancel() {
//		NFe_TestCaseNew::getConn();
//		$fetched_invoice = (object) NFe_ServiceInvoice::fetch("5f74fd838e9d0929c843416e", self::$id);
//		$this->assertNotNull($fetched_invoice);
//		$this->assertNotNull($fetched_invoice->id);
//		$this->assertEquals(self::$id, $fetched_invoice["id"]);
//		$this->assertTrue($fetched_invoice->cancel());
//	}
}
