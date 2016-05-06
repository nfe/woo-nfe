<?php

/**
 * WooCommerce NFe.io WC_NFe_Admin Class
 *
 * @author   Renato Alves
 * @category Admin
 * @package  NFe_WooCommerce/Classes/WC_NFe_Admin
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * WC_NFe_Admin
 */
class WC_NFe_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
        // Actions
		add_action( 'add_meta_boxes',         array( $this, 'add_meta_boxes' ), 25 );
		add_action( 'save_post',              array( $this, 'save'         ) );

        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'order_status_column_c' ) );
        add_action( 'woocommerce_order_actions', array( $this, 'order_meta_box_actions' ) );

        // Filters
        add_filter( 'woocommerce_admin_order_actions', array( $this, 'order_actions'), 10, 2 );
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'order_status_column_header' ), 20 );

        // add_action( 'woocommerce_order_action_wc_nfe_emitir', array( $this, 'process_order_meta_box_actions' ) );
        // add_action( 'admin_footer-edit.php', array( $this, 'order_bulk_actions' ) );
        // add_action( 'load-edit.php', array( 'WooCommerceNFe_Backend', 'process_order_bulk_actions' ) );
	}

	/**
	 * Add WC Meta box
	 */
	public function add_meta_boxes() {
		add_meta_box( 'woocommerce-nfe-data', 
			_x( 'NFe.io - Product Fiscal Activities', 'meta box title', 'woocommerce-nfe' ), 
			array( $this, 'output' ), 
			'product', 'normal', 'default' );
	}

	/**
	 * Meta box display callback.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function output( $post ) {
        $fiscal_activities = get_post_meta( $post->ID, 'nfe_woo_fiscal_activities', true );

	    // Add an nonce field so we can check for it later.
        wp_nonce_field( 'nfe_woocommerce_box_nonce', 'nfe_woocommerce_box_nonce' ); ?>
  
        <table id="nfe-woo-fieldset-one" width="100%">
            <thead>
                <tr>
                    <th width="50%"><?php esc_html_e( 'Activity Name', 'woocommerce-nfe' ); ?></th>
                    <th width="42%"><?php esc_html_e( 'Code', 'woocommerce-nfe' ); ?></th>
                    <th width="8%"></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $fiscal_activities ) :
                
                    foreach ( (array) $fiscal_activities as $activity ) { ?>
                    <tr>
                        <td>
                            <input type="text" class="widefat" name="name[]" value="<?php if( $activity['name'] !== '' ) echo esc_attr( $activity['name'] ); ?>" />
                        </td>
                    
                        <td>
                            <input type="text" class="widefat" name="code[]" value="<?php if ( $activity['code'] !== '') echo esc_attr( $activity['code'] ); ?>" />
                        </td>
                    
                        <td>
                            <a class="button remove-row" href="#"><?php esc_html_e( 'Remove', 'woocommerce-nfe' ); ?></a>
                        </td>
                    </tr>
                    <?php }
                
                else : // show a blank one ?>

                    <tr>
                        <td><input type="text" class="widefat" name="name[]" /></td>
                        <td><input type="text" class="widefat" name="code[]" value="" /></td>
                        <td><a class="button remove-row" href="#"><?php esc_html_e( 'Remove', 'woocommerce-nfe' ); ?></a></td>
                    </tr>

                <?php endif; ?>
                
                <!-- empty hidden one for jQuery -->
                <tr class="empty-row screen-reader-text">
                    <td><input type="text" class="widefat" name="name[]" /></td>
                    <td><input type="text" class="widefat" name="code[]" value="" /></td>
                    <td><a class="button remove-row" href="#"><?php esc_html_e( 'Remove', 'woocommerce-nfe' ); ?></a></td>
                </tr>
            </tbody>
        </table>
    
        <p>
            <a id="add-row" class="button" href="#">
                <?php esc_html_e( 'Add Another', 'woocommerce-nfe' ); ?>
            </a>
        </p>
        <?php
	}

    /**
     * Save the meta when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save( $post_id ) {
        if ( 'product' !== get_post_type() ) {
            return;
        }
    
        $nonce = $_POST['nfe_woocommerce_box_nonce'];

        // Check if our nonce is set and verify that the nonce is valid.
        if ( ! isset( $nonce ) || ! wp_verify_nonce( $nonce, 'nfe_woocommerce_box_nonce' ) ) {
            return $post_id;
        }
 
        /*
         * If this is an autosave, our form has not been submitted,
         * so we don't want to do anything.
         */
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
 
        // Check the user's permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }

        $old = get_post_meta( $post_id, 'nfe_woo_fiscal_activities', true );
        $new = array();

        $names = $_POST['name'];
        $codes = $_POST['code'];

        $count = count( $names );

        for ( $i = 0; $i < $count; $i++ ) {
            if ( $names[$i] !== '' ) :
                $new[$i]['name'] = sanitize_text_field( $names[$i] );
            endif;

             if ( $codes[$i] !== '' ) :
                $new[$i]['code'] = sanitize_text_field( sanitize_key( $codes[$i] ) );
            endif;
        }

        // Update meta fields
        if ( !empty( $new ) && $new !== $old ) {
            update_post_meta( $post_id, 'nfe_woo_fiscal_activities', $new );
        } elseif ( empty($new) && $old ) {
            delete_post_meta( $post_id, 'nfe_woo_fiscal_activities', $old );
        }
    }

    public function order_status_column_header( $columns ) {
        $new_columns = array();

        foreach ( $columns as $column_name => $column_info ) {

            $new_columns[ $column_name ] = $column_info;

            if ( 'order_status' == $column_name ) {
                $new_columns['sales-receipt'] = __( 'Sales Receipt', 'woocommerce-nfe' );
            }
        }

        return $new_columns;
    }

    public function order_status_column_c( $column ) {
        global $post;

        if ( 'sales-receipt' === $column ) {

            $nfe = get_post_meta( $post->ID, 'nfe', true );
            $order = new WC_Order( $post->ID );
            
            if ( $order->get_status() == 'pending' || $order->get_status() == 'cancelled' ) {
                echo '<span class="nfe_none">-</span>';

            } elseif ($nfe) {

                echo '<div class="nfe_success">' . __( 'NFe Issued', 'woocoomerce-nfe' ) . '</div>';

            } else { 
                echo '<div class="nfe_alert">' . __( 'NFe not issued', 'woocoomerce-nfe' ) . '</div>';
            } 
        }   
    }

    public function order_meta_box_actions( $actions ) {
        $actions['wc_nfe_emitir'] = __( 'Issue NFe', 'woocommerce-nfe' );

        return $actions;
    }

   /*  public function order_bulk_actions() {
        global $post_type, $post_status;

        if ( $post_type == 'shop_order' ) {

            if (get_option( 'sefaz' ) == 'offline') return false;
            if ($post_status == 'trash' || $post_status == 'wc-cancelled' || $post_status == 'wc-pending') return false;

            ?>
             <script type="text/javascript">
                jQuery( document ).ready( function ( $ ) {
                          var $emitir_nfe = $('<option>').val('wc_nfe_emitir').text('<?php _e( 'Emitir NF-e' )?>');
                          $( 'select[name^="action"]' ).append( $emitir_nfe );
                      });
            </script>
        }
    } */

    function process_order_bulk_actions(){
        
        global $typenow;

        if ( 'shop_order' == $typenow ) {

            $wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
            $action        = $wp_list_table->current_action();

            if ( ! in_array( $action, array( 'wc_nfe_emitir') ) ) return false;
            if ( isset( $_REQUEST['post'] ) ) $order_ids = array_map( 'absint', $_REQUEST['post'] );
            if ( empty( $order_ids ) ) return false;
            
            if ($action == 'wc_nfe_emitir') WC_NFe()->emitirNFe( $order_ids );
            
        }
        
    }
    
    function process_order_meta_box_actions( $post ){
        
        $order_id = $post->id;
        $post_status = $post->post_status;
        if ($post_status == 'trash' || $post_status == 'wc-cancelled') return false;
        
        parent::emitirNFe( array( $order_id ) );
        
    }

    /**
     * Adds the NFe actions on the Orders
     * 
     * @return array
     */
    public function order_actions( $actions, $order ) {
        if ( $order->has_status( 'completed' ) && strtotime( $order->post_date ) < strtotime('-1 year') ) {
            $actions['nfe-issue'] = array(
                'url'       => '', // todo
                'name'      => __( 'Issue NFe', 'woocommerce-nfe' ),
                'action'    => "issue"
            );

            $actions['nfe-download'] = array(
                'url'       => '', // todo
                'name'      => __( 'Download Issue', 'woocommerce-nfe' ),
                'action'    => "download"
            );
        }

        return $actions;
    }
}

new WC_NFe_Admin();
