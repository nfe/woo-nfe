<?php

/**
 * WooCommerce NFe.io NFe_Woo Class
 *
 * @author   Renato Alves
 * @package  NFe_WooCommerce/NFe_Woo/Class
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('NFe_Woo') ) :

	/**
    * NFe_Woo Main Class
    *
    * @since 1.0.0
    */
	class NFe_Woo {
	    
	    /**
	     * Construct
	     */
	    private function __construct( array $vars ) {
	    	// Bail if not enabled
			if ( nfe_get_field('nfe_enable') == 'no' ) {
				return;
			}

			$key = nfe_get_field('api_key');
			$company_id = nfe_get_field('company_id');

			if ( empty( $key ) || empty( $company_id ) ) {
				return;
			}
	    }
	    
	    /**
	     * Issue a NFe Invoice
	     */
		public function issue_invoice( $order_ids = array() ) {
	        $key        = nfe_get_field('api_key');
	        $company_id = nfe_get_field('company_id');

	        Nfe::setApiKey($key);
			
			foreach ( $order_ids as $order_id ) {
			
				$data = self::order_data( $order_id );

	            $response = Nfe_ServiceInvoice::create( 
	                $company_id,
	                $data 
	            );
	            
	            // $webmaniabr = new NFe( WC_NFe()->settings );
	            // $response = $webmaniabr->emissaoNotaFiscal( $data );
	           
	            if ( isset( $response->error ) || $response->status == 'reprovado' ) {
	                
	                $mensagem = 'Erro ao emitir a NF-e do Pedido #'.$order_id.':';
	                
	                $mensagem .= '<ul style="padding-left:20px;">';
	                $mensagem .= '<li>'.$response->error.'</li>';
	                
	                if (isset($response->log)){
	                    
	                    if ($response->log->xMotivo){
	                        
	                        $mensagem .= '<li>'.$response->log->xMotivo.'</li>';
	                        
	                    } else { 
	                    
	                        foreach ($response->log as $erros){
	                            foreach ($erros as $erro) {
	                                $mensagem .= '<li>'.$erro.'</li>';
	                            }
	                        }
	                        
	                    }
	                    
	                }
	                
	                $mensagem .= '</ul>';
	                
	                // WC_NFe()->add_error( $mensagem );
	                
	            } else {
	                
	                $nfe = get_post_meta( $order_id, 'nfe_issued', true );
					if ( ! $nfe) {
						$nfe = array();
					}
	                
	                $nfe[] = array(
						'status' 	=> (string) $response->status,
						'api_key' 	=> $response->key,
						'n_invoice' => (int) $response->invioce,
						'n_nfe' 	=> (int) $response->nfe,
						'data' 		=> date_i18n('d/m/Y'),
					);
					
					update_post_meta( $order_id, 'nfe_issued', $nfe );
	                
	                // WC_NFe()->add_success( 'NF-e emitida com sucesso do Pedido #' . $order_id );
	            }
			}
		}

		public function order_data( $post_id ) {
			global $wpdb;
	        
	        $WooCommerceNFe_Format = new WooCommerceNFe_Format;
			$order = new WC_Order( $post_id );
	 
	        // Order
	        $data = array(
	            'ID' => $post_id, // Número do pedido
	            'operacao' => 1, // Tipo de Operação da Nota Fiscal
	            'natureza_operacao' => get_option('wc_settings_woocommercenfe_natureza_operacao'), // Natureza da Operação
	            'modelo' => 1, // Modelo da Nota Fiscal (NF-e ou NFC-e)
	            'emissao' => 1, // Tipo de Emissão da NF-e 
	            'finalidade' => 1, // Finalidade de emissão da Nota Fiscal
	            'ambiente' => (int) get_option('wc_settings_woocommercenfe_ambiente') // Identificação do Ambiente do Sefaz 
	        );
	        
	        $data['pedido'] = array(
	            'pagamento' => 0, // Indicador da forma de pagamento 
	            'presenca' => 2, // Indicador de presença do comprador no estabelecimento comercial no momento da operação 
	            'modalidade_frete' => 0, // Modalidade do frete 
	            'frete' => get_post_meta( $order->id, '_order_shipping', true ), // Total do frete 
	            'desconto' => $order->get_total_discount(), // Total do desconto 
	            'total' => $order->order_total // Total do pedido - sem descontos
	        );
	        
	        // Customer
	        $tipo_pessoa = get_post_meta($post_id, '_billing_persontype', true);
	        
	        if ( $tipo_pessoa == 1 ) { 
	            $data['cliente'] = array(
	                'cpf' => $WooCommerceNFe_Format->cpf(get_post_meta($post_id, '_billing_cpf', true)), // (pessoa fisica) Número do CPF
	                'nome_completo' => get_post_meta($post_id, '_billing_first_name', true).' '.get_post_meta($post_id, '_billing_last_name', true), // (pessoa fisica) Nome completo
	                'endereco' => get_post_meta($post_id, '_shipping_address_1', true), // Endereço de entrega dos produtos
	                'complemento' => get_post_meta($post_id, '_shipping_address_2', true), // Complemento do endereço de entrega
	                'numero' => get_post_meta($post_id, '_shipping_number', true), // Número do endereço de entrega
	                'bairro' => get_post_meta($post_id, '_shipping_neighborhood', true), // Bairro do endereço de entrega
	                'cidade' => get_post_meta($post_id, '_shipping_city', true), // Cidade do endereço de entrega
	                'uf' => get_post_meta($post_id, '_shipping_state', true), // Estado do endereço de entrega
	                'cep' => $WooCommerceNFe_Format->cep(get_post_meta($post_id, '_shipping_postcode', true)), // CEP do endereço de entrega
	                'telefone' => get_user_meta($post_id, 'billing_phone', true), // Telefone do cliente
	                'email' => get_post_meta($post_id, '_billing_email', true) // E-mail do cliente para envio da NF-e
	            );
	        }
	        
	        if ( $tipo_pessoa == 2 ) {
	            $data['cliente'] = array(
	                'cnpj' => $WooCommerceNFe_Format->cnpj(get_post_meta($post_id, '_billing_cnpj', true)), // (pessoa jurídica) Número do CNPJ
	                'razao_social' => get_post_meta($post_id, '_billing_company', true), // (pessoa jurídica) Razão Social
	                'ie' => get_post_meta($post_id, '_billing_ie', true), // (pessoa jurídica) Número da Inscrição Estadual
	                'endereco' => get_post_meta($post_id, '_shipping_address_1', true), // Endereço de entrega dos produtos
	                'complemento' => get_post_meta($post_id, '_shipping_address_2', true), // Complemento do endereço de entrega
	                'numero' => get_post_meta($post_id, '_shipping_number', true), // Número do endereço de entrega
	                'bairro' => get_post_meta($post_id, '_shipping_neighborhood', true), // Bairro do endereço de entrega
	                'cidade' => get_post_meta($post_id, '_shipping_city', true), // Cidade do endereço de entrega
	                'uf' => get_post_meta($post_id, '_shipping_state', true), // Estado do endereço de entrega
	                'cep' => $WooCommerceNFe_Format->cep(get_post_meta($post_id, '_shipping_postcode', true)), // CEP do endereço de entrega
	                'telefone' => get_user_meta($post_id, 'billing_phone', true), // Telefone do cliente
	                'email' => get_post_meta($post_id, '_billing_email', true) // E-mail do cliente para envio da NF-e
	            );
	        }
			
			// Products
			foreach ( $order->get_items() as $key => $item ) {
	            $product_id = $item['product_id'];
	            $variation_id = $item['variation_id'];
	            
	            $emitir = apply_filters( 'emitir_nfe_produto', true, $product_id );
	            if ($variation_id) $emitir = apply_filters( 'emitir_nfe_produto', true, $variation_id );
	            
	            if ($emitir){
	                
	                $product = $order->get_product_from_item( $item );
	                
	                // Vars
	                $codigo_ean = get_post_meta($product_id, '_nfe_codigo_ean', true);
	                $codigo_ncm = get_post_meta($product_id, '_nfe_codigo_ncm', true);
	                $codigo_cest = get_post_meta($product_id, '_nfe_codigo_cest', true);
	                $origem = get_post_meta($product_id, '_nfe_origem', true);
	                $imposto = get_post_meta($product_id, '_nfe_classe_imposto', true);
	                $peso = $product->get_weight();
	                if (!$peso) $peso = '0.100';
	                
	                if (!$codigo_ean) $codigo_ean = get_option('wc_settings_woocommercenfe_ean');
	                if (!$codigo_ncm) $codigo_ncm = get_option('wc_settings_woocommercenfe_ncm');
	                if (!$codigo_cest) $codigo_cest = get_option('wc_settings_woocommercenfe_cest');
	                if (!$origem) $origem = get_option('wc_settings_woocommercenfe_origem');
	                if (!$imposto) $imposto = get_option('wc_settings_woocommercenfe_imposto');

	                // Attributes
	                $variacoes = '';
	                foreach (array_keys($item['item_meta']) as $meta){

	                    if (strpos($meta,'pa_') !== false) {
	                        $atributo = $item[$meta];
	                        $nome_atributo = str_replace( 'pa_', '', $meta );
	                        $nome_atributo = $wpdb->get_var( "SELECT attribute_label FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = '$nome_atributo'" );
	                        $valor = strtoupper($item[$meta]);
	                        $variacoes .= ' - '.strtoupper($nome_atributo).': '.$valor;
	                    }
	                }

	                $data['produtos'][] = array(
	                    'nome' => $item['name'].$variacoes, // Nome do produto
	                    'sku' => $product->get_sku(), // Código identificador - SKU
	                    'ean' => $codigo_ean, // Código EAN
	                    'ncm' => $codigo_ncm, // Código NCM
	                    'cest' => $codigo_cest, // Código CEST
	                    'quantidade' => $item['qty'], // Quantidade de itens
	                    'unidade' => 'UN', // Unidade de medida da quantidade de itens 
	                    'peso' => $peso, // Peso em KG. Ex: 800 gramas = 0.800 KG
	                    'origem' => (int) $origem, // Origem do produto 
	                    'subtotal' => $order->get_item_subtotal( $item, false, false ), // Preço unitário do produto - sem descontos
	                    'total' => $item['line_subtotal'], // Preço total (quantidade x preço unitário) - sem descontos 
	                    'classe_imposto' => $imposto // Referência do imposto cadastrado 
	                );   
	            }
			}
	        
			return $data;	
		}
	}

endif;

