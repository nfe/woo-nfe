<?php

/**
 * WooCommerce NFe.io WC_NFe_Admin Class
 *
 * @author   Renato Alves
 * @category Admin
 * @package  WooCommerce_NFe/Classes/WC_NFe_Admin
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
        // Filters
        add_filter( 'manage_edit-shop_order_columns',        array( $this, 'nfe_order_status_column_header' ), 20 );

        // Actions
		add_action( 'add_meta_boxes',                        array( $this, 'add_meta_boxes' ), 25 );
		add_action( 'save_post',                             array( $this, 'save' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'nfe_order_status_column_content' ) );
        add_action( 'woocommerce_order_actions',             array( $this, 'order_meta_box_actions' ) );
        add_action( 'woocommerce_order_action_wc_nfe_issue', array( $this, 'process_order_meta_box_actions' ) );
        add_action( 'admin_footer-edit.php',                 array( $this, 'order_bulk_actions' ) );
        add_action( 'load-edit.php',                         array( $this, 'process_order_bulk_actions' ) );
        add_action( 'admin_enqueue_scripts',                 array( $this, 'enqueue_scripts' ) );
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
            if ( $names[$i] !== '' ) {
                $new[$i]['name'] = sanitize_text_field( $names[$i] );
            }

            if ( $codes[$i] !== '' ) {
                $new[$i]['code'] = sanitize_text_field( $codes[$i] );
            }
        }

        // Update meta fields
        if ( !empty( $new ) && $new !== $old ) {
            update_post_meta( $post_id, 'nfe_woo_fiscal_activities', $new );
        } elseif ( empty($new) && $old ) {
            delete_post_meta( $post_id, 'nfe_woo_fiscal_activities', $old );
        }
    }

    /**
     * NFe Column Header on Order Status
     * 
     * @return array
     */
    public function nfe_order_status_column_header( $columns ) {
        $new_columns = array();

        foreach ( $columns as $column_name => $column_info ) {
            $new_columns[ $column_name ] = $column_info;

            if ( 'order_actions' === $column_name ) {
                $new_columns['sales-receipt'] = __( 'Sales Receipt', 'woocommerce-nfe' );
            }
        }

        return $new_columns;
    }

    /**
     * NFe Column Content on Order Status
     * 
     * @return string
     */
    public function nfe_order_status_column_content( $column ) {
        global $post;

        if ( 'sales-receipt' === $column ) {
            $nfe = get_post_meta( $post->ID, 'nfe_issued', true );
            $order = new WC_Order( $post->ID );

            if ( $order->has_status('completed') ) {
                if ( nfe_get_field( 'issue_past_notes' ) === 'no' && strtotime( $order->post->post_date ) < strtotime('-1 year') ) {
                    echo '<div class="nfe_woo">' . __( 'NFe Issue Time Expired', 'woocommerce-nfe' ) . '</div>';
                }

                if ( nfe_get_field('nfe_enable') === 'yes' && $nfe == false ) {
                    echo '<a href="#" class="button view">' . __( 'Issue NFe', 'woocommerce-nfe' ) . '</a>';
                } 
            }

            if ( current_user_can('manage_woocommerce') && nfe_get_field('nfe_enable') === 'no' ) {
                echo '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=integration' ) . '" class="button view">' . __( 'Enable NFe', 'woocommerce-nfe' ) . '</a>';
            } 

            if ( $nfe == true ) {
                echo '<a href="#" class="button view">' . __( 'Download NFe', 'woocommerce-nfe' ) . '</a>';
            }
        }
    }

    /**
     * Adds the Meta Box on the Order Page
     * 
     * @return array
     */
    public function order_meta_box_actions( $actions ) {
        $actions['wc_nfe_issue'] = __( 'Issue NFe', 'woocommerce-nfe' );

        return $actions;
    }

    /**
     * Order Bulk Actions JavaScript
     * 
     * @return string
     */
    public function order_bulk_actions() {
        global $post_type, $post_status;

        if ( $post_type === 'shop_order' ) {

            // Bail if NFe is disabled
            if ( nfe_get_field('nfe_enable') === 'no' ) {
                return false;
            }
            
            // Bail if post status is true for the following ones
            if ( $post_status == 'trash' || $post_status == 'cancelled' || $post_status == 'pending') {
                return false;
            } ?>
             <script type="text/javascript">
                if ( typeof jq == 'undefined' ) { var jq = jQuery; }

                jq( function() {
                    var IssueNFe = jq('<option>').val('wc_nfe_issue').text('<?php _e( 'Issue NFe', 'woocommerce-nfe' ); ?>');
                    jq('select[name^="action"]' ).append( IssueNFe );
                });
            </script>
            <?php
        }
    }

    /**
     * Issue the NFe on bulk action
     * 
     * @return bool True|False
     */
    public function process_order_bulk_actions() {
        global $typenow;

        if ( 'shop_order' === $typenow ) {
            $wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
            $action        = $wp_list_table->current_action();

            if ( ! in_array( $action, array( 'wc_nfe_issue') ) ) {
                return false;
            }

            if ( isset( $_REQUEST['post'] ) ) {
                $order_ids = array_map( 'absint', $_REQUEST['post'] );
            }

            if ( empty( $order_ids ) ) {
                return false;
            }
            
            if ( $action === 'wc_nfe_issue') {
                // issueNFe( $order_ids );
            }
        }
    }
    
    /**
     * Issue the NFe
     * 
     * @param  object $post Current post
     * @return bool True|False
     */
    function process_order_meta_box_actions( $post ) {
        $order_id       = $post->id;
        $post_status    = $post->post_status;

        if ( $post_status === 'trash' || $post_status === 'cancelled') {
            return false;
        }
        
        // issueNFe( array( $order_id ) );
    }

     /**
     * Adds the admin script
     *
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        // Get admin screen id
        $screen         = get_current_screen();
        $is_woo_screen  = ( in_array( $screen->id, array( 'product' ) ) ) ? true : false;

        $suffix         = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        if ( $is_woo_screen ) {
            wp_enqueue_script( 'nfe-woo-metabox', 
                plugins_url( 'woocommerce-nfe/assets/js/admin' ) . $suffix . '.js',
                array( 'jquery' ),
                WooCommerce_NFe::VERSION, true
            );
        }

        wp_register_style( 'nfe-woo-admin', 
            plugins_url( 'woocommerce-nfe/assets/css/nfe-admin' ) . $suffix . '.css', 
            false, WooCommerce_NFe::VERSION 
        );

        wp_enqueue_style( 'nfe-woo-admin' );
    }
}

$run = new WC_NFe_Admin();
