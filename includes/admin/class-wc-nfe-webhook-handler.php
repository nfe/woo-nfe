<?php
/**
 * WooCommerce NFe WC_NFe_Webhook_Handler Class.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Webhook_Handler
 * @version  1.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Receives NFe.io invoice events.
 *
 * The webhook is the single source of truth for what happens to an invoice
 * after it is sent: the issuing call gets an id, everything else arrives here.
 *
 * Three things about the delivery contract shape this class:
 *
 * 1. The body is signed. HMAC-SHA1 over the raw bytes, hex, in X-Hub-Signature.
 *    The bytes have to be verified exactly as received -- decoding and
 *    re-encoding JSON changes them and the signature stops matching.
 * 2. The event type is in the X-Hook-Event header, not the body. NFS-e
 *    deliveries carry no `action` key, which is why the SDK's constructEvent()
 *    is not used here: it derives the type from a field that never arrives.
 * 3. Delivery is at-least-once. The same event can arrive more than once and a
 *    non-2xx answer causes a redelivery, so events are deduplicated by
 *    X-Hook-Id before anything with a side effect happens.
 */
class WC_NFe_Webhook_Handler {
	/**
	 * WC_Logger Logger instance.
	 *
	 * @var bool
	 */
	public static $logger = false;

	/**
	 * Base Construct.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_' . WC_API_CALLBACK, array( $this, 'handle' ) );
	}

	/**
	 * Handling incoming webhooks.
	 *
	 * @return void
	 */
	public function handle() {
		// Read the body before anything else touches it.
		$raw_body = file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- The signed request body; WP HTTP functions cannot read the input stream.

		// NFe.io posts here while creating the webhook and needs a 2xx, so this
		// window answers politely and does nothing. It lasts minutes.
		if ( WC_NFe_Webhook_Provisioner::is_provisioning() ) {
			$this->respond( 200, 'Provisioning.' );
		}

		$secret = WC_NFe_Webhook_Provisioner::secret();

		if ( '' === $secret ) {
			// Nothing can be trusted without a secret, so nothing is applied.
			// The admin notice raised by the provisioner is what gets this
			// fixed; answering 503 keeps NFe.io retrying meanwhile.
			$this->logger( 'Refused a webhook delivery: no signing secret is configured yet.' );

			$this->respond( 503, 'Webhook not provisioned.' );
		}

		$signature = isset( $_SERVER['HTTP_X_HUB_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HUB_SIGNATURE'] ) ) : '';

		if ( ! \Nfe\Webhook::verifySignature( $raw_body, $signature, $secret ) ) {
			$this->logger( 'Refused a webhook delivery: signature did not verify.' );

			$this->respond( 401, 'Invalid signature.' );
		}

		$event_id   = isset( $_SERVER['HTTP_X_HOOK_ID'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HOOK_ID'] ) ) : '';
		$event_type = isset( $_SERVER['HTTP_X_HOOK_EVENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HOOK_EVENT'] ) ) : '';

		// At-least-once delivery: a repeat of an event already applied is a
		// success, not work to redo.
		if ( '' !== $event_id && ! $this->claim_event( $event_id ) ) {
			$this->logger( sprintf( 'Ignored a repeated delivery of event %s.', $event_id ) );

			$this->respond( 200, 'Already processed.' );
		}

		$body = json_decode( $raw_body, true );

		if ( ! is_array( $body ) ) {
			$this->logger( 'Refused a webhook delivery: body was not valid JSON.' );

			$this->respond( 400, 'Malformed JSON.' );
		}

		// NFS-e deliveries wrap the document in a `payload` envelope; the
		// tolerant read also accepts a bare document.
		$document = isset( $body['payload'] ) && is_array( $body['payload'] ) ? $body['payload'] : $body;

		$this->logger(
			sprintf(
				'Webhook received. event=%1$s id=%2$s invoice=%3$s',
				'' === $event_type ? '(none)' : $event_type,
				'' === $event_id ? '(none)' : $event_id,
				isset( $document['id'] ) && is_scalar( $document['id'] ) ? (string) $document['id'] : '(none)'
			)
		);

		$this->process_event( $document, $event_type );

		$this->respond( 200, 'OK.' );
	}

	/**
	 * Claims an event id, returning false when it was already handled.
	 *
	 * @since 1.5.0
	 *
	 * @param string $event_id value of the X-Hook-Id header.
	 *
	 * @return bool True when this is the first time the event is seen.
	 */
	protected function claim_event( $event_id ) {
		$key = 'nfe_hook_' . md5( $event_id );

		if ( false !== get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, 1, WEEK_IN_SECONDS );

		return true;
	}

	/**
	 * Sends a response and ends the request.
	 *
	 * @since 1.5.0
	 *
	 * @param int    $status  HTTP status code.
	 * @param string $message short, non-sensitive message.
	 *
	 * @return void
	 */
	protected function respond( $status, $message ) {
		status_header( $status );
		header( 'Content-Type: text/plain; charset=utf-8' );

		echo esc_html( $message );

		exit;
	}

	/**
	 * Applies an event to the order it belongs to.
	 *
	 * @param array  $document   invoice document from the event.
	 * @param string $event_type value of the X-Hook-Event header.
	 *
	 * @return void
	 */
	protected function process_event( $document, $event_type ) {
		$order = $this->resolve_order( $document );

		if ( ! $order ) {
			// An event for an order this store does not have is not an error to
			// retry: redelivering it would produce the same result forever.
			return;
		}

		if ( ! $this->environment_matches( $document ) ) {
			$this->logger(
				sprintf(
					'Refused to record an invoice issued in the "%s" environment on order #%d.',
					isset( $document['environment'] ) && is_scalar( $document['environment'] ) ? (string) $document['environment'] : '(unknown)',
					$order->get_id()
				)
			);

			return;
		}

		$flow_status = isset( $document['flowStatus'] ) && is_scalar( $document['flowStatus'] ) ? sanitize_text_field( (string) $document['flowStatus'] ) : '';

		$current = nfe_get_order_meta( $order, 'nfe_issued' );
		$current = is_array( $current ) ? $current : array();

		/*
		 * Only fields the event actually carries are written.
		 *
		 * Later events are not full snapshots: a cancellation or a failure
		 * arrives with the status and little else. Writing a default for every
		 * absent key would merge blanks over the issue date, net amount, check
		 * code and number of an invoice that really was issued -- erasing the
		 * record of a fiscal document because a *subsequent* event said less
		 * about it than the first one did.
		 */
		$update = array( 'status' => $flow_status );

		if ( isset( $document['id'] ) && is_scalar( $document['id'] ) && '' !== (string) $document['id'] ) {
			$update['id'] = sanitize_text_field( (string) $document['id'] );
		}

		if ( isset( $document['issuedOn'] ) && is_scalar( $document['issuedOn'] ) && '' !== (string) $document['issuedOn'] ) {
			$update['issuedOn'] = sanitize_text_field( (string) $document['issuedOn'] );
		}

		if ( isset( $document['amountNet'] ) && is_numeric( $document['amountNet'] ) ) {
			$update['amountNet'] = (float) $document['amountNet'];
		}

		if ( isset( $document['checkCode'] ) && is_scalar( $document['checkCode'] ) && '' !== (string) $document['checkCode'] ) {
			$update['checkCode'] = sanitize_text_field( (string) $document['checkCode'] );
		}

		// A failed issuing reports number 0, which is not an invoice number and
		// must never replace a real one.
		if ( isset( $document['number'] ) && is_numeric( $document['number'] ) && (int) $document['number'] > 0 ) {
			$update['number'] = (int) $document['number'];
		}

		$order->update_meta_data( 'nfe_issued', array_merge( $current, $update ) );

		// Flat meta used to look the order up by invoice ID on both storages. A
		// non-scalar or empty ID would poison the equality lookup for this order,
		// so it is only written when it is usable.
		if ( ! empty( $document['id'] ) && is_scalar( $document['id'] ) ) {
			$order->update_meta_data( '_nfe_invoice_id', (string) $document['id'] );
		}

		// A single save persists every meta value. WC_Abstract_Order::save()
		// catches its own exceptions, reports them through the WooCommerce
		// logger and always returns the order ID, so a persistence failure can
		// be neither caught nor read from the return value here - look for it in
		// the WooCommerce logs, not in this plugin's.
		$order->save();

		// Records the event that was received. It deliberately does not claim the
		// write succeeded: save() reports its own failures to the WooCommerce
		// log and gives the caller no way to tell them apart from a success.
		// translators: 1: Order ID, 2: NFe status received.
		$msg = sprintf( __( 'NFe status received for order #%1$d: %2$s.', 'nota-fiscal-nfe-io-for-woocommerce' ), $order->get_id(), nfe_status_label( $flow_status ) );

		// A one-off rejection by the city hall and an exhausted retry budget
		// share the same flowStatus, so the event type is the only thing that
		// tells them apart. It goes in the note because the difference decides
		// whether re-issuing is worth trying.
		if ( '' !== $event_type ) {
			$msg .= ' ' . sprintf(
				/* translators: %s: webhook event type reported by NFe.io. */
				__( '(event: %s)', 'nota-fiscal-nfe-io-for-woocommerce' ),
				$event_type
			);
		}

		$this->logger( $msg );
		$order->add_order_note( $msg );

		if ( 'Issued' === $flow_status ) {
			/**
			 * Fires when an invoice is confirmed issued by NFe.io.
			 *
			 * This is the only point at which the invoice is known to exist, so
			 * it is what the customer e-mail hangs off. It used to be sent from
			 * the order-status transitions that *start* the issuing, announcing
			 * a document that had not been issued yet and might never be.
			 *
			 * @since 1.5.0
			 *
			 * @param int $order_id Order the invoice belongs to.
			 */
			do_action( 'woo_nfe_receipt_issued', $order->get_id() );
		}
	}

	/**
	 * Whether the event's environment matches the configured company's.
	 *
	 * Guards against recording a test document as a fiscal one. When the
	 * expected environment is not known -- an install that has not provisioned
	 * since the option was introduced -- the event is accepted, because
	 * refusing everything would be a worse failure than not checking.
	 *
	 * @since 1.5.0
	 *
	 * @param array $document invoice document from the event.
	 *
	 * @return bool
	 */
	protected function environment_matches( $document ) {
		$expected = (string) get_option( 'nfe_company_environment', '' );
		$actual   = isset( $document['environment'] ) && is_scalar( $document['environment'] ) ? (string) $document['environment'] : '';

		if ( '' === $expected || '' === $actual ) {
			return true;
		}

		return strtolower( $expected ) === strtolower( $actual );
	}

	/**
	 * Resolves the order an event refers to.
	 *
	 * Tries the external ID first: the plugin sends 'WOO-NFE-{order_id}' (or
	 * '-{n}' for later attempts) when issuing, NFe.io echoes it back, and
	 * parsing it costs no query at all and behaves the same under both
	 * storages. Falls back to the invoice ID lookup when the event carries no
	 * usable external ID, or when it does not check out against the order it
	 * points at.
	 *
	 * @since 1.5.0
	 *
	 * @param array $document invoice document from the event.
	 *
	 * @return WC_Order|false
	 */
	protected function resolve_order( $document ) {
		$invoice_id  = isset( $document['id'] ) ? $document['id'] : '';
		$external_id = isset( $document['externalId'] ) ? $document['externalId'] : '';

		$order = nfe_find_order_by_external_id( $external_id, $invoice_id );

		if ( $order ) {
			return $order;
		}

		return $this->get_order_by_nota_id( $invoice_id );
	}

	/**
	 * Find the order that holds a given NFe.io invoice.
	 *
	 * The lookup goes through nfe_find_order_by_invoice_id(), which queries the
	 * flat '_nfe_invoice_id' meta by equality and returns the same order under
	 * HPOS and on the legacy post storage.
	 *
	 * @since 1.5.0 Reports a miss instead of throwing.
	 *
	 * @param string $id NFe.io receipt ID.
	 *
	 * @return WC_Order|false
	 */
	protected function get_order_by_nota_id( $id ) {
		$invoice_id = is_scalar( $id ) ? trim( (string) $id ) : '';
		$order      = nfe_find_order_by_invoice_id( $invoice_id );

		if ( ! $order ) {
			// translators: %s: NFe.io receipt ID.
			$this->logger( sprintf( __( 'Order with receipt number #%s not found.', 'nota-fiscal-nfe-io-for-woocommerce' ), $invoice_id ) );

			return false;
		}

		return $order;
	}

	/**
	 * Logging method.
	 *
	 * Records metadata only. The event body carries the buyer's name, tax ID and
	 * address, and a debug log is a file on disk that outlives the request.
	 *
	 * @param string $message Log message.
	 *
	 * @return void
	 */
	public static function logger( $message ) {
		$debug = nfe_get_field( 'debug' );

		if ( empty( $debug ) || 'yes' !== $debug ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->debug( $message, array( 'source' => 'nfe_webhook' ) );
	}
}

return new WC_NFe_Webhook_Handler();
