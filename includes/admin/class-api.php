<?php
/**
 * WooCommerce NFe NFe_Woo Class.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Api
 * @version  1.0.7
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'NFe_Woo' ) ) {

	/**
	 * WooCommerce NFe NFe_Woo Class.
	 */
	class NFe_Woo {
		/**
		 * WC_Logger Logger instance.
		 *
		 * @var bool
		 */
		public static $logger = false;

		/**
		 * Construct.
		 *
		 * @see $this->instance Class Instance.
		 */
		private function __construct() {
		}

		/**
		 * NFe_Woo Instance.
		 *
		 * @return NFe_Woo
		 */
		public static function instance() {
			// Store the instance locally to avoid private static replication.
			static $instance = null;

			// Only run these methods if they haven't been run previously.
			if ( null === $instance ) {
				$instance = new NFe_Woo();
			}

			return $instance; // Always return the instance.
		}

		/**
		 * Issue a NFe invoice.
		 *
		 * @param array $order_ids orders to issue the NFe.
		 *
		 * @return bool|NFe_ServiceInvoice
		 */
		public function issue_invoice( $order_ids = array() ) {
			$key        = $this->get_key();
			$company_id = $this->get_company();

			NFe_io::setApiKey( $key );

			foreach ( $order_ids as $order_id ) {
				$order = nfe_wc_get_order( $order_id );

				// translators: Log message.
				$log = sprintf( __( 'NFe issuing process started! Order: #%d', 'woo-nfe' ), $order_id );
				$this->logger( $log );
				$order->add_order_note( $log );

				// If second send.
				$order_saved = get_post_meta( $order_id );
				if ( $order_saved && ( 'WaitingCalculateTaxes' === $order_saved['status'] || 'Issued' === $order_saved['status'] || 'Processing' === $order_saved['status'] ) ) {
					// translators: Log message.
					$log = sprintf( __( 'second attempt to send the same NFE: #%d', 'woo-nfe' ), $order_id );
					$this->logger( $log );

					return false;
				}

				// If value is 0.00, don't issue it.
				if ( $order->get_total() < 0 ) {
					// translators: Log message.
					$log = sprintf( __( 'Not possible to issue NFe without an order value! Order: #%d', 'woo-nfe' ), $order_id );
					$this->logger( $log );
					$order->add_order_note( $log );

					return false;
				}

				$datainvoice = $this->order_info( $order_id );

				// Check if there was a problem while fetching the city code from IBGE. And if the adderss is required.
				if ( nfe_require_address() && empty( $datainvoice['borrower']['address']['city']['code'] ) ) {
					$log = __( 'There was a problem fetching IBGE code! Check your CEP information.', 'woo-nfe' );
					$this->logger( $log );
					$order->add_order_note( $log );
					// Bail early so that it doesn't create an invoice without address.
					return false;
				}

				$rtc_validation = $this->validate_rtc_payload( $order_id, $datainvoice );

				if ( ! empty( $rtc_validation['warnings'] ) ) {
					foreach ( $rtc_validation['warnings'] as $warning ) {
						$this->logger( $warning );
						$order->add_order_note( $warning );
					}
				}

				if ( ! empty( $rtc_validation['errors'] ) ) {
					foreach ( $rtc_validation['errors'] as $error ) {
						$this->logger( $error );
						$order->add_order_note( $error );
					}

					return false;
				}

				$meta    = update_post_meta(
					$order_id,
					'nfe_issued',
					array(
						'status' => 'Processing',
					)
				);
				$invoice = NFe_ServiceInvoice::create( $company_id, $datainvoice );

				if ( isset( $invoice->message ) ) {
					// translators: Log message.
					$log = sprintf( __( 'An error occurred while issuing a NFe: %s', 'woo-nfe' ), print_r( $invoice->message, true ) ); // phpcs:ignore
					$this->logger( $log );
					$order->add_order_note( $log );

					return false;
				}

				// translators: Log message.
				$log = sprintf( __( 'NFe sent sucessfully to issue! Order: #%d', 'woo-nfe' ), $order_id );
				$this->logger( $log );
				$order->add_order_note( $log );

				// Update invoice information.
				$meta = update_post_meta(
					$order_id,
					'nfe_issued',
					array(
						'id'        => $invoice->id,
						'status'    => $invoice->flowStatus,
						'issuedOn'  => $invoice->issuedOn,
						'amountNet' => $invoice->amountNet,
						'checkCode' => $invoice->checkCode,
						'number'    => $invoice->number,
					)
				);

				if ( ! $meta ) {
					// translators: Log message.
					$this->logger( sprintf( __( 'There was a problem while updating the Order #%d with the NFe information.', 'woo-nfe' ), $order_id ) );
				}
			}

			return $invoice;
		}

		/**
		 * Download the invoice(s).
		 *
		 * @param array $order_ids Array of order ids.
		 *
		 * @throws Exception Exception.
		 *
		 * @return Exception|NFe_ServiceInvoice
		 */
		public function download_pdf_invoice( $order_ids = array() ) {
			$key        = $this->get_key();
			$company_id = $this->get_company();

			NFe_io::setApiKey( $key );

			foreach ( $order_ids as $order_id ) {
				$nfe   = get_post_meta( $order_id, 'nfe_issued', true );
				$order = nfe_wc_get_order( $order_id );

				try {
					$pdf = NFe_ServiceInvoice::pdf( $company_id, $nfe['id'] );

					// translators: Log message.
					$log = sprintf( __( 'NFe PDF Donwload successfully. Order: #%d', 'woo-nfe' ), $order_id );
					$this->logger( $log );
					$order->add_order_note( $log );
				} catch ( Exception $e ) {
					// translators: Log message.
					$log = sprintf( __( 'There was a problem when trying to download NFe PDF! Error: %s', 'woo-nfe' ), print_r( $e->getMessage(), true ) ); // phpcs:ignore
					$this->logger( $log );
					$order->add_order_note( $log );

					throw new Exception( $log );
				}
			}

			return $pdf;
		}

		/**
		 * Preparing data to send to NFe.io API.
		 *
		 * @param int $order_id order ID.
		 *
		 * @return array information to issue the invoice.
		 */
		public function order_info( $order_id ) {
			// Get order object.
			$order = nfe_wc_get_order( $order_id );

			// if tax formation is exclude shipping, remove shipping from total.
			if($this->highlight_shipping_tax() == 'exclude_shipping') {
				// subtract shipping from total.
				$servicesAmount = $order->get_total() - $order->get_shipping_total();
				// get invoice info.
				$invoiceInfo = $this->remover_caracter( $this->city_service_info( 'desc', $order_id ) );
				// build shipping info line
				$shippingInfo = __('Shipping', 'woo-nfe') . ": " . $order->get_shipping_method();
				// build shipping value line
				$shippingValueDescription = __('Shipping Value', 'woo-nfe') . ": " . $order->get_shipping_total() . $order->get_currency();
				// final description
				$servicesDescription = $this->remover_caracter("{$invoiceInfo} \n $shippingInfo \n $shippingValueDescription");
			} else {
				// if tax formation is include shipping, keep shipping in total.
				$servicesAmount = $order->get_total();
				// get invoice info.
				$servicesDescription = $this->remover_caracter( $this->city_service_info( 'desc', $order_id ) );
			}

			$address = array(
				'postalCode'            => $this->check_customer_info( 'cep', $order_id ),
				'street'                => $this->remover_caracter( $this->check_customer_info( 'street', $order_id ) ),
				'number'                => $this->remover_caracter( $this->check_customer_info( 'address_number', $order_id ) ),
				'additionalInformation' => $this->remover_caracter( get_post_meta( $order_id, '_billing_address_2', true ) ),
				'district'              => $this->remover_caracter( $this->check_customer_info( 'district', $order_id ) ),
				'country'               => $this->remover_caracter( $this->billing_country( $order_id ) ),
				'state'                 => $this->remover_caracter( $this->check_customer_info( 'state', $order_id ) ),
				'city'                  => array(
					'code' => $this->ibge_code( $order_id ),
					'name' => $this->remover_caracter( $this->check_customer_info( 'city', $order_id ) ),
				),
			);

			$borrower = array(
				'name'             => $this->check_customer_info( 'name', $order_id ),
				'email'            => get_post_meta( $order_id, '_billing_email', true ),
				'federalTaxNumber' => $this->removepontotraco( $this->check_customer_info( 'number', $order_id ) ),
				'address'          => $address,
			);

			$rtc_info       = $this->rtc_fields_info( $order_id );
			$activity_event = $this->activity_event_info( $order_id );

			$data = array(
				'cityServiceCode'    => $this->city_service_info( 'code', $order_id ),
				'federalServiceCode' => $this->city_service_info( 'fed_code', $order_id ),
				'description'        => $servicesDescription,
				'servicesAmount'     => $servicesAmount,
				'borrower'           => $borrower,
				'externalId'         => 'WOO-NFE-' . $order_id,
				'nbsCode'            => $rtc_info['nbsCode'],
				'ibsCbs'             => $rtc_info['ibsCbs'],
				'activityEvent'      => ! empty( $activity_event ) ? $activity_event : null,
			);

			$data = apply_filters( 'woo_nfe_rtc_payload', $data, $order_id, $order );

			// Removes empty, false and null fields from the array.
			return array_filter( $data );
		}

		/**
		 * CPF Converter.
		 *
		 * @param string $cpf CPF.
		 *
		 * @return string|void
		 */
		public function cpf( $cpf ) {
			if ( ! $cpf ) {
				return;
			}

			$cpf = $this->clear( $cpf );

			return $this->mask( $cpf, '###.###.###-##' );
		}

		/**
		 * CNPJ Converter.
		 *
		 * @param string $cnpj CNPJ.
		 *
		 * @return string|void
		 */
		public function cnpj( $cnpj ) {
			if ( ! $cnpj ) {
				return;
			}

			$cnpj = $this->clear( $cnpj );

			return $this->mask( $cnpj, '##.###.###/####-##' );
		}

		/**
		 * CEP Converter.
		 *
		 * @param string $cep content.
		 *
		 * @return string|void
		 */
		public function cep( $cep ) {
			if ( ! $cep ) {
				return;
			}

			$cep = $this->clear( $cep );

			return $this->mask( $cep, '#####-###' );
		}

		/**
		 * Clears.
		 *
		 * @param string $string content.
		 *
		 * @return string
		 */
		public function clear( $string ) {
			return str_replace( array( ',', '-', '!', '.', '/', '?', '(', ')', ' ', '$', 'R$', '€' ), '', $string );
		}

		/**
		 * Masking.
		 *
		 * @param string $val  value that's gonna be masked.
		 * @param string $mask mask pattern.
		 *
		 * @return string
		 */
		public function mask( $val, $mask ) {
			$maskared = '';
			$k        = 0;
			$mark     = strlen( $mask );

			for ( $i = 0; $i <= $mark - 1; ++$i ) {
				if ( '#' === $mask[ $i ] ) {
					if ( isset( $val[ $k ] ) ) {
						$maskared .= $val[ $k++ ];
					}
				} elseif ( isset( $mask[ $i ] ) ) {
					$maskared .= $mask[ $i ];
				}
			}

			return $maskared;
		}

		/**
		 * Get Company.
		 *
		 * @return string
		 */
		public function get_company() {
			return nfe_get_field( 'choose_company' );
		}

		/**
		 * Highlight Shipping fees from the order taxes.
		 * @return string
		 */
		public function highlight_shipping_tax()
		{
			return nfe_get_field( 'highlight_shipping_tax' );
		}

		/**
		 * Logging method.
		 *
		 * @param string $message message.
		 */
		public static function logger( $message ) {
			$debug = nfe_get_field( 'debug' );

			if ( empty( $debug ) ) {
				return;
			}

			if ( 'yes' === $debug ) {
				if ( empty( self::$logger ) ) {
					self::$logger = wc_get_logger();
				}

				self::$logger->info( $message, array( 'source' => 'nfe_api' ) );
			}
		}

		/**
		 * Hack to bring support to Brazilian ISO code (Ex.: BRA instead of BR).
		 *
		 * @param int $order_id order ID.
		 *
		 * @return null|string
		 */
		protected function billing_country( $order_id ) {
			$country = get_post_meta( $order_id, '_billing_country', true );

			if ( empty( $country ) ) {
				$country = 'BR';
			}

			$countries = $this->country_iso_codes();

			$c = null;
			foreach ( $countries as $iso3 => $iso2 ) {
				if ( $country === $iso2 ) {
					$c = $iso3;

					break;
				}
			}

			return $c;
		}

		/**
		 * Fetche the IBGE Code.
		 *
		 * @param int $order_id order ID.
		 *
		 * @return null|string
		 */
		protected function ibge_code( $order_id ) {
			$post_code = get_post_meta( $order_id, '_billing_postcode', true );

			if ( empty( $post_code ) ) {
				if ( ! nfe_require_address() ) {
					return $this->get_company_info( 'code' );
				}

				return null;
			}

			$url      = 'https://open.nfe.io/v1/addresses/' . $post_code . '?api_key=' . $this->get_key();
			$response = wp_remote_get( esc_url_raw( $url ) );

			if ( is_wp_error( $response ) ) {
				return null;
			}

			$address = json_decode( wp_remote_retrieve_body( $response ), true );
			$code    = $address['city']['code'];

			if ( empty( $code ) ) {
				return null;
			}

			return $code;
		}

		/**
		 * Get current company info.
		 *
		 * @param string $field field.
		 *
		 * @return null|string
		 */
		protected function get_company_info( $field ) {
			// Get companies.
			$url      = 'https://api.nfe.io/v1/companies/' . $this->get_company() . '?api_key=' . $this->get_key();
			$response = wp_remote_get( esc_url_raw( $url ) );

			if ( is_wp_error( $response ) ) {
				return null;
			}

			$company = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 'city' === $field ) {
				$name = $company['companies']['address']['city']['name'];

				if ( empty( $name ) ) {
					return null;
				}

				return $name;
			}

			if ( 'code' === $field ) {
				$code = $company['companies']['address']['city']['code'];

				if ( empty( $code ) ) {
					return null;
				}

				return $code;
			}

			$field_value = $company['companies']['address'][ $field ];

			if ( empty( $field_value ) ) {
				return null;
			}

			return $field_value;
		}

		/**
		 * Gets RTC fiscal fields with precedence variation > product > global.
		 *
		 * @param int $order_id order ID.
		 *
		 * @return array
		 */
		protected function rtc_fields_info( $order_id ) {
			$nbs_code            = '';
			$operation_indicator = '';
			$class_code          = '';
			$fallback_item_rtc   = array();

			$order = nfe_wc_get_order( $order_id );

			if ( 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $item ) {
					$product_id   = $item['product_id'];
					$variation_id = $item['variation_id'];

					if ( $variation_id ) {
						$item_nbs_code            = get_post_meta( $variation_id, '_nfe_rtc_nbs_code', true );
						$item_operation_indicator = get_post_meta( $variation_id, '_nfe_rtc_operation_indicator', true );
						$item_class_code          = get_post_meta( $variation_id, '_nfe_rtc_class_code', true );

						if ( empty( $item_nbs_code ) ) {
							$item_nbs_code = get_post_meta( $product_id, '_simple_nfe_rtc_nbs_code', true );
						}

						if ( '' === (string) $item_operation_indicator ) {
							$item_operation_indicator = get_post_meta( $product_id, '_simple_nfe_rtc_operation_indicator', true );
						}

						if ( empty( $item_class_code ) ) {
							$item_class_code = get_post_meta( $product_id, '_simple_nfe_rtc_class_code', true );
						}
					} else {
						$item_nbs_code            = get_post_meta( $product_id, '_simple_nfe_rtc_nbs_code', true );
						$item_operation_indicator = get_post_meta( $product_id, '_simple_nfe_rtc_operation_indicator', true );
						$item_class_code          = get_post_meta( $product_id, '_simple_nfe_rtc_class_code', true );
					}

					if ( '' !== (string) $item_nbs_code ) {
						// Prefer tuple from an item that already has nbsCode.
						$nbs_code            = $item_nbs_code;
						$operation_indicator = $item_operation_indicator;
						$class_code          = $item_class_code;

						break;
					}

					if ( empty( $fallback_item_rtc ) && ( '' !== (string) $item_operation_indicator || '' !== (string) $item_class_code ) ) {
						$fallback_item_rtc = array(
							'nbsCode'            => $item_nbs_code,
							'operationIndicator' => $item_operation_indicator,
							'classCode'          => $item_class_code,
						);
					}
				}
			}

			if ( empty( $nbs_code ) && ! empty( $fallback_item_rtc ) ) {
				$nbs_code            = $fallback_item_rtc['nbsCode'];
				$operation_indicator = $fallback_item_rtc['operationIndicator'];
				$class_code          = $fallback_item_rtc['classCode'];
			}

			if ( empty( $nbs_code ) ) {
				$nbs_code = nfe_get_field( 'nfe_rtc_nbs_code' );
			}

			if ( '' === (string) $operation_indicator ) {
				$operation_indicator = nfe_get_field( 'nfe_rtc_operation_indicator' );
			}

			if ( empty( $class_code ) ) {
				$class_code = nfe_get_field( 'nfe_rtc_class_code' );
			}

			$ibs_cbs = array_filter(
				array(
					'operationIndicator' => $operation_indicator,
					'classCode'          => $class_code,
				),
				static function( $value ) {
					return '' !== (string) $value;
				}
			);

			return array(
				'nbsCode' => $nbs_code,
				'ibsCbs'  => $ibs_cbs,
			);
		}

		/**
		 * Collects activityEvent fields from the first order item that has event name set.
		 * Precedence: variation meta > product meta. No global fallback.
		 *
		 * @param int $order_id order ID.
		 *
		 * @return array activityEvent array ready for payload, or empty array.
		 */
		protected function activity_event_info( $order_id ) {
			$order = nfe_wc_get_order( $order_id );

			if ( 0 >= count( $order->get_items() ) ) {
				return array();
			}

			foreach ( $order->get_items() as $item ) {
				$product_id   = $item['product_id'];
				$variation_id = $item['variation_id'];

				if ( $variation_id ) {
					$name     = get_post_meta( $variation_id, '_nfe_activity_event_name', true );
					$begin_on = get_post_meta( $variation_id, '_nfe_activity_event_begin_on', true );
					$end_on   = get_post_meta( $variation_id, '_nfe_activity_event_end_on', true );
					$code     = get_post_meta( $variation_id, '_nfe_activity_event_code', true );
					$country  = get_post_meta( $variation_id, '_nfe_activity_event_address_country', true );
					$postal   = get_post_meta( $variation_id, '_nfe_activity_event_address_postal_code', true );
					$street   = get_post_meta( $variation_id, '_nfe_activity_event_address_street', true );
					$number   = get_post_meta( $variation_id, '_nfe_activity_event_address_number', true );
					$district = get_post_meta( $variation_id, '_nfe_activity_event_address_district', true );
					$state    = get_post_meta( $variation_id, '_nfe_activity_event_address_state', true );
					$city     = get_post_meta( $variation_id, '_nfe_activity_event_address_city_code', true );

					// Fall back to parent product when variation has no name.
					if ( empty( $name ) ) {
						$name     = get_post_meta( $product_id, '_simple_nfe_activity_event_name', true );
						$begin_on = get_post_meta( $product_id, '_simple_nfe_activity_event_begin_on', true );
						$end_on   = get_post_meta( $product_id, '_simple_nfe_activity_event_end_on', true );
						$code     = get_post_meta( $product_id, '_simple_nfe_activity_event_code', true );
						$country  = get_post_meta( $product_id, '_simple_nfe_activity_event_address_country', true );
						$postal   = get_post_meta( $product_id, '_simple_nfe_activity_event_address_postal_code', true );
						$street   = get_post_meta( $product_id, '_simple_nfe_activity_event_address_street', true );
						$number   = get_post_meta( $product_id, '_simple_nfe_activity_event_address_number', true );
						$district = get_post_meta( $product_id, '_simple_nfe_activity_event_address_district', true );
						$state    = get_post_meta( $product_id, '_simple_nfe_activity_event_address_state', true );
						$city     = get_post_meta( $product_id, '_simple_nfe_activity_event_address_city_code', true );
					}
				} else {
					$name     = get_post_meta( $product_id, '_simple_nfe_activity_event_name', true );
					$begin_on = get_post_meta( $product_id, '_simple_nfe_activity_event_begin_on', true );
					$end_on   = get_post_meta( $product_id, '_simple_nfe_activity_event_end_on', true );
					$code     = get_post_meta( $product_id, '_simple_nfe_activity_event_code', true );
					$country  = get_post_meta( $product_id, '_simple_nfe_activity_event_address_country', true );
					$postal   = get_post_meta( $product_id, '_simple_nfe_activity_event_address_postal_code', true );
					$street   = get_post_meta( $product_id, '_simple_nfe_activity_event_address_street', true );
					$number   = get_post_meta( $product_id, '_simple_nfe_activity_event_address_number', true );
					$district = get_post_meta( $product_id, '_simple_nfe_activity_event_address_district', true );
					$state    = get_post_meta( $product_id, '_simple_nfe_activity_event_address_state', true );
					$city     = get_post_meta( $product_id, '_simple_nfe_activity_event_address_city_code', true );
				}

				if ( empty( $name ) ) {
					continue;
				}

				$address = array_filter(
					array(
						'country'    => $country,
						'postalCode' => $postal,
						'street'     => $street,
						'number'     => $number,
						'district'   => $district,
						'state'      => $state,
						'city'       => array_filter(
							array( 'code' => $city )
						),
					)
				);

				return array_filter(
					array(
						'name'    => $name,
						'beginOn' => $begin_on,
						'endOn'   => $end_on,
						'code'    => $code,
						'address' => $address ?: null,
					)
				);
			}

			return array();
		}

		/**
		 * Validates RTC payload before sending to NFe.io.
		 *
		 * @param int   $order_id order ID.
		 * @param array $payload  request payload.
		 *
		 * @return array
		 */
		protected function validate_rtc_payload( $order_id, $payload ) {
			$errors   = array();
			$warnings = array();

			$profile             = nfe_rtc_validation_profile();
			$nbs_code            = isset( $payload['nbsCode'] ) ? trim( (string) $payload['nbsCode'] ) : '';
			$operation_indicator = isset( $payload['ibsCbs']['operationIndicator'] ) ? trim( (string) $payload['ibsCbs']['operationIndicator'] ) : '';
			$class_code          = isset( $payload['ibsCbs']['classCode'] ) ? trim( (string) $payload['ibsCbs']['classCode'] ) : '';
			$destination         = isset( $payload['destinationIndicator'] ) ? trim( (string) $payload['destinationIndicator'] ) : '';
			$has_recipient       = ! empty( $payload['recipient'] ) && is_array( $payload['recipient'] );
			$has_ibs_cbs_payload = ! empty( $payload['ibsCbs'] ) && is_array( $payload['ibsCbs'] );

			$has_rtc_context = ! empty( $nbs_code ) || $has_ibs_cbs_payload || ! empty( $destination );

			if ( $has_ibs_cbs_payload && ( '' === $operation_indicator || '' === $class_code ) ) {
				$errors[] = sprintf( __( 'RTC validation failed for order #%d: operationIndicator and classCode are required when RTC payload is used.', 'woo-nfe' ), $order_id );
			}

			if ( ! empty( $destination ) && ! in_array( $destination, array( 'SameAsBuyer', 'DifferentFromBuyer' ), true ) ) {
				$errors[] = sprintf( __( 'RTC validation failed for order #%d: destinationIndicator has an invalid value.', 'woo-nfe' ), $order_id );
			}

			if ( empty( $nbs_code ) ) {
				$missing_nbs_message = sprintf( __( 'RTC validation warning for order #%d: nbsCode is missing.', 'woo-nfe' ), $order_id );
				$observability_ctx   = array(
					'missing_fields' => array( 'nbsCode' ),
					'item_ids'       => $this->get_order_item_ids( $order_id ),
				);

				switch ( $profile ) {
					case 'estrito':
						$errors[] = sprintf( __( 'RTC validation failed for order #%d: nbsCode is required in Strict profile.', 'woo-nfe' ), $order_id );
						$observability_ctx['scenario'] = 'strict';
						$this->register_missing_nbs_observability( $order_id, $profile, true, $observability_ctx );
						break;

					case 'equilibrado':
						if ( $has_rtc_context && $this->missing_nbs_in_critical_scenario( $payload ) ) {
							$errors[] = sprintf( __( 'RTC validation failed for order #%d: nbsCode is required in this critical RTC scenario for Balanced profile.', 'woo-nfe' ), $order_id );
							$observability_ctx['scenario'] = 'balanced_critical';
							$this->register_missing_nbs_observability( $order_id, $profile, true, $observability_ctx );
						} else {
							$warnings[] = $missing_nbs_message;
							$observability_ctx['scenario'] = 'balanced_warning';
							$this->register_missing_nbs_observability( $order_id, $profile, false, $observability_ctx );
						}
						break;

					case 'compativel':
					default:
						$warnings[] = $missing_nbs_message;
						$observability_ctx['scenario'] = 'compatible_warning';
						$this->register_missing_nbs_observability( $order_id, $profile, false, $observability_ctx );
						break;
				}
			}

			if ( 'DifferentFromBuyer' === $destination && ! $has_recipient ) {
				$errors[] = sprintf( __( 'RTC validation failed for order #%d: recipient is required when destinationIndicator is DifferentFromBuyer.', 'woo-nfe' ), $order_id );
			}

			if ( 'DifferentFromBuyer' === $destination && $has_recipient && ( ! isset( $payload['recipient']['name'] ) || '' === trim( (string) $payload['recipient']['name'] ) ) ) {
				$errors[] = sprintf( __( 'RTC validation failed for order #%d: recipient.name is required when destinationIndicator is DifferentFromBuyer.', 'woo-nfe' ), $order_id );
			}

			if ( 'SameAsBuyer' === $destination && $has_recipient ) {
				$warnings[] = sprintf( __( 'RTC validation warning for order #%d: recipient was provided even though destinationIndicator is SameAsBuyer.', 'woo-nfe' ), $order_id );
			}

			return array(
				'errors'   => $errors,
				'warnings' => $warnings,
			);
		}

		/**
		 * Checks if nbsCode is missing in a critical Balanced profile scenario.
		 *
		 * @param array $payload request payload.
		 *
		 * @return bool
		 */
		protected function missing_nbs_in_critical_scenario( $payload ) {
			$has_ibs_cbs = ! empty( $payload['ibsCbs'] ) && is_array( $payload['ibsCbs'] );

			if ( $has_ibs_cbs ) {
				return true;
			}

			if ( isset( $payload['destinationIndicator'] ) && 'DifferentFromBuyer' === $payload['destinationIndicator'] ) {
				return true;
			}

			return false;
		}

		/**
		 * Records observability metadata for nbsCode missing events.
		 *
		 * @param int    $order_id order ID.
		 * @param string $profile  active profile.
		 * @param bool   $blocked  if the emission was blocked.
		 * @param array  $context  observability context.
		 */
		protected function register_missing_nbs_observability( $order_id, $profile, $blocked, $context = array() ) {
			$last_event = get_post_meta( $order_id, '_nfe_rtc_missing_nbs_last_event', true );

			$signature_source = array(
				'profile' => $profile,
				'blocked' => (bool) $blocked,
				'context' => $context,
			);

			$event_signature = md5( wp_json_encode( $signature_source ) );
			$missing_count = absint( get_post_meta( $order_id, '_nfe_rtc_missing_nbs_count', true ) );

			if ( empty( $last_event['signature'] ) || $last_event['signature'] !== $event_signature ) {
				update_post_meta( $order_id, '_nfe_rtc_missing_nbs_count', $missing_count + 1 );
			}

			update_post_meta(
				$order_id,
				'_nfe_rtc_missing_nbs_last_event',
				array(
					'profile'   => $profile,
					'blocked'   => (bool) $blocked,
					'context'   => $context,
					'signature' => $event_signature,
					'timestamp' => current_time( 'mysql' ),
				)
			);
		}

		/**
		 * Gets order item IDs for observability context.
		 *
		 * @param int $order_id order ID.
		 *
		 * @return array
		 */
		protected function get_order_item_ids( $order_id ) {
			$order    = nfe_wc_get_order( $order_id );
			$item_ids = array();

			foreach ( $order->get_items() as $item_id => $item ) {
				$item_ids[] = absint( $item_id );
			}

			return $item_ids;
		}

		/**
		 * City Service Information (City and Federal Code, and Description).
		 *
		 * @param string $field    the field info being fetched.
		 * @param int    $order_id order ID.
		 *
		 * @return null|string
		 */
		protected function city_service_info( $field = '', $order_id ) {
			// Bail early.
			if ( empty( $field ) ) {
				return;
			}

			$order = nfe_wc_get_order( $order_id );

			if ( 0 < count( $order->get_items() ) ) {
				// Variations or Simple Product Info.
				foreach ( $order->get_items() as $key => $item ) {
					$product_id   = $item['product_id'];
					$variation_id = $item['variation_id'];

					if ( $variation_id ) {
						$cityservicecode    = get_post_meta( $variation_id, '_cityservicecode', true );
						$federalservicecode = get_post_meta( $variation_id, '_federalservicecode', true );
						$product_desc       = get_post_meta( $variation_id, '_nfe_product_variation_desc', true );
					} else {
						$cityservicecode    = get_post_meta( $product_id, '_simple_cityservicecode', true );
						$federalservicecode = get_post_meta( $product_id, '_simple_federalservicecode', true );
						$product_desc       = get_post_meta( $product_id, '_simple_nfe_product_desc', true );
					}
				}
			}

			switch ( $field ) {
				case 'code':
					$output = $cityservicecode ? $cityservicecode : nfe_get_field( 'nfe_cityservicecode' );

					break;

				case 'fed_code':
					$output = $federalservicecode ? $federalservicecode : nfe_get_field( 'nfe_fedservicecode' );

					break;

				case 'desc':
					$output = $product_desc ? $product_desc : nfe_get_field( 'nfe_cityservicecode_desc' );

					break;

				default:
					$output = null;

					break;
			}

			return $output;
		}

		/**
		 * Fetch customer info depending on the person type.
		 *
		 * @param string $field field to fetch info from.
		 * @param int    $order the order ID.
		 *
		 * @return null|string returns the customer info specific to the person type being fetched.
		 */
		protected function check_customer_info( $field = '', $order ) {
			if ( empty( $field ) ) {
				return;
			}

			// Only check those fields.
			if ( in_array( $field, array( 'number', 'name', 'type' ), true ) ) {
				// Person Type.
				$type = get_post_meta( $order, '_billing_persontype', true );

				// Customer info.
				$cpf      = get_post_meta( $order, '_billing_cpf', true );
				$customer = get_post_meta( $order, '_billing_first_name', true ) . ' ' . get_post_meta( $order, '_billing_last_name', true );

				// Company info.
				$cnpj    = get_post_meta( $order, '_billing_cnpj', true );
				$company = get_post_meta( $order, '_billing_company', true );

				if ( ! empty( $type ) ) {
					if ( '1' === $type ) {
						$id   = $this->cpf( $cpf );
						$name = $customer;
						$type = __( 'Customers', 'woo-nfe' );
					} else {
						$id   = $this->cnpj( $cnpj );
						$name = $company;
						$type = __( 'Company', 'woo-nfe' );
					}
				}
			}

			switch ( $field ) {
				case 'number':
					if ( empty( $type ) ) {
						if ( ! empty( $cpf ) ) {
							$output = $this->cpf( $cpf );
						} else {
							$output = $this->cnpj( $cnpj );
						}
					} else {
						$output = $id;
					}

					break;

				case 'name':
					if ( empty( $type ) ) {
						if ( ! empty( $customer ) ) {
							$output = $customer;
						} else {
							$output = $company;
						}
					} else {
						$output = $name;
					}

					break;

				case 'type':
					$output = $type;

					break;

				case 'city':
					$output = get_post_meta( $order, '_billing_city', true );
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'city' );
					}

					break;

				case 'state':
					$output = get_post_meta( $order, '_billing_state', true );
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'state' );
					}

					break;

				case 'district':
					$output = get_post_meta( $order, '_billing_neighborhood', true );
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'district' );
					}

					break;

				case 'address_number':
					$output = get_post_meta( $order, '_billing_number', true );
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'number' );
					}

					break;

				case 'street':
					$output = get_post_meta( $order, '_billing_address_1', true );
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'street' );
					}

					break;

				case 'cep':
					$output = get_post_meta( $order, '_billing_postcode', true );
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'postalCode' );
					}

					break;

				default:
					$output = null;

					break;
			}

			return $output;
		}

		/**
		 * Remove Ponto Traco.
		 *
		 * @param string $string content to remove.
		 *
		 * @return string
		 */
		protected function removepontotraco( $string ) {
			return ltrim( preg_replace( '/[^0-9]/', '', $string ), '0' );
		}

		/**
		 * Remove Caracter.
		 *
		 * @param string $string content to remove.
		 *
		 * @return string
		 */
		protected function remover_caracter( $string ) {
			$string = preg_replace( '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);~i', '$1', htmlentities( $string, ENT_COMPAT, 'UTF-8' ) );

			return preg_replace( '/[][><}{)(:;,!?*%~^`´&#@ªº°$¨]/', '', $string );
		}

		/**
		 * Get NFe API key.
		 *
		 * @return string
		 */
		protected function get_key() {
			return nfe_get_field( 'api_key' );
		}

		/**
		 * Convertion of country 2 and 3 ISO Codes.
		 *
		 * @return array
		 */
		protected function country_iso_codes() {
			return array(
				'AFG' => 'AF',     // Afghanistan.
				'ALB' => 'AL',     // Albania.
				'ARE' => 'AE',     // U.A.E.
				'ARG' => 'AR',     // Argentina.
				'ARM' => 'AM',     // Armenia.
				'AUS' => 'AU',     // Australia.
				'AUT' => 'AT',     // Austria.
				'AZE' => 'AZ',     // Azerbaijan.
				'BEL' => 'BE',     // Belgium.
				'BGD' => 'BD',     // Bangladesh.
				'BGR' => 'BG',     // Bulgaria.
				'BHR' => 'BH',     // Bahrain.
				'BIH' => 'BA',     // Bosnia and Herzegovina.
				'BLR' => 'BY',     // Belarus.
				'BLZ' => 'BZ',     // Belize.
				'BOL' => 'BO',     // Bolivia.
				'BRA' => 'BR',     // Brazil.
				'BRN' => 'BN',     // Brunei Darussalam.
				'CAN' => 'CA',     // Canada.
				'CHE' => 'CH',     // Switzerland.
				'CHL' => 'CL',     // Chile.
				'CHN' => 'CN',     // People's Republic of China.
				'COL' => 'CO',     // Colombia.
				'CRI' => 'CR',     // Costa Rica.
				'CZE' => 'CZ',     // Czech Republic.
				'DEU' => 'DE',     // Germany.
				'DNK' => 'DK',     // Denmark.
				'DOM' => 'DO',     // Dominican Republic.
				'DZA' => 'DZ',     // Algeria.
				'ECU' => 'EC',     // Ecuador.
				'EGY' => 'EG',     // Egypt.
				'ESP' => 'ES',     // Spain.
				'EST' => 'EE',     // Estonia.
				'ETH' => 'ET',     // Ethiopia.
				'FIN' => 'FI',     // Finland.
				'FRA' => 'FR',     // France.
				'FRO' => 'FO',     // Faroe Islands.
				'GBR' => 'GB',     // United Kingdom.
				'GEO' => 'GE',     // Georgia.
				'GRC' => 'GR',     // Greece.
				'GRL' => 'GL',     // Greenland.
				'GTM' => 'GT',     // Guatemala.
				'HKG' => 'HK',     // Hong Kong S.A.R.
				'HND' => 'HN',     // Honduras.
				'HRV' => 'HR',     // Croatia.
				'HUN' => 'HU',     // Hungary.
				'IDN' => 'ID',     // Indonesia.
				'IND' => 'IN',     // India.
				'IRL' => 'IE',     // Ireland.
				'IRN' => 'IR',     // Iran.
				'IRQ' => 'IQ',     // Iraq.
				'ISL' => 'IS',     // Iceland.
				'ISR' => 'IL',     // Israel.
				'ITA' => 'IT',     // Italy.
				'JAM' => 'JM',     // Jamaica.
				'JOR' => 'JO',     // Jordan.
				'JPN' => 'JP',     // Japan.
				'KAZ' => 'KZ',     // Kazakhstan.
				'KEN' => 'KE',     // Kenya.
				'KGZ' => 'KG',     // Kyrgyzstan.
				'KHM' => 'KH',     // Cambodia.
				'KOR' => 'KR',     // Korea.
				'KWT' => 'KW',     // Kuwait.
				'LAO' => 'LA',     // Lao P.D.R.
				'LBN' => 'LB',     // Lebanon.
				'LBY' => 'LY',     // Libya.
				'LIE' => 'LI',     // Liechtenstein.
				'LKA' => 'LK',     // Sri Lanka.
				'LTU' => 'LT',     // Lithuania.
				'LUX' => 'LU',     // Luxembourg.
				'LVA' => 'LV',     // Latvia.
				'MAC' => 'MO',     // Macao S.A.R.
				'MAR' => 'MA',     // Morocco.
				'MCO' => 'MC',     // Principality of Monaco.
				'MDV' => 'MV',     // Maldives.
				'MEX' => 'MX',     // Mexico.
				'MKD' => 'MK',     // Macedonia (FYROM).
				'MLT' => 'MT',     // Malta.
				'MNE' => 'ME',     // Montenegro.
				'MNG' => 'MN',     // Mongolia.
				'MYS' => 'MY',     // Malaysia.
				'NGA' => 'NG',     // Nigeria.
				'NIC' => 'NI',     // Nicaragua.
				'NLD' => 'NL',     // Netherlands.
				'NOR' => 'NO',     // Norway.
				'NPL' => 'NP',     // Nepal.
				'NZL' => 'NZ',     // New Zealand.
				'OMN' => 'OM',     // Oman.
				'PAK' => 'PK',     // Islamic Republic of Pakistan.
				'PAN' => 'PA',     // Panama.
				'PER' => 'PE',     // Peru.
				'PHL' => 'PH',     // Republic of the Philippines.
				'POL' => 'PL',     // Poland.
				'PRI' => 'PR',     // Puerto Rico.
				'PRT' => 'PT',     // Portugal.
				'PRY' => 'PY',     // Paraguay.
				'QAT' => 'QA',     // Qatar.
				'ROU' => 'RO',     // Romania.
				'RUS' => 'RU',     // Russia.
				'RWA' => 'RW',     // Rwanda.
				'SAU' => 'SA',     // Saudi Arabia.
				'SCG' => 'CS',     // Serbia and Montenegro (Former).
				'SEN' => 'SN',     // Senegal.
				'SGP' => 'SG',     // Singapore.
				'SLV' => 'SV',     // El Salvador.
				'SRB' => 'RS',     // Serbia.
				'SVK' => 'SK',     // Slovakia.
				'SVN' => 'SI',     // Slovenia.
				'SWE' => 'SE',     // Sweden.
				'SYR' => 'SY',     // Syria.
				'TAJ' => 'TJ',     // Tajikistan.
				'THA' => 'TH',     // Thailand.
				'TKM' => 'TM',     // Turkmenistan.
				'TTO' => 'TT',     // Trinidad and Tobago.
				'TUN' => 'TN',     // Tunisia.
				'TUR' => 'TR',     // Turkey.
				'TWN' => 'TW',     // Taiwan.
				'UKR' => 'UA',     // Ukraine.
				'URY' => 'UY',     // Uruguay.
				'USA' => 'US',     // United States.
				'UZB' => 'UZ',     // Uzbekistan.
				'VEN' => 'VE',     // Bolivarian Republic of Venezuela.
				'VNM' => 'VN',     // Vietnam.
				'YEM' => 'YE',     // Yemen.
				'ZAF' => 'ZA',     // South Africa.
				'ZWE' => 'ZW',     // Zimbabwe.
			);
		}
	}

	/**
	 * The main function responsible for returning the one true NFe_Woo Instance.
	 *
	 * @since 1.0.0
	 *
	 * @return NFe_Woo the one true NFe_Woo Instance.
	 */
	function NFe_Woo() {
		return NFe_Woo::instance();
	}
}
