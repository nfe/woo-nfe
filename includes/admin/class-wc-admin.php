<?php

/**
 * WooCommerce NFe.io WC_NFe_Admin Class
 *
 * @author   Renato Alves
 * @category Admin
 * @package  WooCommerce_NFe/Class/WC_NFe_Admin
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
        add_filter( 'manage_edit-shop_order_columns',        array( $this, 'order_status_column_header' ), 20 );

        // Actions
        add_action( 'add_meta_boxes',                        array( $this, 'add_meta_boxes' ), 25 );
        add_action( 'save_post',                             array( $this, 'save' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'order_status_column_content' ) );
        add_action( 'woocommerce_order_actions',             array( $this, 'nfe_order_actions' ), 10, 1 );
        add_action( 'woocommerce_order_action_wc_nfe_issue', array( $this, 'process_nfe_order_actions' ), 10, 1 );
        add_action( 'admin_footer-edit.php',                 array( $this, 'order_bulk_actions' ) );
        add_action( 'load-edit.php',                         array( $this, 'process_order_bulk_actions' ) );
        add_action( 'admin_enqueue_scripts',                 array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add WC Meta box
	 */
	public function add_meta_boxes() {
		add_meta_box( 'woocommerce-nfe-data', 
			_x( 'NFe.io - Product Fiscal Activity', 'meta box title', 'woocommerce-nfe' ), 
			array( $this, 'output' ), 
			'product', 'normal', 'default' );
	}

	/**
	 * Meta box display callback.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function output( $post ) {
        $fiscal_activities = get_post_meta( $post->ID, 'nfe_woo_fiscal_activity', true );

	    // Add an nonce field so we can check for it later.
        wp_nonce_field( 'nfe_woocommerce_box_nonce', 'nfe_woocommerce_box_nonce' ); ?>
  
        <table id="nfe-woo-fieldset-one" width="100%">
            <thead>
                <tr>
                    <th width="50%"><?php esc_html_e( 'CityServiceCode', 'woocommerce-nfe' ); ?></th>
                    <th width="50%"><?php esc_html_e( 'Description', 'woocommerce-nfe' ); ?></th>
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
                    </tr>
                    <?php }
                
                else : // show a blank one ?>

                    <tr>
                        <td><input type="text" class="widefat" name="name[]" /></td>
                        <td><input type="text" class="widefat" name="code[]" value="" /></td>
                    </tr>

                <?php endif; ?>
            </tbody>
        </table>
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

        $old = get_post_meta( $post_id, 'nfe_woo_fiscal_activity', true );
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
            update_post_meta( $post_id, 'nfe_woo_fiscal_activity', $new );
        } elseif ( empty($new) && $old ) {
            delete_post_meta( $post_id, 'nfe_woo_fiscal_activity', $old );
        }
    }

    /**
     * NFe Column Header on Order Status
     * 
     * @param  array $columns Array of Columns
     * @return array          NFe Custom Column
     */
    public function order_status_column_header( $columns ) {
        $new_columns = array();

        foreach ( $columns as $column_name => $column_info ) {
            $new_columns[ $column_name ] = $column_info;

            if ( 'order_actions' === $column_name ) {
                $new_columns['sales_receipt'] = __( 'Sales Receipt', 'woocommerce-nfe' );
            }
        }

        return $new_columns;
    }

    /**
     * Column Content on Order Status
     *
     * @return string
     */
    public function order_status_column_content( $column ) {
        global $post;

        if ( 'sales_receipt' === $column ) {
            $nfe = get_post_meta( $post->ID, 'nfe_issued', true );
            $order = new WC_Order( $post->ID );

            if ( $order->has_status('completed') ) {
                if ( nfe_get_field( 'issue_past_notes' ) === 'no' && strtotime( $order->post->post_date ) < strtotime('last year') ) {
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
     * Adds the NFe Action on the Order Page
     * 
     * @return array
     */
    public function nfe_order_actions( $actions ) {
        $actions['wc_nfe_issue'] = __( 'Issue NFe', 'woocommerce-nfe' );

        return $actions;
    }

    /**
     * Issue the NFe on Box Actions
     * 
     * @param  object $post Current post
     * @return bool True|False
     */
    public function process_nfe_order_actions( $post ) {
        if ( $post->post_status === 'wc-trash' || $post->post_status === 'wc-cancelled' ) {
            return false;
        }
        
        NFe_Woo()->issue_invoice( array( $post->ID ) );
    }

    /**
     * Order Bulk Actions - JavaScript
     *
     * @todo Maybe use get_post_type(), check var_dump( $post_type ) if has proper output
     * @todo The same for $post_status
     * 
     * @return string
     */
    public function order_bulk_actions() {
        global $post_type, $post_status;

        if ( 'shop_order' === get_post_type() ) {

            // Bail if NFe is disabled
            if ( nfe_get_field('nfe_enable') === 'no' ) {
                return false;
            }
            
            // Bail if post status is true for the following post_status
            if ( $post_status == 'wc-trash' || $post_status == 'wc-cancelled' || $post_status == 'wc-pending') {
                return false;
            } ?>
             <script type="text/javascript">
                if ( typeof jq == 'undefined' ) { var jq = jQuery; }

                jq( function() {
                    var IssueNFe = jq('<option>').val('wc_nfe_issue').text('<?php esc_html_e( 'Issue NFe', 'woocommerce-nfe' ); ?>');
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
        global $post, $typenow;

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
                NFe_Woo()->issue_invoice( array( $order_ids ) );
            }
        }
    }

    /**
     * Adds the admin script
     */
    public function enqueue_scripts() {
        $suffix         = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        wp_register_style( 'nfe-woo-admin-css', 
            plugins_url( 'woocommerce-nfe/assets/css/nfe-admin' ) . $suffix . '.css', 
            false, WooCommerce_NFe::VERSION 
        );

        wp_enqueue_style( 'nfe-woo-admin-css' );
    }
}

$run = new WC_NFe_Admin;
