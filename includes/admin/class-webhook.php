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
     * WC_Logger Logger instance
     * 
     * @var boolean
     */
    public static $logger = false;

    /**
     * Base Construct
     */
	public function __construct() {
        add_action('woocommerce_api_' . WC_API_CALLBACK, array( $this, 'handle' ) );
    }

	/**
     * Handling incoming webhooks
     * 
     * @return void
     */
    public function handle() {
        $raw_body  = file_get_contents('php://input');
        $body      = json_decode($raw_body);

        $this->logger( 'Novo Webhook chamado.' );

        try {
            $this->process_event( $body );
        } 
        catch (Exception $e) {
            $this->logger( $e->getMessage() );

            if ( 2 === $e->getCode() ) {
                http_response_code(422);
                die( $e->getMessage() );
            }
        }
	}

    /**
     * Read json entity received and proccess the webhook
     * 
     * @param string $body
     **/
    private function process_event( $body ) {
        if ( null == $body ) {
            throw new Exception( 'Falha ao interpretar JSON do webhook!' );
        }

        $this->logger('Novo Evento processado.');

        $order = $this->find_order_by_nota_id( $body->id );

        $nfe = array(
            'id'        => $body->id,
            'status'    => $body->flowStatus,
            'issuedOn'  => $body->issuedOn,
        );

        update_post_meta( $order->id, 'nfe_issued', $nfe );

        $this->logger('Pedido atualizado com sucesso. Pedido: #' . $order->id . ' Status da Nota: ' . $body->flowStatus );
    }
    
    /**
     * Find orders by NFe ID
     * 
     * @param  Nota ID $id 
     * @return WC_Order
     */
    private function find_order_by_nota_id( $id ) {
        $args = array(
            'post_type'   => 'shop_order',
            'meta_query' => array(
                array(
                    'key'     => 'nfe_issued',
                    'value'   => $id,
                    'compare' => 'LIKE',
                ),
            ),
            'post_status' => 'wc-completed',
        );
        $query = new WP_Query($args);
        
        if ( false === $query->have_posts() ) {
            throw new Exception( 'Pedido com id de nota fiscal #' . $id . ' não encontrado!', 2 );
        }
        return nfe_wc_get_order( $query->post->ID );
    }

    /**
     * Logging method.
     *
     * @param string $message
     */
    public static function logger( $message ) {
        if ( nfe_get_field('debug') == 'yes' ) {
            if ( empty( self::$logger ) ) {
                self::$logger = new WC_Logger();
            }
            self::$logger->add( 'nfe_webhook', $message );
        }
    }
}

return new WC_NFe_Webhook_Handler();

endif;

// That's it! =)
