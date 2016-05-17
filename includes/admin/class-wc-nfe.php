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
    
    protected static $_instance = NULL;
    
    /**
     * Nfe_Woo Instance
     */
    public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

    /**
     * Construct
     */
    private function __construct() {}
    
    /**
     * Issue a NFe Invoice
     */
	public function issue_invoice( $order_ids = array() ) {
        $key        = nfe_get_field('api_key');
        $company_id = nfe_get_field('company_id');

        Nfe::setApiKey($key);
		
		foreach ( $order_ids as $order_id ) {
			$data = $this->order_info( $order_id );

            $invoice = Nfe_ServiceInvoice::create( 
                $company_id,
                $data 
            );


            // if ( $invoice
		}

		return $invoice;
	}

    /**
     * Ordering and preparing data to send to NFe API
     * 
     * @param  int $order Order ID
     * @return array Array with the order information to issue the invoice
     */
	public function order_info( $order ) {
    	$data = array(
			'cityServiceCode' 	=> '2690', // Código do serviço de acordo com o a cidade
    		'description' 		=> 'WooCommerce NFe - TESTE EMISSAO', // Descrição dos serviços prestados
			'servicesAmount' 	=> '08', // $order->order_total, // Valor total do serviços
            'borrower' => array(
      			'federalTaxNumber' 			=> $this->check_customer_type( 'tax-number', $order ), // (CNPJ/CPF)
      			'name' 						=> $this->check_customer_type( 'customer-name', $order ), // Nome Completo
            	'email' 					=> get_post_meta( $order, '_billing_email', true ), // Email
            	'address' => array(
			        'postalCode' 			=> $this->cep( get_post_meta( $order, '_billing_postcode', true ) ), // CEP
			        'street' 				=> get_post_meta( $order, '_billing_address_1', true ), // Logradouro
			        'number' 				=> get_post_meta( $order, '_billing_number', true ), // Número 
			        'additionalInformation' => get_post_meta( $order, '_billing_address_2', true ), // Complemento
			        'district' 				=> get_post_meta( $order, '_billing_neighborhood', true ), // Bairro
			        'country' 				=> get_post_meta( $order, '_billing_country', true ), // País (BRA)
			        'state' 				=> get_post_meta( $order, '_billing_state', true ), // Sigla do Estado
        			'city' => array(
            			'code' => '', // Código do IBGE para a Cidade // 5300108
            			'name' => get_post_meta( $order, '_billing_city', true ), // Nome da Cidade
        			)
        		)
            )
        );
        
		return $data;	
	}

	/**
	 * Fetching customer info depending on the person type
	 * 
	 * @param  string  $field       Field to fetch info from
	 * @param  int  $order     	The order ID
	 * @return string|empty 		Returns the customer info specific to the person type being fetched
	 */
	public function check_customer_type( $field = '', $order ) {
		if ( empty($order ) || empty($field) ) {
			return;
		}

		// Customer Person Type
		$person_type = get_post_meta( $order, '_billing_persontype', true );

		if ( $field === 'tax-number' ) {
			// Customer ID Number
			$cpf = $this->cpf( get_post_meta( $order, '_billing_cpf', true ) );
			$cnpj = $this->cnpj( get_post_meta( $order, '_billing_cnpj', true ) );

			if ( $person_type == 1 ) {
				$result = $cpf;
			} elseif ( $person_type == 2 ) {
				$result = $cnpj;
			}

		} elseif ( $field === 'customer-name' ) {
			// Customer Name
			$cnpj_name 	= get_post_meta( $order, '_billing_company', true );
			$cpf_name 	= get_post_meta( $order, '_billing_first_name', true ) . ' ' . get_post_meta( $order, '_billing_last_name', true );

			if ( $person_type == 1 ) {
				$result = $cpf_name;
			} elseif ( $person_type == 2 ) {
				$result = $cnpj_name;
			}
		}

        return $result;
	}

	public function cpf( $cpf ) {
		if ( ! $cpf ) {
			return;
		}

		$cpf = $this->clear( $cpf );
		$cpf = $this->mask($cpf,'###.###.###-##');

		return $cpf;	
	}

	public function cnpj( $cnpj ) {
		if ( ! $cnpj ) {
			return;
		}

		$cnpj = $this->clear( $cnpj );
		$cnpj = $this->mask($cnpj,'##.###.###/####-##');
		
		return $cnpj;	
	}

	public function cep( $cep ) {
		if ( ! $cep ) {
			return;
		}

		$cep = $this->clear( $cep );
		$cep = $this->mask($cep,'#####-###');

		return $cep;	
	}

	public function clear( $string ) {
        $string = str_replace( array(',', '-', '!', '.', '/', '?', '(', ')', ' ', '$', 'R$', '€'), '', $string );

        return $string;
	}
    
	public function mask( $val, $mask ) {
	   $maskared = '';
	   $k 		 = 0;

	   	for( $i = 0; $i <= strlen($mask) - 1; $i++ ) {
           	if ( $mask[$i] == '#' ) {
               	if ( isset($val[$k]) ) {
                   $maskared .= $val[$k++];
               	}

           } elseif ( isset($mask[$i]) ) {
            	$maskared .= $mask[$i];
           }
	   	}

	   	return $maskared;
	}
}

function NFe_Woo() {
    return NFe_Woo::instance();    
}

// That's it! =)
