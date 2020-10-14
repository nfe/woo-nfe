<?php
/**
 * @coversDefaultClass NFe_CompanyTest
 */
class NFe_CompanyTest extends NFe_TestCaseNew {
	private static $id = null;
	/**
	 * @covers ::testCreate
	 */
	public function testCreate() {
		NFe_TestCaseNew::getConn();
		$attributes = array(
		  'federalTaxNumber' => cnpjRandom(0), // Generate CNPJ here: http://www.geradordecnpj.org/
		  'name'             => 'TEST Company Name',
		  'tradeName'        => 'Company Name',
		  'email'            => 'nfe@mailinator.com',
			  'address'          => array(
				'country'               => 'BRA',
				'postalCode' => '17021320',
				'street' => 'Rua Vicente Barbugiani',
				'number' => '483',
				'additionalInformation' => '',
				'district' => 'Jardim Godoy',
					'city'          => array(
						'code' => '3506003',
						'name' => 'Bauru'
					),
				'state' => 'SP'
			  ),
		  'environment'     => 'Development'
		);
		$object = (object) NFe_Company::create($attributes);
		$this->assertNotNull($object);
		$this->assertEquals('TEST Company Name', $object->companies->name);
		self::$id = $object->companies->id;
	}
	/**
	 * @covers ::testSearch
	 */
	public function testSearch() {
		NFe_TestCaseNew::getConn();
		$object = (object) NFe_Company::fetch(self::$id);
		$this->assertNotNull($object);
		$this->assertNotNull($object->companies->name);
		$this->assertEquals( 'TEST Company Name', $object->companies->name);
	}
//	/* Todo
//	/**
//	 * @covers ::testRefresh
//	 */
//	public function testRefresh() {
//		notCoverage();
//	}
//	/**
//	 * @covers ::testFetch
//	 */
//	public function testFetch() {
//		notCoverage();
//	}
	/**
	 * @covers ::testSave
	 */
	public function testSave() {
		NFe_TestCaseNew::getConn();
		$object = NFe_Company::fetch(self::$id);
		$object->companies->name = 'BB SA';
		$object->save();
		$this->assertNotNull($object);
		$this->assertNotNull($object->companies->name);
		$this->assertEquals( 'BB SA', $object->companies->name );
	}
	/**
	 * @covers ::testDelete
	 */
	public function testDelete() {
		NFe_TestCaseNew::getConn();
		$object =  (object) NFe_Company::fetch(self::$id);
		$this->assertNotNull($object);
		$this->assertTrue($object->delete());
	}
}
