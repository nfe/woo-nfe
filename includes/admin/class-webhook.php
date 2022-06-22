<?php
/**
 * Exit if accessed directly.
 */

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
	 *
	 * @throws Exception Exception.
	 *
	 * @return void
	 */
	public function handle() {
		$raw_body = file_get_contents( 'php://input' );
		$body     = json_decode( $raw_body );

		// translators: Fired when a new webhook is called.
		$this->logger( sprintf( __( 'New webhook called. %s', 'woo-nfe' ), $raw_body ) );

		try {
			$this->process_event( $body );
		} catch ( Exception $e ) {
			// translators: Error message from event.
			$this->logger( sprintf( __( 'Error: %s.', 'woo-nfe' ), $e->getMessage() ) );

			if ( $e->getCode() === 2 ) {
				http_response_code( 422 );
				die( esc_html( $e->getMessage() ) );
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
		$this->logger( __( 'Starting to proccess new webhook event.', 'woo-nfe' ) );

		if ( is_null( $body ) ) {
			$this->logger( __( 'Error while checking webhook JSON.', 'woo-nfe' ) );
			$this->logger( sprintf( __( 'Error: %s.', 'woo-nfe' ), $body ) );

			throw new Exception( __( 'Error while checking webhook JSON.', 'woo-nfe' ) );
		}

		$this->logger( __( 'Getting Order ID of the webhook.', 'woo-nfe' ) );
		$order = $this->get_order_by_nota_id( $body->id );

		$this->logger( __( 'Updating Order with NFe info.', 'woo-nfe' ) );

		$meta = update_post_meta(
			$order->get_id(),
			'nfe_issued',
			array(
				'id'        => $body->id,
				'status'    => $body->flowStatus,
				'issuedOn'  => $body->issuedOn,
				'amountNet' => $body->amountNet,
				'checkCode' => $body->checkCode,
				'number'    => $body->number,
			)
		);

		if ( ! $meta ) {
			$this->logger( sprintf( __( 'There was a problem while updating the Order #%d with the NFe information.', 'woo-nfe' ), $order->get_id() ) );
			return;
		}

		// translators: Order updated with its status.
		$msg = sprintf( __( 'Order updated. Order: #%1$d NFe status: %2$s.', 'woo-nfe' ), $order->get_id(), nfe_status_label( $body->flowStatus ) );
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
		$query = nfe_get_order_by_nota_value( $id );

		if ( ! $query->have_posts() || is_wp_error( $query ) ) {
			// translators: Order with receipt number.
			$this->logger( sprintf( __( 'Order with receipt number #%d not found.', 'woo-nfe' ), $id ) );

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
				self::$logger = wc_get_logger();
			}

			self::$logger->debug( $message, array( 'source' => 'nfe_webhook' ) );
		}
	}
}

return new WC_NFe_Webhook_Handler();
