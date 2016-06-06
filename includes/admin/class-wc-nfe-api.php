<?php

/**
 * WooCommerce NFe.io NFe_Woo Class
 *
 * @author   Renato Alves
 * @package  WooCommerce_NFe/Class/NFe_Woo
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
* NFe_Woo Main Class
*/
class NFe_Woo {
        
    /**
     * Nfe_Woo Instance.
     */
    public static function instance() {

    	// Store the instance locally to avoid private static replication
        static $instance = null;

        // Only run these methods if they haven't been run previously
        if ( null === $instance ) {
            $instance = new NFe_Woo;
            $instance->setup_hooks();
        }

        // Always return the instance
        return $instance;
	}

    /**
     * Construct.
     *
     * @see $this->instance Class Instance
     */
    private function __construct() {}

    /**
     * Set hooks.
     */
    private function setup_hooks() {
    	add_action( 'admin_notices', array( $this, 'display_messages' ) );
    }
    
    /**
     * Issue a NFe Invoice.
     * 
     * @param  array  $order_ids Orders to issue the NFe
     * @return string Error
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

            if ( isset( $invoice->error ) ) {
                $mensagem = sprintf( __( 'Error when issuing the receipt for order %1d:', 'woocommerce-nfe'), $order_id );
                
                $mensagem .= '<ul style="padding-left:20px;">';
                $mensagem .= '<li>' . $invoice->error . '</li>';
                
                if ( isset( $invoice->log ) ) {
                    
                    if ( $invoice->log ) {
                        
                        $mensagem .= '<li>' . $invoice->log . '</li>';
                        
                    } else { 
                    
                        foreach ( $invoice->log as $erros ) {
                            foreach ( $erros as $erro ) {
                                $mensagem .= '<li>' . $erro . '</li>';
                            }
                        }   
                    }
                }
                
                $mensagem .= '</ul>';
                
                $this->add_error( $mensagem );

			} else {

				$nfe = get_post_meta( $order_id, 'nfe_issued', true );
				
				if ( !$nfe ) {
					$nfe = array();
				}
                
                $nfe[] = array(
                	'id' 	 => (int) $invoce->id,
					'status' => (string) $invoice->flowStatus,
				);

				update_post_meta( $order_id, 'nfe_issued', $nfe );
                $this->add_success( sprintf( __( 'NFe issued sucessfully: Order # %1d:', 'woocommerce-nfe'), $order_id ) );
			}
		}
		
		return $invoice;
	}

	/**
	 * Downloads the invoice
	 * 
	 * @param  array  $order_ids Array of order ids
	 * @return string            Pdf
	 */
	public function down_invoice( $order_ids = array() ) {
		$key 		= nfe_get_field('api_key');
		$company 	= nfe_get_field('company_id');

		foreach ( $order_ids as $order_id ) {
			$nfe = get_post_meta( $order_id, 'nfe_issued', true );

            $pdf = Nfe_ServiceInvoice::pdf( 
                $company_id,
                $nfe['id'] // Issue ID, not the Order ID
            );
        }

		return $pdf;
	}

    /**
     * Ordering and preparing data to send to NFe API
     * 
     * @param  int $order Order ID
     * @return array 	  Array with the order information to issue the invoice
     */
	public function order_info( $order ) {
		$total = wc_get_order( $order );

        $data = array(
    		// Obrigatório - Serviço municipal atrelado ao serviço federal
			'cityServiceCode' 	=> $this->city_service_info( 'code', $order ), // Código do serviço de acordo com o a cidade
    		'federalServiceCode'=> $this->city_service_info( 'fed_code', $order ), // Optional - Código de nível federal
    		'description' 		=> $this->city_service_info( 'desc', $order ),
			'servicesAmount' 	=> $total->order_total,
            'borrower' => array(
      			'federalTaxNumber' 			=> $this->check_customer_type( 'tax-number', $order ),
      			'municipalTaxNumber' 		=> '',
      			'name' 						=> $this->check_customer_type( 'customer-name', $order ),
            	'email' 					=> get_post_meta( $order, '_billing_email', true ),
            	'address' 		=> array(
			        'postalCode' 			=> $this->cep( get_post_meta( $order, '_billing_postcode', true ) ),
			        'street' 				=> get_post_meta( $order, '_billing_address_1', true ),
			        'number' 				=> get_post_meta( $order, '_billing_number', true ),
			        'additionalInformation' => get_post_meta( $order, '_billing_address_2', true ),
			        'district' 				=> get_post_meta( $order, '_billing_neighborhood', true ),
			        'country' 				=> get_post_meta( $order, '_billing_country', true ),
					'city' 					=> array(
		    			'code' 		=> $this->ibge_code( $order ),
		    			'name' 		=> get_post_meta( $order, '_billing_city', true ),
					),
					'state' 				=> get_post_meta( $order, '_billing_state', true ),
				),
				'type' 			=> $this->check_customer_type( 'type', $order ),
            )
        );
        
		return $data;	
	}

	/**
	 * Fetches the IBG Code
	 *
	 * @todo Add a check for non Brazilian countrie to remove it.
	 * 
	 * @param  int $order_id Order ID
	 * @return string
	 */
	public function ibge_code( $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}

		$key = nfe_get_field('api_key');
		$cep = get_post_meta( $order_id, '_billing_postcode', true );

		$url 		= 'http://open.nfe.io/v1/addresses/' . $cep . '?api_key='. $key . '';
		$response 	= wp_remote_get( esc_url_raw( $url ) );
		$address 	= json_decode( wp_remote_retrieve_body( $response ), true );

		return $address['city']['code'];
	}

	/**
	 * City Service Information (Code and Description).
	 * 
	 * @param  string $field The field info being fetched
	 * @return string
	 */
	public function city_service_info( $field = '', $post_id ) {
		if ( empty( $field ) ) { 
			return;
		}

		$activity = get_post_meta( $post_id, 'nfe_woo_fiscal_activity', true );

		if ( ! empty( $post_id ) && ! empty( $activity ) ) {

			if ( $field == 'code' ) {
				$output = $activity['code'];

			} elseif ( $field == 'desc' ) {
				$output = $activity['name'];

			} elseif ( $field == 'fed_code' ) {
				$output = $activity['fed_code'];
			}

		} else {

			if ( $field == 'code' ) {
				$output = nfe_get_field('nfe_cityservicecode');

			} elseif ( $field == 'desc' ) {
				$output = nfe_get_field('nfe_cityservicecode_desc');

			} elseif ( $field == 'fed_code' ) {
				$output = nfe_get_field('nfe_fedservicecode');
			}
		}

		return $output;
	}

	/**
	 * Fetching customer info depending on the person type
	 * 
	 * @param  string  $field       Field to fetch info from
	 * @param  int  $order     		The order ID
	 * @return string|empty 		Returns the customer info specific to the person type being fetched
	 */
	public function check_customer_type( $field = '', $order ) {
		if ( empty($field) ) {
			return;
		}

		// Customer Person Type
		(int) $person_type = get_post_meta( $order, '_billing_persontype', true );

		if ( $field == 'tax-number' ) {
			// Customer ID Number
			$cpf = $this->cpf( get_post_meta( $order, '_billing_cpf', true ) );
			$cnpj = $this->cnpj( get_post_meta( $order, '_billing_cnpj', true ) );

			if ( $person_type == 1 ) {
				$result = $cpf;
			} elseif ( $person_type == 2 ) {
				$result = $cnpj;
			}

		} elseif ( $field == 'customer-name' ) {
			// Customer Name
			$cnpj_name 	= get_post_meta( $order, '_billing_company', true );
			$cpf_name 	= get_post_meta( $order, '_billing_first_name', true ) . ' ' . get_post_meta( $order, '_billing_last_name', true );

			if ( $person_type == 1 ) {
				$result = $cpf_name;
			} elseif ( $person_type == 2 ) {
				$result = $cnpj_name;
			}

		} elseif ( $field == 'type' ) {
			if ( $person_type == 1 ) {
				$result = 'Customers';
			} elseif ( $person_type == 2 ) {
				$result = 'Company';
			}
		}

		if ( empty($result) ) {
			$result = '...';
		}

        return $result;
	}

	/**
	 * Displaying NFe messages.
	 * 
	 * @return string
	 */
	public function display_messages() {
        $error_msg 	= get_option('woocommerce_nfe_woo_error_messages');
        $succes_msg = get_option('woocommerce_nfe_woo_success_messages');
    
        if ( $error_msg ) { ?>
            <div class="error">
                <? foreach ( $error_msg as $message ) { 
                    echo '<p>' . $message . '</p>'; 
                } ?>
            </div>
            <?php
        
            delete_option('woocommerce_nfe_woo_error_messages');
        }
    
        if ( $succes_msg ) { ?>
            <div class="updated notice notice-success">
                <? foreach ( $succes_msg as $message ) {
                    echo '<p>' . $message . '</p>';
                } ?>
            </div>
            <?php
        
            delete_option('woocommerce_nfe_woo_success_messages');
        }
    }

    /**
     * Adding error messages
     * 
     * @param string
     */
    public function add_error( $message ) {
        $messages = get_option('woocommerce_nfe_woo_error_messages');

        if ( ! $messages ) {
            $messages = array();
        }

        if ( $messages && count($messages) > 0 ) { 
            foreach ( $messages as $msg ) { 
                if ( $msg == $message ) {
                    return false;
                }
            }
        }

        $messages[] = $message;
        update_option('woocommerce_nfe_woo_error_messages', $messages);
    }

    /**
     * Adding success messages
     * 
     * @param string
     */
    public function add_success( $message ) {
        $messages = get_option('woocommerce_nfe_woo_success_messages');

        if ( ! $messages ) {
            $messages = array();
        }

        if ( $messages && count($messages) > 0 ) { 
            foreach ( $messages as $msg ) { 
                if ( $msg == $message ) {
                    return false;
                }
            }
        }

        $messages[] = $message;
        update_option('woocommerce_nfe_woo_success_messages', $messages);
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

/**
 * The main function responsible for returning the one true NFe_Woo Instance.
 *
 * @since 1.0.0
 *
 * @return NFe_Woo The one true NFe_Woo Instance.
 */
function NFe_Woo() {
    return NFe_Woo::instance();    
}

// That's it! =)
