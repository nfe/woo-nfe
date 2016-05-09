<?php

/**
 * WooCommerce NFe.io Custom Functions
 *
 * @author   Renato Alves
 * @package  NFe_WooCommerce
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

function nfe_get_field( $value = '' ) {
	$nfe_fields = get_option( 'woocommerce_nfe-woo-integration_settings' );

	if ( empty( $value ) ) {
		$output = $nfe_fields;
	} else {
		$output = $nfe_fields[$value];
	}

	return $output;
}

class NFe_Woo {
    
    /**
     * 
     * 
     * @param array $vars [description]
     */
    private function __construct( array $vars ) {
    	// Bail if not enabled
		if ( nfe_get_field('nfe_enable') == 'no' ) {
			return;
		}

        $this->apikey 		= nfe_get_field('apikey'); //  $vars['apikey']; // c73d49f9649046eeba36dcf69f6334fd
        $this->company_id 	= nfe_get_field('company_id'); // $vars['company_id'];

		if ( empty( $this->$key ) || empty( $this->company_id ) ) {
			return;
		}
    }
    
    /**
     * Issue a NFe
     *
     * @since 1.0.0
     * 
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    public static function issue_invoice() {
		Nfe::setApiKey($this->key);

		Nfe_ServiceInvoice::create(
		  // ID da empresa, você deve copiar exatamente como está no painel
		  $this->company_id,

		  // Dados da nota fiscal de serviço
		  Array (
		    // Código do serviço de acordo com o a cidade
		    'cityServiceCode' => '2690',
		    // Descrição dos serviços prestados
		    'description' => 'TESTE EMISSAO',
		    // Valor total do serviços
		    'servicesAmount' => 0.01,
		    // Dados do Tomador dos Serviços
		    'borrower' => Array(
		      // CNPJ ou CPF (opcional para tomadores no exterior)
		      'federalTaxNumber' => 191,
		      // Nome da pessoa física ou Razão Social da Empresa
		      'name' => 'BANCO DO BRASIL SA',
		      // Email para onde deverá ser enviado a nota fiscal
		      'email' => 'hackers@nfe.io',
		      // Endereço do tomador
		      'address' => Array(
		        // Código do pais com três letras
		        'country' => 'BRA',
		        // CEP do endereço (opcional para tomadores no exterior)
		        'postalCode' => '70073901',
		        // Logradouro
		        'street' => 'Outros Quadra 1 Bloco G Lote 32',
		        // Número (opcional)
		        'number' => 'S/N',
		        // Complemento (opcional)
		        'additionalInformation' => 'QUADRA 01 BLOCO G',
		        // Bairro
		        'district' => 'Asa Sul',
		        // Cidade é opcional para tomadores no exterior
		        'city' => Array(
		            // Código do IBGE para a Cidade
		            'code' => '5300108',
		            // Nome da Cidade
		            'name' => 'Brasilia'
		        ),
		        // Sigla do estado (opcional para tomadores no exterior)
		        'state' => 'DF'
		      )
		    )
		  )
		);
	}
    
    public static function check_invoice() {}

    public static function cancel_invoice() {}
}
