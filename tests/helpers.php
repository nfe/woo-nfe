<?php

/**
 * Woocommerce mock functions.
 */
function is_woocommerce_active() {
	return true;
}

function woothemes_queue_update($file, $file_id, $product_id) {
	return true;
}

function wcwspay_is_woocommerce_active() {
	return true;
}

/**
 * Call protected/private method of a class.
 *
 * @param object &$object    Instantiated object that we will run method on.
 * @param string $methodName Method name to call
 * @param array  $parameters Array of parameters to pass into method.
 * @return mixed
 */
function invokePrivateMethod( &$object, $methodName, array $parameters = array() ) {
	$reflection = new ReflectionClass(get_class($object));
	$method = $reflection->getMethod($methodName);
	$method->setAccessible(true);
	return $method->invokeArgs($object, $parameters);
}


function invokePrivateMethod2( &$object, $methodName, $parameters) {
	$reflection = new ReflectionClass(get_class($object));
	$method = $reflection->getMethod($methodName);
	$method->setAccessible(true);

	return $method->invoke($object, $parameters);
}

/**
 * Call Valid Document - CNPJ
 * @example cpfRandom(0) return unmask
 * @param int $mask
 * @return string
 */

function cnpjRandom($mask = "1") {
	$n1 = rand(0, 9);
	$n2 = rand(0, 9);
	$n3 = rand(0, 9);
	$n4 = rand(0, 9);
	$n5 = rand(0, 9);
	$n6 = rand(0, 9);
	$n7 = rand(0, 9);
	$n8 = rand(0, 9);
	$n9 = 0;
	$n10 = 0;
	$n11 = 0;
	$n12 = 1;
	$d1 = $n12 * 2 + $n11 * 3 + $n10 * 4 + $n9 * 5 + $n8 * 6 + $n7 * 7 + $n6 * 8 + $n5 * 9 + $n4 * 2 + $n3 * 3 + $n2 * 4 + $n1 * 5;
	$d1 = 11 - (mod($d1, 11) );
	if ($d1 >= 10) {
		$d1 = 0;
	}
	$d2 = $d1 * 2 + $n12 * 3 + $n11 * 4 + $n10 * 5 + $n9 * 6 + $n8 * 7 + $n7 * 8 + $n6 * 9 + $n5 * 2 + $n4 * 3 + $n3 * 4 + $n2 * 5 + $n1 * 6;
	$d2 = 11 - (mod($d2, 11) );
	if ($d2 >= 10) {
		$d2 = 0;
	}
	$return = '';
	if ($mask == 1) {
		$return = '' . $n1 . $n2 . "." . $n3 . $n4 . $n5 . "." . $n6 . $n7 . $n8 . "/" . $n9 . $n10 . $n11 . $n12 . "-" . $d1 . $d2;
	} else {
		$return = '' . $n1 . $n2 . $n3 . $n4 . $n5 . $n6 . $n7 . $n8 . $n9 . $n10 . $n11 . $n12 . $d1 . $d2;
	}
	return $return;
}

/**
 * Call Valid Document - CPF
 * @example cpfRandom(0) return unmask
 * @param int $mask
 * @return string
 */
function cpfRandom($mask = "1") {
	$n1 = rand(0, 9);
	$n2 = rand(0, 9);
	$n3 = rand(0, 9);
	$n4 = rand(0, 9);
	$n5 = rand(0, 9);
	$n6 = rand(0, 9);
	$n7 = rand(0, 9);
	$n8 = rand(0, 9);
	$n9 = rand(0, 9);
	$d1 = $n9 * 2 + $n8 * 3 + $n7 * 4 + $n6 * 5 + $n5 * 6 + $n4 * 7 + $n3 * 8 + $n2 * 9 + $n1 * 10;
	$d1 = 11 - (mod($d1, 11) );
	if ($d1 >= 10) {
		$d1 = 0;
	}
	$d2 = $d1 * 2 + $n9 * 3 + $n8 * 4 + $n7 * 5 + $n6 * 6 + $n5 * 7 + $n4 * 8 + $n3 * 9 + $n2 * 10 + $n1 * 11;
	$d2 = 11 - (mod($d2, 11) );
	if ($d2 >= 10) {
		$d2 = 0;
	}
	$return = '';
	if ($mask == 1) {
		$return = '' . $n1 . $n2 . $n3 . "." . $n4 . $n5 . $n6 . "." . $n7 . $n8 . $n9 . "-" . $d1 . $d2;
	} else {
		$return = '' . $n1 . $n2 . $n3 . $n4 . $n5 . $n6 . $n7 . $n8 . $n9 . $d1 . $d2;
	}
	return $return;
}

/**
 * @param type $divi
 * @param type $divizor
 * @return type
 */
function mod($divi, $divizor) {
	return round($divi - (floor($divi / $divizor) * $divizor));
}


function notCoverage(){
	$woo_nfe = woo_nfe();
	$test = $woo_nfe->nfe_integration(array('test'));
	if ( $test[0] == "test" ) {
		$this->assertTrue(true);
	}else{
		$this->assertTrue(false);
	}
}

function objectToArray($object) {
	if(!is_object($object) && !is_array($object)){
		return $object;
	}
	return array_map('objectToArray', (array) $object);
}


function arrayToObject($array) {
	return json_encode($array, true);
}
