<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce NFe WC_NFe_Webhook_Handler Class.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Webhook_Handler
 * @version  1.0.4
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
	 */
	public function handle() {
		$raw_body = file_get_contents( 'php://input' );
		$body = json_decode( $raw_body );

		// translators: Fired when a new webhook is called.
		$this->logger( sprintf( __( 'New webhook called. %s', 'woo-nfe' ), $raw_body ) );

		try {
			$this->process_event( $body );
		} catch ( Exception $e ) {
			// translators: Error message from event.
			$this->logger( sprintf( __( 'Error: %s.', 'woo-nfe' ), $e->getMessage() ) );

			if ( 2 === $e->getCode() ) {
				http_response_code( 422 );
				die( $e->getMessage() );
			}
		}
	}

	/**
	 * Read json entity received and proccess the webhook.
	 *
	 * @throws Exception Throws an exception.
	 * @param  string $body Event body.
	 *
	 * @return void
	 */
	protected function process_event( $body ) {
		if ( is_null( $body ) ) {
			throw new Exception( sprintf( __( 'Error while checking webhook JSON.', 'woo-nfe' ) ) );
		}

		$this->logger( sprintf( __( 'New event procced.', 'woo-nfe' ) ) );

		$order = $this->get_order_by_nota_id( $body->id );

		$nfe = array(
			'id'        => $body->id,
			'status'    => $body->flowStatus,
			'issuedOn'  => $body->issuedOn,
			'amountNet' => $body->amountNet,
			'checkCode' => $body->checkCode,
			'number'    => $body->number,
		);

		update_post_meta( $order->get_id(), 'nfe_issued', $nfe );

		// translators: Order updated with its status.
		$msg = sprintf( __( 'Order updated. Order: #%d NFe status: %s .', 'woo-nfe' ), $order->get_id(), $body->flowStatus );
		$this->logger( $msg );
		$order->add_order_note( $msg );
	}

	/**
	 * Find orders by NFe.io ID.
	 *
	 * @throws Exception Throws an exception.
	 * @param string $id NFE.io Receipt ID.
	 *
	 * @return WC_Order Order ID.
	 */
	protected function get_order_by_nota_id( $id ) {
		$issue_status = nfe_get_field( 'issue_when_status' );

		$query_args = array(
			'post_type' => 'shop_order',
			'post_status' => "wc-{$issue_status}",
			'meta_query' => array( // WPCS: slow query ok.
				array(
					'key' => 'nfe_issued',
					'value' => $id,
					'compare' => 'LIKE',
				),
			),
		);

		$query = new WP_Query( $query_args );

		if ( false === $query->have_posts() ) {
			// translators: Order with receipt number.
			throw new Exception( sprintf( __( 'Order with receipt number #%d not found.', 'woo-nfe' ), $id ), 2 );
		}

		return nfe_wc_get_order( $query->post->ID );
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 *
	 * @return void
	 */
	public static function logger( $message ) {
		$debug = nfe_get_field( 'debug' );

		if ( empty( $debug ) ) {
			return;
		}

		if ( 'yes' === $debug ) {
			if ( empty( self::$logger ) ) {
				self::$logger = new WC_Logger();
			}

			self::$logger->add( 'nfe_webhook', $message );
		}
	}
}

return new WC_NFe_Webhook_Handler();
