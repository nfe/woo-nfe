<?php
class NFe_TestCaseNew extends WP_UnitTestCase {

  public function getConn() {
    $apiKey = getenv('NFE_API_KEY');
    NFe_io::setApiKey($apiKey);
  }

}
