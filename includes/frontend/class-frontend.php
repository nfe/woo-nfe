<?php
/**
 * WooCommerce NFe WC_NFe_FrontEnd Class.
 *
 * @author   NFe.io.
 *
 * @version  1.0.4
 *
 * @package WooCommerce_NFe/Class/Frontend/WC_NFe_FrontEnd
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_NFe_FrontEnd' ) ) {

	/**
	 * WC_NFe_FrontEnd.
	 */
	class WC_NFe_FrontEnd {
		/**
		 * Constructor.
		 *
		 * @since 1.0.4
		 */
		public function __construct() {
			// Filters.
			add_filter( 'woocommerce_my_account_my_orders_columns', array( $this, 'nfe_column' ) );
			add_filter( 'woocommerce_my_account_my_address_description', array( $this, 'account_desc' ) );

			// Actions.
			add_action( 'woocommerce_my_account_my_orders_column_sales-receipt', array( $this, 'column_content' ) );
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'column_content' ) );
			add_action( 'woocommerce_before_edit_address_form_billing', array( $this, 'billing_notice' ) );
		}

		/**
		 * Notice added on the WooCommerce edit-address page.
		 */
		public function billing_notice() {
			if ( nfe_get_field( 'nfe_enable' ) === 'yes' ) {
				echo wp_kses_post( '<div class="woocommerce-message">' . __( 'The following address will <strong>also</strong> be used when issuing a NFe Sales Receipt.', 'woo-nfe' ) . '</div>' );
			}
		}

		/**
		 * Notice added in the My Account page.
		 *
		 * @return string
		 */
		public function account_desc() {
			return esc_html__( 'The following address(es) will be used on the checkout page by default and also when issuing a NFe sales receipt.', 'woo-nfe' );
		}

		/**
		 * NFe Column Header on Recent Orders.
		 *
		 * @param array $columns columns.
		 *
		 * @return array
		 */
		public function nfe_column( $columns ) {
			$new_columns = array();

			foreach ( $columns as $column_name => $column_info ) {
				$new_columns[ $column_name ] = $column_info;

				if ( 'order-total' === $column_name ) {
					$new_columns['sales-receipt'] = esc_html__( 'Sales Receipt', 'woo-nfe' );
				}
			}

			return $new_columns;
		}

		/**
		 * NFe Sales Receipt Column Content on Recent Orders.
		 *
		 * @since 1.0.9
		 *
		 * @param WC_Order $order order object.
		 */
		public function column_content( $order ) {
			if ( ! is_a( $order, 'WC_Order' ) ) {
				return;
			}

			if ( nfe_get_field( 'nfe_enable' ) !== 'yes' ) {
				return;
			}

			// Get order information.
			$order_id = $order->get_id();
			$nfe      = nfe_get_order_meta( $order, 'nfe_issued' );
			$nfe      = is_array( $nfe ) ? $nfe : array();

			// Presence guard: the meta may exist without these keys.
			$nfe_status = isset( $nfe['status'] ) ? $nfe['status'] : '';
			$nfe_id     = isset( $nfe['id'] ) ? $nfe['id'] : '';

			// Build actions. Labels and URLs are escaped at the point of output, below.
			$actions = array();

			if ( 'Cancelled' === $nfe_status ) {
				$actions['woo_nfe_cancelled'] = array(
					'url'    => '#',
					'name'   => __( 'NFe Cancelled', 'woo-nfe' ),
					'action' => 'woo_nfe_cancelled',
				);
			} elseif ( 'Issued' === $nfe_status && ! empty( $nfe_id ) ) {
				// An issued receipt is exactly the case where the customer wants
				// the file. This branch sits ahead of the generic download one
				// below, so while it rendered a dead '#' label the download was
				// unreachable for every successfully issued invoice.
				$actions['woo_nfe_download'] = array(
					'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_download&order_id=' . $order_id ), 'woo_nfe_download' ),
					'name'   => __( 'Download NFe', 'woo-nfe' ),
					'action' => 'woo_nfe_download',
				);
			} elseif ( 'Issued' === $nfe_status ) {
				$actions['woo_nfe_emitida'] = array(
					'url'    => '#',
					'name'   => __( 'NFe Issued', 'woo-nfe' ),
					'action' => 'woo_nfe_emitida',
				);
			} elseif ( 'CancelFailed' === $nfe_status ) {
				$actions['woo_nfe_issue'] = array(
					'url'    => '#',
					'name'   => __( 'NFe Cancelling Failed', 'woo-nfe' ),
					'action' => 'woo_nfe_issue',
				);
			} elseif ( 'IssueFailed' === $nfe_status ) {
				$actions['woo_nfe_issue'] = array(
					'url'    => '#',
					'name'   => __( 'NFe Issuing Failed', 'woo-nfe' ),
					'action' => 'woo_nfe_issue',
				);
			} elseif ( 'Processing' === $nfe_status ) {
				$actions['woo_nfe_issue'] = array(
					'url'    => '#',
					'name'   => __( 'NFe Processing', 'woo-nfe' ),
					'action' => 'woo_nfe_issue',
				);
			} elseif ( in_array( $nfe_status, nfe_processing_status(), true ) ) {
				$actions['woo_nfe_issuing'] = array(
					'url'    => '#',
					'name'   => __( 'Processing NFe', 'woo-nfe' ),
					'action' => 'woo_nfe_issuing',
				);
			} elseif ( ! nfe_order_address_filled( $order_id ) ) {
				$actions['woo_nfe_pending_address'] = array(
					'url'    => wc_get_endpoint_url( 'edit-address' ),
					'name'   => __( 'Pending Address', 'woo-nfe' ),
					'action' => 'woo_nfe_pending_address',
				);
			} elseif ( ! empty( $nfe_id ) ) {
				$actions['woo_nfe_download'] = array(
					'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_download&order_id=' . $order_id ), 'woo_nfe_download' ),
					'name'   => __( 'Download NFe', 'woo-nfe' ),
					'action' => 'woo_nfe_download',
				);
			} elseif ( nfe_get_field( 'issue_past_notes' ) === 'yes' ) {
				if ( nfe_issue_past_orders( $order ) && empty( $nfe_id ) ) {
					$actions['woo_nfe_issue'] = array(
						'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_issue&order_id=' . $order_id ), 'woo_nfe_issue' ),
						'name'   => __( 'Issue NFe', 'woo-nfe' ),
						'action' => 'woo_nfe_issue',
					);
				} else {
					$actions['woo_nfe_expired'] = array(
						'url'    => '#',
						'name'   => __( 'Issue Expired', 'woo-nfe' ),
						'action' => 'woo_nfe_expired',
					);
				}
			} else {
				$actions['woo_nfe_issue'] = array(
					'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_issue&order_id=' . $order_id ), 'woo_nfe_issue' ),
					'name'   => __( 'Issue NFe', 'woo-nfe' ),
					'action' => 'woo_nfe_issue',
				);
			}

			// Compared numerically: get_total() is a formatted string, so '0',
			// '0.0000' and a zero with a different decimal separator all slipped
			// past the old '0.00' string match.
			if ( (float) $order->get_total() <= 0 ) {
				$actions = array();
			}

			foreach ( $actions as $action ) {
				printf(
					'<a class="button view %1$s" href="%2$s" data-tip="%3$s">%4$s</a>',
					esc_attr( $action['action'] ),
					esc_url( $action['url'] ),
					esc_attr( $action['name'] ),
					esc_html( $action['name'] )
				);
			}
		}
	}

	return new WC_NFe_FrontEnd();
}
