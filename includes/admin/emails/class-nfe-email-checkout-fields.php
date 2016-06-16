<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('WC_NFe_Checkout_Fields') ) :

/**
 * Email to remind the customer to update NFe information
 *
 * @class 	WC_NFe_Checkout_Fields
 * @version	1.0.0
 * @package	WooCommerce_NFe/Classes/Emails
 * @author 	NFe.io
 * @extends WC_Email
 */	
class WC_NFe_Checkout_Fields extends WC_Email {

	/**
	 * Create an instance of the class.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->id          = 'checkout_fields';
		$this->title       = __( 'NFe Update Checkout Fields', 'woocommerce-nfe' );
		$this->description = __( 'Email to remind the customer to add/update NFe fields. These fields are used from WooCommerce NFe.', 'woocommerce-nfe' );

		$this->heading     = __( 'NFe Update Checkout Fields', 'woocommerce-nfe' );
		
		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
		$this->subject     = sprintf( _x( '[%s] NFe Update Checkout Fields', 'default email subject for safe copy emails sent to the admin or a custom email chosen in the NFe settings page', 'woocommerce-nfe' ), '{blogname}' );

		$this->template_html  = 'emails/nfe-checkout-fields.php';
		$this->template_plain = 'emails/plain/nfe-checkout-fields.php';
		$this->template_base  = WOOCOMMERCE_NFE_PATH . 'templates/';

		// Triggers
    	add_action( 'woocommerce_order_status_pending_to_processing_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_pending_to_completed_notification', array( $this, 'trigger' ) );
    	add_action( 'nfe_checkout_fields_notification', array( $this, 'trigger' ) );

		parent::__construct();

		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * trigger public function.
	 *
	 * @access public
	 * @return void
	 */
	public function trigger( $order_id ) {
		if ( $this->where_note() ) {
			return;
		}

		if ( $order_id ) {
			$this->object 	= wc_get_order( $order_id );
			$customer_email = get_post_meta( $order_id, '_billing_email', true );
		}

		$recipients_list = array_merge( array( $this->get_recipient() ), $customer_email );

		if ( ! $this->is_enabled() || ! $recipients_list ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Get content html.
	 *
	 * @access public
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'			=> $this
		) );
	}

	/**
	 * Get content plain.
	 *
	 * @access public
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'			=> $this
		) );
	}

	/**
	 * Initialise Settings Form Fields
	 *
	 * @access public
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'         => _x( 'Enable/Disable', 'an email notification', 'woocommerce-nfe' ),
				'type'          => 'checkbox',
				'label'         => __( 'Enable this email notification', 'woocommerce-nfe' ),
				'default'       => $this->where_note_enabled(),
			),
			'recipient' => array(
				'title'         => _x( 'Recipient(s)', 'of an email', 'woocommerce-nfe' ),
				'type'          => 'text',
				// translators: placeholder is admin email
				'description'   => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to <code>%s</code>.', 'woocommerce-nfe' ), esc_attr(  get_option( 'admin_email' ) ) ),
				'placeholder'   => '',
				'default'       => get_option( 'admin_email' ),
			),
			'subject' => array(
				'title'         => _x( 'Subject', 'of an email', 'woocommerce-nfe' ),
				'type'          => 'text',
				'description'   => sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'woocommerce-nfe' ), $this->subject ),
				'placeholder'   => '',
				'default'       => '',
			),
			'heading' => array(
				'title'         => _x( 'Email Heading', 'Name the setting that controls the main heading contained within the email notification', 'woocommerce-nfe' ),
				'type'          => 'text',
				'description'   => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'woocommerce-nfe' ), $this->heading ),
				'placeholder'   => '',
				'default'       => '',
			),
			'email_type' => array(
				'title'         => _x( 'Email type', 'text, html or multipart', 'woocommerce-nfe' ),
				'type'          => 'select',
				'description'   => __( 'Choose which format of email to send.', 'woocommerce-nfe' ),
				'default'       => 'html',
				'class'         => 'email_type',
				'options'       => array(
					'plain'         => _x( 'Plain text', 'email type', 'woocommerce-nfe' ),
					'html'          => _x( 'HTML', 'email type', 'woocommerce-nfe' ),
				),
			),
		);
	}

	/**
	 * Where Note Status
	 * 
	 * @access public
	 * @return bool true|false
	 */
	public function where_note() {
		if ( nfe_get_field('where_note') == 'before' || nfe_get_field('where_note') == 'manual' ) {
			return true;
		}
	}

	/**
	 * Check if Where Note is enabled
	 *
	 * @access public
	 * @return string
	 */
	public function where_note_enabled() {
		return nfe_get_field('where_note') == 'after' ? 'yes' : 'no';
	}
}

endif;

// That's it! =)
