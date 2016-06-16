<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('WC_NFe_Emails') ) :

/**
 * WooCommerce NFe Email Class
 * 
 * @version 	1.0.0
 * @package 	WooCommerce_NFe/WC_NFe_Emails
 * @author  	NFe.io
 */
class WC_NFe_Emails {

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 * 
	 * @access public
	 */
	public static function init() {
		add_action( 'woocommerce_email_classes', __CLASS__ . '::add_emails', 10, 1 );
	}

	/**
	 * Add NFe's email classes.
	 *
	 * @access public
	 */
	public static function add_emails( $email_classes ) {
		require_once( 'emails/class-nfe-email-safe-copy.php' 		);
		require_once( 'emails/class-nfe-email-checkout-fields.php' 	);
		require_once( 'emails/class-nfe-email-receipt-issued.php' 	);

		$email_classes['WC_NFe_Email_Safe_Copy']      = new WC_NFe_Email_Safe_Copy();
		$email_classes['WC_NFe_Checkout_Fields']      = new WC_NFe_Checkout_Fields();
		$email_classes['WC_NFe_Email_Receipt_Issued'] = new WC_NFe_Email_Receipt_Issued();

		return $email_classes;
	}
}

endif;

WC_NFe_Emails::init();
