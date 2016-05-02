<?php

/**
 * Nfe Integration
 *
 * @since 1.0.0
 *
 * @package  WC_Nfe_Integration
 * @category Integration
 * @author   Renato Alves
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Nfe_Integration' ) ) :

	class WC_Nfe_Integration extends WC_Integration {

		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			$this->plugin_id 		  = 'nfe-woo-integration';
			$this->id                 = 'nfe-woo';
			$this->method_title 	  = __( 'Nfe Integration', 'nfe-woocommerce' );
			$this->method_description = __( 'This is the Nfe.io integration/settings page.', 'nfe-woocommerce' );
			$this->enabled            = 'yes';

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables.
			$this->api_key          = $this->get_option( 'api_key' );
			$this->debug            = $this->get_option( 'debug' );

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
				'api_key' => array(
					'title'             => __( 'API Key', 'nfe-woocommerce' ),
					'type'              => 'text',
					'description'       => __( 'Enter with your API Key. You can find this in you nfe.io profile in....', 'nfe-woocommerce' ),
					'desc_tip'          => true,
					'default'           => ''
				),
				'debug' => array(
					'title'             => __( 'Debug Log', 'nfe-woocommerce' ),
					'type'              => 'checkbox',
					'label'             => __( 'Enable logging', 'nfe-woocommerce' ),
					'default'           => 'no',
					'description'       => __( 'Log events such as API requests', 'nfe-woocommerce' ),
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
				$settings['api_key'] = strtoupper( $settings['api_key'] );
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

endif;

// That's it! =)
