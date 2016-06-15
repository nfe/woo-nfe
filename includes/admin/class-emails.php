<?php

/**
 * WooCommerce NFe Email Class
 *
 * Modifies the base WooCommerce email class and extends it to send nfe emails.
 *
 * @package		WooCommerce_NFe
 * @subpackage	WC_NFe_Emails
 * @category	Class
 * @author		NFe.io
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WC_NFe_Emails {

	private static $woocommerce_email;

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'woocommerce_email_classes', __CLASS__ . '::add_emails', 10, 1 );
		add_action( 'woocommerce_init', __CLASS__ . '::hook_transactional_emails' );
	}

	/**
	 * Add NFe's email classes.
	 *
	 * @since 1.0.0
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

	/**
	 * Hooks up all of Subscription's transaction emails after the WooCommerce object is constructed.
	 *
	 * @since 1.0.0
	 */
	public static function hook_transactional_emails() {
		$order_email_actions = array(
			'woocommerce_order_status_pending_to_processing',
			'woocommerce_order_status_pending_to_completed',
			'woocommerce_order_status_pending_to_on-hold',
			'woocommerce_order_status_failed_to_processing_notification',
			'woocommerce_order_status_failed_to_completed_notification',
			'woocommerce_order_status_failed_to_on-hold_notification',
			'woocommerce_order_status_completed',
			'woocommerce_generated_manual_renewal_order',
			'woocommerce_order_status_failed',
			
			'nfe_checkout_fields_notification',
			'nfe_safe_copy_notification',
			'nfe_receipt_issued_notification',
		);

		foreach ( $order_email_actions as $action ) {
			add_action( $action, __CLASS__ . '::maybe_remove_woocommerce_email', 9 );
		}
	}

	/**
	 * If the order is a renewal order, don't send core emails.
	 *
	 * @param int $user_id The ID of the user who the subscription belongs to
	 * @param string $subscription_key A subscription key of the form created by @see self::get_subscription_key()
	 * @return void
	 */
	public static function maybe_remove_woocommerce_email( $order_id ) {
		// if ( wcs_order_contains_renewal( $order_id ) || wcs_order_contains_switch( $order_id ) ) {
			// remove_action( current_filter(), array( 'WC_Emails', 'send_transactional_email' ) );
		// }
	}
}

WC_NFe_Emails::init();
