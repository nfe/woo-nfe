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
     * Logging method.
     *
     * @param string $message
     */
    public static function logger( $message ) {
        if ( nfe_get_field('debug') == 'yes' ) {
            if ( ! class_exists( 'WC_Logger' ) ) {
                include_once( 'class-wc-logger.php' );
            }

            if ( empty( self::$logger ) ) {
                self::$logger = new WC_Logger();
            }
            self::$logger->add( 'nfe_webhook', $message );
        }
    }

	/**
	 * Handle incoming webhook.
	 */
	public function handle() {
        $raw_body = file_get_contents('php://input');
        $body     = json_decode($raw_body);

        $this->logger( 'Novo Webhook chamado: ' . $raw_body );

        try {
            $this->process_event($body);
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
    private function process_event($body) {
        if ( null == $body || empty($body) ) {
            throw new Exception( 'Falha ao interpretar JSON do webhook: Evento do Webhook não encontrado!' );
        }

		$type = $body->type;
		$data = $body;

        if ( method_exists( $this, $type ) ) {
            $this->logger('Novo Evento processado: ' . $type);
            
            return $this->{$type}($data);
        }

        $this->logger('Evento do webhook ignorado pelo plugin: ' . $type);
    }

    /**
     * Cancell Web Hook
     * 
     * @param  $data array
     */
    private function cancel( $data ) {
        $nfe = get_post_meta( $order_id, 'nfe_issued', true );

        if ( empty( $nfe['id'] ) ) {
            return;
        }

        $nfe['status'] = 'Cancelled';

        update_post_meta( $order_id, 'nfe_issued', $nfe );
    }
}

endif;

return new WC_NFe_Webhook_Handler();

// That's it! =)
