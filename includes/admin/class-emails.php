<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('WC_NFe_Emails') ) :

/**
 * WooCommerce NFe Email Class
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Emails
 * @version  1.0.1
 */
class WC_NFe_Emails {

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 */
	public static function init() {
		add_action( 'woocommerce_email_classes', __CLASS__ . '::add_emails', 10, 1 );
	}

	/**
	 * Add NFe's email classes.
	 */
	public static function add_emails( $email_classes ) {
		require_once( 'emails/class-nfe-email-receipt-issued.php' 	);

		$email_classes['WC_NFe_Email_Receipt_Issued'] = new WC_NFe_Email_Receipt_Issued();

		return $email_classes;
	}
}

WC_NFe_Emails::init();

endif;
