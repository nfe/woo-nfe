<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce NFe.io Integration
 *
 * @author   NFe.io
 * @category Admin
 * @package  WooCommerce_NFe/Class/WC_NFe_Integration
 * @version  1.0.1
 */
class WC_NFe_Integration extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->id                 = 'woo-nfe';
		$this->method_title       = __( 'NFe Integration', 'woo-nfe' );
		$this->method_description = __( 'This is the NFe.io integration/settings page.', 'woo-nfe' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Actions.
		add_action( 'admin_notices', array( $this, 'display_errors' ) );
		add_action( 'network_admin_notices', array( $this, 'display_errors' ) );
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_integration', array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		if ( $this->has_api_key() ) {
			$lists = $this->get_companies();

			if ( empty( $lists ) ) {
				$company_list = array_merge( array( '' => __( 'No company found in account', 'woo-nfe' ) ), $lists );
			} else {
				$company_list = array_merge( array( '' => __( 'Select a company...', 'woo-nfe' ) ), $lists );
			}
		} else {
			$company_list = array( 'no-company' => __( 'Enter your API key to see your company(ies).', 'woo-nfe' ) );
		}

		$this->form_fields = array(
			'nfe_enable' 		=> array(
				'title'             => __( 'Enable/Disable', 'woo-nfe' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable NFe.io', 'woo-nfe' ),
				'default'           => 'yes',
			),
			'api_key' 			=> array(
				'title'             => __( 'API Key', 'woo-nfe' ),
				'type'              => 'text',
				'label'             => __( 'API Key', 'woo-nfe' ),
				'default'           => '',
				'description'       => sprintf( __( '%s to look up API Key', 'woo-nfe' ), '<a href="' . esc_url( 'https://app.nfe.io/account/apikeys' ) . '">' . __( 'Click here', 'woo-nfe' ) . '</a>' ),
			),
			'choose_company' 	=> array(
				'title'             => __( 'Choose the Company', 'woo-nfe' ),
				'type'              => 'select',
				'label'             => __( 'Choose the Company', 'woo-nfe' ),
				'default'           => '',
				'options' 			=> $company_list,
				'class'    			=> 'wc-enhanced-select',
				'css'      			=> 'min-width:300px;',
				'desc_tip'       	=> __( 'Choose one of your companies.', 'woo-nfe' ),
				'description'       => sprintf( __( '%s to check the registered companies', 'woo-nfe' ), '<a href="' . esc_url( 'https://app.nfe.io/companies' ) . '">' . __( 'Click here', 'woo-nfe' ) . '</a>' ),

			),
			'issue_when' 			=> array(
				'title'             => __( 'NFe Issuing', 'woo-nfe' ),
				'type'              => 'select',
				'label'             => __( 'NFe Issuing', 'woo-nfe' ),
				'default'           => 'auto',
				'options' 			=> array(
					'auto'     	=> __( 'Automattic (Default)', 'woo-nfe' ),
					'manual'    => __( 'Manual', 'woo-nfe' ),
				),
				'class'    			=> 'wc-enhanced-select',
				'css'      			=> 'min-width:300px;',
				'desc_tip'       	=> __( 'Option to issue a NFe.', 'woo-nfe' ),
			),
			'issue_when_status'		=> array(
				'title'             => __( 'Issue on order status', 'woo-nfe' ),
				'type'              => 'select',
				'label'             => __( 'Issue on order status', 'woo-nfe' ),
				'default'           => 'wc-completed',
				'options' 			=> array(
					'wc-pending'    => _x( 'Pending Payment', 'Order status', 'woocommerce' ),
					'wc-processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
					'wc-on-hold'    => _x( 'On Hold', 'Order status', 'woocommerce' ),
					'wc-completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
				),
				'class'    			=> 'wc-enhanced-select',
				'css'      			=> 'min-width:300px;',
				'desc_tip'       	=> __( 'Option to issue a NFe.', 'woo-nfe' ),
			),
			'nfe_events_title' 	=> array(
				'title' 			=> __( 'NFe.io Webkook Setup', 'woo-nfe' ),
				'type' 				=> 'title',
			),
			'nfe_webhook_url' => array(
				'title'             => __( 'Webhook URL', 'woo-nfe' ),
				'type'              => 'text',
				'label'             => __( 'Webhook URL', 'woo-nfe' ),
				'default'           => $this->get_events_url(),
				'custom_attributes' => array(
					'readonly' => 'readonly'
				),
				'description'       => sprintf( __( 'Copy this link and use it to set up the %s', 'woo-nfe' ), '<a href="' . esc_url( 'https://app.nfe.io/account/webhooks' ) . '">' . __( 'NFe.io Webhooks', 'woo-nfe' ) . '</a>' ),
			),
			'issue_past_title' 	=> array(
				'title' 			=> __( 'Manual Retroactive Issue of NFe', 'woo-nfe' ),
				'type' 				=> 'title',
			),
			'issue_past_notes' => array(
				'title'             => __( 'Enable Retroactive Issue', 'woo-nfe' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable to issue NFe.io in past products', 'woo-nfe' ),
				'default'           => 'no',
				'description'       => __( 'Enabling this allows users to issue nfe.io notes on bought products in the past.', 'woo-nfe' ),
			),
			'issue_past_days' 	=> array(
				'title'             => __( 'Days in the past', 'woo-nfe' ),
				'type'              => 'number',
				'default'  			=> '60',
				'css'      			=> 'width:50px;',
				'desc_tip'       	=> __( 'Days in the past to allow NFe manual issue.', 'woo-nfe' ),
			),
			'nfe_fiscal_title' 	=> array(
				'title' 			=> __( 'Fiscal Activity (Global)', 'woo-nfe' ),
				'type' 				=> 'title',
			),
			'nfe_cityservicecode' => array(
				'title'             => __( 'CityServiceCode', 'woo-nfe' ),
				'type'              => 'text',
				'label'             => __( 'CityServiceCode', 'woo-nfe' ),
				'default'           => '',
				'desc_tip'       	=> __( 'Global value: Used when issuing a receipt', 'woo-nfe' ),
			),
			'nfe_fedservicecode' => array(
				'title'             => __( 'FederalServiceCode', 'woo-nfe' ),
				'type'              => 'text',
				'label'             => __( 'FederalServiceCode', 'woo-nfe' ),
				'default'           => '',
				'desc_tip'       	=> __( 'Global value: federalServiceCode, used when issuing a receipt', 'woo-nfe' ),
			),
			'nfe_cityservicecode_desc' => array(
				'title'             => __( 'Description', 'woo-nfe' ),
				'type'              => 'text',
				'label'             => __( 'Description', 'woo-nfe' ),
				'default'           => '',
				'desc_tip'       	=> __( 'Global value: Description used when issuing a receipt', 'woo-nfe' ),
			),
			'debug' 			=> array(
				'title'             => __( 'Debug Log', 'woo-nfe' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable logging', 'woo-nfe' ),
				'default'           => 'no',
				'description' 		=> sprintf( __( 'Log events such as API requests, you can check this log in %s.', 'woo-nfe' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status - Logs', 'woo-nfe' ) . '</a>' ),
			),
		);
		return apply_filters( 'woo_nfe_settings_' . $this->id, $this->form_fields );
	}

	/**
	 * Fetches companies via the NFe API.
	 *
	 * @return bool|array Bail with error message | An array of companies.
	 */
	private function get_companies() {
		$key          = nfe_get_field( 'api_key' );
		$api_key      = md5( $key );
		$cache_key    = 'woo_nfecompanylist_' . $api_key;
		$company_list = get_transient( $cache_key );

		// If there is a list from cache, load it.
		if ( isset( $company_list ) ) {
			return $company_list;
		}

		$url       = "https://api.nfe.io/v1/companies?api_key={$key}";
		$response  = wp_remote_get( esc_url_raw( $url ) );
		$companies = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $companies->message ) ) {
			add_action( 'admin_notices',         array( $this, 'nfe_api_error_msg' ) );
			add_action( 'network_admin_notices', array( $this, 'nfe_api_error_msg' ) );

			return false;
		} else {
			$company_list = array();

			if ( count( $companies ) > 0 && count( $companies['companies'] ) > 0 ) {
				foreach ( $companies['companies'] as $c ) {
					$company_list[ $c['id'] ] = ucwords( strtolower( $c['name'] ) );
				}

				if ( count( $company_list ) > 0 ) {
					set_transient( $cache_key, $company_list, 24 * HOUR_IN_SECONDS );
				}
			}
		}
	}

	/**
	 * URL that will receive the webhooks.
	 *
	 * @return string
	 */
	private function get_events_url() {
		return sprintf( '%s/wc-api/%s', get_site_url(), WC_API_CALLBACK );
	}

	/**
	 * Displays notifications when the admin has something wrong with the NFe.io configuration.
	 *
	 * @return void
	 */
	public function display_errors() {
		if ( $this->is_active() ) {
			if ( empty( nfe_get_field( 'api_key' ) ) ) {
				echo $this->get_message( '<strong>' . __( 'WooCommerce NFe', 'woo-nfe' ) . '</strong>: ' . sprintf( __( 'Plugin is enabled but no API key was provided. You should inform your API Key. %s', 'woo-nfe' ), '<a href="' . WOOCOMMERCE_NFE_SETTINGS_URL . '">' . __( 'Click here to configure!', 'woo-nfe' ) . '</a>' ) );
			}

			if ( nfe_get_field( 'issue_past_notes' ) === 'yes' && $this->issue_past_days() ) {
				echo $this->get_message( '<strong>' . __( 'WooCommerce NFe', 'woo-nfe' ) . '</strong>: ' . sprintf( __( 'Enable Retroactive Issue is enabled, but no days was added. %s.', 'woo-nfe' ), '<a href="' . WOOCOMMERCE_NFE_SETTINGS_URL . '">' . __( 'Add a date to calculate or disable it.', 'woo-nfe' ) . '</a>' ) );
			}
		}
	}

	/**
	 * Display message to user if there is an issue when fetching the companies.
	 *
	 * @return void
	 */
	public function nfe_api_error_msg() {
		echo $this->get_message( '<strong>' . __( 'WooCommerce NFe.io', 'woo-nfe' ) . '</strong>: ' . sprintf( __( 'Unable to load the companies list from NFe.io.', 'woo-nfe' ) ) );
	}

	/**
	 * Get error message.
	 *
	 * @param string $message Message.
	 * @param string $type    Message type.
	 *
	 * @return string Error
	 */
	private function get_message( $message, $type = 'error' ) {
		ob_start();
		?>
		<div class="<?php echo esc_attr( $type ); ?>">
			<p><?php echo $message; // WPCS: XSS ok. ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Issue past date check.
	 *
	 * @return bool
	 */
	public function issue_past_days() {
		$days = nfe_get_field( 'issue_past_days' );

		if ( empty( $days ) ) {
			return true;
		}

		return false;
	}

	/**
	 * The API key exists?
	 *
	 * @return bool
	 */
	public function has_api_key() {
		$key = nfe_get_field( 'api_key' );

		if ( empty( $key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Is the plugin active?
	 *
	 * @return bool
	 */
	public function is_active() {
		$enabled = nfe_get_field( 'nfe_enable' );

		if ( empty( $enabled ) ) {
			return false;
		}

		if ( 'yes' === $enabled ) {
			return true;
		}

		return false;
	}
}
