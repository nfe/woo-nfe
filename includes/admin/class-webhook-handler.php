<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_NFe_Webhook_Handler' ) ) :

/**
 * WooCommerce NFe WC_NFe_Webhook_Handler Class
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Webhook_Handler
 * @version  1.0.0
 */
class WC_NFe_Webhook_Handler {

    /**
    * @var string
    */
    const WC_API_CALLBACK = 'nfe_webhook';

    /**
    * @var WC_NFe_Webhook_Handler
    */
    private $webhook_handler = null;

    /**
     * Base Construct
     */
	public function __construct() {
        add_action('woocommerce_api_' . self::WC_API_CALLBACK, array( $this->webhook_handler, 'handle' ) );

        // Debug.
        if ( nfe_get_field('debug') == 'yes' ) {
            $this->logger = new WC_Logger();
        }
    }

	/**
	 * Handle incoming webhook.
	 */
	public function handle() {
        // $token    = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
        $raw_body = file_get_contents('php://input');
        $body     = json_decode($raw_body);

        // if ( ! $this->validate_access_token($token) ) {
           //  die('invalid access token');
        // }

        $this->logger->log( 'Novo Webhook chamado: ' . $raw_body );

        try {
            $this->process_event($body);
        } 
        catch (Exception $e) {
            $this->logger->log( $e->getMessage() );

            if ( 2 === $e->getCode() ) {
                http_response_code(422);
                die( $e->getMessage() );
            }
        }
	}

    /**
     * Read json entity received and proccess the right event
     * @param string $body
     **/
    private function process_event($body) {
        if(null == $body || empty($body->event))
            throw new Exception('Falha ao interpretar JSON do webhook: Evento do Webhook não encontrado!');

		$type = $body->event->type;
		$data = $body->event->data;

        if( method_exists($this, $type) ) {
            $this->logger->log('Novo Evento processado: ' . $type);
            return $this->{$type}($data);
        }

        $this->logger->log('Evento do webhook ignorado pelo plugin: ' . $type);
    }

    /**
     * Process test event from webhook
     * @param $data array
     */
    private function test($data) {
        $this->logger->log('Evento de teste do webhook.');
    }
}

endif;

$run = new WC_NFe_Webhook_Handler;

// That's it! =)

    /**
     * Cancell Web Hook
     * 
     * @param  int $order_id Order ID
     * @return bool true|false
     */
    public function cancel_nfe( $order_id ) {
        $nfe = get_post_meta( $order_id, 'nfe_issued', true );

        if ( empty( $nfe['id'] ) ) {
            return;
        }

        $nfe['status'] = 'Cancelled';

        update_post_meta( $order_id, 'nfe_issued', $nfe );
    }