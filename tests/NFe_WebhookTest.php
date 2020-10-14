<?php
/**
 * @coversDefaultClass NFe_WebhookTest
 */
class NFe_WebhookTest extends NFe_TestCaseNew {
	private static $id = null;
	/**
	 * @covers ::testCreate
	 */
	public function testCreate() {
		NFe_TestCaseNew::getConn();
		$attributes = array(
			'url'    => 'http://google.com/test',
			'events' => array(
				'issue',
				'cancel'
			),
			'status' => 'active'
		);

		$object = (object) NFe_Webhook::create($attributes);
		$this->assertNotNull($object);
		$this->assertNotNull($object->hooks->url);
		$this->assertEquals($attributes['url'], $object->hooks->url);
		self::$id = $object->hooks->id;
	}
//	/** Todo
//	 * @covers ::testRefresh
//	 */
//	public function testRefresh() {
//		$this->assertTrue(true);
//	}
	/**
	 * @covers ::testSearch
	 */
	public function testSearch() {
		NFe_TestCaseNew::getConn();
		$hooks = (object) NFe_Webhook::search();
		$this->assertNotNull($hooks);
		$this->assertNotNull($hooks->hooks);
	}
	/**
	 * @covers ::testFetch
	 */
	public function testFetch() {
		NFe_TestCaseNew::getConn();
		$object = (object) NFe_Webhook::fetch( self::$id);
		$this->assertNotNull($object);
		$this->assertNotNull($object['hooks']->url);
		$this->assertEquals('http://google.com/test', $object['hooks']->url);
	}
	/**
	 * @covers ::testSave
	 */
	public function testSave() {
		NFe_TestCaseNew::getConn();
		$object = (object) NFe_Webhook::fetch( self::$id);
		$new_url = 'http://google.com/test2';
		$object->hooks->url = $new_url;
		$object->save();
		$this->assertNotNull($object);
		$this->assertNotNull($object->hooks->url);
		$this->assertEquals($new_url, $object->hooks->url);
	}
	/**
	 * @covers ::testDelete
	 */
	public function testDelete() {
		$object = (object) NFe_Webhook::fetch( self::$id );
		$this->assertNotNull($object);
		$this->assertNotNull( $object->hooks->url);
		$this->assertTrue($object->delete());
	}
}
