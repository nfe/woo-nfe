<?php
/**
 * @coversDefaultClass NFe_LegalPersonTest
 */
class NFe_LegalPersonTest extends NFe_TestCaseNew {
	private static $company_id = '53f0d6b690c14737349bd29c';
	/**
	 * @covers ::testFetch
	 */
	public function testFetch() {
		NFe_TestCaseNew::getConn();
		$result = (object) NFe_LegalPerson::fetch(self::$company_id, '5775013f56c8e806dc72ad5e');
		$this->assertNotNull($result);
	}
//	/** Todo
//	 * @covers ::testFetchFail
//	 */
//	public function testFetchFail() {
//		NFe_TestCaseNew::getConn();
//		$result = (object) NFe_LegalPerson::fetch(self::$company_id, self::$company_id);
//		$this->expectException('NFeObjectNotFound');
//		$this->assertNull($result);
//	}
	/**
	 * @covers ::testSearch
	 */
	public function testSearch() {
		NFe_TestCaseNew::getConn();
		$result = (object) NFe_LegalPerson::search(self::$company_id);
		$person = objectToArray($result);
		$this->assertTrue(count($person) >= 1);
	}
}
