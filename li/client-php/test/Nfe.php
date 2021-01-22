<?php
$dir = dirname(__FILE__);

include_once( $dir . '/../vendor/simpletest/simpletest/autorun.php');

error_reporting( E_ALL | E_STRICT );

echo 'Running NFe.io PHP Test Suite - ';

include_once( $dir . '/../lib/init.php');
include_once( $dir . '/../test/NFe/TestCase.php');
include_once( $dir . '/../test/NFe/CompanyTestNew.php');
include_once( $dir . '/../test/NFe/LegalPersonTest.php');
include_once( $dir . '/../test/NFe/NaturalPersonTest.php');
include_once( $dir . '/../test/NFe/WebhookTest.php');
include_once( $dir . '/../test/NFe/ServiceInvoiceTest.php');
