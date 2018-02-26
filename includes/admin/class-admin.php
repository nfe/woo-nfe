<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_NFe_Admin' ) ) :

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

			// Add column to show receipt status updated via NFe.io API.
			add_filter( 'manage_edit-shop_order_columns', [ $this, 'order_status_column_header' ] );
			add_action( 'manage_shop_order_posts_custom_column', [ $this, 'order_status_column_content' ], 10, 1 );

			// Addings NFe actions to the order edit screen.
			add_action( 'woocommerce_order_actions', [ $this, 'download_and_issue_actions' ], 10 , 1 );
			add_action( 'woocommerce_order_action_nfe_download_order_action', [ $this, 'download_issue_action' ] );
			add_action( 'woocommerce_order_action_nfe_issue_order_action', [ $this, 'issue_order_action' ] );

			// Filters
			add_filter( 'woocommerce_product_data_tabs',                				array( $this, 'product_data_tab' ) );

			// Actions
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
		 *
		 * @param  int $order_id Order ID.
		 * @return void
		 */
		public function issue_trigger( $order_id ) {
			if ( nfe_get_field( 'issue_when' ) === 'manual' ) {
				return;
			}

			$order = nfe_wc_get_order( $order_id );
			$order_id = $order->get_id();

			if ( ! $order_id ) {
				return;
			}

			// Checking if the address of order is filled.
			if ( nfe_order_address_filled( $order_id ) ) {
				return;
			}

			// We just can issue the invoice if the status is equal to the configured one.
			if ( $order->has_status( nfe_get_field( 'issue_when_status' ) ) ) {
				NFe_Woo()->issue_invoice( array( $order_id ) );
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
		 * Adds NFe product fields (tab content)
		 *
		 * @global int $post Uses to fetch the current product ID
		 *
		 * @return string
		 */
		public function product_data_fields() {
			global $post;
			?>
			<div id="nfe_product_info_data" class="panel woocommerce_options_panel">
				<?php
				woocommerce_wp_text_input( array(
					'id'            => '_simple_cityservicecode',
					'label'         => esc_html__( 'CityServiceCode', 'woo-nfe' ),
					'wrapper_class' => 'hide_if_variable',
					'desc_tip'      => 'true',
					'description'   => esc_html__( 'Enter the CityServiceCode.', 'woo-nfe' ),
					'value'         => get_post_meta( $post->ID, '_simple_cityservicecode', true )
				) );

				woocommerce_wp_text_input( array(
					'id'            => '_simple_federalservicecode',
					'label'         => esc_html__( 'FederalServiceCode', 'woo-nfe' ),
					'wrapper_class' => 'hide_if_variable',
					'desc_tip'      => 'true',
					'description'   => esc_html__( 'Enter the FederalServiceCode.', 'woo-nfe' ),
					'value'         => get_post_meta( $post->ID, '_simple_federalservicecode', true )
				) );

				woocommerce_wp_textarea_input( array(
					'id'            => '_simple_nfe_product_desc',
					'label'         => esc_html__( 'Product Description', 'woo-nfe' ),
					'wrapper_class' => 'hide_if_variable',
					'desc_tip'      => 'true',
					'description'   => esc_html__( 'Description for this product output in NFe receipt.', 'woo-nfe' ),
					'value'         => get_post_meta( $post->ID, '_simple_nfe_product_desc', true )
				) );
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
			update_post_meta( $post_id, '_simple_cityservicecode', esc_attr( $simple_cityservicecode ) );

			// Text Field - Federal Service Code
			$simple_federalservicecode = $_POST['_simple_federalservicecode'];
			update_post_meta( $post_id, '_simple_federalservicecode', esc_attr( $simple_federalservicecode ) );

			// TextArea Field - Product Description
			$simple_product_desc = $_POST['_simple_nfe_product_desc'];
			update_post_meta( $post_id, '_simple_nfe_product_desc', esc_html( $simple_product_desc ) );
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
			woocommerce_wp_text_input( array(
				'id'            => '_cityservicecode[' . $variation->ID . ']',
				'label'         => esc_html__( 'NFe CityServiceCode', 'woo-nfe' ),
				'desc_tip'      => 'true',
				'description'   => esc_html__( 'Enter the CityServiceCode.', 'woo-nfe' ),
				'value'         => get_post_meta( $variation->ID, '_cityservicecode', true )
			) );

			woocommerce_wp_text_input( array(
				'id'            => '_federalservicecode[' . $variation->ID . ']',
				'label'         => esc_html__( 'NFe FederalServiceCode', 'woo-nfe' ),
				'desc_tip'      => 'true',
				'description'   => esc_html__( 'Enter the FederalServiceCode.', 'woo-nfe' ),
				'value'         => get_post_meta( $variation->ID, '_federalservicecode', true )
			) );

			woocommerce_wp_textarea_input( array(
				'id'            => '_nfe_product_variation_desc[' . $variation->ID . ']',
				'label'         => esc_html__( 'NFe Product Description', 'woo-nfe' ),
				'value'         => get_post_meta( $variation->ID, '_nfe_product_variation_desc', true )
			) );
		}

		/**
		 * Adds the Download and Issue actions to the actions list in the order edit page.
		 *
		 * @param array $actions Order actions array to display.
		 *
		 * @return array List of actions.
		 */
		public function download_and_issue_actions( $actions ) {
			global $theorder;

			if ( ! is_object( $theorder ) ) {
				$theorder = nfe_wc_get_order( get_the_ID() );
			}

			$order_id = $theorder->get_id();
			$download = get_post_meta( $order_id, 'nfe_issued', true );

			// Load the download actin if there is a issue to download.
			if ( ! empty( $download ) && isset( $download['id'] ) ) {
				$actions['nfe_download_order_action'] = __( 'Download NFe receipt', 'woo-nfe' );

				return $actions;
			}

			if ( $theorder->has_status( nfe_get_field( 'issue_when_status' ) ) && ! nfe_order_address_filled( $order_id ) ) {
				$actions['nfe_issue_order_action'] = __( 'Issue NFe receipt', 'woo-nfe' );

				return $actions;
			}

			return $actions;
		}

		/**
		 * NFe receipt downloading actoin.
		 *
		 * @param  WC_Order $order Order object.
		 * @return void
		 */
		public function download_issue_action( $order ) {

			// Order note.
			$message = esc_html__( 'NFe receipt downloaded.', 'woo-nfe' );
			$order->add_order_note( $message );

			WC_NFe_Ajax::download_pdf( $order->get_id() );
		}

		/**
		 * Issuing a NFe receipt.
		 *
		 * @param  WC_Order $order Order object.
		 * @return void
		 */
		public function issue_order_action( $order ) {
			// Issue NFe receipt.
			$invoice = NFe_Woo()->issue_invoice( array( $order->get_id() ) );

			if ( ! is_object( $invoice ) ) {
				return;
			}
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
			update_post_meta( $post_id, '_cityservicecode', esc_attr( $cityservicecode ) );

			// Text Field - Federal Service Code
			$_federalservicecode = $_POST['_federalservicecode'][ $post_id ];
			update_post_meta( $post_id, '_federalservicecode', esc_attr( $_federalservicecode ) );

			// TextArea Field - Product Variation Description
			$product_desc = $_POST['_nfe_product_variation_desc'][ $post_id ];
			update_post_meta( $post_id, '_nfe_product_variation_desc', esc_html( $product_desc ) );
		}

		/**
		 * Add column to show receipt status updated via NFe.io API.
		 *
		 * @param  array $columns Array of Columns.
		 *
		 * @return array Array of colunms with the NFe one.
		 */
		public function order_status_column_header( $columns ) {
			$column_header = '<span class="tips" data-tip="' . esc_attr__( 'Sales Receipt updated via NFe.io API', 'woo-nfe' ) . '">' . esc_attr__( 'Sales Receipt', 'woo-nfe' ) . '</span>';

			$new_columns = $this->array_insert_after( 'order_total', $columns, 'nfe_receipts', $column_header );

			return $new_columns;
		}

		/**
		 * Column Content on Order Status
		 *
		 * @since 1.0.9
		 *
		 * @param string $column Column id.
		 *
		 * @return void
		 */
		public function order_status_column_content( $column ) {
			// Get information.
			$order      = nfe_wc_get_order( get_the_ID() );
			$order_id   = $order->get_id();
			$nfe        = get_post_meta( $order_id, 'nfe_issued', true );
			$status     = array( 'PullFromCityHall', 'WaitingCalculateTaxes', 'WaitingDefineRpsNumber' );

			// Bail early.
			if ( 'nfe_receipts' !== $column ) {
				return;
			}
			?>
			<mark>
			<?php
			$actions = array();

			if ( $order->has_status( 'completed' ) ) {
				if ( ! empty( $nfe ) && ( 'Cancelled' === $nfe['status'] || 'Issued' === $nfe['status'] ) ) {
					if ( 'Cancelled' === $nfe['status'] ) {
						$actions['woo_nfe_cancelled'] = array(
							'name'      => esc_html__( 'NFe Cancelled', 'woo-nfe' ),
							'action'    => 'woo_nfe_cancelled',
						);
					} elseif ( 'Issued' === $nfe['status'] ) {
						$actions['woo_nfe_emitida'] = array(
							'name'      => esc_html__( 'Issued', 'woo-nfe' ),
							'action'    => 'woo_nfe_emitida',
						);
					}
				} elseif ( ! empty( $nfe ) && in_array( $nfe['status'], $status, true ) ) {
					$actions['woo_nfe_issuing'] = array(
						'name'      => esc_html__( 'Issuing NFe', 'woo-nfe' ),
						'action'    => 'woo_nfe_issuing',
					);
				} else {
					if ( nfe_order_address_filled( $order_id ) ) {
						$actions['woo_nfe_pending_address'] = array(
							'name'      => esc_html__( 'Pending Address', 'woo-nfe' ),
							'action'    => 'woo_nfe_pending_address',
						);
					} else {
						if ( nfe_get_field( 'issue_past_notes' ) === 'yes' ) {
							if ( nfe_issue_past_orders( $order ) && empty( $nfe['id'] ) ) {
								$actions['woo_nfe_issue'] = array(
									'name'      => esc_html__( 'Issue NFe', 'woo-nfe' ),
									'action'    => 'woo_nfe_issue',
								);
							} else {
								$actions['woo_nfe_expired'] = array(
									'name'      => esc_html__( 'Issue Expired', 'woo-nfe' ),
									'action'    => 'woo_nfe_expired',
								);
							}
						} else {
							$actions['woo_nfe_issue'] = array(
								'name'      => esc_html__( 'Issue NFe', 'woo-nfe' ),
								'action'    => 'woo_nfe_issue',
							);
						}
					}
				}
			} else {
				$actions['woo_nfe_payment'] = array(
					'name'      => esc_html__( 'Pending Payment', 'woo-nfe' ),
					'action'    => 'woo_nfe_payment',
				);
			}

			foreach ( $actions as $action ) {
				printf( '<span class="woo_nfe_actions %s">%s</span>',
					esc_attr( $action['action'] ),
					esc_attr( $action['name'] )
				);
			} ?>
			</mark>
			<?php
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

	    /**
	     * Inserts a new key/value after the key in the array.
	     *
	     * @param string $needle    The array key to insert the element after.
	     * @param array  $haystack  An array to insert the element into.
	     * @param string $new_key   The key to insert.
	     * @param string $new_value An value to insert.
	     *
	     * @return The new array if the $needle key exists, otherwise an unmodified $haystack
	     */
	    protected function array_insert_after( $needle, $haystack, $new_key, $new_value ) {

	    	if ( array_key_exists( $needle, $haystack ) ) {

	    		$new_array = array();

	    		foreach ( $haystack as $key => $value ) {

	    			$new_array[ $key ] = $value;

	    			if ( $key === $needle ) {
	    				$new_array[ $new_key ] = $new_value;
	    			}
	    		}

	    		return $new_array;
	    	}

	    	return $haystack;
	    }
	}

	return new WC_NFe_Admin();

endif;
