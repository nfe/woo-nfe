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
        $nfe_event = wp_remote_retrieve_header( $body, 'X-Nfeio-Event' );

        $this->logger( 'Novo Webhook ('. $nfe_event .') chamado: ' . $raw_body );

        try {
            $this->process_event( $body, $nfe_event );
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
     * Read json entity received and proccess the right event
     * 
     * @param string $body
     **/
    private function process_event( $body, $event ) {
        if ( null == $body || empty($event) ) {
            throw new Exception( 'Falha ao interpretar JSON do webhook: Evento do Webhook não encontrado!' );
        }

        if ( method_exists( $this, $event ) ) {
            $this->logger('Novo Evento processado: ' . $event);
            
            return $this->{$event}($body);
        }

        $this->logger('Evento do webhook ignorado pelo plugin: ' . $event);
    }

    /**
     * Cancel Webhook
     * 
     * @param  $data array
     */
    private function cancel( $data ) {
        $status = $data->flowStatus;
        $nfe_id = $data->id;

        if ( $status == 'Cancelled' ) {
            $order = $this->find_order_by_nota_id( $nfe_id );

            $nfe = get_post_meta( $order->id, 'nfe_issued', true );

            $nfe['status'] = 'Cancelled';

            update_post_meta( $order->id, 'nfe_issued', $nfe );
        }
    }

    // private function issue( $data ) {}

    /**
     * Finds order by id
     * 
     * @param  int $order_id 
     * @return WC_Order
     */
    private function find_order_by_id( $order_id ) {
        $order = nfe_wc_get_order( $order_id );

        if ( empty($order) ) {
            throw new Exception( 'Pedido #' . $order_id . ' não encontrado!', 2 );
        }
        return $order;
    }
    
    /**
     * Find orders with NFe ID meta
     * 
     * @param  Nota ID $id 
     * @return WC_Order
     */
    private function find_order_by_nota_id( $id ) {
        $args = array(
            'post_type'   => 'shop_order',
            'meta_key'    => 'nfe_issued',
            'meta_value'  => $id,
            'post_status' => 'any',
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
