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
		 * The single instance
		 */
		protected static $_instance = null;

		/**
		 * Singleton getter
		 */
		public static function get_instance() {
			if ( is_null(self::$_instance) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

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

			// NFe.io Order Details Preview.
			add_action( 'woocommerce_admin_order_data_after_shipping_address', [ $this, 'display_order_data_preview_in_admin' ], 20 );
			add_action( 'woocommerce_admin_order_preview_start', [ $this, 'nfe_admin_order_preview' ] );
			add_filter( 'woocommerce_admin_order_preview_get_order_details', [ $this, 'nfe_admin_order_preview_details' ], 20, 2 );

			add_filter( 'woocommerce_product_data_tabs',                				array( $this, 'product_data_tab' ) );
			add_action( 'woocommerce_product_after_variable_attributes', 				array( $this, 'variation_fields' ), 10, 3 );
			add_action( 'woocommerce_save_product_variation',            				array( $this, 'save_variations_fields' ), 10, 2 );
			add_action( 'woocommerce_product_data_panels',               				array( $this, 'product_data_fields' ) );
			add_action( 'woocommerce_process_product_meta',              				array( $this, 'product_data_fields_save' ) );

			add_action( 'admin_enqueue_scripts',                 						array( $this, 'register_enqueue_css' ) );
			add_action( 'woocommerce_after_dashboard_status_widget', [ $this, 'nfe_status_widget_order_rows' ] );

			// NFe issue triggers.
			add_action( 'woocommerce_order_status_pending', 					array( $this, 'issue_trigger' ) );
			add_action( 'woocommerce_order_status_on-hold', 					array( $this, 'issue_trigger' ) );
			add_action( 'woocommerce_order_status_processing', 					array( $this, 'issue_trigger' ) );
			add_action( 'woocommerce_order_status_completed', 					array( $this, 'issue_trigger' ) );

			// WooCommerce Subscriptions Support.
			if ( class_exists( 'WC_Subscriptions' ) ) {
				add_action( 'processed_subscription_payments_for_order',	array( $this, 'issue_trigger') );
				add_action( 'woocommerce_renewal_order_payment_complete',	array( $this, 'issue_trigger') );
			}
		}

		/**
		 * Issue a NFe receipt when WooCommerce does its thing.
		 *
		 * @param  int $order_id Order ID.
		 *
		 * @return void
		 */
		public function issue_trigger( $order_id ) {

			// Bail early.
			if ( nfe_get_field( 'issue_when' ) === 'manual' ) {
				return;
			}

			// Check if order exists first.
			$order    = nfe_wc_get_order( $order_id );
			$order_id = $order->get_id();

			// Bail for no order.
			if ( ! $order_id ) {
				return;
			}

			// We just can issue the invoice automatically if the status is equal to the configured one.
			if ( $order->has_status( nfe_get_field( 'issue_when_status' ) ) ) {
				NFe_Woo()->issue_invoice( [ $order_id ] );
			}
		}

		/**
		 * Get orders count.
		 *
		 * @param  string $value NFe status.
		 * @return int
		 */
		protected function get_order_count( $value ) {
			$query = nfe_get_order_by_nota_value( $value );

			return $query->found_posts;
		}

		/**
		 * Show NFe.io order data is status widget.
		 *
		 * @return void
		 */
		public function nfe_status_widget_order_rows() {
			if ( ! current_user_can( 'edit_shop_orders' ) ) {
				return;
			}

			$nfe_issued_count    = $this->get_order_count( 'Issued' );
			$nfe_issuing_count   = $this->get_order_count( 'WaitingCalculateTaxes' );
			$nfe_error_count     = $this->get_order_count( 'Error' );
			$nfe_cancelled_count = $this->get_order_count( 'Cancelled' );
			?>

			<li class="nfe-issued-orders">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>">
					<?php
					/* translators: %s: order count */
					printf(
						_n( '<strong>%s receipt</strong> issued', '<strong>%s receipts</strong> issued', $nfe_issued_count, 'woo-nfe' ),
						$nfe_issued_count
					);
					?>
				</a>
			</li>

			<li class="nfe-processing-orders">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>">
					<?php
					/* translators: %s: order count */
					printf(
						_n( '<strong>%s receipt</strong> processing', '<strong>%s receipts</strong> processing', $nfe_issuing_count, 'woo-nfe' ),
						$nfe_issuing_count
					);
					?>
				</a>
			</li>

			<li class="nfe-error-orders">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>">
					<?php
					/* translators: %s: order count */
					printf(
						_n( '<strong>%s receipt</strong> with error', '<strong>%s receipts</strong> with error', $nfe_error_count, 'woo-nfe' ),
						$nfe_error_count
					);
					?>
				</a>
			</li>

			<li class="nfe-cancelled-orders">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>">
					<?php
					/* translators: %s: order count */
					printf(
						_n( '<strong>%s receipt</strong> cancelled', '<strong>%s receipts</strong> cancelled', $nfe_cancelled_count, 'woo-nfe' ),
						$nfe_cancelled_count
					);
					?>
				</a>
			</li>
			<?php
		}

		/**
		 * Adds NFe custom tab
		 *
		 * @param array $product_data_tabs Array of product tabs.
		 * @return array Array with product data tabs
		 */
		public function product_data_tab( $product_data_tabs ) {
			$product_data_tabs['nfe-product-info-tab'] = array(
				'label'  => esc_html__( 'WooCommerce NFe', 'woo-nfe' ),
				'target' => 'nfe_product_info_data',
				'class'  => array( 'hide_if_variable' ),
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
					'label'         => __( 'City Service Code (CityServiceCode)', 'woo-nfe' ),
					'wrapper_class' => 'hide_if_variable',
					'desc_tip'      => 'true',
					'description'   => __( 'City Service Code, this is the code that will identify to the cityhall which type of service you are delivering.', 'woo-nfe' ),
					'value'         => get_post_meta( $post_id, '_simple_cityservicecode', true ),
				) );

				woocommerce_wp_text_input( array(
					'id'            => '_simple_federalservicecode',
					'label'         => __( 'Federal Service Code LC 116 (FederalServiceCode)', 'woo-nfe' ),
					'wrapper_class' => 'hide_if_variable',
					'desc_tip'      => 'true',
					'description'   => __( 'Service Code based on the Federal Law (LC 116), this is a federal code that will identify to the cityhall which type of service you are delivering.', 'woo-nfe' ),
					'value'         => get_post_meta( $post_id, '_simple_federalservicecode', true ),
				) );

				woocommerce_wp_textarea_input( array(
					'id'            => '_simple_nfe_product_desc',
					'label'         => __( 'Service Description', 'woo-nfe' ),
					'wrapper_class' => 'hide_if_variable',
					'desc_tip'      => 'true',
					'description'   => __( 'Put the description that will appear in the receipt. This description must explain in detail what service was delivered. Ask your accountant, if in doubt.', 'woo-nfe' ),
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
				'label'         => __( 'City Service Code (CityServiceCode)', 'woo-nfe' ),
				'desc_tip'      => 'true',
				'description'   => __( 'City Service Code, this is the code that will identify to the cityhall which type of service you are delivering.', 'woo-nfe' ),
				'value'         => get_post_meta( $variation->ID, '_cityservicecode', true ),
			) );

			woocommerce_wp_text_input( array(
				'id'            => '_federalservicecode[' . $product_id . ']',
				'label'         => __( 'Federal Service Code LC 116 (FederalServiceCode)', 'woo-nfe' ),
				'desc_tip'      => 'true',
				'description'   => __( 'Service Code based on the Federal Law (LC 116), this is a federal code that will identify to the cityhall which type of service you are delivering.', 'woo-nfe' ),
				'value'         => get_post_meta( $product_id, '_federalservicecode', true ),
			) );

			woocommerce_wp_textarea_input( array(
				'id'            => '_nfe_product_variation_desc[' . $product_id . ']',
				'label'         => __( 'Service Description', 'woo-nfe' ),
				'desc_tip'      => 'true',
				'description'   => __( 'Put the description that will appear in the receipt. This description must explain in detail what service was delivered. Ask your accountant, if in doubt.', 'woo-nfe' ),
				'value'         => get_post_meta( $product_id, '_nfe_product_variation_desc', true ),
			) );
		}

		/**
		 * Save the NFe fields for product variations.
		 *
		 * @param  int $post_id Product ID.
		 * @return void
		 */
		public function save_variations_fields( $post_id ) {

			// Text Field - City Service Code.
			update_post_meta( $post_id, '_cityservicecode', esc_attr( $_POST['_cityservicecode'][ intval( $post_id ) ] ) );

			// Text Field - Federal Service Code.
			update_post_meta( $post_id, '_federalservicecode', esc_attr( $_POST['_federalservicecode'][ intval( $post_id ) ] ) );

			// TextArea Field - Product Variation Description.
			update_post_meta( $post_id, '_nfe_product_variation_desc', esc_html( $_POST['_nfe_product_variation_desc'][ intval( $post_id ) ] ) );
		}

		/**
		 * Adds the Download and Issue actions to the actions list in the order edit page.
		 *
		 * @param array $actions Order actions array to display.
		 * @return array|void List of actions.
		 */
		public function download_and_issue_actions( $actions ) {
			global $theorder;

			if ( ! is_object( $theorder ) ) {
				$theorder = nfe_wc_get_order( get_the_ID() );
			}

			$order_id = $theorder->get_id();

			if ( ! $order_id ) {
				return;
			}

			$download = get_post_meta( $order_id, 'nfe_issued', true );

			// Load the download action if there is a issue to download.
			if ( ! empty( $download['id'] ) && 'Issued' === $download['status'] ) {
				$actions['nfe_download_order_action'] = __( 'Download NFe receipt', 'woo-nfe' );
			}

			if ( $this->should_we_issue( $download, $theorder ) ) {
				$actions['nfe_issue_order_action'] = __( 'Issue NFe receipt', 'woo-nfe' );
			}

			return $actions;
		}

		/**
		 * NFe receipt downloading action.
		 *
		 * @param  WC_Order $order Order object.
		 * @return void
		 */
		public function download_issue_action( $order ) {

			// Order note.
			$order->add_order_note( esc_html__( 'NFe receipt downloaded.', 'woo-nfe' ) );

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
		 * @param string $column Column id.
		 *
		 * @return void
		 */
		public function order_status_column_content( $column ) {

			// Get information.
			$order    = nfe_wc_get_order( get_the_ID() );
			$order_id = $order->get_id();
			$nfe      = get_post_meta( $order_id, 'nfe_issued', true );

			// Bail early.
			if ( 'nfe_receipts' !== $column ) {
				return;
			}
			?>
			<mark>
				<?php
				$actions = array();

				if ( ! empty( $nfe ) && 'Cancelled' === $nfe['status'] ) {
					$actions['woo_nfe_cancelled'] = array(
						'name'      => __( 'NFe Cancelled', 'woo-nfe' ),
						'action'    => 'woo_nfe_cancelled',
					);
				} elseif ( ! empty( $nfe ) && 'Issued' === $nfe['status'] ) {
					$actions['woo_nfe_emitida'] = array(
						'name'      => __( 'NFe Issued', 'woo-nfe' ),
						'action'    => 'woo_nfe_emitida',
					);
				} elseif ( ! empty( $nfe ) && 'CancelledFailed' === $nfe['status'] ) {
					$actions['woo_nfe_issue'] = array(
						'name'      => __( 'NFe Cancelling Failed', 'woo-nfe' ),
						'action'    => 'woo_nfe_issue',
					);
				} elseif ( ! empty( $nfe ) && 'IssueFailed' === $nfe['status'] ) {
					$actions['woo_nfe_issue'] = array(
						'name'      => __( 'NFe Issuing Failed', 'woo-nfe' ),
						'action'    => 'woo_nfe_issue',
					);
				} elseif ( ! empty( $nfe ) && in_array( $nfe['status'], nfe_processing_status(), true ) ) {
					$actions['woo_nfe_issuing'] = array(
						'name'      => __( 'Processing NFe', 'woo-nfe' ),
						'action'    => 'woo_nfe_issuing',
					);
				} else {
					if ( '0.00' === $order->get_total() ) {
						$actions['woo_nfe_pending_address'] = array(
							'name'      => __( 'Zero Order', 'woo-nfe' ),
							'action'    => 'woo_nfe_pending_address',
						);
					} elseif ( ! nfe_order_address_filled( $order_id ) ) {
						$actions['woo_nfe_pending_address'] = array(
							'name'      => __( 'Pending Address', 'woo-nfe' ),
							'action'    => 'woo_nfe_pending_address',
						);
					} else {
						if ( nfe_get_field( 'issue_past_notes' ) === 'yes' ) {
							if ( nfe_issue_past_orders( $order ) && empty( $nfe['id'] ) ) {
								$actions['woo_nfe_issue'] = array(
									'name'      => __( 'Issue NFe', 'woo-nfe' ),
									'action'    => 'woo_nfe_issue',
								);
							} else {
								$actions['woo_nfe_expired'] = array(
									'name'      => __( 'Issue Expired', 'woo-nfe' ),
									'action'    => 'woo_nfe_expired',
								);
							}
						} else {
							$actions['woo_nfe_issue'] = array(
								'name'      => __( 'Issue NFe', 'woo-nfe' ),
								'action'    => 'woo_nfe_issue',
							);
						}
					}
				}

				foreach ( $actions as $action ) {
					printf( '<span class="woo_nfe_actions %s">%s</span>',
						esc_attr( $action['action'] ),
						esc_attr( $action['name'] )
					);
				}
				?>
			</mark>
			<?php
		}

		/**
		 * Adds NFe information preview on order page.
		 *
		 * @param  WC_Order $order Order object.
		 *
		 * @return void
		 */
		public function display_order_data_preview_in_admin( $order ) {
			$nfe = get_post_meta( $order->get_id(), 'nfe_issued', true );
			?>
			<h4>
				<strong><?php esc_html_e( 'Receipts Details (NFE.io)', 'woo-nfe' ); ?></strong>
				<br />
			</h4>
			<div class="nfe-details">
				<p>
					<strong><?php esc_html_e( 'Status: ', 'woo-nfe' ); ?></strong>
					<?php if ( ! empty( $nfe['status'] ) ) : ?>
						<?php echo esc_html( nfe_status_label( $nfe['status'] ) ); ?>
					<?php endif; ?>
					<br />

					<strong><?php esc_html_e( 'Number: ', 'woo-nfe' ); ?></strong>
					<?php if ( ! empty( $nfe['number'] ) ) : ?>
						<?php echo esc_html( $nfe['number'] ); ?>
					<?php endif; ?>
					<br />

					<strong><?php esc_html_e( 'CheckCode: ', 'woo-nfe' ); ?></strong>
					<?php if ( ! empty( $nfe['checkCode'] ) ) : ?>
						<?php echo esc_html( $nfe['checkCode'] ); ?>
					<?php endif; ?>
					<br />

					<strong><?php esc_html_e( 'Issued On: ', 'woo-nfe' ); ?></strong>
					<?php if ( ! empty( $nfe['issuedOn'] ) ) : ?>
						<?php echo date_i18n( get_option( 'date_format' ), strtotime( $nfe['issuedOn'] ) ); // WPCS: XSS ok. ?>
					<?php endif; ?>
					<br />

					<strong><?php esc_html_e( 'Price: ', 'woo-nfe' ); ?></strong>
					<?php if ( ! empty( $nfe['amountNet'] ) ) : ?>
						<?php echo wc_price( $nfe['amountNet'], array( 'currency' => $order->get_currency() ) ); // WPCS: XSS ok. ?>
					<?php endif; ?>
					<br />

					<strong><?php esc_html_e( 'Fatura: ', 'woo-nfe' );?></strong>
						<?php echo sprintf( '<a href="https://app.nfe.io/companies/'.NFe_Woo()->get_company().'/service-invoices/'.$nfe['id'].'">Link</a>', 'woo-nfe' );?>
					<br />
				</p>
			</div>
			<?php
		}

		/**
		 * Outputs the NFe.io Order Preview Information.
		 *
		 * @since 1.0.8
		 *
		 * @param  array    $fields Order details/data.
		 * @param  WC_Order $order  Order.
		 *
		 * @return array Modified order details.
		 */
		public function nfe_admin_order_preview_details( $fields, $order ) {
			$nfe = get_post_meta( $order->get_id(), 'nfe_issued', true );

			if ( isset( $fields ) ) {

				if ( empty( $nfe ) ) {
					return $fields;
				}

				$fields['nfe'] = [
					'status'     => ! empty( $nfe['status'] ) ? nfe_status_label( $nfe['status'] ) : '',
					'number'     => ! empty( $nfe['number'] ) ? $nfe['number'] : '',
					'check_code' => ! empty( $nfe['checkCode'] ) ? $nfe['checkCode'] : '',
					'issued'     => ! empty( $nfe['issuedOn'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $nfe['issuedOn'] ) ) : '',
				];
			}

			return $fields;
		}

		/**
		 * NFe.io Order Preview HTML.
		 *
		 * @since 1.0.8
		 *
		 * @return void
		 */
		public function nfe_admin_order_preview() {
			?>
			<# if ( data.nfe ) { #>
			<div class="wc-order-preview-addresses">
				<div class="wc-order-preview-address">
					<h2><?php esc_html_e( 'NFe Details', 'woo-nfe' ); ?></h2>

					<# if ( data.nfe.status ) { #>
					<strong><?php esc_html_e( 'Status', 'woo-nfe' ); ?></strong>
					{{{ data.nfe.status }}}
					<# } #>

					<# if ( data.nfe.number ) { #>
					<strong><?php esc_html_e( 'Number', 'woo-nfe' ); ?></strong>
					{{{ data.nfe.number }}}
					<# } #>

					<# if ( data.nfe.check_code ) { #>
					<strong><?php esc_html_e( 'CheckCode', 'woo-nfe' ); ?></strong>
					{{{ data.nfe.check_code }}}
					<# } #>

					<# if ( data.nfe.issued ) { #>
					<strong><?php esc_html_e( 'Issued On', 'woo-nfe' ); ?></strong>
					{{{ data.nfe.issued }}}
					<# } #>
				</div>
			</div>
			<# } #>
			<?php
		}

		/**
		 * Adds the NFe Admin CSS
		 *
		 * @return void
		 */
		public function register_enqueue_css() {
			wp_enqueue_style( 'nfe-woo-admin-css', plugins_url( 'woo-nfe/assets/css/nfe' ) . '.css', [], '1.2.8', false );
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

		/**
		 * Issue Helper Method.
		 *
		 * @param  array    $download NFe info.
		 * @param  WC_Order $order    Order object.
		 *
		 * @return bool
		 */
		protected function should_we_issue( $download, $order ) {

			// Bail for these stati.
			if ( ! empty( $download['status'] ) && ( 'Issued' === $download['status'] || 'Cancelled' === $download['status'] ) ) {
				return false;
			}

			// Bail for zeroed order.
			if ( '0.00' === $order->get_total() ) {
				return false;
			}

			// Bail if there is no address and it is required.
			if ( nfe_require_address() && ! nfe_order_address_filled( $order->get_id() ) ) {
				return false;
			}

			return true;
		}
	}

	return WC_NFe_Admin::get_instance();

endif;
