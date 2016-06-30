<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('NFe_Woo') ) :

/**
 * WooCommerce NFe.io NFe_Woo Class
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/NFe_Woo
 * @version  1.0.0
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
		$company_id = nfe_get_field('choose_company');

		Nfe::setApiKey($key);
		
		foreach ( $order_ids as $order_id ) {
			$data = $this->order_info( $order_id );

			$invoice = Nfe_ServiceInvoice::create( $company_id, $data );

			if ( $invoice->errors ) {

				add_action( 'admin_notices',         array( $this, 'nfe_api_error_msg' ) );
				add_action( 'network_admin_notices', array( $this, 'nfe_api_error_msg' ) );

				return false;

			} else {
				
				$nfe = array(
					'id' 	  	=> $invoice->id,
					'status'  	=> $invoice->flowStatus,
					'data' 		=> date_i18n('d/m/Y'),
				);

				update_post_meta( $order_id, 'nfe_issued', $nfe );
			}
		}

		return $invoice;
	}

	/**
	 * Downloads the invoice(s)
	 * 
	 * @param  array  $order_ids Array of order ids
	 * @return string            Pdf
	 */
	public function down_invoice( $order_ids = array() ) {
		$key 		= nfe_get_field('api_key');
		$company_id = nfe_get_field('choose_company');

		Nfe::setApiKey($key);

		foreach ( $order_ids as $order_id ) {
			$nfe = get_post_meta( $order_id, 'nfe_issued', true );
			$pdf = Nfe_ServiceInvoice::pdf( $company_id, $nfe['id'] );
		}

		return $pdf;
	}

	/**
	 * Cancell Web Hook
	 * 
	 * @param  int $order_id Order ID
	 * @return bool true|false
	 */
	public function cancel_nfe( $order_id ) {
		$nfe = array(
			'id' 	  	=> $invoice->id,
			'status'  	=> $invoice->flowStatus,
			'data' 		=> date_i18n('d/m/Y'),
		);

		update_post_meta( $order_id, 'nfe_issued', $nfe );
	}

	/**
	 * Preparing data to send to NFe API
	 * 
	 * @param  int $order Order ID
	 * @return array 	  Array with the order_id information to issue the invoice
	 */
	public function order_info( $order_id ) {
		$total = nfe_wc_get_order( $order_id );

		$data = array(
			'cityServiceCode' 				=> $this->city_service_info( 'code', $order_id ), 
			'federalServiceCode'			=> $this->city_service_info( 'fed_code', $order_id ),
			'description' 					=> $this->city_service_info( 'desc', $order_id ),
			'servicesAmount' 				=> $total->order_total,
			'borrower' 			=> array(
				'name' 						=> $this->check_customer_info( 'name', $order_id ), 
				'email' 					=> get_post_meta( $order_id, '_billing_email', true ),
				'federalTaxNumber' 			=> $this->check_customer_info( 'number', $order_id ),
				'address' 		=> array(
					'postalCode' 			=> $this->cep( get_post_meta( $order_id, '_billing_postcode', true ) ),
					'street' 				=> get_post_meta( $order_id, '_billing_address_1', true ),
					'number' 				=> get_post_meta( $order_id, '_billing_number', true ),
					'additionalInformation' => get_post_meta( $order_id, '_billing_address_2', true ),
					'district' 				=> get_post_meta( $order_id, '_billing_neighborhood', true ),
					'country' 				=> $this->billing_country( $order_id ),
					'state' 				=> get_post_meta( $order_id, '_billing_state', true ),
					'city' 		=> array(
						'code' 				=> $this->ibge_code( $order_id ),
						'name' 				=> get_post_meta( $order_id, '_billing_city', true ),
					),
				),
			),
		);

		// Removes empty, false and null fields from the array
		return array_filter($data);
	}

	/**
	 * Hack to bring support to Brazilian ISO code (Ex.: BRA instead of BR)
	 * 
	 * @param  int $order_id Product ID
	 * @return string
	 */
	public function billing_country( $order_id ) {
		$country   = get_post_meta( $order_id, '_billing_country', true );
		$countries = $this->country_iso_codes();

		foreach ( $countries as $iso3 => $iso2 ) {
			if ( $country == $iso2 ) {
				$c = $iso3;
			}
		}

		return $c;
	}

	/**
	 * Fetches the IBGE Code
	 *
	 * @todo Add a check for non Brazilian countries to remove it.
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

		$url 		= 'https://open.nfe.io/v1/addresses/' . $cep . '?api_key='. $key . '';
		$response 	= wp_remote_get( esc_url_raw( $url ) );
		$address 	= json_decode( wp_remote_retrieve_body( $response ), true );

		return $address['city']['code'];
	}

	/**
	 * City Service Information (City and Federal Code, and Description).
	 * 
	 * @param  string $field The field info being fetched
	 * @return string
	 */
	public function city_service_info( $field = '', $post_id ) {
		if ( empty( $field ) ) { 
			return;
		}

		$order = nfe_wc_get_order( $post_id );

		if ( 0 < count( $order->get_items() ) ) {
			// Variations or Simple Product Info
			foreach ( $order->get_items() as $key => $item ) {
				$product_id   = $item['product_id'];
				$variation_id = $item['variation_id'];

				if ( $variation_id ) {
					$cityservicecode    = get_post_meta( $variation_id, '_cityservicecode', true );
					$federalservicecode = get_post_meta( $variation_id, '_federalservicecode', true );
					$product_desc       = get_post_meta( $variation_id, '_nfe_product_variation_desc', true );	
				} 
				else {
					$cityservicecode    = get_post_meta( $product_id, '_simple_cityservicecode', true );
					$federalservicecode = get_post_meta( $product_id, '_simple_federalservicecode', true );
					$product_desc       = get_post_meta( $product_id, '_simple_nfe_product_desc', true );
				}
			}
		}

		switch ($field) {
			case 'code':
				$output = $cityservicecode ? $cityservicecode : nfe_get_field('nfe_cityservicecode');
				break;

			case 'fed_code':
				$output = $federalservicecode ? federalservicecode : nfe_get_field('nfe_fedservicecode');
				break;

			case 'desc':
				$output = $product_desc ? $product_desc : nfe_get_field('nfe_cityservicecode_desc');
				break;
			
			default:
				$output = null;
				break;
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
	public function check_customer_info( $field = '', $order ) {
		if ( empty($field) ) {
			return;
		}

		// Customer Person Type
		(int) $type = get_post_meta( $order, '_billing_persontype', true );

		switch ($field) {
			case 'number': // Customer ID Number
				if ( $type == 1 ) {
					$output = $this->cpf( get_post_meta( $order, '_billing_cpf', true ) );
				} 
				elseif ( $type == 2 ) {
					$output = $this->cnpj( get_post_meta( $order, '_billing_cnpj', true ) );
				}
				break;

			case 'name': // Customer Name/Razão Social
				if ( $type == 1 ) {
					$output = get_post_meta( $order, '_billing_first_name', true ) . ' ' . get_post_meta( $order, '_billing_last_name', true );
				} 
				elseif ( $type == 2 ) {
					$output = get_post_meta( $order, '_billing_company', true );
				}
				break;

			case 'type': // Customer Type
				if ( $type == 1 ) {
					$output = __('Customers', 'woocommerce-nfe');
				} 
				elseif ( $type == 2 ) {
					$output = __('Company', 'woocommerce-nfe');
				}
				break;

			default:
				$output = null;
				break;
		}

		return $output;
	}

	/**
	 * Display message to user if there is an issue with the NFe API call
	 *
	 * @return html the message for the user
	 */
	public function nfe_api_error_msg() {
		ob_start();
		?>
		<div class="error">
			<p><?php echo '<strong>' . __( 'WooCommerce NFe.io', 'woocommerce-nfe' ) . '</strong>: ' . sprintf( __( 'There was a problem issuing a receipt in NFe.io.', 'woocommerce-nfe' ) ); ?></p>
		</div>
		<?php
		return ob_get_clean();
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

	/**
	 * Country 2 and 3 ISO Codes
	 * 
	 * @return array
	 */
	private function country_iso_codes() {
		$iso_codes = array(
		   'AFG' => 'AF',     // Afghanistan
		   'ALB' => 'AL',     // Albania
		   'ARE' => 'AE',     // U.A.E.
		   'ARG' => 'AR',     // Argentina
		   'ARM' => 'AM',     // Armenia
		   'AUS' => 'AU',     // Australia
		   'AUT' => 'AT',     // Austria
		   'AZE' => 'AZ',     // Azerbaijan
		   'BEL' => 'BE',     // Belgium
		   'BGD' => 'BD',     // Bangladesh
		   'BGR' => 'BG',     // Bulgaria
		   'BHR' => 'BH',     // Bahrain
		   'BIH' => 'BA',     // Bosnia and Herzegovina
		   'BLR' => 'BY',     // Belarus
		   'BLZ' => 'BZ',     // Belize
		   'BOL' => 'BO',     // Bolivia
		   'BRA' => 'BR',     // Brazil
		   'BRN' => 'BN',     // Brunei Darussalam
		   'CAN' => 'CA',     // Canada
		   'CHE' => 'CH',     // Switzerland
		   'CHL' => 'CL',     // Chile
		   'CHN' => 'CN',     // People's Republic of China
		   'COL' => 'CO',     // Colombia
		   'CRI' => 'CR',     // Costa Rica
		   'CZE' => 'CZ',     // Czech Republic
		   'DEU' => 'DE',     // Germany
		   'DNK' => 'DK',     // Denmark
		   'DOM' => 'DO',     // Dominican Republic
		   'DZA' => 'DZ',     // Algeria
		   'ECU' => 'EC',     // Ecuador
		   'EGY' => 'EG',     // Egypt
		   'ESP' => 'ES',     // Spain
		   'EST' => 'EE',     // Estonia
		   'ETH' => 'ET',     // Ethiopia
		   'FIN' => 'FI',     // Finland
		   'FRA' => 'FR',     // France
		   'FRO' => 'FO',     // Faroe Islands
		   'GBR' => 'GB',     // United Kingdom
		   'GEO' => 'GE',     // Georgia
		   'GRC' => 'GR',     // Greece
		   'GRL' => 'GL',     // Greenland
		   'GTM' => 'GT',     // Guatemala
		   'HKG' => 'HK',     // Hong Kong S.A.R.
		   'HND' => 'HN',     // Honduras
		   'HRV' => 'HR',     // Croatia
		   'HUN' => 'HU',     // Hungary
		   'IDN' => 'ID',     // Indonesia
		   'IND' => 'IN',     // India
		   'IRL' => 'IE',     // Ireland
		   'IRN' => 'IR',     // Iran
		   'IRQ' => 'IQ',     // Iraq
		   'ISL' => 'IS',     // Iceland
		   'ISR' => 'IL',     // Israel
		   'ITA' => 'IT',     // Italy
		   'JAM' => 'JM',     // Jamaica
		   'JOR' => 'JO',     // Jordan
		   'JPN' => 'JP',     // Japan
		   'KAZ' => 'KZ',     // Kazakhstan
		   'KEN' => 'KE',     // Kenya
		   'KGZ' => 'KG',     // Kyrgyzstan
		   'KHM' => 'KH',     // Cambodia
		   'KOR' => 'KR',     // Korea
		   'KWT' => 'KW',     // Kuwait
		   'LAO' => 'LA',     // Lao P.D.R.
		   'LBN' => 'LB',     // Lebanon
		   'LBY' => 'LY',     // Libya
		   'LIE' => 'LI',     // Liechtenstein
		   'LKA' => 'LK',     // Sri Lanka
		   'LTU' => 'LT',     // Lithuania
		   'LUX' => 'LU',     // Luxembourg
		   'LVA' => 'LV',     // Latvia
		   'MAC' => 'MO',     // Macao S.A.R.
		   'MAR' => 'MA',     // Morocco
		   'MCO' => 'MC',     // Principality of Monaco
		   'MDV' => 'MV',     // Maldives
		   'MEX' => 'MX',     // Mexico
		   'MKD' => 'MK',     // Macedonia (FYROM)
		   'MLT' => 'MT',     // Malta
		   'MNE' => 'ME',     // Montenegro
		   'MNG' => 'MN',     // Mongolia
		   'MYS' => 'MY',     // Malaysia
		   'NGA' => 'NG',     // Nigeria
		   'NIC' => 'NI',     // Nicaragua
		   'NLD' => 'NL',     // Netherlands
		   'NOR' => 'NO',     // Norway
		   'NPL' => 'NP',     // Nepal
		   'NZL' => 'NZ',     // New Zealand
		   'OMN' => 'OM',     // Oman
		   'PAK' => 'PK',     // Islamic Republic of Pakistan
		   'PAN' => 'PA',     // Panama
		   'PER' => 'PE',     // Peru
		   'PHL' => 'PH',     // Republic of the Philippines
		   'POL' => 'PL',     // Poland
		   'PRI' => 'PR',     // Puerto Rico
		   'PRT' => 'PT',     // Portugal
		   'PRY' => 'PY',     // Paraguay
		   'QAT' => 'QA',     // Qatar
		   'ROU' => 'RO',     // Romania
		   'RUS' => 'RU',     // Russia
		   'RWA' => 'RW',     // Rwanda
		   'SAU' => 'SA',     // Saudi Arabia
		   'SCG' => 'CS',     // Serbia and Montenegro (Former)
		   'SEN' => 'SN',     // Senegal
		   'SGP' => 'SG',     // Singapore
		   'SLV' => 'SV',     // El Salvador
		   'SRB' => 'RS',     // Serbia
		   'SVK' => 'SK',     // Slovakia
		   'SVN' => 'SI',     // Slovenia
		   'SWE' => 'SE',     // Sweden
		   'SYR' => 'SY',     // Syria
		   'TAJ' => 'TJ',     // Tajikistan
		   'THA' => 'TH',     // Thailand
		   'TKM' => 'TM',     // Turkmenistan
		   'TTO' => 'TT',     // Trinidad and Tobago
		   'TUN' => 'TN',     // Tunisia
		   'TUR' => 'TR',     // Turkey
		   'TWN' => 'TW',     // Taiwan
		   'UKR' => 'UA',     // Ukraine
		   'URY' => 'UY',     // Uruguay
		   'USA' => 'US',     // United States
		   'UZB' => 'UZ',     // Uzbekistan
		   'VEN' => 'VE',     // Bolivarian Republic of Venezuela
		   'VNM' => 'VN',     // Vietnam
		   'YEM' => 'YE',     // Yemen
		   'ZAF' => 'ZA',     // South Africa
		   'ZWE' => 'ZW',     // Zimbabwe
		);

		return $iso_codes;
	}
}

endif;

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
