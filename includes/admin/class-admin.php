<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('WC_NFe_Admin') ) :

/**
 * WooCommerce NFe WC_NFe_Admin Class
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Admin
 * @version  1.0.6
 */
class WC_NFe_Admin {

	/**
	 * Class Constructor
	 *
	 * @since 1.0.6
	 */
	public function __construct() {
		// Filters
		add_filter( 'manage_edit-shop_order_columns',               				array( $this, 'order_status_column_header' ), 20 );
		add_filter( 'woocommerce_product_data_tabs',                				array( $this, 'product_data_tab' ) );

		// Actions
		add_action( 'manage_shop_order_posts_custom_column',         				array( $this, 'order_status_column_content' ) );
		add_action( 'woocommerce_product_after_variable_attributes', 				array( $this, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation',            				array( $this, 'save_variations_fields' ), 10, 2 );
		add_action( 'woocommerce_product_data_panels',               				array( $this, 'product_data_fields' ) );
		add_action( 'woocommerce_process_product_meta',              				array( $this, 'product_data_fields_save' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', 			array( $this, 'display_order_data_in_admin' ), 20 );
		add_action( 'admin_enqueue_scripts',                 						array( $this, 'register_enqueue_css' ) );

		/*
		Woo Commmerce status triggers

		- woocommerce_order_status_pending
		- woocommerce_order_status_failed
		- woocommerce_order_status_on-hold
		- woocommerce_order_status_processing
		- woocommerce_order_status_completed
		- woocommerce_order_status_refunded
		- woocommerce_order_status_cancelled
		*/

		add_action( 'woocommerce_order_status_pending', 					array( $this, 'issue_trigger' ) );
		add_action( 'woocommerce_order_status_on-hold', 					array( $this, 'issue_trigger' ) );
		add_action( 'woocommerce_order_status_processing', 					array( $this, 'issue_trigger' ) );
		add_action( 'woocommerce_order_status_completed', 					array( $this, 'issue_trigger' ) );

		// WooCommerce Subscriptions Support
		if ( class_exists('WC_Subscriptions') ) {
			add_action( 'processed_subscription_payments_for_order',	array( $this, 'issue_trigger') );
			add_action( 'woocommerce_renewal_order_payment_complete',	array( $this, 'issue_trigger') );
		}
	}

	/**
	 * Issue a NFe receipt when WooCommerce does its thing.
	 * Issue on order status
	 * @param  int $order_id Order ID
	 * @return bool true|false
	 */
	public function issue_trigger( $order_id ) {
		if ( nfe_get_field('issue_when') === 'manual' ) {
			return;
		}
		if ( $order_id ) {
			$order    = nfe_wc_get_order( $order_id );
			$order_id = $order->id;
		}

		// Checking if the address of order is filled
		if ( ! nfe_order_address_filled( $order_id ) ) {
			// We just can issue the invoice if the status
			// is equal to the configured one
			if ( $order->post_status === nfe_get_field('issue_when_status') ) {
				NFe_Woo()->issue_invoice( array( $order_id ) );
			}
		}
	}

	/**
	 * Adds NFe custom tab
	 *
	 * @param array $product_data_tabs Array of product tabs
	 * @return array Array with product data tabs
	 */
	public function product_data_tab( $product_data_tabs ) {
		$product_data_tabs['nfe-product-info-tab'] = array(
			'label'     => esc_html__( 'WooCommerce NFe', 'woo-nfe' ),
			'target'    => 'nfe_product_info_data',
			'class'     => array( 'hide_if_variable' ),
		);
		return $product_data_tabs;
	}

	/**
	 * Adds NFe product fields (tab content).
	 *
	 * @return void
	 */
	public function product_data_fields() {
		$post_id = get_the_ID();
		?>
		<div id="nfe_product_info_data" class="panel woocommerce_options_panel">
			<?php
			woocommerce_wp_text_input( array(
				'id'            => '_simple_cityservicecode',
				'label'         => esc_html__( 'CityServiceCode', 'woo-nfe' ),
				'wrapper_class' => 'hide_if_variable',
				'desc_tip'      => 'true',
				'description'   => esc_html__( 'CityServiceCode, used when issuing a receipt.', 'woo-nfe' ),
				'value'         => get_post_meta( $post_id, '_simple_cityservicecode', true ),
			) );

			woocommerce_wp_text_input( array(
				'id'            => '_simple_federalservicecode',
				'label'         => esc_html__( 'FederalServiceCode', 'woo-nfe' ),
				'wrapper_class' => 'hide_if_variable',
				'desc_tip'      => 'true',
				'description'   => esc_html__( 'FederalServiceCode, used when issuing a receipt.', 'woo-nfe' ),
				'value'         => get_post_meta( $post_id, '_simple_federalservicecode', true ),
			) );

			woocommerce_wp_textarea_input( array(
				'id'            => '_simple_nfe_product_desc',
				'label'         => esc_html__( 'Product Description', 'woo-nfe' ),
				'wrapper_class' => 'hide_if_variable',
				'desc_tip'      => 'true',
				'description'   => esc_html__( 'Description used when issuing a receipt.', 'woo-nfe' ),
				'value'         => get_post_meta( $post_id, '_simple_nfe_product_desc', true ),
			) );
			?>
		</div>
		<?php
	}

	/**
	 * Saving product data information.
	 *
	 * @param  int $post_id Product ID.
	 *
	 * @return void
	 */
	public function product_data_fields_save( $post_id ) {

		// Text Field - City Service Code.
		update_post_meta( $post_id, '_simple_cityservicecode', esc_attr( $_POST['_simple_cityservicecode'] ) );

		// Text Field - Federal Service Code.
		update_post_meta( $post_id, '_simple_federalservicecode', esc_attr( $_POST['_simple_federalservicecode'] ) );

		// TextArea Field - Product Description.
		update_post_meta( $post_id, '_simple_nfe_product_desc', esc_html( $_POST['_simple_nfe_product_desc'] ) );
	}

	/**
	 * Adds the NFe fields for the product variations.
	 *
	 * @param  array  $loop           Product loop.
	 * @param  array  $variation_data Product/variation data.
	 * @param  string $variation      Variation.
	 *
	 * @return void
	 */
	public function variation_fields( $loop, $variation_data, $variation ) {

		// Product ID.
		$product_id = $variation->ID;

		woocommerce_wp_text_input( array(
			'id'            => '_cityservicecode[' . $product_id . ']',
			'label'         => esc_html__( 'CityServiceCode', 'woo-nfe' ),
			'desc_tip'      => 'true',
			'description'   => esc_html__( 'CityServiceCode, used when issuing a receipt.', 'woo-nfe' ),
			'value'         => get_post_meta( $variation->ID, '_cityservicecode', true ),
		) );

		woocommerce_wp_text_input( array(
			'id'            => '_federalservicecode[' . $product_id . ']',
			'label'         => esc_html__( 'FederalServiceCode', 'woo-nfe' ),
			'desc_tip'      => 'true',
			'description'   => esc_html__( 'FederalServiceCode, used when issuing a receipt.', 'woo-nfe' ),
			'value'         => get_post_meta( $product_id, '_federalservicecode', true ),
		) );

		woocommerce_wp_textarea_input( array(
			'id'            => '_nfe_product_variation_desc[' . $product_id . ']',
			'label'         => esc_html__( 'Product Description', 'woo-nfe' ),
			'desc_tip'      => 'true',
			'description'   => esc_html__( 'Description used when issuing a receipt.', 'woo-nfe' ),
			'value'         => get_post_meta( $product_id, '_nfe_product_variation_desc', true ),
		) );
	}

   /**
	* Save the NFe fields for product variations.
	*
	* @param  int $post_id Product ID.
	*
	* @return void
	*/
	public function save_variations_fields( $post_id ) {

		// Text Field - City Service Code
		update_post_meta( $post_id, '_cityservicecode',
			esc_attr( $_POST['_cityservicecode'][ $post_id ] ) );

		// Text Field - Federal Service Code.
		update_post_meta( $post_id, '_federalservicecode',
			esc_attr( $_POST['_federalservicecode'][ $post_id ] ) );

		// TextArea Field - Product Variation Description.
		update_post_meta( $post_id, '_nfe_product_variation_desc',
			esc_html( $_POST['_nfe_product_variation_desc'][ $post_id ] ) );
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
				$new_columns['sales_receipt'] = esc_html__( 'Sales Receipt', 'woo-nfe' );
			}
		}
		return $new_columns;
	}

	/**
	 * Column Content on Order Status
	 *
	 * @since 1.0.9
	 *
	 * @return string
	 */
	public function order_status_column_content( $column ) {
		global $post;

		$order    	= nfe_wc_get_order( (int) $post->ID );
		$order_data = $order->get_data();
		$order_id 	= (int) $order_data['id'];
		$nfe      	= get_post_meta( $order_id, 'nfe_issued', true );
		$status   	= array( 'PullFromCityHall', 'WaitingCalculateTaxes', 'WaitingDefineRpsNumber' );

		if ( 'sales_receipt' === $column ) {
			?><p>
			<?php
			$actions = array();

			if ( nfe_get_field('nfe_enable') === 'yes') {
				if ( ! empty($nfe) && ( $nfe['status'] === 'Cancelled' || $nfe['status'] === 'Issued' ) ) {
					if ( $nfe['status'] === 'Cancelled' ) {
						$actions['woo_nfe_cancelled'] = array(
							'name'      => esc_html__( 'NFe Cancelled', 'woo-nfe' ),
							'action'    => 'woo_nfe_cancelled'
						);
					}
					elseif ( $nfe['status'] === 'Issued' ) {
						$actions['woo_nfe_emitida'] = array(
							'name'      => esc_html__( 'Issued', 'woo-nfe' ),
							'action'    => 'woo_nfe_emitida'
						);
					}

					$actions['woo_nfe_download'] = array(
						'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_download&order_id=' . $order_id ), 'woo_nfe_download' ),
						'name'      => esc_html__( 'Download NFe', 'woo-nfe' ),
						'action'    => 'woo_nfe_download'
					);
				}
				elseif ( ! empty($nfe) && in_array( $nfe['status'], $status ) ) {
					$actions['woo_nfe_issuing'] = array(
						'name'      => esc_html__( 'Issuing NFe', 'woo-nfe' ),
						'action'    => 'woo_nfe_issuing'
					);
				}
				else {
					if ( nfe_order_address_filled( $order_id ) ) {
						$actions['woo_nfe_pending_address'] = array(
							'name'      => esc_html__( 'Pending Address', 'woo-nfe' ),
							'action'    => 'woo_nfe_pending_address'
						);
					}
					else {
						if ( nfe_get_field('issue_past_notes') === 'yes' ) {
							if ( nfe_issue_past_orders( $order ) && empty( $nfe['id'] ) ) {
								$actions['woo_nfe_issue'] = array(
									'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_issue&order_id=' . $order_id ), 'woo_nfe_issue' ),
									'name'      => esc_html__( 'Issue NFe', 'woo-nfe' ),
									'action'    => 'woo_nfe_issue'
								);
							}
							else {
								$actions['woo_nfe_expired'] = array(
									'name'      => esc_html__( 'Issue Expired', 'woo-nfe' ),
									'action'    => 'woo_nfe_expired'
								);
							}
						}
						else {
							$actions['woo_nfe_issue'] = array(
								'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_issue&order_id=' . $order_id ), 'woo_nfe_issue' ),
								'name'      => esc_html__( 'Issue NFe', 'woo-nfe' ),
								'action'    => 'woo_nfe_issue'
							);
						}
					}
				}
			}

			if ( nfe_get_field('nfe_enable') === 'no' && current_user_can('manage_woocommerce') ) {
				$actions['woo_nfe_tab'] = array(
					'url'       => WOOCOMMERCE_NFE_SETTINGS_URL,
					'name'      => esc_html__( 'Enable NFe', 'woo-nfe' ),
					'action'    => 'woo_nfe_tab'
				);
			}

			foreach ( $actions as $action ) {
				if ( $action['action'] === 'woo_nfe_issue' || $action['action'] === 'woo_nfe_download' ) {
					printf( '<a class="button view %s" href="%s" data-tip="%s">%s</a>',
						esc_attr( $action['action'] ),
						esc_url( $action['url'] ),
						esc_attr( $action['name'] ),
						esc_attr( $action['name'] )
					);
				}
				else {
					printf( '<span class="woo_nfe_actions %s" data-tip="%s">%s</span>',
						esc_attr( $action['action'] ),
						esc_attr( $action['name'] ),
						esc_attr( $action['name'] )
					);
				}
			} ?>
			</p><?php
		}
	}

	/**
	 * Adds NFe information on order page
	 *
	 * @since 1.0.8 Updated how details is being checked
	 *
	 * @param  WC_Order $order
	 * @return string
	 */
	public function display_order_data_in_admin( $order ) {
		$order_data = $order->get_data();
		$order_id 	= (int) $order_data['id'];
		$nfe 		= get_post_meta( $order_id, 'nfe_issued', true );
		?>
	    <h4><?php echo '<strong>' . esc_html__( 'NFe Details', 'woo-nfe' ) . '</strong><br />'; ?></h4>
	    <div class="nfe-details">
	        <?php
		        $details = array('status', 'number', 'checkCode', 'issuedOn', 'amountNet');

				foreach ($details as $data) {
					if ( ! isset($nfe[$data]) ) {
						$nfe[$data] = '';
					}
				}

	        	echo '<p>';
	            echo '<strong>' . esc_html__( 'Status', 'woo-nfe' ) . ': </strong>' . $nfe['status'] . '<br />';
	            echo '<strong>' . esc_html__( 'Number', 'woo-nfe' ) . ': </strong>' . $nfe['number'] . '<br />';
	            echo '<strong>' . esc_html__( 'Check Code', 'woo-nfe' ) . ': </strong>' . $nfe['checkCode'] . '<br />';
	            echo '<strong>' . esc_html__( 'Issued on', 'woo-nfe' ) . ': </strong>' . date_i18n( get_option( 'date_format' ), strtotime( $nfe['issuedOn'] ) ) . '<br />';
	            echo '<strong>' . esc_html__( 'Price', 'woo-nfe' ) . ': </strong>' . wc_price( $nfe['amountNet'] ) . '<br />';
	            echo '</p>';
			?>
	    </div>
	<?php }

	/**
     * Adds the NFe Admin CSS
     */
    public function register_enqueue_css() {
        wp_register_style( 'nfe-woo-admin-css', plugins_url( 'woo-nfe/assets/css/nfe' ) . '.css' );
        wp_enqueue_style( 'nfe-woo-admin-css' );
    }
}

return new WC_NFe_Admin();

endif;

// That's it! =)
