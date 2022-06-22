<?php
/**
 * Exit if accessed directly.
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( class_exists( 'WC_Integration' ) ) {
	/**
	 * WooCommerce NFe.io Integration.
	 *
	 * @author   NFe.io
	 *
	 * @category Admin
	 *
	 * @version  1.0.1
	 */
	class WC_NFe_Integration extends WC_Integration {
		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			$this->id                 = 'woo-nfe';
			$this->method_title       = __( 'Receipts (NFE.io)', 'woo-nfe' );
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
		 */
		public function init_form_fields() {
			if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php' ) ) {
				$custom_fields_plugin         = 'yes';
				$custom_fields_plugin_message = 'instalado';
				$description                  = '';
			} else {
				$custom_fields_plugin         = 'no';
				$custom_fields_plugin_message = 'não instalado';
				$description                  = __( '<a href="plugin-install.php?tab=plugin-information&amp;plugin=woocommerce-extra-checkout-fields-for-brazil&" aria-label="Mais informações sobre Brazilian Market on WooCommerce" data-title="Brazilian Market on WooCommerce">Ver detalhes</a>', 'woo-nfe' );
			}

			if ( $this->has_api_key() ) {
				$lists = $this->get_companies();

				if ( empty( $lists ) ) {
					$company_list = array_merge( array( '' => __( 'No company found', 'woo-nfe' ) ), $lists );
				} else {
					$company_list = array_merge( array( '' => __( 'Select a company...', 'woo-nfe' ) ), $lists );
				}
			} else {
				$company_list = array(
					'no-company' => __( 'Enter your API key to see your company(ies).', 'woo-nfe' ),
				);
			}

			$this->form_fields = array(
				'custom_fields'            => array(
					'title'       => __( 'Custom Fields Plugin', 'woo-nfe' ),
					'type'        => 'checkbox',
					'label'       => __( $custom_fields_plugin_message, 'woo-nfe' ),
					'default'     => $custom_fields_plugin,
					'disabled'    => true,
					'description' => $description,
				),
				'nfe_enable'               => array(
					'title'   => __( 'Enable/Disable', 'woo-nfe' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable NFe.io', 'woo-nfe' ),
					'default' => 'yes',
				),
				'api_key'                  => array(
					'title'       => __( 'API Key', 'woo-nfe' ),
					'type'        => 'text',
					'label'       => __( 'API Key', 'woo-nfe' ),
					'default'     => '',
					'description' => sprintf( __( '%s to look up API Key', 'woo-nfe' ), '<a href="' . esc_url( 'https://app.nfe.io/account/apikeys' ) . '">' . __( 'Click here', 'woo-nfe' ) . '</a>' ),
				),
				'choose_company'           => array(
					'title'       => __( 'Choose the Company', 'woo-nfe' ),
					'type'        => 'select',
					'label'       => __( 'Choose the Company', 'woo-nfe' ),
					'default'     => '',
					'options'     => $company_list,
					'class'       => 'wc-enhanced-select',
					'css'         => 'min-width:300px;',
					'desc_tip'    => __( 'Choose one of your companies.', 'woo-nfe' ),
					'description' => sprintf( __( '%s to check the registered companies', 'woo-nfe' ), '<a href="' . esc_url( 'https://app.nfe.io/companies' ) . '">' . __( 'Click here', 'woo-nfe' ) . '</a>' ),
				),
				'issue_when'               => array(
					'title'    => __( 'NFe Issuing', 'woo-nfe' ),
					'type'     => 'select',
					'label'    => __( 'NFe Issuing', 'woo-nfe' ),
					'default'  => 'auto',
					'options'  => array(
						'auto'   => __( 'Automattic (Default)', 'woo-nfe' ),
						'manual' => __( 'Manual', 'woo-nfe' ),
					),
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width:300px;',
					'desc_tip' => __( 'Option to issue a NFe.', 'woo-nfe' ),
				),
				'issue_when_status'        => array(
					'title'    => __( 'Issue on order status', 'woo-nfe' ),
					'type'     => 'select',
					'label'    => __( 'Issue on order status', 'woo-nfe' ),
					'default'  => 'wc-completed',
					'options'  => array(
						'pending'    => _x( 'Pending Payment', 'Order status', 'woo-nfe' ),
						'processing' => _x( 'Processing', 'Order status', 'woo-nfe' ),
						'on-hold'    => _x( 'On Hold', 'Order status', 'woo-nfe' ),
						'completed'  => _x( 'Completed', 'Order status', 'woo-nfe' ),
					),
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width:300px;',
					'desc_tip' => __( 'Option to issue a NFe.', 'woo-nfe' ),
				),
				'require_address'          => array(
					'title'    => __( 'Require address to issue', 'woo-nfe' ),
					'type'     => 'select',
					'label'    => __( 'Does an address is required to issue a NFe?', 'woo-nfe' ),
					'default'  => 'yes',
					'options'  => array(
						'yes' => __( 'Yes (Default)', 'woo-nfe' ),
						'no'  => __( 'No', 'woo-nfe' ),
					),
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width:300px;',
					'desc_tip' => __( 'Does an address is required to issue a NFe?', 'woo-nfe' ),
				),
				'nfe_events_title'         => array(
					'title' => __( 'NFe.io Webkook Setup', 'woo-nfe' ),
					'type'  => 'title',
				),
				'nfe_webhook_url'          => array(
					'title'             => __( 'Webhook URL', 'woo-nfe' ),
					'type'              => 'text',
					'label'             => __( 'Webhook URL', 'woo-nfe' ),
					'default'           => $this->get_events_url(),
					'custom_attributes' => array(
						'readonly' => 'readonly',
					),
					'description'       => sprintf( __( 'Copy this link and use it to set up the %s', 'woo-nfe' ), '<a href="' . esc_url( 'https://app.nfe.io/account/webhooks' ) . '">' . __( 'NFe.io Webhooks', 'woo-nfe' ) . '</a>' ),
				),
				'issue_past_title'         => array(
					'title' => __( 'Manual Retroactive Issue of NFe', 'woo-nfe' ),
					'type'  => 'title',
				),
				'issue_past_notes'         => array(
					'title'       => __( 'Enable Retroactive Issue', 'woo-nfe' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable to issue NFe.io in past products', 'woo-nfe' ),
					'default'     => 'no',
					'description' => __( 'Enabling this allows users to issue nfe.io notes on bought products in the past.', 'woo-nfe' ),
				),
				'issue_past_days'          => array(
					'title'    => __( 'Days in the past', 'woo-nfe' ),
					'type'     => 'number',
					'default'  => '60',
					'css'      => 'width:50px;',
					'desc_tip' => __( 'Days in the past to allow NFe manual issue.', 'woo-nfe' ),
				),
				'nfe_fiscal_title'         => array(
					'title'       => __( 'Receipt Service Settings', 'woo-nfe' ),
					'type'        => 'title',
					'description' => sprintf(
						__( 'If you are in doubt on how to fill the fields below, ask for help from you accountant or get in contact with our team via <a href="mailto:%1$s">%2$s</a>', 'woo-nfe' ),
						antispambot( 'suporte@nfe.io' ),
						antispambot( 'suporte@nfe.io' )
					),
				),
				'nfe_cityservicecode'      => array(
					'title'    => __( 'City Service Code (CityServiceCode)', 'woo-nfe' ),
					'type'     => 'text',
					'label'    => __( 'City Service Code', 'woo-nfe' ),
					'default'  => '',
					'desc_tip' => __( 'City Service Code, this is the code that will identify to the cityhall which type of service you are delivering.', 'woo-nfe' ),
				),
				'nfe_fedservicecode'       => array(
					'title'    => __( 'Federal Service Code LC 116 (FederalServiceCode)', 'woo-nfe' ),
					'type'     => 'text',
					'label'    => __( 'Federal Service Code', 'woo-nfe' ),
					'default'  => '',
					'desc_tip' => __( 'Service Code based on the Federal Law (LC 116), this is a federal code that will identify to the cityhall which type of service you are delivering.', 'woo-nfe' ),
				),
				'nfe_cityservicecode_desc' => array(
					'title'    => __( 'Service Description', 'woo-nfe' ),
					'type'     => 'text',
					'label'    => __( 'Service Description', 'woo-nfe' ),
					'default'  => '',
					'desc_tip' => __( 'Put the description that will appear in the receipt. This description must explain in detail what service was delivered. Ask your accountant, if in doubt.', 'woo-nfe' ),
				),
				'debug'                    => array(
					'title'       => __( 'Debug Log', 'woo-nfe' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'woo-nfe' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Log events such as API requests, you can check this log in %s.', 'woo-nfe' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status - Logs', 'woo-nfe' ) . '</a>' ),
				),
			);

			return apply_filters( 'woo_nfe_settings_' . $this->id, $this->form_fields );
		}

		/**
		 * Displays notifications when the admin has something wrong with the NFe.io configuration.
		 */
		public function display_errors() {
			// Bail early.
			if ( ! $this->is_active() ) {
				return;
			}

			if ( ! $this->has_api_key() ) {
				echo wp_kses_post( $this->get_message( '<strong>' . __( 'WooCommerce NFe', 'woo-nfe' ) . '</strong>: ' . sprintf( __( 'Plugin is enabled but no API key was provided. You should inform your API Key. %s', 'woo-nfe' ), '<a href="' . WOOCOMMERCE_NFE_SETTINGS_URL . '">' . __( 'Click here to configure!', 'woo-nfe' ) . '</a>' ) ) ); // phpcs:ignoreStandard.Category.SniffName.ErrorCode
			}

			$issue_past_notes = nfe_get_field( 'issue_past_notes' );
			if ( $issue_past_notes && $this->issue_past_days() === 'yes' ) {
				echo wp_kses_post( $this->get_message( '<strong>' . __( 'WooCommerce NFe', 'woo-nfe' ) . '</strong>: ' . sprintf( __( 'Enable Retroactive Issue is enabled, but no days was added. %s.', 'woo-nfe' ), '<a href="' . WOOCOMMERCE_NFE_SETTINGS_URL . '">' . __( 'Add a date to calculate or disable it.', 'woo-nfe' ) . '</a>' ) ) ); // phpcs:ignoreStandard.Category.SniffName.ErrorCode
			}
		}

		/**
		 * Display message to user if there is an issue when fetching the companies.
		 */
		public function nfe_api_error_msg() {
			echo wp_kses_post( $this->get_message( '<strong>' . __( 'WooCommerce NFe.io', 'woo-nfe' ) . '</strong>: ' . sprintf( __( 'Unable to load the companies list from NFe.io.', 'woo-nfe' ) ) ) ); // phpcs:ignoreStandard.Category.SniffName.ErrorCode
		}

		/**
		 * Fetches companies via the NFe API.
		 *
		 * @return array|bool bail with error message | An array of companies
		 */
		protected function get_companies() {
			$key          = nfe_get_field( 'api_key' );
			$cache_key    = 'woo_nfecompanylist_' . md5( $key );
			$company_list = get_transient( $cache_key );

			// If there is a list from cache, load it.
			if ( ! empty( $company_list ) && is_array( $company_list ) ) {
				return $company_list;
			}

			NFe_io::setApiKey( $key );

			$companies = NFe_Company::search();

			// Bail early with error message.
			if ( ! empty( $companies->message ) || empty( $companies ) || empty( $companies['companies'] ) ) {
				add_action( 'admin_notices', array( $this, 'nfe_api_error_msg' ) );
				add_action( 'network_admin_notices', array( $this, 'nfe_api_error_msg' ) );

				return false;
			}

			$company_list = array();
			foreach ( $companies['companies'] as $company ) {
				$company_list[ $company->id ] = ucwords( strtolower( $company->name ) );
			}

			// Save it for 30 days.
			set_transient( $cache_key, $company_list, 30 * DAY_IN_SECONDS );

			return $company_list;
		}

		/**
		 * URL that will receive the webhooks.
		 *
		 * @return string
		 */
		protected function get_events_url() {
			return sprintf( '%s/wc-api/%s', get_site_url(), WC_API_CALLBACK );
		}

		/**
		 * Issue past date check.
		 *
		 * @return bool
		 */
		protected function issue_past_days() {
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
		protected function has_api_key() {
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
		protected function is_active() {
			$enabled = nfe_get_field( 'nfe_enable' );

			if ( empty( $enabled ) ) {
				return false;
			}

			if ( 'yes' === $enabled ) {
				return true;
			}

			return false;
		}

		/**
		 * Get error message.
		 *
		 * @param string $message message.
		 * @param string $type message type.
		 *
		 * @return string Error
		 */
		private function get_message( $message, $type = 'error' ) {
			ob_start(); ?>
			<div class="<?php echo esc_attr( $type ); ?>">
				<p><?php echo esc_html( $message ); // phpcs:ignoreStandard.Category.SniffName.ErrorCode ?></p>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}
