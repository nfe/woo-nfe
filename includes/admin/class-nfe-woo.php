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
		 * Timeout, in seconds, for the issuing call.
		 *
		 * Issuing runs synchronously inside order-status and checkout hooks, so
		 * the worst case has to stay well under the request budget. The API
		 * answers 202 and the document arrives by webhook, so waiting longer
		 * buys nothing.
		 *
		 * @var int
		 */
		const ISSUE_TIMEOUT = 15;

		/**
		 * Address API host used for postal-code lookups.
		 *
		 * Deliberately not the SDK default (address.api.nfe.io/v2): that host
		 * requires a separate data key, while this one accepts the invoice API
		 * key the store already has. See ibge_code().
		 *
		 * @var string
		 */
		const ADDRESS_BASE_URL = 'https://open.nfe.io/v1';

		/**
		 * Shared NFe.io SDK client, built on first use.
		 *
		 * @var \Nfe\Client|null
		 */
		private $client = null;

		/**
		 * Memoised company record for the current request.
		 *
		 * The getter is asked for one field at a time, six times over while
		 * building a single payload. Without this it was six HTTP round trips
		 * for one answer.
		 *
		 * @var array|null|false Null until fetched, false when the fetch failed.
		 */
		private $company_info = null;

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
		 * Returns the shared NFe.io API client, building it on first use.
		 *
		 * One client is enough. Since SDK 3.2.0 the retry policy is aware of both
		 * method and idempotency, so a POST is only replayed on 429, on a
		 * connection that provably never reached the server, or when an
		 * Idempotency-Key is present -- exactly the cases where replaying is safe.
		 *
		 * The environment is always Production. The SDK deprecated Sandbox in
		 * 3.4.0 because no sandbox host ever existed: selecting it changed
		 * nothing but the caller's expectations, which is a dangerous thing to
		 * offer when the side effect is a real fiscal document. Isolation for
		 * testing comes from a development-account API key plus a company
		 * configured outside production.
		 *
		 * @since 1.5.0
		 *
		 * @throws \Nfe\Exception\InvalidRequestException When no API key is set.
		 *
		 * @return \Nfe\Client
		 */
		protected function client() {
			if ( null === $this->client ) {
				$this->client = new \Nfe\Client(
					apiKey: (string) $this->get_key(),
					environment: \Nfe\Environment::Production
				);
			}

			return $this->client;
		}

		/**
		 * Merges values into the 'nfe_issued' meta, preserving what is there.
		 *
		 * @since 1.5.0
		 *
		 * @param WC_Order $order  order object.
		 * @param array    $values values to merge in.
		 * @param bool     $save   whether to persist immediately.
		 *
		 * @return void
		 */
		protected function merge_invoice_meta( $order, $values, $save = true ) {
			$current = nfe_get_order_meta( $order, 'nfe_issued' );
			$current = is_array( $current ) ? $current : array();

			nfe_set_order_meta( $order, 'nfe_issued', array_merge( $current, $values ), $save );
		}

		/**
		 * Marks the order as having an issuing attempt in flight.
		 *
		 * Merges rather than replaces. The previous code assigned
		 * `array( 'status' => 'Processing' )` outright, which erased the id,
		 * number and check code of an invoice issued earlier every time a later
		 * attempt was made -- losing the record of a real fiscal document.
		 *
		 * @since 1.5.0
		 *
		 * @param WC_Order $order order object.
		 *
		 * @return void
		 */
		protected function set_processing_marker( $order ) {
			$this->merge_invoice_meta( $order, array( 'status' => 'Processing' ) );
		}

		/**
		 * Releases the in-flight marker so the order can be issued again.
		 *
		 * Only ever called once it is established that no invoice was created.
		 *
		 * @since 1.5.0
		 *
		 * @param WC_Order $order order object.
		 *
		 * @return void
		 */
		protected function release_processing_marker( $order ) {
			$issued = nfe_get_order_meta( $order, 'nfe_issued' );
			$issued = is_array( $issued ) ? $issued : array();

			// Preserve an earlier invoice's data if there is one; only the
			// status goes back to a re-issuable state.
			$issued['status'] = empty( $issued['id'] ) ? '' : 'IssueFailed';

			nfe_set_order_meta( $order, 'nfe_issued', $issued );
		}

		/**
		 * Whether an order must not be issued again.
		 *
		 * The previous guard read `get_post_meta( $order_id )` with no key and
		 * then indexed the result as if it were the invoice array, so it never
		 * matched and never blocked anything.
		 *
		 * Making it work has a consequence worth stating plainly: 'Processing'
		 * becomes a blocking state, so an attempt that failed and left the
		 * marker behind would lock the order out of issuing for good. That is
		 * why every failure path either resolves or releases the marker.
		 *
		 * @since 1.5.0
		 *
		 * @param WC_Order $order order object.
		 *
		 * @return bool
		 */
		protected function is_blocked_for_issuing( $order ) {
			$issued = nfe_get_order_meta( $order, 'nfe_issued' );

			if ( ! is_array( $issued ) || empty( $issued['status'] ) ) {
				return false;
			}

			$status  = (string) $issued['status'];
			$blocked = array_merge( array( 'Processing', 'Issued' ), nfe_processing_status() );

			if ( ! in_array( $status, $blocked, true ) ) {
				return false;
			}

			// translators: 1: Order ID, 2: current NFe status.
			$log = sprintf( __( 'Skipping a second issuing attempt for order #%1$d: an invoice is already %2$s.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order->get_id(), $status );

			$this->logger( $log );

			return true;
		}

		/**
		 * Allocates and persists the externalId for a new issuing attempt.
		 *
		 * NFe.io treats externalId as an idempotency key with *replay*
		 * semantics: a value already processed successfully makes the API return
		 * the original invoice instead of creating a new one. The key therefore
		 * identifies one emission, not one order.
		 *
		 * The plugin used to send a fixed 'WOO-NFE-{order_id}', so every
		 * legitimate re-issue -- after a cancellation, say -- silently got the
		 * old, possibly cancelled, invoice back and reported it as a success.
		 * Numbering the attempts fixes that while keeping the first one on the
		 * bare form, so invoices issued by earlier versions still resolve.
		 *
		 * The counter is derived, not migrated: an order carrying an invoice id
		 * but no counter was issued by an older version, which consumed the bare
		 * key, so its next attempt starts at 1. That covers the upgrade case
		 * without touching a single existing order.
		 *
		 * @since 1.5.0
		 *
		 * @param WC_Order $order order object.
		 *
		 * @return string
		 */
		protected function allocate_external_id( $order ) {
			$stored = nfe_get_order_meta( $order, '_nfe_external_seq' );

			if ( '' !== $stored && null !== $stored ) {
				$sequence = (int) $stored + 1;
			} else {
				$issued   = nfe_get_order_meta( $order, 'nfe_issued' );
				$sequence = ( is_array( $issued ) && ! empty( $issued['id'] ) ) ? 1 : 0;
			}

			$external_id = 0 === $sequence
				? 'WOO-NFE-' . $order->get_id()
				: 'WOO-NFE-' . $order->get_id() . '-' . $sequence;

			nfe_set_order_meta( $order, '_nfe_external_seq', $sequence, false );
			nfe_set_order_meta( $order, '_nfe_external_id', $external_id, false );
			$order->save();

			return $external_id;
		}

		/**
		 * Writes a fully issued invoice onto the order.
		 *
		 * Reads the typed properties of the ServiceInvoice DTO, populated by the
		 * SDK since 3.3.0. `totalAmount` is deliberately never read: it is
		 * deprecated and the API does not return it, so the value comes from
		 * amountNet with servicesAmount as the fallback.
		 *
		 * The meta shape is kept identical to the one the webhook writes, so the
		 * two paths stay interchangeable for every consumer.
		 *
		 * @since 1.5.0
		 *
		 * @param WC_Order $order   order object.
		 * @param object   $invoice ServiceInvoice DTO.
		 *
		 * @return void
		 */
		protected function store_invoice( $order, $invoice ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- SDK DTO properties mirror the NFe.io API field names.
			$amount = null !== $invoice->amountNet ? $invoice->amountNet : $invoice->servicesAmount;

			/*
			 * Only fields the DTO actually carries are written, for the same
			 * reason the webhook handler does it: an invoice recovered after a
			 * failure may come back with fewer fields populated than the order
			 * already holds, and merging nulls as '' or 0 would erase the record
			 * of a document that really was issued.
			 */
			$update = array( 'status' => (string) $invoice->flowStatus );

			if ( ! empty( $invoice->id ) ) {
				$update['id'] = (string) $invoice->id;
			}

			if ( ! empty( $invoice->issuedOn ) ) {
				$update['issuedOn'] = (string) $invoice->issuedOn;
			}

			if ( null !== $amount ) {
				$update['amountNet'] = (float) $amount;
			}

			if ( ! empty( $invoice->checkCode ) ) {
				$update['checkCode'] = (string) $invoice->checkCode;
			}

			// A failed issuing reports number 0, which is not an invoice number.
			if ( is_int( $invoice->number ) && $invoice->number > 0 ) {
				$update['number'] = $invoice->number;
			}

			$this->merge_invoice_meta( $order, $update, false );

			// Flat meta so the order can be looked up by invoice id under both storages.
			if ( ! empty( $invoice->id ) ) {
				nfe_set_order_meta( $order, '_nfe_invoice_id', (string) $invoice->id, false );
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			$order->save();
		}

		/**
		 * Looks an invoice up by externalId, allowing for indexing lag.
		 *
		 * Three outcomes, and the difference between the last two matters:
		 * the DTO when an invoice exists, `null` when the API positively says
		 * none does, and `false` when the lookup itself failed and the question
		 * is simply unanswered.
		 *
		 * Right after a 202 the invoice can take a few seconds to appear on the
		 * lookup route, hence the backoff before concluding it is absent.
		 *
		 * @since 1.5.0
		 *
		 * @param string $company_id  company id.
		 * @param string $external_id externalId of the attempt.
		 *
		 * @return object|null|false DTO, null when confirmed absent, false when unknown.
		 */
		protected function find_invoice_by_external_id( $company_id, $external_id ) {
			$delays = array( 0, 2, 3 );

			foreach ( $delays as $delay ) {
				if ( $delay > 0 ) {
					sleep( $delay );
				}

				try {
					$invoice = $this->client()->serviceInvoices->findByExternalId( $company_id, $external_id );
				} catch ( \Nfe\Exception\ApiErrorException $e ) {
					// translators: 1: externalId, 2: error message.
					$this->logger( sprintf( __( 'Could not check whether externalId %1$s produced an invoice: %2$s', 'nota-fiscal-nfe-io-for-woocommerce' ), $external_id, $e->getMessage() ) );

					return false;
				}

				if ( $invoice ) {
					return $invoice;
				}
			}

			return null;
		}

		/**
		 * Handles a failed issuing attempt, recovering the invoice if one exists.
		 *
		 * A failure here is ambiguous by nature: the request may never have
		 * reached the API, or it may have created an invoice whose response was
		 * lost on the way back. Releasing the order without checking would let
		 * the next attempt issue a second, duplicate NFS-e for the same sale.
		 *
		 * So the order is only released once the API positively confirms that
		 * nothing was created. When the check itself cannot be completed, the
		 * order deliberately stays marked: a stuck order that says so in its
		 * notes is recoverable, a duplicate fiscal document is not.
		 *
		 * @since 1.5.0
		 *
		 * @param WC_Order                         $order       order object.
		 * @param string                           $company_id  company id.
		 * @param string                           $external_id externalId of this attempt.
		 * @param \Nfe\Exception\ApiErrorException $exception   the failure.
		 *
		 * @return bool True when an invoice was recovered.
		 */
		protected function recover_from_failure( $order, $company_id, $external_id, $exception ) {
			if ( $exception instanceof \Nfe\Exception\AuthenticationException ) {
				// Nothing can have been created: the request never authenticated.
				$log = __( 'NFe could not be issued: the NFe.io API key was rejected. Check the API key in the plugin settings.', 'nota-fiscal-nfe-io-for-woocommerce' );

				$this->logger( $log );
				$order->add_order_note( $log );
				$this->release_processing_marker( $order );

				return false;
			}

			$invoice = $this->find_invoice_by_external_id( $company_id, $external_id );

			if ( false === $invoice ) {
				// translators: %s: error message returned by the API.
				$log = sprintf( __( 'The NFe issuing call failed (%s) and it could not be confirmed whether an invoice was created. This order was left marked as in progress on purpose, to avoid issuing a duplicate. Check the invoice in the NFe.io panel, then re-issue only if none exists.', 'nota-fiscal-nfe-io-for-woocommerce' ), $exception->getMessage() );

				$this->logger( $log );
				$order->add_order_note( $log );

				return false;
			}

			if ( $invoice ) {
				// The invoice existed all along; it was the response that went
				// missing. Record it and let the webhook carry it the rest of
				// the way.
				$this->store_invoice( $order, $invoice );

				// translators: %s: error message returned by the API.
				$log = sprintf( __( 'The NFe issuing call failed (%s), but the invoice had already been created and was recovered. No second invoice was issued.', 'nota-fiscal-nfe-io-for-woocommerce' ), $exception->getMessage() );

				$this->logger( $log );
				$order->add_order_note( $log );

				return true;
			}

			// translators: %s: error message returned by the API.
			$log = sprintf( __( 'An error occurred while issuing a NFe: %s', 'nota-fiscal-nfe-io-for-woocommerce' ), $exception->getMessage() );

			$this->logger( $log );
			$order->add_order_note( $log );
			$this->release_processing_marker( $order );

			return false;
		}

		/**
		 * Issue a NFe invoice.
		 *
		 * Issuing is asynchronous on the NFe.io side: the API answers 202 with
		 * an invoice id (Pending) and the finished document arrives later over
		 * the webhook. A 201 (Issued) is possible and terminal, and is the one
		 * case where this flow writes final invoice data itself.
		 *
		 * Each order is independent. A rejected order used to abort the whole
		 * batch, which was harmless only because the re-send guard never fired;
		 * with that guard fixed, one already-issued order would have silently
		 * cancelled every remaining order in a bulk action.
		 *
		 * @since 1.5.0 Migrated to the nfe/nfe SDK.
		 *
		 * @param array $order_ids orders to issue the NFe.
		 *
		 * @return bool True when at least one order was accepted by the API.
		 */
		public function issue_invoice( $order_ids = array() ) {
			$company_id = (string) $this->get_company();
			$issued_any = false;

			foreach ( (array) $order_ids as $order_id ) {
				$order = nfe_wc_get_order( $order_id );

				if ( ! is_a( $order, 'WC_Order' ) ) {
					continue;
				}

				// translators: Log message.
				$log = sprintf( __( 'NFe issuing process started! Order: #%d', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
				$this->logger( $log );
				$order->add_order_note( $log );

				if ( $this->is_blocked_for_issuing( $order ) ) {
					continue;
				}

				// A zero-total order has nothing to invoice. The old guard read
				// `< 0`, which let 0.00 through against the documented behaviour.
				if ( $order->get_total() <= 0 ) {
					// translators: Log message.
					$log = sprintf( __( 'Not possible to issue NFe without an order value! Order: #%d', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
					$this->logger( $log );
					$order->add_order_note( $log );

					continue;
				}

				$datainvoice = $this->order_info( $order_id );

				// Check if there was a problem while fetching the city code from IBGE. And if the address is required.
				if ( nfe_require_address() && empty( $datainvoice['borrower']['address']['city']['code'] ) ) {
					$log = __( 'There was a problem fetching IBGE code! Check your CEP information.', 'nota-fiscal-nfe-io-for-woocommerce' );
					$this->logger( $log );
					$order->add_order_note( $log );

					// Bail early so that it doesn't create an invoice without address.
					continue;
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

					continue;
				}

				// The client is built before anything is marked. A configuration
				// problem -- no API key, most of all -- means no request was ever
				// made, so it must not leave an in-flight marker behind: with the
				// re-send guard working, that marker would lock the order out of
				// issuing forever, and a store that enables the plugin before
				// pasting its key would do that to every order it touched.
				try {
					$client = $this->client();
				} catch ( \Nfe\Exception\ApiErrorException $e ) {
					// translators: %s: error message.
					$log = sprintf( __( 'NFe could not be issued because the NFe.io connection is not configured: %s', 'nota-fiscal-nfe-io-for-woocommerce' ), $e->getMessage() );

					$this->logger( $log );
					$order->add_order_note( $log );

					continue;
				}

				// Persisted before the POST on purpose: if the response is lost,
				// this value is the only way back to whatever the API did with
				// the request.
				$external_id               = $this->allocate_external_id( $order );
				$datainvoice['externalId'] = $external_id;

				$this->set_processing_marker( $order );

				try {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Resource accessor defined by the nfe/nfe SDK; renaming it is not ours to do.
					$invoice = $client->serviceInvoices->create(
						$company_id,
						$datainvoice,
						new \Nfe\Http\RequestOptions( timeout: self::ISSUE_TIMEOUT )
					);
				} catch ( \Nfe\Exception\ApiErrorException $e ) {
					// A duplicate rejection means the attempt already produced an
					// invoice, so it is recovered rather than re-sent.
					if ( $this->recover_from_failure( $order, $company_id, $external_id, $e ) ) {
						$issued_any = true;
					}

					continue;
				}

				if ( $invoice instanceof \Nfe\Response\ServiceInvoicePending ) {
					// 202: there is nothing to read from the body beyond the id.
					// The document itself arrives over the webhook.
					$this->merge_invoice_meta( $order, array( 'id' => $invoice->invoiceId() ), false );
					nfe_set_order_meta( $order, '_nfe_invoice_id', $invoice->invoiceId(), false );
					$order->save();

					// translators: Log message.
					$log = sprintf( __( 'NFe sent successfully to issue! Order: #%d', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
				} else {
					// 201: terminal already, so the data is written here.
					$this->store_invoice( $order, $invoice->resource() );

					// translators: Log message.
					$log = sprintf( __( 'NFe issued! Order: #%d', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
				}

				$this->logger( $log );
				$order->add_order_note( $log );

				$issued_any = true;
			}

			return $issued_any;
		}

		/**
		 * Download the invoice(s).
		 *
		 * Returns the raw PDF bytes straight from the API. The previous version
		 * asked the API for a URL and fetched it separately, which was broken in
		 * two independent ways: the vendored SDK never reached the call at all
		 * (a stray `echo`/`exit` debug line sat in front of it), and the URLs
		 * carried in webhook payloads are internal `r2://` references that are
		 * not publicly fetchable in the first place.
		 *
		 * It also no longer throws. The old code raised an Exception that no
		 * caller caught, so a bad response from the API became a fatal error on
		 * the storefront; and its error path fell through to `return $pdf` on a
		 * variable that was never assigned.
		 *
		 * @since 1.5.0 Returns bytes from the SDK, and reports failure instead of throwing.
		 *
		 * @param array $order_ids Array of order ids. The first one that has a
		 *                         downloadable invoice wins.
		 *
		 * @return string|false Raw PDF bytes, or false when none could be fetched.
		 */
		public function download_pdf_invoice( $order_ids = array() ) {
			$company_id = (string) $this->get_company();

			foreach ( (array) $order_ids as $order_id ) {
				$order = nfe_wc_get_order( $order_id );

				if ( ! is_a( $order, 'WC_Order' ) ) {
					continue;
				}

				$nfe = nfe_get_order_meta( $order, 'nfe_issued' );

				if ( ! is_array( $nfe ) || empty( $nfe['id'] ) ) {
					// translators: Log message.
					$this->logger( sprintf( __( 'There is no NFe invoice to download for order #%d.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id ) );

					continue;
				}

				try {
					$pdf = $this->client()->serviceInvoices->downloadPdf( $company_id, (string) $nfe['id'] );
				} catch ( \Nfe\Exception\ApiErrorException $e ) {
					// translators: 1: Order ID, 2: error message returned by the API.
					$log = sprintf( __( 'There was a problem when trying to download NFe PDF for order #%1$d! Error: %2$s', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id, $e->getMessage() );

					$this->logger( $log );
					$order->add_order_note( $log );

					continue;
				}

				// translators: Log message.
				$log = sprintf( __( 'NFe PDF download successful. Order: #%d', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );

				$this->logger( $log );
				$order->add_order_note( $log );

				return $pdf;
			}

			return false;
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
			if ( 'exclude_shipping' === $this->highlight_shipping_tax() ) {
				// subtract shipping from total.
				$services_amount = $order->get_total() - $order->get_shipping_total();
				// get invoice info.
				$invoice_info = $this->remover_caracter( $this->city_service_info( 'desc', $order_id ) );
				// build shipping info line.
				$shipping_info = __( 'Shipping', 'nota-fiscal-nfe-io-for-woocommerce' ) . ': ' . $order->get_shipping_method();
				// build shipping value line.
				$shipping_value_description = __( 'Shipping Value', 'nota-fiscal-nfe-io-for-woocommerce' ) . ': ' . $order->get_shipping_total() . $order->get_currency();
				// final description.
				$services_description = $this->remover_caracter( "{$invoice_info} \n $shipping_info \n $shipping_value_description" );
			} else {
				// if tax formation is include shipping, keep shipping in total.
				$services_amount = $order->get_total();
				// get invoice info.
				$services_description = $this->remover_caracter( $this->city_service_info( 'desc', $order_id ) );
			}

			$address = array(
				'postalCode'            => $this->check_customer_info( 'cep', $order_id ),
				'street'                => $this->remover_caracter( $this->check_customer_info( 'street', $order_id ) ),
				'number'                => $this->remover_caracter( $this->check_customer_info( 'address_number', $order_id ) ),
				'additionalInformation' => $this->remover_caracter( $order->get_billing_address_2() ),
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
				'email'            => $order->get_billing_email(),
				'federalTaxNumber' => $this->removepontotraco( $this->check_customer_info( 'number', $order_id ) ),
				'address'          => $address,
			);

			$rtc_info       = $this->rtc_fields_info( $order_id );
			$activity_event = $this->activity_event_info( $order_id );

			$data = array(
				'cityServiceCode'    => $this->city_service_info( 'code', $order_id ),
				'federalServiceCode' => $this->city_service_info( 'fed_code', $order_id ),
				'description'        => $services_description,
				'servicesAmount'     => $services_amount,
				'borrower'           => $borrower,
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
		 * @param string $value content.
		 *
		 * @return string
		 */
		public function clear( $value ) {
			return str_replace( array( ',', '-', '!', '.', '/', '?', '(', ')', ' ', '$', 'R$', '€' ), '', $value );
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
		 *
		 * @return string
		 */
		public function highlight_shipping_tax() {
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
			$order   = nfe_wc_get_order( $order_id );
			$country = $order ? $order->get_billing_country() : '';

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
			$order     = nfe_wc_get_order( $order_id );
			$post_code = $order ? $order->get_billing_postcode() : '';

			if ( empty( $post_code ) ) {
				if ( ! nfe_require_address() ) {
					return $this->get_company_info( 'code' );
				}

				return null;
			}

			/*
			 * Resolved through the SDK, but against the address host this plugin
			 * has always used.
			 *
			 * The SDK defaults to address.api.nfe.io/v2, which answers 403 to the
			 * invoice API key and wants a separate data key. open.nfe.io/v1
			 * accepts the invoice key, so pointing the call there keeps the store
			 * on the single credential it has always had -- while still going
			 * through the SDK, which sends the key in an Authorization header
			 * instead of the `?api_key=` query string the old code used, where it
			 * landed in every access log and proxy on the way.
			 *
			 * The v1 body is a bare address object, which the SDK's parser (built
			 * for the v2 `{address:{...}}` envelope) leaves out of ->addresses, so
			 * the value is read from ->raw. ->addresses is tried first so this
			 * keeps working if the shapes ever converge.
			 */
			try {
				$lookup = $this->client()->addresses->lookupByPostalCode(
					(string) $post_code,
					new \Nfe\Http\RequestOptions( baseUrl: self::ADDRESS_BASE_URL )
				);
			} catch ( \Nfe\Exception\ApiErrorException $e ) {
				// translators: 1: postal code, 2: error message returned by the API.
				$this->logger( sprintf( __( 'Could not resolve the IBGE city code for postal code %1$s: %2$s', 'nota-fiscal-nfe-io-for-woocommerce' ), $post_code, $e->getMessage() ) );

				return null;
			}

			$address = isset( $lookup->addresses[0] ) && is_array( $lookup->addresses[0] )
				? $lookup->addresses[0]
				: (array) $lookup->raw;

			if ( empty( $address['city']['code'] ) ) {
				return null;
			}

			return $address['city']['code'];
		}

		/**
		 * Get current company info.
		 *
		 * @param string $field field.
		 *
		 * @return null|string
		 */
		protected function get_company_info( $field ) {
			$address = $this->company_address();

			if ( 'city' === $field ) {
				return empty( $address['city']['name'] ) ? null : $address['city']['name'];
			}

			if ( 'code' === $field ) {
				return empty( $address['city']['code'] ) ? null : $address['city']['code'];
			}

			return empty( $address[ $field ] ) ? null : $address[ $field ];
		}

		/**
		 * The configured company's address, fetched once per request.
		 *
		 * Goes through the SDK's companies resource. The previous call built the
		 * URL by hand with `?api_key=`, exposing the credential in a query
		 * string, and indexed into the decoded body without guards, which raised
		 * notices whenever the company had no address on file.
		 *
		 * @since 1.5.0
		 *
		 * @return array Address array, empty when unavailable.
		 */
		protected function company_address() {
			if ( null === $this->company_info ) {
				try {
					$company = $this->client()->companies->retrieve( (string) $this->get_company() );

					$this->company_info = isset( $company->raw['address'] ) && is_array( $company->raw['address'] )
						? $company->raw['address']
						: array();
				} catch ( \Nfe\Exception\ApiErrorException $e ) {
					// translators: %s: error message returned by the API.
					$this->logger( sprintf( __( 'Could not fetch the company data from NFe.io: %s', 'nota-fiscal-nfe-io-for-woocommerce' ), $e->getMessage() ) );

					$this->company_info = false;
				}
			}

			return is_array( $this->company_info ) ? $this->company_info : array();
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
				static function ( $value ) {
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
						'address' => empty( $address ) ? null : $address,
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
				/* translators: %d: WooCommerce order number. */
				$errors[] = sprintf( __( 'RTC validation failed for order #%d: operationIndicator and classCode are required when RTC payload is used.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
			}

			if ( ! empty( $destination ) && ! in_array( $destination, array( 'SameAsBuyer', 'DifferentFromBuyer' ), true ) ) {
				/* translators: %d: WooCommerce order number. */
				$errors[] = sprintf( __( 'RTC validation failed for order #%d: destinationIndicator has an invalid value.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
			}

			if ( empty( $nbs_code ) ) {
				/* translators: %d: WooCommerce order number. */
				$missing_nbs_message = sprintf( __( 'RTC validation warning for order #%d: nbsCode is missing.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
				$observability_ctx   = array(
					'missing_fields' => array( 'nbsCode' ),
					'item_ids'       => $this->get_order_item_ids( $order_id ),
				);

				switch ( $profile ) {
					case 'estrito':
						/* translators: %d: WooCommerce order number. */
						$errors[]                      = sprintf( __( 'RTC validation failed for order #%d: nbsCode is required in Strict profile.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
						$observability_ctx['scenario'] = 'strict';
						$this->register_missing_nbs_observability( $order_id, $profile, true, $observability_ctx );
						break;

					case 'equilibrado':
						if ( $has_rtc_context && $this->missing_nbs_in_critical_scenario( $payload ) ) {
							/* translators: %d: WooCommerce order number. */
							$errors[]                      = sprintf( __( 'RTC validation failed for order #%d: nbsCode is required in this critical RTC scenario for Balanced profile.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
							$observability_ctx['scenario'] = 'balanced_critical';
							$this->register_missing_nbs_observability( $order_id, $profile, true, $observability_ctx );
						} else {
							$warnings[]                    = $missing_nbs_message;
							$observability_ctx['scenario'] = 'balanced_warning';
							$this->register_missing_nbs_observability( $order_id, $profile, false, $observability_ctx );
						}
						break;

					case 'compativel':
					default:
						$warnings[]                    = $missing_nbs_message;
						$observability_ctx['scenario'] = 'compatible_warning';
						$this->register_missing_nbs_observability( $order_id, $profile, false, $observability_ctx );
						break;
				}
			}

			if ( 'DifferentFromBuyer' === $destination && ! $has_recipient ) {
				/* translators: %d: WooCommerce order number. */
				$errors[] = sprintf( __( 'RTC validation failed for order #%d: recipient is required when destinationIndicator is DifferentFromBuyer.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
			}

			if ( 'DifferentFromBuyer' === $destination && $has_recipient && ( ! isset( $payload['recipient']['name'] ) || '' === trim( (string) $payload['recipient']['name'] ) ) ) {
				/* translators: %d: WooCommerce order number. */
				$errors[] = sprintf( __( 'RTC validation failed for order #%d: recipient.name is required when destinationIndicator is DifferentFromBuyer.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
			}

			if ( 'SameAsBuyer' === $destination && $has_recipient ) {
				/* translators: %d: WooCommerce order number. */
				$warnings[] = sprintf( __( 'RTC validation warning for order #%d: recipient was provided even though destinationIndicator is SameAsBuyer.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order_id );
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
			$order = nfe_wc_get_order( $order_id );

			if ( ! $order ) {
				return;
			}

			$last_event = nfe_get_order_meta( $order, '_nfe_rtc_missing_nbs_last_event' );

			$signature_source = array(
				'profile' => $profile,
				'blocked' => (bool) $blocked,
				'context' => $context,
			);

			$event_signature = md5( wp_json_encode( $signature_source ) );
			$missing_count   = absint( nfe_get_order_meta( $order, '_nfe_rtc_missing_nbs_count', 0 ) );

			if ( empty( $last_event['signature'] ) || $last_event['signature'] !== $event_signature ) {
				nfe_set_order_meta( $order, '_nfe_rtc_missing_nbs_count', $missing_count + 1, false );
			}

			nfe_set_order_meta(
				$order,
				'_nfe_rtc_missing_nbs_last_event',
				array(
					'profile'   => $profile,
					'blocked'   => (bool) $blocked,
					'context'   => $context,
					'signature' => $event_signature,
					'timestamp' => current_time( 'mysql' ),
				),
				false
			);

			// Single save() for every observability meta accumulated above.
			$order->save();
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
		protected function city_service_info( $field, $order_id ) {
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
		protected function check_customer_info( $field, $order ) {
			if ( empty( $field ) ) {
				return;
			}

			// Despite its name, $order holds an order ID: resolve it once and reuse it below.
			$wc_order = is_a( $order, 'WC_Order' ) ? $order : nfe_wc_get_order( $order );

			// Only check those fields.
			if ( in_array( $field, array( 'number', 'name', 'type' ), true ) ) {
				// Person Type.
				$type = nfe_get_order_meta( $wc_order, '_billing_persontype' );

				// Customer info.
				$cpf      = nfe_get_order_meta( $wc_order, '_billing_cpf' );
				$customer = ( $wc_order ? $wc_order->get_billing_first_name() : '' ) . ' ' . ( $wc_order ? $wc_order->get_billing_last_name() : '' );

				// Company info.
				$cnpj    = nfe_get_order_meta( $wc_order, '_billing_cnpj' );
				$company = $wc_order ? $wc_order->get_billing_company() : '';

				if ( ! empty( $type ) ) {
					if ( '1' === $type ) {
						$id   = $this->cpf( $cpf );
						$name = $customer;
						$type = __( 'Customers', 'nota-fiscal-nfe-io-for-woocommerce' );
					} else {
						$id   = $this->cnpj( $cnpj );
						$name = $company;
						$type = __( 'Company', 'nota-fiscal-nfe-io-for-woocommerce' );
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
					$output = $wc_order ? $wc_order->get_billing_city() : '';
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'city' );
					}

					break;

				case 'state':
					$output = $wc_order ? $wc_order->get_billing_state() : '';
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'state' );
					}

					break;

				case 'district':
					$output = nfe_get_order_meta( $wc_order, '_billing_neighborhood' );
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'district' );
					}

					break;

				case 'address_number':
					$output = nfe_get_order_meta( $wc_order, '_billing_number' );
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'number' );
					}

					break;

				case 'street':
					$output = $wc_order ? $wc_order->get_billing_address_1() : '';
					if ( ! empty( $output ) ) {
						$output = $output;
					} elseif ( false === nfe_require_address() ) {
						$output = $this->get_company_info( 'street' );
					}

					break;

				case 'cep':
					$output = $wc_order ? $wc_order->get_billing_postcode() : '';
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
		 * @param string $value content to remove.
		 *
		 * @return string
		 */
		protected function removepontotraco( $value ) {
			// Coerced because the callers pass straight through from order meta,
			// which is null whenever the field was never filled in. PHP 8.2 --
			// the floor this plugin targets -- deprecates passing null here, and
			// it happens on the payload-building path of every issuing attempt.
			return ltrim( preg_replace( '/[^0-9]/', '', (string) $value ), '0' );
		}

		/**
		 * Remove Caracter.
		 *
		 * @param string $value content to remove.
		 *
		 * @return string
		 */
		protected function remover_caracter( $value ) {
			// See removepontotraco(): an unfilled address field arrives as null,
			// which htmlentities() deprecates on PHP 8.2.
			$value = preg_replace( '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);~i', '$1', htmlentities( (string) $value, ENT_COMPAT, 'UTF-8' ) );

			return preg_replace( '/[][><}{)(:;,!?*%~^`´&#@ªº°$¨]/', '', $value );
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
	function NFe_Woo() { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Public accessor kept for backward compatibility.
		return NFe_Woo::instance();
	}
}
