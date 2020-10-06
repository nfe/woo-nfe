<?php
$apiKey = "J6aFxW5rTe4uPKb0XtmWtJjJNR0aEtF0E4bq9KAqF8i8fGBgbIkqnW6irZn3pq8YJr1";

putenv("NFE_API_KEY=$apiKey");

$dir = dirname(__FILE__);

//echo 'AQUI:'.$dir;

include_once( $dir . '/../vendor/simpletest/simpletest/autorun.php');

error_reporting( E_ALL | E_STRICT );

echo 'Running NFe.io PHP Test Suite - ';

include_once( $dir . '/../lib/init.php');
include_once( $dir . '/../test/NFe/TestCase.php');
include_once( $dir . '/../test/NFe/CompanyTestNew.php');
//include_once( $dir . '/../test/NFe/LegalPersonTest.php');
//include_once( $dir . '/../test/NFe/NaturalPersonTest.php');
//include_once( $dir . '/../test/NFe/WebhookTest.php');
//include_once( $dir . '/../test/NFe/ServiceInvoiceTest.php');
