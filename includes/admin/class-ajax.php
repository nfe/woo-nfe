<?php
/**
 * Exit if accessed directly.
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce NFe Ajax Class
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Ajax
 * @version  1.0.4
 */
class WC_NFe_Ajax {

	/**
	 * Bootstraps the class and hooks required actions
	 */
	public static function init() {
		add_action( 'wp_loaded', array( __CLASS__, 'front_issue' ), 20 );
		add_action( 'wp_loaded', array( __CLASS__, 'front_download_pdf' ), 20 );
	}

	/**
	 * NFe issue from the front-end.
	 *
	 * @return void
	 */
	public static function front_issue() {
		$get       = wp_unslash( $_GET );
		$nfe_issue = sanitize_text_field( $get['nfe_issue'] );
		$wp_nonce  = sanitize_text_field( $get['_wpnonce'] );
		// Nothing to do.
		if ( ! isset( $nfe_issue ) || ! is_user_logged_in() || ! isset( $wp_nonce ) || ! wp_verify_nonce( $wp_nonce, 'woocommerce_nfe_issue' ) ) {
			return;
		}

		$order = nfe_wc_get_order( absint( $nfe_issue ) );

		// Bail if there is no order id or it is false.
		if ( empty( $order->id ) || ! $order ) {
			return;
		}

		if ( ! nfe_order_address_filled( $order->id ) ) {
			wc_add_notice( __( 'The order is missing important NFe information, update it before trying to issue it.', 'woo-nfe' ), 'error' );
		} else {
			NFe_Woo()->issue_invoice( array( $order->id ) );

			wc_add_notice( __( 'NFe was issued successfully.', 'woo-nfe' ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	/**
	 * Download NFe from the Front-end
	 */
	public static function front_download_pdf() {
		$get              = wp_unslash( $_GET );
		$nfe_download_pdf = sanitize_text_field( $get['nfe_download_pdf'] );
		$wp_nonce         = sanitize_text_field( $get['_wpnonce'] );
		if ( ! isset( $nfe_download_pdf ) || ! is_user_logged_in() || ! isset( $wp_nonce ) || ! wp_verify_nonce( $wp_nonce, 'woocommerce_nfe_download_pdf' ) ) {
			return;
		}

		$order = nfe_wc_get_order( absint( $nfe_download_pdf ) );

		// Bail if there is no order id or it is false.
		if ( empty( $order->id ) || ! $order ) {
			return;
		}

		self::download_pdf( $order->id );
	}

	/**
	 * Download base.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string PDF
	 */
	public static function download_pdf( $order_id ) {
		// Bail if there is no order id.
		if ( empty( $order_id ) ) {
			return;
		}

		$nfe = get_post_meta( $order_id, 'nfe_issued', true );

		// Bail if there is no receipt id.
		if ( empty( $nfe['id'] ) ) {
			return;
		}

		// PDF Location/directory, file name.
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/nfe/';
		$name       = "nfse-{$nfe['id']}.pdf";
		$file       = $dir . $name;

		// Create directory if it doesn't already exist.
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}

		// Check if file already exists.
		if ( ! file_exists( $file ) ) {
			// Save PDF info fetched from NFe API.
			$pdf = NFe_Woo()->download_pdf_invoice( array( $order_id ) );

			// If it doesn't, put the content on this pdf.
			file_put_contents( $file, file_get_contents( $pdf ) );
		}

		$size = filesize( $file );

		// Download the PDF.
		self::output_pdf( $name, $size, $file );
	}

	/**
	 * PDF Outputting
	 *
	 * @param string $name Name of the PDF.
	 * @param int    $size Size of the PDF.
	 * @param string $file PDF information with path location.
	 * @return void
	 */
	public static function output_pdf( $name, $size, $file ) {
		// turn off output buffering to decrease cpu usage.
		@ob_end_clean();

		// required for IE, otherwise Content-Disposition may be ignored.
		if ( ini_get( 'zlib.output_compression' ) ) {
			ini_set( 'zlib.output_compression', 'Off' );
		}

		// Download it.
		header( 'Content-type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Accept-Ranges: bytes' );
		header( 'Content-Length: ' . $size );

		// The three lines below basically make the	download_pdf non-cacheable.
		header( 'Cache-control: private' );
		header( 'Pragma: private' );
		header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );

		// multipart-download_pdf and download_pdf resuming support.
		$http_range_header = isset( $_SERVER['HTTP_RANGE'] ) ? wp_unslash( $_SERVER['HTTP_RANGE'] ) : ''; // phpcs:ignore
		if ( ! empty( $http_range_header ) ) {
			list( $a, $range )         = explode( '=', $http_range_header, 2 );
			list( $range )             = explode( ',', $range, 2 );
			list( $range, $range_end ) = explode( '-', $range );
			$range                     = intval( $range );

			if ( ! $range_end ) {
				$range_end = $size - 1;
			} else {
				$range_end = intval( $range_end );
			}
			$new_length = $range_end - $range + 1;
			header( 'HTTP/1.1 206 Partial Content' );
			header( 'Content-Length: $new_length' );
			header( 'Content-Range: bytes ' . $range - $range_end / $size );
		} else {
			$new_length = $size;
			header( 'Content-Length: ' . $size );
		}

		// output the file itself.
		$chunksize  = 1 * ( 1024 * 1024 );
		$bytes_send = 0;
		$file       = fopen( $file, 'r' );

		if ( $file ) {
			if ( ! empty( $http_range_header ) ) {
				fseek( $file, $range );
			}

			while ( ! feof( $file ) && ! connection_aborted() && ( $bytes_send < $new_length ) ) {
				$buffer = fread( $file, $chunksize );
				echo( $buffer );
				flush();
				$bytes_send += strlen( $buffer );
			}
			fclose( $file );
		} else {
			wp_die( 'Error - can not open file.' );
		}
	}
}

WC_NFe_Ajax::init();
