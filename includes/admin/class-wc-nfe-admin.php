<?php
/**
 * WooCommerce NFe WC_NFe_Admin Class.
 *
 * @author   NFe.io
 *
 * @version  1.0.6
 *
 * @package WooCommerce_NFe/Class/WC_NFe_Admin
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_NFe_Admin' ) ) {

	/**
	 * WC_NFe_Admin.
	 */
	class WC_NFe_Admin {

		/**
		 * The single instance.
		 *
		 * @var null $instance instance.
		 */
		protected static $instance = null;

		/**
		 * Class Constructor.
		 *
		 * @since 1.0.6
		 */
		public function __construct() {
			// Add column to show receipt status updated via NFe.io API (legacy and HPOS order screens).
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'order_status_column_header' ) );
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'order_status_column_header' ) );
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'order_status_column_content' ), 10, 2 );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'order_status_column_content' ), 10, 2 );

			// Addings NFe actions to the order edit screen.
			add_filter( 'woocommerce_order_actions', array( $this, 'download_and_issue_actions' ), 10, 2 );
			add_action( 'woocommerce_order_action_nfe_download_order_action', array( $this, 'download_issue_action' ) );
			add_action( 'woocommerce_order_action_nfe_issue_order_action', array( $this, 'issue_order_action' ) );

			// NFe.io Order Details Preview.
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_order_data_preview_in_admin' ), 20 );
			add_action( 'woocommerce_admin_order_preview_start', array( $this, 'nfe_admin_order_preview' ) );
			add_filter( 'woocommerce_admin_order_preview_get_order_details', array( $this, 'nfe_admin_order_preview_details' ), 20, 2 );

			add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_data_tab' ) );
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variation_fields' ), 10, 3 );
			add_action( 'woocommerce_save_product_variation', array( $this, 'save_variations_fields' ), 10, 2 );
			add_action( 'woocommerce_product_data_panels', array( $this, 'product_data_fields' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'product_data_fields_save' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'register_enqueue_css' ) );
			add_action( 'woocommerce_after_dashboard_status_widget', array( $this, 'nfe_status_widget_order_rows' ) );

			// NFe issue triggers.
			add_action( 'woocommerce_order_status_pending', array( $this, 'issue_trigger' ) );
			add_action( 'woocommerce_order_status_on-hold', array( $this, 'issue_trigger' ) );
			add_action( 'woocommerce_order_status_processing', array( $this, 'issue_trigger' ) );
			add_action( 'woocommerce_order_status_completed', array( $this, 'issue_trigger' ) );

			// WooCommerce Subscriptions Support.
			//
			// The deprecated 'processed_subscription_payments_for_order' hook was
			// dropped. It is NOT replaced by 'woocommerce_subscription_payment_complete':
			// on a renewal both would reach issue_trigger() with the very same
			// order - the subscription hook resolves get_last_order(), which is
			// the renewal order already routed here - and issue two invoices for
			// one payment. Renewals are covered by the renewal hook below and the
			// first order of a subscription by the regular status triggers.
			if ( class_exists( 'WC_Subscriptions' ) ) {
				add_action( 'woocommerce_renewal_order_payment_complete', array( $this, 'issue_trigger' ) );
			}
		}

		/**
		 * Singleton getter.
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Issue a NFe receipt when WooCommerce does its thing.
		 *
		 * @param int $order_id order ID.
		 */
		public function issue_trigger( $order_id ) {
			// Bail early.
			if ( nfe_get_field( 'issue_when' ) === 'manual' ) {
				return;
			}

			// Check if order exists first.
			$order = nfe_wc_get_order( $order_id );

			// Bail for no order.
			if ( ! is_a( $order, 'WC_Order' ) ) {
				return;
			}

			$order_id = $order->get_id();

			// Bail for no order ID.
			if ( ! $order_id ) {
				return;
			}

			// We just can issue the invoice automatically if the status is equal to the configured one.
			if ( $order->has_status( nfe_get_field( 'issue_when_status' ) ) ) {
				NFe_Woo()->issue_invoice( array( $order_id ) );
			}
		}

		/**
		 * Show NFe.io order data is status widget.
		 */
		public function nfe_status_widget_order_rows() {
			if ( ! current_user_can( 'edit_shop_orders' ) ) {
				return;
			}

			$nfe_issued_count    = $this->get_order_count( 'Issued' );
			$nfe_issuing_count   = $this->get_order_count( 'WaitingCalculateTaxes' );
			$nfe_error_count     = $this->get_order_count( 'Error' );
			$nfe_cancelled_count = $this->get_order_count( 'Cancelled' );

			// The order list screen depends on the active order storage (HPOS or legacy posts).
			$orders_url = $this->orders_list_url();
			?>

			<li class="nfe-issued-orders">
				<a href="<?php echo esc_url( $orders_url ); ?>">
					<?php
					// translators: %s: order count.
					printf( wp_kses_post( _n( '<strong>%s receipt</strong> issued', '<strong>%s receipts</strong> issued', $nfe_issued_count, 'nota-fiscal-nfe-io-for-woocommerce' ) ), esc_html( $nfe_issued_count ) );
					?>
				</a>
			</li>

			<li class="nfe-processing-orders">
				<a href="<?php echo esc_url( $orders_url ); ?>">
					<?php
					// translators: %s: order count.
					printf( wp_kses_post( _n( '<strong>%s receipt</strong> processing', '<strong>%s receipts</strong> processing', $nfe_issuing_count, 'nota-fiscal-nfe-io-for-woocommerce' ) ), esc_html( $nfe_issuing_count ) );
					?>
				</a>
			</li>

			<li class="nfe-error-orders">
				<a href="<?php echo esc_url( $orders_url ); ?>">
					<?php
					// translators: %s: order count.
					printf( wp_kses_post( _n( '<strong>%s receipt</strong> with error', '<strong>%s receipts</strong> with error', $nfe_error_count, 'nota-fiscal-nfe-io-for-woocommerce' ) ), esc_html( $nfe_error_count ) );
					?>
				</a>
			</li>

			<li class="nfe-cancelled-orders">
				<a href="<?php echo esc_url( $orders_url ); ?>">
					<?php
					// translators: %s: order count.
					printf( wp_kses_post( _n( '<strong>%s receipt</strong> cancelled', '<strong>%s receipts</strong> cancelled', $nfe_cancelled_count, 'nota-fiscal-nfe-io-for-woocommerce' ) ), esc_html( $nfe_cancelled_count ) );
					?>
				</a>
			</li>
			<?php
		}

		/**
		 * Adds NFe custom tab.
		 *
		 * @param array $product_data_tabs array of product tabs.
		 *
		 * @return array Array with product data tabs.
		 */
		public function product_data_tab( $product_data_tabs ) {
			$product_data_tabs['nfe-product-info-tab'] = array(
				'label'  => esc_html__( 'WooCommerce NFe', 'nota-fiscal-nfe-io-for-woocommerce' ),
				'target' => 'nfe_product_info_data',
				'class'  => array( 'hide_if_variable' ),
			);

			return $product_data_tabs;
		}

		/**
		 * Adds NFe product fields (tab content).
		 */
		public function product_data_fields() {
			$post_id = get_the_ID();
			?>
			<div id="nfe_product_info_data" class="panel woocommerce_options_panel">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_cityservicecode',
						'label'         => __( 'City Service Code (CityServiceCode)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'City Service Code, this is the code that will identify to the cityhall which type of service you are delivering.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_cityservicecode', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_federalservicecode',
						'label'         => __( 'Federal Service Code LC 116 (FederalServiceCode)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'Service Code based on the Federal Law (LC 116), this is a federal code that will identify to the cityhall which type of service you are delivering.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_federalservicecode', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_rtc_nbs_code',
						'label'         => __( 'NBS code (nbsCode)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'RTC NBS code used in service invoice emission.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_rtc_nbs_code', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_rtc_operation_indicator',
						'label'         => __( 'Operation indicator (ibsCbs.operationIndicator)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'RTC operation indicator used in ibsCbs payload.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_rtc_operation_indicator', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_rtc_class_code',
						'label'         => __( 'Class code (ibsCbs.classCode)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'RTC class code used in ibsCbs payload.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_rtc_class_code', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_name',
						'label'         => __( 'Event name (activityEvent.name)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'Name of the activity event. When filled, the activityEvent block will be included in the invoice payload.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_name', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_begin_on',
						'label'         => __( 'Event start (activityEvent.beginOn)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'Start date/time of the activity event (ISO 8601, e.g. 2025-07-01T09:00:00).', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_begin_on', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_end_on',
						'label'         => __( 'Event end (activityEvent.endOn)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'End date/time of the activity event (ISO 8601, e.g. 2025-07-01T18:00:00).', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_end_on', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_code',
						'label'         => __( 'Event code (activityEvent.code)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'Optional free-text code identifying the activity event.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_code', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_address_country',
						'label'         => __( 'Event country (activityEvent.address.country)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'Country where the event takes place (e.g. BRA).', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_address_country', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_address_postal_code',
						'label'         => __( 'Event postal code (activityEvent.address.postalCode)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'Postal/ZIP code of the event address.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_address_postal_code', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_address_street',
						'label'         => __( 'Event street (activityEvent.address.street)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'Street name of the event address.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_address_street', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_address_number',
						'label'         => __( 'Event number (activityEvent.address.number)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'Street number of the event address.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_address_number', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_address_district',
						'label'         => __( 'Event district (activityEvent.address.district)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'District/neighbourhood of the event address.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_address_district', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_address_state',
						'label'         => __( 'Event state (activityEvent.address.state)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'State code of the event address (e.g. SP).', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_address_state', true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'            => '_simple_nfe_activity_event_address_city_code',
						'label'         => __( 'Event city code (activityEvent.address.city.code)', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'IBGE city code of the event address.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_activity_event_address_city_code', true ),
					)
				);

				woocommerce_wp_textarea_input(
					array(
						'id'            => '_simple_nfe_product_desc',
						'label'         => __( 'Service Description', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'wrapper_class' => 'hide_if_variable',
						'desc_tip'      => 'true',
						'description'   => __( 'Put the description that will appear in the receipt. This description must explain in detail what service was delivered. Ask your accountant, if in doubt.', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'value'         => get_post_meta( $post_id, '_simple_nfe_product_desc', true ),
					)
				);
				?>
			</div>
			<?php
		}

		/**
		 * Saving product data information.
		 *
		 * The request is only read after the WooCommerce product meta box nonce checks
		 * out and the current user is allowed to edit this product. Each field then
		 * follows the presence -> sanitize -> store boundary.
		 *
		 * @param int $post_id product ID.
		 */
		public function product_data_fields_save( $post_id ) {

			// Validate the request before reading anything out of it.
			if ( ! isset( $_POST['woocommerce_meta_nonce'] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
				return;
			}

			$post_id = absint( $post_id );

			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			// Text fields: the request key and the meta key are the same.
			$text_fields = array(
				'_simple_cityservicecode',
				'_simple_federalservicecode',
				'_simple_nfe_rtc_nbs_code',
				'_simple_nfe_rtc_operation_indicator',
				'_simple_nfe_rtc_class_code',
				'_simple_nfe_activity_event_name',
				'_simple_nfe_activity_event_begin_on',
				'_simple_nfe_activity_event_end_on',
				'_simple_nfe_activity_event_code',
				'_simple_nfe_activity_event_address_country',
				'_simple_nfe_activity_event_address_postal_code',
				'_simple_nfe_activity_event_address_street',
				'_simple_nfe_activity_event_address_number',
				'_simple_nfe_activity_event_address_district',
				'_simple_nfe_activity_event_address_state',
				'_simple_nfe_activity_event_address_city_code',
			);

			foreach ( $text_fields as $field ) {
				$value = '';

				if ( isset( $_POST[ $field ] ) && ! is_array( $_POST[ $field ] ) ) {
					$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				}

				update_post_meta( $post_id, $field, esc_attr( $value ) );
			}

			// TextArea Field - Product Description.
			$nfe_product_description = '';

			if ( isset( $_POST['_simple_nfe_product_desc'] ) && ! is_array( $_POST['_simple_nfe_product_desc'] ) ) {
				$nfe_product_description = sanitize_textarea_field( wp_unslash( $_POST['_simple_nfe_product_desc'] ) );
			}

			update_post_meta( $post_id, '_simple_nfe_product_desc', esc_html( $nfe_product_description ) );
		}

		/**
		 * Adds the NFe fields for the product variations.
		 *
		 * @param array  $loop           product loop.
		 * @param array  $variation_data product/variation data.
		 * @param string $variation      variation.
		 */
		public function variation_fields( $loop, $variation_data, $variation ) {
			// Product ID.
			$product_id = $variation->ID;

			woocommerce_wp_text_input(
				array(
					'id'          => '_cityservicecode[' . $product_id . ']',
					'label'       => __( 'City Service Code (CityServiceCode)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'City Service Code, this is the code that will identify to the cityhall which type of service you are delivering.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $variation->ID, '_cityservicecode', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_federalservicecode[' . $product_id . ']',
					'label'       => __( 'Federal Service Code LC 116 (FederalServiceCode)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'Service Code based on the Federal Law (LC 116), this is a federal code that will identify to the cityhall which type of service you are delivering.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_federalservicecode', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_rtc_nbs_code[' . $product_id . ']',
					'label'       => __( 'NBS code (nbsCode)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'RTC NBS code used in service invoice emission.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_rtc_nbs_code', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_rtc_operation_indicator[' . $product_id . ']',
					'label'       => __( 'Operation indicator (ibsCbs.operationIndicator)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'RTC operation indicator used in ibsCbs payload.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_rtc_operation_indicator', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_rtc_class_code[' . $product_id . ']',
					'label'       => __( 'Class code (ibsCbs.classCode)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'RTC class code used in ibsCbs payload.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_rtc_class_code', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_name[' . $product_id . ']',
					'label'       => __( 'Event name (activityEvent.name)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'Name of the activity event. When filled, the activityEvent block will be included in the invoice payload.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_name', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_begin_on[' . $product_id . ']',
					'label'       => __( 'Event start (activityEvent.beginOn)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'Start date/time of the activity event (ISO 8601, e.g. 2025-07-01T09:00:00).', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_begin_on', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_end_on[' . $product_id . ']',
					'label'       => __( 'Event end (activityEvent.endOn)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'End date/time of the activity event (ISO 8601, e.g. 2025-07-01T18:00:00).', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_end_on', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_code[' . $product_id . ']',
					'label'       => __( 'Event code (activityEvent.code)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'Optional free-text code identifying the activity event.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_code', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_address_country[' . $product_id . ']',
					'label'       => __( 'Event country (activityEvent.address.country)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'Country where the event takes place (e.g. BRA).', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_address_country', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_address_postal_code[' . $product_id . ']',
					'label'       => __( 'Event postal code (activityEvent.address.postalCode)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'Postal/ZIP code of the event address.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_address_postal_code', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_address_street[' . $product_id . ']',
					'label'       => __( 'Event street (activityEvent.address.street)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'Street name of the event address.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_address_street', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_address_number[' . $product_id . ']',
					'label'       => __( 'Event number (activityEvent.address.number)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'Street number of the event address.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_address_number', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_address_district[' . $product_id . ']',
					'label'       => __( 'Event district (activityEvent.address.district)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'District/neighbourhood of the event address.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_address_district', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_address_state[' . $product_id . ']',
					'label'       => __( 'Event state (activityEvent.address.state)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'State code of the event address (e.g. SP).', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_address_state', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_nfe_activity_event_address_city_code[' . $product_id . ']',
					'label'       => __( 'Event city code (activityEvent.address.city.code)', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'IBGE city code of the event address.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_activity_event_address_city_code', true ),
				)
			);

			woocommerce_wp_textarea_input(
				array(
					'id'          => '_nfe_product_variation_desc[' . $product_id . ']',
					'label'       => __( 'Service Description', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'desc_tip'    => 'true',
					'description' => __( 'Put the description that will appear in the receipt. This description must explain in detail what service was delivered. Ask your accountant, if in doubt.', 'nota-fiscal-nfe-io-for-woocommerce' ),
					'value'       => get_post_meta( $product_id, '_nfe_product_variation_desc', true ),
				)
			);
		}

		/**
		 * Save the NFe fields for product variations.
		 *
		 * WooCommerce saves variations over AJAX (`save-variations` nonce) and, in the
		 * meta box flow, together with the product data (`woocommerce_save_data` nonce).
		 * Either one is accepted, but the capability is always checked before writing.
		 *
		 * @param int $post_id variation ID.
		 */
		public function save_variations_fields( $post_id ) {

			// Validate the request before reading anything out of it.
			$nonce_is_valid = false;

			if ( isset( $_POST['security'] ) ) {
				$nonce_is_valid = (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'save-variations' );
			}

			if ( ! $nonce_is_valid && isset( $_POST['woocommerce_meta_nonce'] ) ) {
				$nonce_is_valid = (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' );
			}

			if ( ! $nonce_is_valid ) {
				return;
			}

			$post_id = absint( $post_id );

			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			// Variation fields are posted as arrays indexed by the variation ID.
			$text_fields = array(
				'_cityservicecode',
				'_federalservicecode',
				'_nfe_rtc_nbs_code',
				'_nfe_rtc_operation_indicator',
				'_nfe_rtc_class_code',
				'_nfe_activity_event_name',
				'_nfe_activity_event_begin_on',
				'_nfe_activity_event_end_on',
				'_nfe_activity_event_code',
				'_nfe_activity_event_address_country',
				'_nfe_activity_event_address_postal_code',
				'_nfe_activity_event_address_street',
				'_nfe_activity_event_address_number',
				'_nfe_activity_event_address_district',
				'_nfe_activity_event_address_state',
				'_nfe_activity_event_address_city_code',
			);

			foreach ( $text_fields as $field ) {
				$value = '';

				if ( isset( $_POST[ $field ] ) && is_array( $_POST[ $field ] )
					&& isset( $_POST[ $field ][ $post_id ] ) && ! is_array( $_POST[ $field ][ $post_id ] ) ) {
					$value = sanitize_text_field( wp_unslash( $_POST[ $field ][ $post_id ] ) );
				}

				update_post_meta( $post_id, $field, esc_attr( $value ) );
			}

			// TextArea Field - Product Variation Description.
			$nfe_product_variation_desc = '';

			if ( isset( $_POST['_nfe_product_variation_desc'] ) && is_array( $_POST['_nfe_product_variation_desc'] )
				&& isset( $_POST['_nfe_product_variation_desc'][ $post_id ] ) && ! is_array( $_POST['_nfe_product_variation_desc'][ $post_id ] ) ) {
				$nfe_product_variation_desc = sanitize_textarea_field( wp_unslash( $_POST['_nfe_product_variation_desc'][ $post_id ] ) );
			}

			update_post_meta( $post_id, '_nfe_product_variation_desc', esc_html( $nfe_product_variation_desc ) );
		}

		/**
		 * Adds the Download and Issue actions to the actions list in the order edit page.
		 *
		 * Since WooCommerce 6.7 the `woocommerce_order_actions` filter passes the order
		 * as its second argument, which works on both the legacy and the HPOS screens.
		 *
		 * @param array    $actions order actions array to display.
		 * @param WC_Order $order   order being edited.
		 *
		 * @return array list of actions.
		 */
		public function download_and_issue_actions( $actions, $order = null ) {
			if ( ! is_a( $order, 'WC_Order' ) ) {
				global $theorder;

				$order = is_a( $theorder, 'WC_Order' ) ? $theorder : null;
			}

			if ( ! is_a( $order, 'WC_Order' ) || ! $order->get_id() ) {
				return $actions;
			}

			$download = nfe_get_order_meta( $order, 'nfe_issued' );

			// Load the download action if there is a issue to download.
			if ( ! empty( $download['id'] ) && 'Issued' === $download['status'] ) {
				$actions['nfe_download_order_action'] = __( 'Download NFe receipt', 'nota-fiscal-nfe-io-for-woocommerce' );
			}

			if ( $this->should_we_issue( $download, $order ) ) {
				$actions['nfe_issue_order_action'] = __( 'Issue NFe receipt', 'nota-fiscal-nfe-io-for-woocommerce' );
			}

			return $actions;
		}

		/**
		 * NFe receipt downloading action.
		 *
		 * @param WC_Order $order order object.
		 */
		public function download_issue_action( $order ) {
			// Order note.
			$order->add_order_note( esc_html__( 'NFe receipt downloaded.', 'nota-fiscal-nfe-io-for-woocommerce' ) );

			WC_NFe_Ajax::download_pdf( $order->get_id() );
		}

		/**
		 * Issuing a NFe receipt.
		 *
		 * @param WC_Order $order order object.
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
		 * @param array $columns array of Columns.
		 *
		 * @return array array of colunms with the NFe one.
		 */
		public function order_status_column_header( $columns ) {
			$column_header = '<span class="tips" data-tip="' . esc_attr__( 'Sales Receipt updated via NFe.io API', 'nota-fiscal-nfe-io-for-woocommerce' ) . '">' . esc_attr__( 'Sales Receipt', 'nota-fiscal-nfe-io-for-woocommerce' ) . '</span>';

			return $this->array_insert_after( 'order_total', $columns, 'nfe_receipts', $column_header );
		}

		/**
		 * Column Content on Order Status.
		 *
		 * Serves both the legacy list table, which passes the order post ID, and the
		 * HPOS order list screen, which passes the WC_Order object.
		 *
		 * @param string       $column column id.
		 * @param int|WC_Order $order  order ID or order object, as given by the screen hook.
		 */
		public function order_status_column_content( $column, $order = 0 ) {
			// Bail early.
			if ( 'nfe_receipts' !== $column ) {
				return;
			}

			// Get information.
			$order = is_a( $order, 'WC_Order' ) ? $order : nfe_wc_get_order( $order );

			if ( ! is_a( $order, 'WC_Order' ) ) {
				return;
			}

			$order_id = $order->get_id();
			$nfe      = nfe_get_order_meta( $order, 'nfe_issued' );
			?>
			<mark>
				<?php
				$actions = array();

				if ( ! empty( $nfe ) && 'Cancelled' === $nfe['status'] ) {
					$actions['woo_nfe_cancelled'] = array(
						'name'   => __( 'NFe Cancelled', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'action' => 'woo_nfe_cancelled',
					);
				} elseif ( ! empty( $nfe ) && 'Issued' === $nfe['status'] ) {
					$actions['woo_nfe_emitida'] = array(
						'name'   => __( 'NFe Issued', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'action' => 'woo_nfe_emitida',
					);
				} elseif ( ! empty( $nfe ) && 'CancelFailed' === $nfe['status'] ) {
					$actions['woo_nfe_issue'] = array(
						'name'   => __( 'NFe Cancelling Failed', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'action' => 'woo_nfe_issue',
					);
				} elseif ( ! empty( $nfe ) && 'IssueFailed' === $nfe['status'] ) {
					$actions['woo_nfe_issue'] = array(
						'name'   => __( 'NFe Issuing Failed', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'action' => 'woo_nfe_issue',
					);
				} elseif ( ! empty( $nfe ) && in_array( $nfe['status'], nfe_processing_status(), true ) ) {
					$actions['woo_nfe_issuing'] = array(
						'name'   => __( 'Processing NFe', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'action' => 'woo_nfe_issuing',
					);
				} elseif ( ! empty( $nfe ) && 'Processing' === $nfe['status'] ) {
					$actions['woo_nfe_issue'] = array(
						'url'    => '#',
						'name'   => __( 'NFe Processing', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'action' => 'woo_nfe_issue',
					);
				} elseif ( $order->get_total() === '0.00' ) {
					$actions['woo_nfe_pending_address'] = array(
						'name'   => __( 'Zero Order', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'action' => 'woo_nfe_pending_address',
					);
				} elseif ( ! nfe_order_address_filled( $order_id ) ) {
					$actions['woo_nfe_pending_address'] = array(
						'name'   => __( 'Pending Address', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'action' => 'woo_nfe_pending_address',
					);
				} elseif ( nfe_get_field( 'issue_past_notes' ) === 'yes' ) {
					if ( nfe_issue_past_orders( $order ) && empty( $nfe['id'] ) ) {
						$actions['woo_nfe_issue'] = array(
							'name'   => __( 'Issue NFe', 'nota-fiscal-nfe-io-for-woocommerce' ),
							'action' => 'woo_nfe_issue',
						);
					} else {
						$actions['woo_nfe_expired'] = array(
							'name'   => __( 'Issue Expired', 'nota-fiscal-nfe-io-for-woocommerce' ),
							'action' => 'woo_nfe_expired',
						);
					}
				} else {
					$actions['woo_nfe_issue'] = array(
						'name'   => __( 'Issue NFe', 'nota-fiscal-nfe-io-for-woocommerce' ),
						'action' => 'woo_nfe_issue',
					);
				}

				foreach ( $actions as $action ) {
					printf(
						'<span class="woo_nfe_actions %s">%s</span>',
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
		 * @param WC_Order $order order object.
		 */
		public function display_order_data_preview_in_admin( $order ) {
			$nfe = nfe_get_order_meta( $order, 'nfe_issued' );
			?>
			<h4>
				<strong><?php esc_html_e( 'Receipts Details (NFE.io)', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
				<br />
			</h4>
			<div class="nfe-details">
				<p>
					<strong><?php esc_html_e( 'Status: ', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
					<?php if ( ! empty( $nfe['status'] ) ) { ?>
						<?php echo esc_html( nfe_status_label( $nfe['status'] ) ); ?>
					<?php } ?>
					<br />

					<strong><?php esc_html_e( 'Number: ', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
					<?php if ( ! empty( $nfe['number'] ) ) { ?>
						<?php echo esc_html( $nfe['number'] ); ?>
					<?php } ?>
					<br />

					<strong><?php esc_html_e( 'CheckCode: ', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
					<?php if ( ! empty( $nfe['checkCode'] ) ) { ?>
						<?php echo esc_html( $nfe['checkCode'] ); ?>
					<?php } ?>
					<br />

					<strong><?php esc_html_e( 'Issued On: ', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
					<?php if ( ! empty( $nfe['issuedOn'] ) ) { ?>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $nfe['issuedOn'] ) ) ); ?>
					<?php } ?>
					<br />

					<strong><?php esc_html_e( 'Price: ', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
					<?php if ( ! empty( $nfe['amountNet'] ) ) { ?>
						<?php echo wp_kses_post( wc_price( $nfe['amountNet'], array( 'currency' => $order->get_currency() ) ) ); ?>
					<?php } ?>
					<br />

					<?php if ( ! empty( $nfe['id'] ) ) { ?>
					<strong><?php esc_html_e( 'Fatura: ', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
						<?php
						$nfe_invoice_url = 'https://app.nfe.io/companies/' . rawurlencode( (string) NFe_Woo()->get_company() ) . '/service-invoices/' . rawurlencode( (string) $nfe['id'] );
						?>
						<a href="<?php echo esc_url( $nfe_invoice_url ); ?>"><?php esc_html_e( 'Link', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></a>
					<br />
					<?php } ?>


					<?php
					include_once ABSPATH . 'wp-admin/includes/plugin.php';
					if ( ! function_exists( 'is_plugin_active' ) ) {
						return;
					}
					esc_html_e( 'Plugin Custom Fields: ', 'nota-fiscal-nfe-io-for-woocommerce' );

					if ( is_plugin_active( 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php' ) ) {
						echo 'OK';
					} else {
						echo 'NOT_OK';
					}
					?>

				</p>
			</div>
			<?php
		}

		/**
		 * Outputs the NFe.io Order Preview Information.
		 *
		 * @since 1.0.8
		 *
		 * @param array    $fields order details/data.
		 * @param WC_Order $order  order.
		 *
		 * @return array modified order details.
		 */
		public function nfe_admin_order_preview_details( $fields, $order ) {
			$nfe = nfe_get_order_meta( $order, 'nfe_issued' );

			if ( isset( $fields ) ) {
				if ( empty( $nfe ) ) {
					return $fields;
				}

				$fields['nfe'] = array(
					'status'     => ! empty( $nfe['status'] ) ? nfe_status_label( $nfe['status'] ) : '',
					'number'     => ! empty( $nfe['number'] ) ? $nfe['number'] : '',
					'check_code' => ! empty( $nfe['checkCode'] ) ? $nfe['checkCode'] : '',
					'issued'     => ! empty( $nfe['issuedOn'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $nfe['issuedOn'] ) ) : '',
				);
			}

			return $fields;
		}

		/**
		 * NFe.io Order Preview HTML.
		 *
		 * @since 1.0.8
		 */
		public function nfe_admin_order_preview() {
			?>
			<# if ( data.nfe ) { #>
			<div class="wc-order-preview-addresses">
				<div class="wc-order-preview-address">
					<h2><?php esc_html_e( 'NFe Details', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></h2>

					<# if ( data.nfe.status ) { #>
					<strong><?php esc_html_e( 'Status', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
					{{ data.nfe.status }}
					<# } #>

					<# if ( data.nfe.number ) { #>
					<strong><?php esc_html_e( 'Number', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
					{{ data.nfe.number }}
					<# } #>

					<# if ( data.nfe.check_code ) { #>
					<strong><?php esc_html_e( 'CheckCode', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
					{{ data.nfe.check_code }}
					<# } #>

					<# if ( data.nfe.issued ) { #>
					<strong><?php esc_html_e( 'Issued On', 'nota-fiscal-nfe-io-for-woocommerce' ); ?></strong>
					{{ data.nfe.issued }}
					<# } #>
				</div>
			</div>
			<# } #>
			<?php
		}

		/**
		 * Adds the NFe Admin CSS.
		 */
		public function register_enqueue_css() {
			// Derived from the main plugin file, not from a hardcoded folder name --
			// the folder was renamed once and this line broke silently with a 404.
			wp_enqueue_style( 'nfe-woo-admin-css', plugins_url( 'assets/css/nfe.css', WOOCOMMERCE_NFE_FILE ), array(), '1.2.8', false );
		}

		/**
		 * URL of the order list screen for the active order storage.
		 *
		 * HPOS moves the order list to `admin.php?page=wc-orders`; the legacy post
		 * storage keeps it on `edit.php?post_type=shop_order`.
		 *
		 * @since 1.5.0
		 *
		 * @return string admin URL of the order list screen.
		 */
		protected function orders_list_url() {
			if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' )
				&& method_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled' )
				&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
				return admin_url( 'admin.php?page=wc-orders' );
			}

			return admin_url( 'edit.php?post_type=shop_order' );
		}

		/**
		 * Get orders count.
		 *
		 * Counting is delegated to nfe_count_orders_by_invoice_status(), which
		 * asks the WooCommerce order query for a paginated total and therefore
		 * returns the same number under HPOS and on the legacy post storage.
		 *
		 * @since 1.5.0 Counts through the WooCommerce order query instead of WP_Query.
		 *
		 * @param string $value NFe status.
		 *
		 * @return int
		 */
		protected function get_order_count( $value ) {
			return nfe_count_orders_by_invoice_status( $value );
		}

		/**
		 * Inserts a new key/value after the key in the array.
		 *
		 * @param string $needle    the array key to insert the element after.
		 * @param array  $haystack  an array to insert the element into.
		 * @param string $new_key   the key to insert.
		 * @param string $new_value an value to insert.
		 *
		 * @return The new array if the $needle key exists, otherwise an unmodified $haystack.
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
		 * @param array    $download NFe info.
		 * @param WC_Order $order    order object.
		 *
		 * @return bool
		 */
		protected function should_we_issue( $download, $order ) {
			// Bail for these stati.
			if ( ! empty( $download['status'] ) && ( 'Issued' === $download['status'] || 'Cancelled' === $download['status'] ) ) {
				return false;
			}

			// Bail for zeroed order.
			if ( $order->get_total() === '0.00' ) {
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
}
