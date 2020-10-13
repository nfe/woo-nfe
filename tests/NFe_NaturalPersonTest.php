<?php
/**
 * @coversDefaultClass NFe_NaturalPersonTest
 */
class NFe_NaturalPersonTest extends NFe_TestCaseNew {
	private static $company_id = '53f0d6b690c14737349bd29c';
	/**
	 * @covers ::testFetch
	 */
	public function testFetch() {
		NFe_TestCaseNew::getConn();
		$result = (object) NFe_NaturalPerson::fetch( self::$company_id, '5581c760146dc70d384da4b5' );
		$this->assertNotNull($result);
	}
//	/**
//	 * @covers ::testFetchFail
//	 */
//	public function testFetchFail() {
//		NFe_TestCaseNew::getConn();
//		$result = NFe_NaturalPerson::fetch( self::$company_id, self::$company_id );
//		$this->expectException('NFeObjectNotFound');
//		$this->assertNull($result);
//	}
	/**
	 * @covers ::testSearch
	 */
	public function testSearch() {
		NFe_TestCaseNew::getConn();
		$result = (object) NFe_NaturalPerson::search(self::$company_id);
		$person = objectToArray($result);
		$this->assertTrue( count($person) >= 1);
	}
}
