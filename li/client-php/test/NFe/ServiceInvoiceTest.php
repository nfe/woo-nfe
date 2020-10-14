<?php

class NFe_ServiceInvoiceTest extends NFe_TestCase {
	public function testIssue() {
		$this->invoice = NFe_ServiceInvoice::create("5f74fd838e9d0929c843416e",
			array(
			'cityServiceCode' => '2690',
			'description'     => 'TESTE EMISSAO',
			'servicesAmount'  => 0.01,
				'borrower' => array(
				'federalTaxNumber' => 191,
				'name' => 'TEST BANCO DO BRASIL SA',
				'email' => 'exemplo@company.com.br',
					'address' => array(
					'country'               => 'BRA',
					'postalCode'            => '70073901',
					'street'                => 'Outros Quadra 1 Bloco G Lote 32',
					'number'                => 'S/N',
					'additionalInformation' => 'QUADRA 01 BLOCO G',
					'district' => 'Asa Sul',
						'city' => array(
							'code' => '5300108',
							'name' => 'Brasilia'
						),
					'state' => 'DF'
					)
				)
			)
		);

		$this->assertNotNull($this->invoice);
		$this->assertNotNull($this->invoice->id);
		$this->assertEquals(0.01, $this->invoice->servicesAmount);
		$this->assertEquals('2690', $this->invoice->cityServiceCode);
	}

	public function testFetchInvoice() {
	$fetched_invoice = NFe_ServiceInvoice::fetch("5f74fd838e9d0929c843416e", $this->invoice->id);

	$this->assertNotNull( $fetched_invoice );
	$this->assertNotNull( $fetched_invoice->id );
	$this->assertNotNull( $fetched_invoice->borrower );
	$this->assertEquals("TEST BANCO DO BRASIL SA", $fetched_invoice->borrower->name);
	}

	public function testDownloadPdfInvoice() {
	$url = NFe_ServiceInvoice::pdf(
	  "5f74fd838e9d0929c843416e",
	  $this->invoice->id
	);

	$this->assertTrue( strpos($url, "pdf") );
	}

	public function testDownloadXmlInvoice() {
	$url = NFe_ServiceInvoice::xml("5f74fd838e9d0929c843416e", $this->invoice->id);
	$this->assertTrue( strpos($url, "xml") );
	}

	public function testCancelInvoice() {
	$fetched_invoice = NFe_ServiceInvoice::fetch(
	  "5f74fd838e9d0929c843416e",
	  $this->invoice->id
	);

	$this->assertNotNull($fetched_invoice);
	$this->assertNotNull($fetched_invoice->id);
	$this->assertEquals($this->invoice->id, $fetched_invoice->id);

	// cancel invoice
	$this->assertTrue($fetched_invoice->cancel());
	}
}
