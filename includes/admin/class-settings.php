<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Integration' ) ) :

/**
 * WooCommerce NFe.io Integration
 *
 * @author   NFe
 * @category Admin
 * @package  WooCommerce_NFe/Class/WC_NFe_Integration
 * @version  1.0.0
 */
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

		// Debug.
		if ( nfe_get_field('debug') == 'yes' ) {
			$this->log = new WC_Logger();
		}

		// Actions.
		add_action( 'admin_notices', 										array( $this, 'display_errors' ) );
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_integration',              	array( $this, 'process_admin_options') );
	}

	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {
		if ( $this->has_api_key() ) {
			$lists = $this->fetch_companies();
			$company_list = array_merge( array( '' => __( 'Select a company...', 'woocommerce-nfe' ) ), $lists );

		} else {
			$company_list = array( 'no-company' => __( 'Enter your API key and save to see your companies.', 'woocommerce-nfe' ) );
		}

		$this->form_fields = apply_filters( 'woocommerce_nfe_settings', 
			array(
				'nfe_enable' 		=> array(
					'title'             => __( 'Enable/Disable', 'woocommerce-nfe' ),
					'type'              => 'checkbox',
					'label'             => __( 'Enable NFe.io', 'woocommerce-nfe' ),
					'default'           => 'yes',
				),
				'api_key' 			=> array(
					'title'             => __( 'API Key', 'woocommerce-nfe' ),
					'type'              => 'text',
					'label'             => __( 'API Key', 'woocommerce-nfe' ),
					'default'           => '',
					'description'       => sprintf( __( 'Log in to NFe.io to look up your API key. - %s', 'woocommerce-nfe' ), '<a href="' . esc_url('https://app.nfe.io/account/apikeys') . '">' . __( 'NFe.io - Account', 'woocommerce-nfe' ) . '</a>' ),
				),
				'choose_company' 	=> array(
					'title'             => __( 'Choose the Company', 'woocommerce-nfe' ),
					'type'              => 'select',
					'label'             => __( 'Choose the Company', 'woocommerce-nfe' ),
					'default'           => '',
					'options' 			=> $company_list,
					'class'    			=> 'wc-enhanced-select',
					'css'      			=> 'min-width:300px;',
					'desc_tip'       	=> __( 'Choose one of your companies.', 'woocommerce-nfe' ),
				),
				'where_note' 		=> array(
					'title'             => __( 'NFe.io Filling', 'woocommerce-nfe' ),
					'type'              => 'select',
					'label'             => __( 'NFe.io Filling', 'woocommerce-nfe' ),
					'default'           => 'before',
					'options' 			=> array(
						'before'     	=> __( 'Before Checkout (Default)', 'woocommerce-nfe' ),
						'after'     	=> __( 'After Checkout', 'woocommerce-nfe' ),
					),
					'class'    			=> 'wc-enhanced-select',
					'css'      			=> 'min-width:300px;',
					'desc_tip'       	=> __( 'Option for user to fill the NFe.io information.', 'woocommerce-nfe' ),
				),
				'issue_past_title' 	=> array(
					'title' 			=> __( 'Manual Retroactive Issue of NFe', 'woocommerce-nfe' ),
					'type' 				=> 'title',
				),
				'issue_past_notes' => array(
					'title'             => __( 'Enable Retroactive Issue', 'woocommerce-nfe' ),
					'type'              => 'checkbox',
					'label'             => __( 'Enable to issue NFe.io in past products', 'woocommerce-nfe' ),
					'default'           => 'no',
					'description'       => __( 'Enabling this allows users to issue nfe.io notes on bought products in the past.', 'woocommerce-nfe' ),
				),
				'issue_past_days' 	=> array(
					'title'             => __( 'Days in the past', 'woocommerce-nfe' ),
					'type'              => 'number',
					'default'  			=> '60',
					'css'      			=> 'width:50px;',
					'desc_tip'       	=> __( 'Days in the past to allow NFe manual issue.', 'woocommerce-nfe' ),
				),
				'nfe_fiscal_title' 	=> array(
					'title' 			=> __( 'Fiscal Activity (Global)', 'woocommerce-nfe' ),
					'type' 				=> 'title',
				),
				'nfe_cityservicecode' => array(
					'title'             => __( 'CityServiceCode', 'woocommerce-nfe' ),
					'type'              => 'text',
					'label'             => __( 'CityServiceCode', 'woocommerce-nfe' ),
					'default'           => '',
					'desc_tip'       	=> __( 'Global value: Used when issuing a receipt', 'woocommerce-nfe' ),
				),
				'nfe_fedservicecode' => array(
					'title'             => __( 'FederalServiceCode', 'woocommerce-nfe' ),
					'type'              => 'text',
					'label'             => __( 'FederalServiceCode', 'woocommerce-nfe' ),
					'default'           => '',
					'desc_tip'       	=> __( 'Global value: federalServiceCode, used when issuing a receipt', 'woocommerce-nfe' ),
				),
				'nfe_cityservicecode_desc' => array(
					'title'             => __( 'Description', 'woocommerce-nfe' ),
					'type'              => 'text',
					'label'             => __( 'Description', 'woocommerce-nfe' ),
					'default'           => '',
					'desc_tip'       	=> __( 'Global value: Description used when issuing a receipt', 'woocommerce-nfe' ),
				),
				'debug' 			=> array(
					'title'             => __( 'Debug Log', 'woocommerce-nfe' ),
					'type'              => 'checkbox',
					'label'             => __( 'Enable logging', 'woocommerce-nfe' ),
					'default'           => 'no',
					'description' 		=> sprintf( __( 'Log events such as API requests, you can check this log in %s.', 'woocommerce-nfe' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'woocommerce-nfe' ) . '</a>' ),
				),
			)
		);

		return apply_filters( 'woocommerce_nfe_settings_' . $this->id, $this->form_fields );
	}

	/**
	 * Fetches NFe Companies
	 * 
	 * @return array An array of companies
	 */
	private function fetch_companies() {
		$key 			= nfe_get_field('api_key');
		$company_list 	= get_transient( 'nfecompanylist_' . md5( $key ) );

		if ( false === $company_list ) {
			$url 		= 'https://api.nfe.io/v1/companies?api_key='. $key . '';
			$response 	= wp_remote_get( esc_url_raw( $url ) );
			$companies 	= json_decode( wp_remote_retrieve_body( $response ), true );

			if ( is_wp_error( $companies ) ) {

				add_action( 'admin_notices',         array( $this, 'nfe_api_error_msg' ) );
				add_action( 'network_admin_notices', array( $this, 'nfe_api_error_msg' ) );

				return false;
			}
			else {
				$company_list = array();
				foreach ( $companies['companies'] as $c ) {
					$company_list[ $c['id'] ] = ucwords( strtolower( $c['name'] ) );
				}

				if ( sizeof( $company_list ) > 0 ) {
					set_transient( 'nfecompanylist_' . md5( $key ), $company_list, 1 * HOUR_IN_SECONDS );
				}
			}
		}

		return $company_list;
	}

	/**
	 * Displays notifications when the admin has something wrong with the NFe.io configuration.
	 *
	 * @return void
	 */
	public function display_errors() {
		if ( $this->is_active() ) {
			if ( empty( nfe_get_field('api_key') ) ) {
				echo $this->get_message( '<strong>' . __( 'WooCommerce NFe', 'woocommerce-nfe' ) . '</strong>: ' . sprintf( __( 'Plugin is enabled but no API key provided. You should inform your API Key. %s', 'woocommerce-nfe' ), '<a href="' . WOOCOMMERCE_NFE_SETTINGS_URL . '">' . __( 'Click here to configure!', 'woocommerce-nfe' ) . '</a>' ) );
			}
		
			if ( nfe_get_field('issue_past_notes') == 'yes' && $this->issue_past_days() ) {
				echo $this->get_message( '<strong>' . __( 'WooCommerce NFe', 'woocommerce-nfe' ) . '</strong>: ' . sprintf( __( 'Enable Retroactive Issue is enabled, but no days was added. %s.', 'woocommerce-nfe' ), '<a href="' . WOOCOMMERCE_NFE_SETTINGS_URL . '">' . __( 'Add a date to calculate or disable it.', 'woocommerce-nfe' ) . '</a>' ) );
			}
		}
	}

	/**
	 * Display message to user if there is an issue with the NFe API call
	 *
	 * @param void
	 * @return html the message for the user
	 */
	public function nfe_api_error_msg() {
		echo $this->get_message( '<strong>' . __( 'WooCommerce NFe.io', 'woocommerce-nfe' ) . '</strong>: ' . sprintf( __( 'Unable to load the companies list from NFe.io.', 'woocommerce-nfe' ) ) );
	}

	/**
	 * Get message
	 * 
	 * @return string Error
	 */
	private function get_message( $message, $type = 'error' ) {
		ob_start();
		?>
		<div class="<?php echo esc_attr( $type ) ?>">
			<p><?php echo $message ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Issue past date check
	 * 
	 * @return bool true|false
	 */
	public function issue_past_days() {
		$days = nfe_get_field('issue_past_days');

		if ( empty( $days ) || $days == 0 ) {
			return true;
		}
	}

	/**
	 * has_api_key function.
	 *
	 * @return void
	 */
	public function has_api_key() {
		if ( nfe_get_field('api_key') ) {
			return true;
		}

		return false;
	}

	/**
	 * is_active function.
	 *
	 * @return void
	 */
	public function is_active() {
		if ( nfe_get_field('nfe_enable') === 'yes'  ) {
			return true;
		}

		return false;
	}

	/**
	 * Helper log function for debugging.
	 *
	 * @return string
	 */
	static function log( $message ) {
		if ( WP_DEBUG === true ) {
			$logger = new WC_Logger();
			if ( is_array( $message ) || is_object( $message ) ) {
				$logger->add( 'nfe', print_r( $message, true ) );
			}
			else {
				$logger->add( 'nfe', $message );
			}
		}
	}
}

endif;

// That's it! =)
