<?php

/**
 * WooCommerce NFe.io NFe_Woo Class
 *
 * @author   Renato Alves
 * @package  WooCommerce_NFe/NFe_Woo/Class
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
* NFe_Woo Main Class
*/
class NFe_Woo {
    
    /**
     * Construct
     */
    private function __construct() {}
    
    /**
     * Issue a NFe Invoice
     */
	public static function issue_invoice( $order_ids = array()  ) {
        $key        = nfe_get_field('api_key');
        $company_id = nfe_get_field('company_id');

        Nfe::setApiKey($key);
		
		foreach ( $order_ids as $order_id ) {
			$data = NFe_Woo::order_info( $order_id );

            $invoice = Nfe_ServiceInvoice::create( 
                $company_id,
                $data 
            );
		}
	}

    /**
     * Ordering and preparing data to send to NFe API
     * 
     * @param  int $post_id Order ID
     * @return array Array with the order information to issue the invoice
     */
	public static function order_info( $post_id ) {
		$order = new WC_Order( $post_id );

    	$data = array(
			'cityServiceCode' 	=> '2690', // Código do serviço de acordo com o a cidade
    		'description' 		=> 'WooCommerce NFe - TESTE EMISSAO', // Descrição dos serviços prestados
			'servicesAmount' 	=> $order->order_total, // Valor total do serviços

            'borrower' => array(
      			'federalTaxNumber' 			=> '09166978432', //NFe_Woo::check_customer_type( 'tax-number', $post_id ), // (CNPJ/CPF)
      			'name' 						=> 'Renato Alves', // NFe_Woo::check_customer_type( 'customer-name', $post_id ), // Nome Completo
            	'email' 					=> get_post_meta($post_id, '_billing_email', true), // Email
            	
            	'address' => array(
			        'postalCode' 			=> NFe_Woo::cep( get_post_meta($post_id, '_billing_postcode', true) ), // CEP
			        'street' 				=> get_post_meta($post_id, '_billing_address_1', true), // Logradouro
			        'number' 				=> get_post_meta($post_id, '_billing_number', true), // Número 
			        'additionalInformation' => get_post_meta($post_id, '_billing_address_2', true), // Complemento
			        'district' 				=> get_post_meta($post_id, '_billing_neighborhood', true), // Bairro
			        'country' 				=> get_post_meta($post_id, '_billing_country', true), // País (BRA)
			        'state' 				=> get_post_meta($post_id, '_billing_state', true), // Sigla do Estado
			        	
        			'city' => array(
            			'code' => '5300108', // Código do IBGE para a Cidade
            			'name' => get_post_meta($post_id, '_billing_city', true) // Nome da Cidade
        			),
        		)
            )
        );
        
		return $data;	
	}

	/**
	 * Fetching customer info depending on the person type
	 * 
	 * @param  string  $field       Field to fetch info from
	 * @param  int  $post_id     	The order ID
	 * @return string|empty 		Returns the customer info specific to the person type being fetched
	 */
	public static function check_customer_type( $field = '', $post_id ) {
		if ( empty($post_id ) || empty($field) ) {
			return;
		}

		// Customer Person Type
		$person_type = get_post_meta( $post_id, '_billing_persontype', true );

		if ( $field == 'tax-number' ) {
			// Customer ID Number
			$cpf = NFe_Woo::cpf( get_post_meta( $post_id, '_billing_cpf', true ) );
			$cnpj = NFe_Woo::cnpj( get_post_meta( $post_id, '_billing_cnpj', true ) );

			if ( $person_type == 1 ) {
				$result = $cpf;
			} elseif ( $person_type == 2 ) {
				$result = $cnpj;
			}
		}

		if ( $field == 'customer-name' ) {
			// Customer Name
			$cnpj_name 	= get_post_meta($post_id, '_billing_company', true);
			$cpf_name 	= get_post_meta($post_id, '_billing_first_name', true) . ' ' . get_post_meta($post_id, '_billing_last_name', true);

			if ( $person_type == 1 ) {
				$result = $cpf_name;
			} elseif ( $person_type == 2 ) {
				$result = $cnpj_name;
			}
		}

        return $result;
	}

	public static function cpf( $cpf ) {
		if ( ! $cpf ) {
			return;
		}

		$cpf = NFe_Woo::clear( $cpf );
		$cpf = NFe_Woo::mask($cpf,'###.###.###-##');

		return $cpf;	
	}

	public static function cnpj( $cnpj ) {
		if ( ! $cnpj ) {
			return;
		}

		$cnpj = NFe_Woo::clear( $cnpj );
		$cnpj = NFe_Woo::mask($cnpj,'##.###.###/####-##');
		
		return $cnpj;	
	}

	public static function cep( $cep ) {
		if ( ! $cep ) {
			return;
		}

		$cep = NFe_Woo::clear( $cep );
		$cep = NFe_Woo::mask($cep,'#####-###');

		return $cep;	
	}

	public static function clear( $string ) {
        $string = str_replace( array(',', '-', '!', '.', '/', '?', '(', ')', ' ', '$', 'R$', '€'), '', $string );

        return $string;
	}
    
	public static function mask( $val, $mask ) {
	   $maskared 	= '';
	   $k 			= 0;

	   	for( $i = 0; $i <= strlen($mask) - 1; $i++ ) {
           	if ( $mask[$i] == '#' ) {
               	if ( isset($val[$k]) ) {
                   $maskared .= $val[$k++];
               	}

           } else {
				if ( isset($mask[$i]) ) {
            		$maskared .= $mask[$i];
				}
           }
	   	}

	   	return $maskared;
	}
}

// That's it! =)
