<?php
/**
 * Exit if accessed directly.
 */

defined( 'ABSPATH' ) || exit;

/**
 * NFe Receipt Issued Email
 *
 * @class   WC_NFe_Email_Receipt_Issued
 * @author  NFe.io
 * @package WooCommerce_NFe/Class/Emails
 * @version 1.0.1
 * @extends WC_Email
 */
class WC_NFe_Email_Receipt_Issued extends WC_Email {

	/**
	 * Create an instance of the class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->id          = 'receipt_issued';
		$this->title       = __( 'NFe Receipt Issued', 'woo-nfe' );
		$this->description = __( 'Safe copy emails are sent when a customer issues an receipt. The e-mail is sent to the admin as a saving measure.', 'woo-nfe' );

		$this->heading = __( 'NFe Receipt Issued', 'woo-nfe' );

		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out.
		$this->subject = sprintf( _x( '[%s] NFe Receipt Issued', 'default email subject for safe copy emails sent to the admin or a custom email chosen in the NFe settings page', 'woo-nfe' ), '{blogname}' );

		$this->template_base  = WOOCOMMERCE_NFE_PATH . 'templates/';
		$this->template_html  = 'emails/nfe-receipt-issued.php';
		$this->template_plain = 'emails/plain/nfe-receipt-issued.php';
		$this->customer_email = true;

		// Triggers.
		add_action( 'woocommerce_order_status_pending_to_processing_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_pending_to_completed_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_completed_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_renewal_order_payment_complete', array( $this, 'trigger' ) );

		parent::__construct();
	}

	/**
	 * Trigger public function.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public function trigger( $order_id ) {
		// Check if order exists first.
		$order    = nfe_wc_get_order( $order_id );
		$order_id = $order->get_id();

		if ( ! $order_id ) {
			return;
		}

		// Checking if the address of order is filled.
		if ( ! nfe_order_address_filled( $order_id ) ) {
			return;
		}

		$this->object    = $order;
		$this->recipient = $this->object->get_billing_email();

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 *  Function get_content_html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Function get_content_plain public function.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Initialise Settings Form Fields
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'    => array(
				'title'   => _x( 'Enable/Disable', 'an email notification', 'woo-nfe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'woo-nfe' ),
				'default' => 'yes',
			),
			'subject'    => array(
				'title'       => _x( 'Subject', 'of an email', 'woo-nfe' ),
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'woo-nfe' ), $this->subject ),
				'placeholder' => '',
				'default'     => '',
			),
			'heading'    => array(
				'title'       => _x( 'Email Heading', 'Name the setting that controls the main heading contained within the email notification', 'woo-nfe' ),
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'woo-nfe' ), $this->heading ),
				'placeholder' => '',
				'default'     => '',
			),
			'email_type' => array(
				'title'       => _x( 'Email type', 'text, html or multipart', 'woo-nfe' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'woo-nfe' ),
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => array(
					'plain' => _x( 'Plain text', 'email type', 'woo-nfe' ),
					'html'  => _x( 'HTML', 'email type', 'woo-nfe' ),
				),
			),
		);
	}
}
