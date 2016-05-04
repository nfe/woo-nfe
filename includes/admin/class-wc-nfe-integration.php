<?php

/**
 * Nfe Integration
 *
 * @since 1.0.0
 *
 * @package  Nfe_WooCommerce/Classes/Integration
 * @author   Renato Alves
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WC_Nfe_Integration extends WC_Integration {

	/**
	 * Nfe.io API URL
	 *
	 * @var string
	 */
	protected $api_url = 'http://requestb.in/13w9wi91';

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->id		  		  = 'nfe-woo-integration';
		$this->method_title 	  = __( 'Nfe Integration', 'nfe-woocommerce' );
		$this->method_description = __( 'This is the Nfe.io integration/settings page.', 'nfe-woocommerce' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->api_key          = $this->get_option( 'api_key' );
		$this->debug            = $this->get_option( 'debug' );

		// Debug.
		if ( 'yes' === $this->debug ) {
			$this->log = new WC_Logger();
		}

		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );

		// Filters.
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );
	}

	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'nfe_enable' => array(
				'title'             => __( 'Enable/Disable', 'nfe-woocommerce' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable Nfe.io', 'nfe-woocommerce' ),
				'default'           => 'no',
			),
			'api_key' => array(
				'title'             => __( 'API Key', 'nfe-woocommerce' ),
				'type'              => 'text',
				'label'             => __( 'API Key', 'nfe-woocommerce' ),
				'default'           => '',
				'description'       => __( 'Enter your API Key. You can find this in you Nfe.io - Account -> Access Keys.', 'nfe-woocommerce' ),
			),
			'where_note' => array(
				'title'             => __( 'Nfe.io Filling', 'nfe-woocommerce' ),
				'type'              => 'select',
				'label'             => __( 'Nfe.io Filling', 'nfe-woocommerce' ),
				'default'           => 'before',
				'options' 			=> array(
					'after'     	=> __( 'After Checkout', 'nfe-woocommerce' ),
					'before'    	=> __( 'Before Checkout', 'nfe-woocommerce' ),
					'manual'    	=> __( 'Manual (Requires admin to issue)', 'nfe-woocommerce' )
				),
				'class'    			=> 'wc-enhanced-select',
				'css'      			=> 'min-width:300px;',
				'description'       => __( 'The place where the user must fill the Nfe.io information.', 'nfe-woocommerce' ),
			),
			'issue_past_notes' => array(
				'title'             => __( 'Enable Retroactive Issue', 'nfe-woocommerce' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable to issue Nfe.io in past products', 'nfe-woocommerce' ),
				'default'           => 'no',
				'description'       => __( 'Enabling this allows users to issue nfe.io notes on bought products in the past.', 'nfe-woocommerce' ),
			),
			'send_email_copy' => array(
				'title'             => __( 'Enable Safe Copy', 'nfe-woocommerce' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable or disable safe issue copy', 'nfe-woocommerce' ),
				'default'           => 'no',
				'description'       => __( 'If enabled, a copy of every note issued is sent to the blog admin email.', 'nfe-woocommerce' ),
			),
			'debug' => array(
				'title'             => __( 'Debug Log', 'nfe-woocommerce' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable logging', 'nfe-woocommerce' ),
				'default'           => 'no',
				'description' 		=> sprintf( __( 'Log events such as API requests, you can check this log in %s.', 'wc-nfe-woocommerce' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'nfe-woocommerce' ) . '</a>' ),
			),
		);
	}

	/**
	 * Santize our settings.
	 * 
	 * @see process_admin_options()
	 */
	public function sanitize_settings( $settings ) {
		if ( isset( $settings ) && isset( $settings['api_key'] ) ) {
			if ( strlen( $settings['api_key'] ) > 10 ) {
				$settings['api_key'] = strtolower( $settings['api_key'] );
			}
		}

		return $settings;
	}

	/**
	 * Validate the API key
	 * 
	 * @see validate_settings_fields()
	 */
	public function validate_api_key_field( $key ) {
		// get the posted value
		$value = $_POST[ $this->plugin_id . $this->id . '_' . $key ];

		return $value;
	}

	/**
	 * Checks the Nfe.io api
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
	 * Display errors by overriding the display_errors() method;
	 * 
	 * @see display_errors()
	 */
	public function display_errors( ) {
		// loop through each error and display it
		foreach ( $this->errors as $key => $value ) {
			?>
			<div class="error">
				<p><?php _e( 'Looks like you made a mistake with the ' . $value . ' field.', 'nfe-woocommerce' ); ?></p>
			</div>
			<?php
		}
	}
}

// That's it! =)
