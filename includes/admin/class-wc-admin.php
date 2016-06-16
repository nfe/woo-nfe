<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('WC_NFe_Admin') ) :

/**
 * WooCommerce NFe.io WC_NFe_Admin Class
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Admin
 * @version  1.0.0
 */
class WC_NFe_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
        // Filters
        add_filter( 'manage_edit-shop_order_columns',               array( $this, 'order_status_column_header' ), 20 );

        // Actions
        add_action( 'manage_shop_order_posts_custom_column',        array( $this, 'order_status_column_content' ) );
        add_action( 'woocommerce_order_actions',                    array( $this, 'nfe_order_actions' ), 10, 1 );
        add_action( 'woocommerce_order_action_wc_nfe_issue',        array( $this, 'process_nfe_order_actions' ), 10, 1 );
        add_action( 'admin_footer-edit.php',                        array( $this, 'order_bulk_actions' ) );
        add_action( 'load-edit.php',                                array( $this, 'process_order_bulk_actions' ) );
        add_action( 'admin_enqueue_scripts',                        array( $this, 'enqueue_scripts' ) );

        // Product Variations
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variation_fields' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation',            array( $this, 'save_variations_fields' ), 10, 2 );

        // Not Product Variations
        add_filter( 'woocommerce_product_data_tabs',                 array( $this, 'product_data_tab' ) );
        add_action( 'woocommerce_product_data_panels',               array( $this, 'product_data_fields' ) );
        add_action( 'woocommerce_process_product_meta',              array( $this, 'product_data_fields_save' ) );
	}

    /**
     * Adds NFe custom tab
     * 
     * @param array $product_data_tabs Array of product tabs
     * @return array Array with product data tabs
     */
    public function product_data_tab( $product_data_tabs ) {
        $product_data_tabs['nfe-product-info-tab'] = array(
            'label'     => __( 'WooCommerce NFe', 'woocommerce-nfe' ),
            'target'    => 'nfe_product_info_data',
            'class'     => array( 'hide_if_variable' ),
        );
        return $product_data_tabs;
    }

    /**
     * Adds NFe product fields (tab content)
     *
     * @global int $post Uses to fetch the current product ID
     * 
     * @return string
     */
    public function product_data_fields() {
        global $post; ?>
        <div id="nfe_product_info_data" class="panel woocommerce_options_panel">
            <?php
            woocommerce_wp_text_input( 
                array( 
                    'id'            => '_simple_cityservicecode',
                    'label'         => __( 'CityServiceCode', 'woocommerce-nfe' ), 
                    'wrapper_class' => 'hide_if_variable', 
                    'desc_tip'      => 'true',
                    'description'   => __( 'Enter the CityServiceCode.', 'woocommerce-nfe' ),
                    'value'         => get_post_meta( $post->ID, '_simple_cityservicecode', true )
                )
            );

            woocommerce_wp_text_input( 
                array( 
                    'id'            => '_simple_federalservicecode',
                    'label'         => __( 'FederalServiceCode', 'woocommerce-nfe' ), 
                    'wrapper_class' => 'hide_if_variable',
                    'desc_tip'      => 'true',
                    'description'   => __( 'Enter the FederalServiceCode.', 'woocommerce-nfe' ),
                    'value'         => get_post_meta( $post->ID, '_simple_federalservicecode', true )
                )
            );

            woocommerce_wp_textarea_input( 
                array( 
                    'id'            => '_simple_nfe_product_desc',
                    'label'         => __( 'Product Description', 'woocommerce-nfe' ),
                    'wrapper_class' => 'hide_if_variable', 
                    'desc_tip'      => 'true', 
                    'description'   => __( 'Description for this product.', 'woocommerce-nfe' ),
                    'value'         => get_post_meta( $post->ID, '_simple_nfe_product_desc', true )
                )
            );
            ?>
        </div>
        <?php
    }

    /**
     * Saving product data information.
     * 
     * @param  int $post_id Product ID
     * @return bool true|false
     */
    public function product_data_fields_save( $post_id ) {
        // Text Field - City Service Code
        $simple_cityservicecode = $_POST['_simple_cityservicecode'];
        if( !empty( $simple_cityservicecode ) ) {
            update_post_meta( $post_id, '_simple_cityservicecode', esc_attr( $simple_cityservicecode ) );
        }

        // Text Field - Federal Service Code
        $simple_federalservicecode = $_POST['_simple_federalservicecode'][ $post_id ];
        if( ! empty( $simple_federalservicecode ) ) {
            update_post_meta( $post_id, '_simple_federalservicecode', esc_attr( $simple_federalservicecode ) );
        }

        // TextArea Field - Product Variation Description
        $simple_product_desc = $_POST['_simple_nfe_product_desc'][ $post_id ];
        if( ! empty( $simple_product_desc ) ) {
            update_post_meta( $post_id, '_simple_nfe_product_desc', esc_html( $simple_product_desc ) );
        }
    }

   /**
    * Adds the NFe fields for product variations
    * 
    * @param  array $loop
    * @param  string $variation_data
    * @param  string $variation
    * @return array
    */
    public function variation_fields( $loop, $variation_data, $variation ) {
        woocommerce_wp_text_input( 
            array( 
                'id'            => '_cityservicecode[' . $variation->ID . ']',
                'label'         => __( 'NFe CityServiceCode', 'woocommerce-nfe' ), 
                'desc_tip'      => 'true',
                'description'   => __( 'Enter the CityServiceCode.', 'woocommerce-nfe' ),
                'value'         => get_post_meta( $variation->ID, '_cityservicecode', true )
            )
        );

        woocommerce_wp_text_input( 
            array( 
                'id'            => '_federalservicecode[' . $variation->ID . ']',
                'label'         => __( 'NFe FederalServiceCode', 'woocommerce-nfe' ), 
                'desc_tip'      => 'true',
                'description'   => __( 'Enter the FederalServiceCode.', 'woocommerce-nfe' ),
                'value'         => get_post_meta( $variation->ID, '_federalservicecode', true )
            )
        );

        woocommerce_wp_textarea_input( 
            array( 
                'id'            => '_nfe_product_variation_desc[' . $variation->ID . ']',
                'label'         => __( 'NFe Product Description', 'woocommerce-nfe' ),
                'value'         => get_post_meta( $variation->ID, '_nfe_product_variation_desc', true )
            )
        );
    }
   
   /**
    * Save the NFe fields for product variations
    * 
    * @param  int $post_id Product ID
    * @return bool true|false
    */
    public function save_variations_fields( $post_id ) {
        // Text Field - City Service Code
        $cityservicecode = $_POST['_cityservicecode'][ $post_id ];
        if( ! empty( $cityservicecode ) ) {
            update_post_meta( $post_id, '_cityservicecode', esc_attr( $cityservicecode ) );
        }

        // Text Field - Federal Service Code
        $_federalservicecode = $_POST['_federalservicecode'][ $post_id ];
        if( ! empty( $_federalservicecode ) ) {
            update_post_meta( $post_id, '_federalservicecode', esc_attr( $_federalservicecode ) );
        }

        // TextArea Field - Product Variation Description
        $product_desc = $_POST['_nfe_product_variation_desc'][ $post_id ];
        if( ! empty( $product_desc ) ) {
            update_post_meta( $post_id, '_nfe_product_variation_desc', esc_html( $product_desc ) );
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

        $order = wc_get_order( $post->ID );
        $nfe   = get_post_meta( $post->ID, 'nfe_issued', true );
       
        if ( 'sales_receipt' == $column ) {
            ?><p>
            <?php
            $actions = array();

            if ( nfe_get_field('nfe_enable') == 'yes' && $order->post_status == 'wc-completed' ) {
                if ( ! $this->issue_past_orders( $order ) && current_user_can('manage_woocommerce') ) {
                    $actions['woo_nfe_expired'] = array(
                        'name'      => __( 'Issue Expired', 'woocommerce-nfe' ),
                        'action'    => 'woo_nfe_expired'
                    );
                }

                if ( $this->issue_past_orders( $order ) && ( nfe_get_field('nfe_enable') == 'yes' && $nfe == false ) ) {
                    $actions['woo_nfe_issue'] = array(
                        'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_issue&order_id=' . $order->id ), 'woocommerce_nfe_issue' ),
                        'name'      => __( 'Issue Nfe', 'woocommerce-nfe' ),
                        'action'    => 'woo_nfe_issue'
                    );
                }
            }

            if ( $nfe == true ) {
                $actions['woo_nfe_download'] = array(
                    'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_download&order_id=' . $order->id ), 'woo_nfe_download' ),
                    'name'      => __( 'Download NFe', 'woocommerce-nfe' ),
                    'action'    => 'woo_nfe_download'
                );
            }

            if ( current_user_can('manage_woocommerce') && nfe_get_field('nfe_enable') == 'no' ) {
                $actions['woo_nfe_tab'] = array(
                    'url'       => WOOCOMMERCE_NFE_SETTINGS_URL,
                    'name'      => __( 'Enable NFe', 'woocommerce-nfe' ),
                    'action'    => 'woo_nfe_tab'
                );
            } 

            foreach ( $actions as $action ) {
                if ( $action['action'] == 'woo_nfe_expired' ) {
                    printf( '<span class="button view %s" data-tip="%s">%s</span>', 
                        esc_attr( $action['action'] ), 
                        esc_attr( $action['name'] ), 
                        esc_attr( $action['name'] ) 
                    );
                } else {
                    printf( '<a class="button view %s" href="%s" data-tip="%s">%s</a>', 
                        esc_attr( $action['action'] ), 
                        esc_url( $action['url'] ), 
                        esc_attr( $action['name'] ), 
                        esc_attr( $action['name'] ) 
                    );
                }
            }
            ?>
            </p><?php
        }
    }

    /**
     * Past Issue Check
     * 
     * @param  string $post_date Post date
     * @param  string $past_days 
     * @return bool true|false
     */
    public function issue_past_orders( $order ) {
        $post_date   = $order->post->post_date;
        $days        = '-' . nfe_get_field( 'issue_past_days' ) . ' days';

        if ( nfe_get_field( 'issue_past_notes' ) == 'yes' && strtotime( $post_date ) > strtotime( $days ) ) {
            return true;
        }

        return false;
    }

    /**
     * Adds the NFe Action on the Order Page
     * 
     * @return array
     */
    public function nfe_order_actions( $actions ) {
        $actions['wc_nfe_issue'] = __( 'Issue NFe', 'woocommerce-nfe' );
        $actions['wc_nfe_down']  = __( 'Download NFe', 'woocommerce-nfe' );

        return $actions;
    }

    /**
     * Issue/Download the NFe on Box Actions
     * 
     * @param  object $post Current post
     * @return bool True|False
     */
    public function process_nfe_order_actions( $post ) {
        if ( $post->post_status == ( 'wc-trash' || 'wc-cancelled' || 'wc-pending' ) ) {
            return false;
        }

        $order = wc_get_order( $post->ID );
        if ( $this->issue_past_orders( $order ) ) {
            return false;
        }
        
        $nfe = get_post_meta( $post->ID, 'nfe_issued', true );

        if ( $nfe->status == 'issued' ) {
            NFe_Woo()->down_invoice( $post->ID );

        } else {
            NFe_Woo()->issue_invoice( array( $post->ID ) );
        }
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

        if ( 'shop_order' == get_post_type() ) {

            // Bail if NFe is disabled
            if ( nfe_get_field('nfe_enable') == 'no' ) {
                return false;
            }
            
            // Bail if post status is true for the following post_status
            if ( $post_status == ( 'wc-trash' || 'wc-cancelled' || 'wc-pending' ) ) {
                return false;
            } ?>
             <script type="text/javascript">
                if ( typeof jq == 'undefined' ) { var jq = jQuery; }

                jq( function() {
                    var IssueNFe = jq('<option>').val('wc_nfe_issue').text('<?php esc_html_e( 'Issue NFe', 'woocommerce-nfe' ); ?>');

                    var DownNFe = jq('<option>').val('wc_nfe_down').text('<?php esc_html_e( 'Download NFe', 'woocommerce-nfe' ); ?>');

                    jq('select[name^="action"]' ).append( IssueNFe );
                    jq('select[name^="action"]' ).append( DownNFe );
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

        if ( 'shop_order' == $typenow ) {
            $wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
            $action        = $wp_list_table->current_action();

            if ( ! in_array( $action, array( 'wc_nfe_issue', 'wc_nfe_down' ) ) ) {
               return false;
            }

            if ( isset( $_REQUEST['post'] ) ) {
                $order_ids = array_map( 'absint', $_REQUEST['post'] );
            }

            if ( empty( $order_ids ) ) {
                return false;
            }
            
            $nfe = get_post_meta( $order_ids, 'nfe_issued', true );

            $order = wc_get_order( $order_ids );
            if ( $this->issue_past_orders( $order ) ) {
                return false;
            }

            if ( $action == 'wc_nfe_issue' ) {

                NFe_Woo()->issue_invoice( array( $order_ids ) );

            } elseif ( $action == 'wc_nfe_down' && $nfe->status != 'issued' ) {
                NFe_Woo()->down_invoice( array( $order_ids ) );
            }
        }
    }

    /**
     * Adds the admin script
     *
     * @return array
     */
    public function enqueue_scripts() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        wp_register_style( 'nfe-woo-admin-css', 
            plugins_url( 'woocommerce-nfe/assets/css/nfe-admin' ) . $suffix . '.css', 
            false, WooCommerce_NFe::VERSION 
        );

        wp_enqueue_style( 'nfe-woo-admin-css' );
    }
}

endif;

$run = new WC_NFe_Admin;

// That's it! =)
