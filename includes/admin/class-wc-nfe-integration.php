<?php

/**
 * WooCommerce NFe.io Integration
 *
 * @author   Renato Alves
 * @category Admin
 * @package  NFe_WooCommerce/Classes/Integration
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WC_NFe_Integration extends WC_Integration {
	
	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->id 				  = 'nfe-woo-integration';
		$this->method_title 	  = __( 'NFe Integration', 'woocommerce-nfe' );
		$this->method_description = __( 'This is the NFe.io integration/settings page.', 'woocommerce-nfe' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->api_key     = $this->get_option( 'api_key' );
		$this->debug       = $this->get_option( 'debug' );
		$this->nfe_enable  = $this->get_option( 'nfe_enable' );

		// Debug.
		if ( 'yes' == $this->debug ) {
			$this->log = new WC_Logger();
		}

		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_notices', 										array( $this, 'display_errors' ) );
	}

	/**
	 * Gets the admin url.
	 *
	 * @return string
	 */
	protected function admin_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=integration' );
	}

	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'nfe_enable' => array(
				'title'             => __( 'Enable/Disable', 'woocommerce-nfe' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable NFe.io', 'woocommerce-nfe' ),
				'default'           => 'no',
			),
			'api_key' => array(
				'title'             => __( 'API Key', 'woocommerce-nfe' ),
				'type'              => 'text',
				'label'             => __( 'API Key', 'woocommerce-nfe' ),
				'default'           => '',
				'description'       => sprintf( __( 'Enter your API Key. - %s', 'woocommerce-nfe' ), '<a href="' . esc_url('https://app.nfe.io/account/apikeys') . '">' . __( 'NFe.io - Account -> Access Keys', 'woocommerce-nfe' ) . '</a>' ),
			),
			'company_id' => array(
				'title'             => __( 'Company ID', 'woocommerce-nfe' ),
				'type'              => 'text',
				'label'             => __( 'Company ID', 'woocommerce-nfe' ),
				'default'           => '',
				'desc_tip'       => __( 'Enter your company ID. You can find this in your NFe.io account.', 'woocommerce-nfe' ),
			),
			'where_note' => array(
				'title'             => __( 'NFe.io Filling', 'woocommerce-nfe' ),
				'type'              => 'select',
				'label'             => __( 'NFe.io Filling', 'woocommerce-nfe' ),
				'default'           => '',
				'options' 			=> array(
					'after'     	=> __( 'After Checkout', 'woocommerce-nfe' ),
					'manual'    	=> __( 'Manual (Requires admin to issue)', 'woocommerce-nfe' )
				),
				'class'    			=> 'wc-enhanced-select',
				'css'      			=> 'min-width:300px;',
				'desc_tip'       	=> __( 'Option for user to fill the NFe.io information.', 'woocommerce-nfe' ),
			),
			'nfe_prefix' => array(
				'title'             => __( 'NFe Prefix', 'woocommerce-nfe' ),
				'type'              => 'text',
				'label'             => __( 'NFe Prefix', 'woocommerce-nfe' ),
				'default'           => 'NFe-',
				'desc_tip'       	=> __( 'Used in the webhook', 'woocommerce-nfe' ),
			),
			'issue_past_notes' => array(
				'title'             => __( 'Enable Retroactive Issue', 'woocommerce-nfe' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable to issue NFe.io in past products', 'woocommerce-nfe' ),
				'default'           => 'no',
				'description'       => __( 'Enabling this allows users to issue nfe.io notes on bought products in the past.', 'woocommerce-nfe' ),
			),
			'send_email_copy' => array(
				'title'             => __( 'Enable Safe Copy', 'woocommerce-nfe' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable or disable safe issue copy', 'woocommerce-nfe' ),
				'default'           => 'no',
				'description'       => __( 'If enabled, a copy of every note issued is sent to the blog admin email.', 'woocommerce-nfe' ),
			),
			'debug' => array(
				'title'             => __( 'Debug Log', 'woocommerce-nfe' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable logging', 'woocommerce-nfe' ),
				'default'           => 'no',
				'description' 		=> sprintf( __( 'Log events such as API requests, you can check this log in %s.', 'woocommerce-nfe' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'woocommerce-nfe' ) . '</a>' ),
			),
		);
	}

	/**
	 * Checks the NFe.io api
	 *
	 * @param string $key
	 * @return stdClass
	 */
	protected function check_api( $key ) {
		if ( 'yes' === $this->debug ) {
			$this->log->add( $this->id, sprintf( 'Checking "%s" key on NFe.io api...', $key ) );
		}

		try {
			// Sth
		} catch ( Exception $e ) {
			if ( 'yes' === $this->debug ) {
				$this->log->add( $this->id, sprintf( 'An error occurred while trying to check the key for "%s": %s', $key, $e->getMessage() ) );
			}
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, sprintf( 'Key for "%s" found successfully: %s', $key ) );
		}
	}

	/**
	 * Displays notifications when the admin has something wrong with the NFe configuration.
	 *
	 * @return void
	 */
	public function display_errors() {
		if ( 'yes' == $this->nfe_enable && empty( $this->api_key ) ) {
			echo '<div class="error"><p><strong>' . __( 'NFe.io WooCommerce', 'woocommerce-nfe' ) . '</strong>: ' . 
		sprintf( __( 'You should inform your API Key and Company ID. %s', 'woocommerce-nfe' ), 
			'<a href="' . $this->admin_url() . '">' .
			 __( 'Click here to configure!', 'woocommerce-nfe' ) . '</a>' ) . '</p></div>';
		}
	}
}

// That's it! =)
