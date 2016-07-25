<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('WC_NFe_Ajax') ) :

/**
 * WooCommerce NFe Ajax Class
 * 
 * @package 	WooCommerce_NFe/Class/WC_NFe_Ajax
 * @author  	NFe.io
 * @version     1.0.0
 */
class WC_NFe_Ajax {

	/**
	 * Bootstraps the class and hooks required actions
	 */
	public static function init() {
		// Back-end Ajax
		$ajax_events = array(
			'nfe_issue'     => false,
			'nfe_download'  => false,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}

		// Front-end Ajax
		add_action( 'wp_loaded', array( __CLASS__, 'front_issue' ), 20 );
		add_action( 'wp_loaded', array( __CLASS__, 'front_download' ), 20 );
	}

	/**
	 * NFe issue from the back-end
	 */
	public static function nfe_issue() {
		if ( ! current_user_can( 'edit_shop_orders' ) && ! check_admin_referer( 'woo_nfe_issue' ) ) {
			return;
		}

		$order = nfe_wc_get_order( absint( $_GET['order_id'] ) );

		// Bail if there is no order id
		if ( empty( $order->id ) ) {
			return;
		}

		// Bail if not completed
		if ( ! $order->has_status( 'completed' ) ) {
			return;
		}

		// Bail if user needs to update address
		if ( ! nfe_order_address_filled( $order->id ) ) {
			NFe_Woo()->issue_invoice( array( $order->id ) );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		die();
	}

	/**
	 * NFe issue from the front-end
	 */
	public static function front_issue() {
		// Nothing to do
		if ( ! isset( $_GET['nfe_issuue'] ) || ! is_user_logged_in() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'woocommerce_nfe_issue' ) ) {
			return;
		}

		$order = nfe_wc_get_order( absint( $_GET['nfe_issue'] ) );

		// Bail if there is no order id
		if ( empty( $order->id ) ) {
			return;
		}

		// Bail if not completed
		if ( ! $order->has_status( 'completed' ) ) {
			return;
		}

		if ( nfe_order_address_filled( $order->id ) ) {
			wc_add_notice( __( 'The order is missing important NFe information, update it before trying to issue it.', 'woocommerce-nfe' ), 'error' );
		}
		else {
			NFe_Woo()->issue_invoice( array( $order->id ) );

			wc_add_notice( __( 'NFe was issued successfully.', 'woocommerce-nfe' ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	/**
	 * Download NFe from the back-end
	 */
	public static function nfe_download() {
		if ( ! current_user_can( 'edit_shop_orders' ) && ! check_admin_referer( 'woo_nfe_download' ) ) {
			return;
		}

		$order_id = absint( $_GET['order_id'] );

		self::download_base( $order_id );
	}

	/**
	 * Download NFe from the Front-end
	 */
	public static function front_download() {
		// Nothing to do
		if ( ! isset( $_GET['nfe_download'] ) || ! is_user_logged_in() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'woocommerce_nfe_download' ) ) {
			return;
		}

		$order_id = absint( $_GET['nfe_download'] );

		self::download_base( $order_id );
	}

	/**
	 * Download Base
	 */
	public static function download_base( $order_id ) {
		// Bail if there is no order id
		if ( empty( $order_id ) ) {
			return;
		}

		// PDF Location/directory, file name
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/nfe/';
		$name       = 'nfe-'. $order_id . '.pdf';
		$file       = $dir . $name;

		// Create directory if it doesn't already exist
		if ( ! is_dir($dir) ) {
			mkdir($dir, 0777, true);
		}

		// Check if file already exists
		if ( ! file_exists($file) ) {
			// Save PDF info fetched from NFe API
			$pdf = NFe_Woo()->down_invoice( array( $order_id ) );

			// If it doesn't, put the content on this pdf
			file_put_contents( $file, file_get_contents($pdf) );
		}

		$size = filesize($file);

		// Download the PDF
		self::output_pdf( $name, $size, $file );
	}

	/**
	 * PDF Outputting
	 * 
	 * @param  string 	$name Name of the PDF
	 * @param  int 		$size Size of the PDF
	 * @param  string 	$file PDF information with path location
	 * @return void
	 */
	public static function output_pdf( $name, $size, $file ) {
		// turn off output buffering to decrease cpu usage
		@ob_end_clean();
		
		// required for IE, otherwise Content-Disposition may be ignored
		if ( ini_get('zlib.output_compression') ) {
			ini_set('zlib.output_compression', 'Off');
		}

		// Download it
		header('Content-type: application/pdf');
		header('Content-Disposition: attachment; filename="'. $name .'"');
		header('Content-Transfer-Encoding: binary');
		header('Accept-Ranges: bytes');
		header('Content-Length: '. $size );

		// The three lines below basically make the	download non-cacheable
		header("Cache-control: private");
		header('Pragma: private');
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

		// multipart-download and download resuming support
		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			list( $a, $range )         = explode( "=", $_SERVER['HTTP_RANGE'], 2 );
			list( $range )             = explode( ",", $range, 2 );
			list( $range, $range_end ) = explode( "-", $range );
			$range                     = intval($range);

			if ( ! $range_end ) {
				$range_end = $size - 1;
			} 
			else {
				$range_end = intval($range_end);
			}
			$new_length = $range_end - $range + 1;
			header('HTTP/1.1 206 Partial Content');
			header('Content-Length: $new_length');
			header('Content-Range: bytes ' . $range - $range_end / $size );
		} 
		else {
			$new_length = $size;
			header('Content-Length: '. $size );
		}

		// output the file itself
		$chunksize  = 1 * ( 1024 * 1024 );
		$bytes_send = 0;
		if ( $file = fopen($file, 'r') ) {
			if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
				fseek($file, $range);
			}

			while ( ! feof($file) && ! connection_aborted() && ( $bytes_send < $new_length ) ) {
				$buffer = fread($file, $chunksize);
				echo($buffer);
				flush();
				$bytes_send += strlen($buffer);
			}
			fclose($file);
		} 
		else { 
			wp_die('Error - can not open file.');
		}
	}
}

endif;

WC_NFe_Ajax::init();

// That's it! =)
