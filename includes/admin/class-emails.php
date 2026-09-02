<?php
/**
 * WooCommerce NFe Email Class
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Emails
 * @version  1.0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce NFe Email Class
 */
class WC_NFe_Emails {

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 */
	public static function init() {
		add_action( 'woocommerce_email_classes', __CLASS__ . '::add_emails', 10, 1 );
		add_filter( 'woocommerce_email_actions', __CLASS__ . '::add_email_actions', 10, 1 );
	}

	/**
	 * Registers the plugin's own action with WooCommerce's e-mail bootstrap.
	 *
	 * WooCommerce instantiates its mailer lazily, only for the actions listed
	 * here. Without this, an invoice confirmed by the webhook fired
	 * 'woo_nfe_receipt_issued' into a request where the e-mail class had never
	 * been constructed -- so nothing was listening and the customer was never
	 * told, silently.
	 *
	 * WooCommerce turns each registered action into '<action>_notification'
	 * after loading the mailer, which is what the e-mail class hooks.
	 *
	 * @since 1.5.0
	 *
	 * @param array $actions Actions that trigger transactional e-mails.
	 *
	 * @return array
	 */
	public static function add_email_actions( $actions ) {
		$actions[] = 'woo_nfe_receipt_issued';

		return $actions;
	}

	/**
	 * Add NFe's email classes.
	 *
	 * @param array $email_classes Email classes registered by WooCommerce.
	 *
	 * @return array
	 */
	public static function add_emails( $email_classes ) {
		require_once __DIR__ . '/emails/class-nfe-email-receipt-issued.php';

		$email_classes['WC_NFe_Email_Receipt_Issued'] = new WC_NFe_Email_Receipt_Issued();

		return $email_classes;
	}
}

WC_NFe_Emails::init();
