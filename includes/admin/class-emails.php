<?php

/**
 * Subscriptions Email Class
 *
 * Modifies the base WooCommerce email class and extends it to send subscription emails.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_NFe_Emails
 * @category	Class
 * @author		Brent Shepherd
 */
class WC_NFe_Emails {

	private static $woocommerce_email;

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 1.0
	 */
	public static function init() {

		add_action( 'woocommerce_email_classes', __CLASS__ . '::add_emails', 10, 1 );

		add_action( 'woocommerce_init', __CLASS__ . '::hook_transactional_emails' );

		add_filter( 'woocommerce_resend_order_emails_available', __CLASS__ . '::renewal_order_emails_available', -1 ); // run before other plugins so we don't remove their emails

	}

	/**
	 * Add NFe's email classes.
	 *
	 * @since 1.0.0
	 */
	public static function add_emails( $email_classes ) {

		require_once( 'emails/class-nfe-email-safe-copy.php' );

		$email_classes['WC_NFe_Email_Safe_Copy'] = new WC_NFe_Email_Safe_Copy();

		return $email_classes;
	}

	/**
	 * Hooks up all of Subscription's transaction emails after the WooCommerce object is constructed.
	 *
	 * @since 1.4
	 */
	public static function hook_transactional_emails() {

		// Don't send subscription
		if ( ! defined( 'WCS_FORCE_EMAIL' ) ) {
			return;
		}

		add_action( 'woocommerce_subscription_status_updated', __CLASS__ . '::send_cancelled_email', 10, 2 );

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
		);

		foreach ( $order_email_actions as $action ) {
			add_action( $action, __CLASS__ . '::maybe_remove_woocommerce_email', 9 );
		}
	}

	/**
	 * Init the mailer and call for the cancelled email notification hook.
	 *
	 * @param $subscription WC Subscription
	 * @since 2.0
	 */
	public static function send_cancelled_email( $subscription ) {
		WC()->mailer();

		if ( $subscription->has_status( array( 'pending-cancel', 'cancelled' ) ) && 'true' !== get_post_meta( $subscription->id, '_cancelled_email_sent', true ) ) {
			do_action( 'cancelled_subscription_notification', $subscription );
		}
	}

	/**
	 * Init the mailer and call the notifications for the renewal orders.
	 *
	 * @param int $user_id The ID of the user who the subscription belongs to
	 * @param string $subscription_key A subscription key of the form created by @see self::get_subscription_key()
	 * @return void
	 */
	public static function send_renewal_order_email( $order_id ) {
		WC()->mailer();

		if ( wcs_order_contains_renewal( $order_id ) ) {
			do_action( current_filter() . '_renewal_notification', $order_id );
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
		if ( wcs_order_contains_renewal( $order_id ) || wcs_order_contains_switch( $order_id ) ) {
			remove_action( current_filter(), array( 'WC_Emails', 'send_transactional_email' ) );
		}
	}

	/**
	 * If the order is a renewal order, don't send core emails.
	 *
	 * @param int $user_id The ID of the user who the subscription belongs to
	 * @param string $subscription_key A subscription key of the form created by @see self::get_subscription_key()
	 * @return void
	 */
	public static function maybe_reattach_woocommerce_email( $order_id ) {
		if ( wcs_order_contains_renewal( $order_id ) || wcs_order_contains_switch( $order_id ) ) {
			add_action( current_filter(), array( 'WC_Emails', 'send_transactional_email' ) );
		}
	}
}

WC_NFe_Emails::init();
