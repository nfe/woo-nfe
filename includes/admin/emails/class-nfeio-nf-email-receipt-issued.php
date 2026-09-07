<?php
/**
 * NFe Receipt Issued Email
 *
 * @class   NFEIO_NF_Email_Receipt_Issued
 * @author  NFE.io
 * @package NFEIO_NF_Plugin/Class/Emails
 * @version 1.0.1
 * @extends WC_Email
 */

defined( 'ABSPATH' ) || exit;

/**
 * NFEIO_NF_Email_Receipt_Issued Class.
 */
class NFEIO_NF_Email_Receipt_Issued extends WC_Email {

	/**
	 * Create an instance of the class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->id          = 'receipt_issued';
		$this->title       = __( 'NFe Receipt Issued', 'nfe-io-nota-fiscal-for-woocommerce' );
		$this->description = __( 'Sent to the customer once NFE.io confirms the service receipt for their order was issued, with a link to download it.', 'nfe-io-nota-fiscal-for-woocommerce' );

		$this->heading = __( 'NFe Receipt Issued', 'nfe-io-nota-fiscal-for-woocommerce' );

		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out.
		$this->subject = sprintf( _x( '[%s] NFe Receipt Issued', 'default email subject for safe copy emails sent to the admin or a custom email chosen in the NFe settings page', 'nfe-io-nota-fiscal-for-woocommerce' ), '{blogname}' );

		$this->template_base  = NFEIO_NF_PATH . 'templates/';
		$this->template_html  = 'emails/nfe-receipt-issued.php';
		$this->template_plain = 'emails/plain/nfe-receipt-issued.php';
		$this->customer_email = true;

		/*
		 * Triggered by the webhook, once NFE.io confirms the invoice was issued.
		 *
		 * It used to hang off the same order-status transitions that *start* the
		 * issuing, so the customer was told their receipt had been issued before
		 * the API had even answered -- and again if issuing then failed, which
		 * announced a document that would never exist.
		 */
		add_action( 'nfeio_nf_receipt_issued_notification', array( $this, 'trigger' ) );

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
		// Validate the order before using it: the hook may hand over an unknown ID.
		$order = nfeio_nf_wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// The invoice is already issued by the time this runs, so there is
		// nothing left to validate about the address -- NFE.io accepted it.

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
				'title'   => _x( 'Enable/Disable', 'an email notification', 'nfe-io-nota-fiscal-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'nfe-io-nota-fiscal-for-woocommerce' ),
				'default' => 'yes',
			),
			'subject'    => array(
				'title'       => _x( 'Subject', 'of an email', 'nfe-io-nota-fiscal-for-woocommerce' ),
				'type'        => 'text',
				// translators: %s: default subject used when the field is left blank.
				'description' => sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'nfe-io-nota-fiscal-for-woocommerce' ), $this->subject ),
				'placeholder' => '',
				'default'     => '',
			),
			'heading'    => array(
				'title'       => _x( 'Email Heading', 'Name the setting that controls the main heading contained within the email notification', 'nfe-io-nota-fiscal-for-woocommerce' ),
				'type'        => 'text',
				// translators: %s: default heading used when the field is left blank.
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'nfe-io-nota-fiscal-for-woocommerce' ), $this->heading ),
				'placeholder' => '',
				'default'     => '',
			),
			'email_type' => array(
				'title'       => _x( 'Email type', 'text, html or multipart', 'nfe-io-nota-fiscal-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => array(
					'plain' => _x( 'Plain text', 'email type', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'html'  => _x( 'HTML', 'email type', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
			),
		);
	}
}
