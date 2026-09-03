<?php
/**
 * WooCommerce NFe Ajax Class
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Ajax
 * @version  1.0.4
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce NFe Ajax Class
 */
class WC_NFe_Ajax {

	/**
	 * Bootstraps the class and hooks required actions.
	 *
	 * The front-end links built in WC_NFe_FrontEnd point to admin-ajax.php with
	 * the actions below, so the handlers only run when one of them is called,
	 * instead of on every request.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_woocommerce_nfe_issue', array( __CLASS__, 'front_issue' ) );
		add_action( 'wp_ajax_woocommerce_nfe_download', array( __CLASS__, 'front_download_pdf' ) );
	}

	/**
	 * Validates a front-end request and returns the order it targets.
	 *
	 * Applies the input boundary in order: presence guard, sanitization by type
	 * and domain validation (nonce, existing order, ownership or capability).
	 *
	 * @since 1.5.0
	 *
	 * @param string $nonce_action nonce action signed by the front-end link.
	 *
	 * @return WC_Order|false the order when the request is legitimate, false otherwise.
	 */
	private static function get_request_order( $nonce_action ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return false;
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		if ( empty( $order_id ) ) {
			return false;
		}

		$order = nfe_wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) || empty( $order->get_id() ) ) {
			return false;
		}

		if ( ! self::current_user_can_manage_order( $order ) ) {
			return false;
		}

		return $order;
	}

	/**
	 * Whether the current user may act on the given order.
	 *
	 * Shop managers act on any order; everybody else only on their own.
	 *
	 * @since 1.5.0
	 *
	 * @param WC_Order $order order object.
	 *
	 * @return bool
	 */
	private static function current_user_can_manage_order( $order ) {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		$customer_id = (int) $order->get_customer_id();

		return $customer_id > 0 && get_current_user_id() === $customer_id;
	}

	/**
	 * Refuses the request without any side effect.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	private static function deny_request() {
		wp_die(
			esc_html__( 'You are not allowed to perform this action.', 'nota-fiscal-nfe-io-for-woocommerce' ),
			esc_html__( 'Forbidden', 'nota-fiscal-nfe-io-for-woocommerce' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * NFe issue from the front-end.
	 *
	 * Handles admin-ajax.php?action=woocommerce_nfe_issue&order_id=N, signed
	 * with the woo_nfe_issue nonce.
	 *
	 * @return void
	 */
	public static function front_issue() {
		$order = self::get_request_order( 'woo_nfe_issue' );

		if ( ! $order ) {
			self::deny_request();
		}

		if ( ! nfe_order_address_filled( $order ) ) {
			wc_add_notice( __( 'The order is missing important NFe information, update it before trying to issue it.', 'nota-fiscal-nfe-io-for-woocommerce' ), 'error' );
		} elseif ( NFe_Woo()->issue_invoice( array( $order->get_id() ) ) ) {
			// Accepted by the API, not finished: the document is issued
			// asynchronously and confirmed later by the webhook. Saying it is
			// already issued would be the same mistake the receipt e-mail used
			// to make.
			wc_add_notice( __( 'The NFe request was sent. You will be notified when the receipt is issued.', 'nota-fiscal-nfe-io-for-woocommerce' ) );
		} else {
			// The request was refused before reaching the API -- an invoice is
			// already in flight, the order is worth nothing, or the API rejected
			// it. The order notes carry the reason.
			wc_add_notice( __( 'The NFe could not be requested for this order. Please check the order details or try again later.', 'nota-fiscal-nfe-io-for-woocommerce' ), 'error' );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	/**
	 * Download NFe from the Front-end.
	 *
	 * Handles admin-ajax.php?action=woocommerce_nfe_download&order_id=N, signed
	 * with the woo_nfe_download nonce.
	 *
	 * @return void
	 */
	public static function front_download_pdf() {
		$order = self::get_request_order( 'woo_nfe_download' );

		if ( ! $order ) {
			self::deny_request();
		}

		self::download_pdf( $order->get_id() );
	}

	/**
	 * Streams the invoice PDF to the browser.
	 *
	 * The bytes come straight from the API through the SDK. Everything that
	 * used to sit in between is gone, and each piece of it was broken:
	 *
	 * - The local cache under uploads/nfe/ wrote `file_put_contents( $file,
	 *   wp_remote_get( $pdf ) )`, storing the *response array* rather than its
	 *   body, so every cached file contained the literal string "Array".
	 * - Those files were named predictably (nfse-{id}.pdf) inside a publicly
	 *   served directory, so a fiscal document carrying the buyer's name, tax
	 *   ID and address could be fetched by guessing the URL, with none of the
	 *   authorisation this handler performs.
	 * - The range-request reader sent `Content-Length: $new_length` in single
	 *   quotes (the literal variable name, not its value) and computed
	 *   Content-Range as `'bytes ' . $range - $range_end / $size`, whose
	 *   precedence produced values like "bytes -0.08".
	 *
	 * A NFS-e PDF is a small document fetched once, so serving it in one piece
	 * removes all of that without costing anything.
	 *
	 * @since 1.5.0 Serves SDK bytes directly; no cache, no range requests.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public static function download_pdf( $order_id ) {
		// Bail if there is no order id.
		if ( empty( $order_id ) ) {
			return;
		}

		$nfe = nfe_get_order_meta( $order_id, 'nfe_issued' );

		// Bail if there is no receipt id.
		if ( ! is_array( $nfe ) || empty( $nfe['id'] ) ) {
			return;
		}

		$pdf = NFe_Woo()->download_pdf_invoice( array( $order_id ) );

		if ( ! is_string( $pdf ) || '' === $pdf ) {
			wp_die(
				esc_html__( 'The NFe receipt could not be downloaded right now. Please try again in a few minutes.', 'nota-fiscal-nfe-io-for-woocommerce' ),
				esc_html__( 'Download failed', 'nota-fiscal-nfe-io-for-woocommerce' ),
				array( 'response' => 502 )
			);
		}

		self::output_pdf( self::pdf_filename( $nfe['id'] ), $pdf );
	}

	/**
	 * Builds a safe download filename from an invoice id.
	 *
	 * The id reaches this point from order meta, which the webhook writes from
	 * a request body. Anything outside this character set is dropped so it can
	 * never break out of the Content-Disposition header.
	 *
	 * @since 1.5.0
	 *
	 * @param string $invoice_id NFe.io invoice id.
	 *
	 * @return string
	 */
	protected static function pdf_filename( $invoice_id ) {
		$safe_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $invoice_id );

		return 'nfse-' . ( '' === $safe_id ? 'receipt' : $safe_id ) . '.pdf';
	}

	/**
	 * PDF Outputting.
	 *
	 * @since 1.5.0 Takes the bytes themselves instead of a path to read.
	 *
	 * @param string $name  File name offered to the browser.
	 * @param string $bytes Raw PDF bytes.
	 *
	 * @return void
	 */
	public static function output_pdf( $name, $bytes ) {
		// Drop anything already buffered so it cannot end up inside the file.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		if ( ! headers_sent() ) {
			nocache_headers();

			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . $name . '"' );
			header( 'Content-Length: ' . strlen( $bytes ) );
			header( 'X-Content-Type-Options: nosniff' );
		}

		/*
		 * Binary passthrough: these are raw PDF bytes, not HTML. The
		 * Content-Type header above defines the output context, and any HTML
		 * escaping here would corrupt the file.
		 */
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw PDF bytes; HTML escaping would corrupt the download.

		exit;
	}
}

WC_NFe_Ajax::init();
