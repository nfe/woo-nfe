<?php
/**
 * WooCommerce NFE.io Integration.
 *
 * @author   NFE.io
 * @category Admin
 * @package  NFEIO_NF_Plugin/Class/NFEIO_NF_Integration
 * @version  1.0.1
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( class_exists( 'WC_Integration' ) ) {

	/**
	 * NFEIO_NF_Integration Class.
	 */
	class NFEIO_NF_Integration extends WC_Integration {
		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			$this->id                 = 'woo-nfe';
			$this->method_title       = __( 'Receipts (NFE.io)', 'nfe-io-nota-fiscal-for-woocommerce' );
			$this->method_description = __( 'This is the NFE.io integration/settings page.', 'nfe-io-nota-fiscal-for-woocommerce' );

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
				$custom_fields_plugin_message = __( 'instalado', 'nfe-io-nota-fiscal-for-woocommerce' );
				$description                  = '';
			} else {
				$custom_fields_plugin         = 'no';
				$custom_fields_plugin_message = __( 'não instalado', 'nfe-io-nota-fiscal-for-woocommerce' );
				$description                  = sprintf(
					'<a href="%1$s" aria-label="%2$s" data-title="Brazilian Market on WooCommerce">%3$s</a>',
					esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woocommerce-extra-checkout-fields-for-brazil' ) ),
					esc_attr__( 'Mais informações sobre Brazilian Market on WooCommerce', 'nfe-io-nota-fiscal-for-woocommerce' ),
					esc_html__( 'Ver detalhes', 'nfe-io-nota-fiscal-for-woocommerce' )
				);
			}

			if ( $this->has_api_key() ) {
				// Get companies. If no companies, return an empty array.
				$lists = $this->get_companies() ? $this->get_companies() : array();

				if ( empty( $lists ) ) {
					$company_list = array_merge( array( '' => __( 'No company found', 'nfe-io-nota-fiscal-for-woocommerce' ) ), $lists );
				} else {
					$company_list = array_merge( array( '' => __( 'Select a company...', 'nfe-io-nota-fiscal-for-woocommerce' ) ), $lists );
				}
			} else {
				$company_list = array(
					'no-company' => __( 'Enter your API key to see your company(ies).', 'nfe-io-nota-fiscal-for-woocommerce' ),
				);
			}

			$this->form_fields = array(
				'custom_fields'               => array(
					'title'       => __( 'Custom Fields Plugin', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'        => 'checkbox',
					'label'       => $custom_fields_plugin_message,
					'default'     => $custom_fields_plugin,
					'disabled'    => true,
					'description' => $description,
				),
				'nfe_enable'                  => array(
					'title'   => __( 'Enable/Disable', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable NFE.io', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default' => 'yes',
				),
				'api_key'                     => array(
					'title'       => __( 'API Key', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'        => 'password',
					'label'       => __( 'API Key', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'     => '',
					/* translators: %s: link to the NFE.io API keys page. */
					'description' => sprintf( __( '%s to look up API Key', 'nfe-io-nota-fiscal-for-woocommerce' ), '<a href="' . esc_url( 'https://app.nfe.io/account/apikeys' ) . '">' . esc_html_x( 'Click here', 'link to the NFE.io API keys page', 'nfe-io-nota-fiscal-for-woocommerce' ) . '</a>' ),
				),
				'choose_company'              => array(
					'title'       => __( 'Choose the Company', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'        => 'select',
					'label'       => __( 'Choose the Company', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'     => '',
					'options'     => $company_list,
					'class'       => 'wc-enhanced-select',
					'css'         => 'min-width:300px;',
					'desc_tip'    => __( 'Choose one of your companies.', 'nfe-io-nota-fiscal-for-woocommerce' ),
					/* translators: %s: link to the NFE.io companies page. */
					'description' => sprintf( __( '%s to check the registered companies', 'nfe-io-nota-fiscal-for-woocommerce' ), '<a href="' . esc_url( 'https://app.nfe.io/companies' ) . '">' . esc_html_x( 'Click here', 'link to the NFE.io companies page', 'nfe-io-nota-fiscal-for-woocommerce' ) . '</a>' ),
				),
				'issue_when'                  => array(
					'title'    => __( 'NFe Issuing', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'select',
					'label'    => __( 'NFe Issuing', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'  => 'auto',
					'options'  => array(
						'auto'   => __( 'Automattic (Default)', 'nfe-io-nota-fiscal-for-woocommerce' ),
						'manual' => __( 'Manual', 'nfe-io-nota-fiscal-for-woocommerce' ),
					),
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width:300px;',
					'desc_tip' => __( 'Option to issue a NFe.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'issue_when_status'           => array(
					'title'    => __( 'Issue on order status', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'select',
					'label'    => __( 'Issue on order status', 'nfe-io-nota-fiscal-for-woocommerce' ),
					// Sem o prefixo 'wc-': as opcoes abaixo nao o usam, e o
					// consumidor e WC_Order::has_status(), que compara com
					// get_status() -- que devolve o status ja sem o prefixo.
					// Com 'wc-completed' o padrao nao casava com nada: o select
					// caia na primeira opcao (Pending Payment) e a emissao
					// automatica nunca disparava em instalacao nova.
					'default'  => 'completed',
					'options'  => array(
						'pending'    => _x( 'Pending Payment', 'Order status', 'nfe-io-nota-fiscal-for-woocommerce' ),
						'processing' => _x( 'Processing', 'Order status', 'nfe-io-nota-fiscal-for-woocommerce' ),
						'on-hold'    => _x( 'On Hold', 'Order status', 'nfe-io-nota-fiscal-for-woocommerce' ),
						'completed'  => _x( 'Completed', 'Order status', 'nfe-io-nota-fiscal-for-woocommerce' ),
					),
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width:300px;',
					'desc_tip' => __( 'Option to issue a NFe.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'require_address'             => array(
					'title'    => __( 'Require address to issue', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'select',
					'label'    => __( 'Does an address is required to issue a NFe?', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'  => 'yes',
					'options'  => array(
						'yes' => __( 'Yes (Default)', 'nfe-io-nota-fiscal-for-woocommerce' ),
						'no'  => __( 'No', 'nfe-io-nota-fiscal-for-woocommerce' ),
					),
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width:300px;',
					'desc_tip' => __( 'Does an address is required to issue a NFe?', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'highlight_shipping_tax'      => array(
					'title'    => __( 'Highlight shipping from taxes', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'select',
					'label'    => __( 'Highlight shipping from taxes', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'  => 'include_shipping',
					'options'  => array(
						'include_shipping' => __( 'Include Shipping fees on tax calculation', 'nfe-io-nota-fiscal-for-woocommerce' ),
						'exclude_shipping' => __( 'Exclude Shipping fees on tax calculation', 'nfe-io-nota-fiscal-for-woocommerce' ),
					),
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width:300px;',
					'desc_tip' => __( 'Tax Formation: total + shipping will considerate ship value on tax calculation. Total - shipping will not considerate ship value on tax calculation.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_events_title'            => array(
					'title' => __( 'NFE.io Webhook Setup', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'  => 'title',
				),
				'nfe_webhook_url'             => array(
					'title'             => __( 'Webhook URL', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'              => 'text',
					'label'             => __( 'Webhook URL', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'           => $this->get_events_url(),
					'custom_attributes' => array(
						'readonly' => 'readonly',
					),
					'description'       => __( 'The address NFE.io delivers invoice status updates to. The plugin registers it for you; it is shown here for reference.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_webhook_status'          => array(
					'title'             => __( 'Webhook status', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'              => 'text',
					'default'           => $this->get_webhook_status(),
					'custom_attributes' => array(
						'readonly' => 'readonly',
					),
					'description'       => $this->get_webhook_action_link(),
				),
				'issue_past_title'            => array(
					'title' => __( 'Manual Retroactive Issue of NFe', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'  => 'title',
				),
				'issue_past_notes'            => array(
					'title'       => __( 'Enable Retroactive Issue', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable to issue NFE.io in past products', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'     => 'no',
					'description' => __( 'Enabling this allows users to issue nfe.io notes on bought products in the past.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'issue_past_days'             => array(
					'title'    => __( 'Days in the past', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'number',
					'default'  => '60',
					'css'      => 'width:50px;',
					'desc_tip' => __( 'Days in the past to allow NFe manual issue.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_fiscal_title'            => array(
					'title'       => __( 'Receipt Service Settings', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'        => 'title',
					'description' => sprintf(
						/* translators: 1: support e-mail address used in the mailto link, 2: support e-mail address shown to the user. */
						__( 'If you are in doubt on how to fill the fields below, ask for help from you accountant or get in contact with our team via <a href="mailto:%1$s">%2$s</a>', 'nfe-io-nota-fiscal-for-woocommerce' ),
						antispambot( 'suporte@nfe.io' ),
						antispambot( 'suporte@nfe.io' )
					),
				),
				'nfe_cityservicecode'         => array(
					'title'    => __( 'City Service Code (CityServiceCode)', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'text',
					'label'    => __( 'City Service Code', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'  => '',
					'desc_tip' => __( 'City Service Code, this is the code that will identify to the cityhall which type of service you are delivering.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_fedservicecode'          => array(
					'title'    => __( 'Federal Service Code LC 116 (FederalServiceCode)', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'text',
					'label'    => __( 'Federal Service Code', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'  => '',
					'desc_tip' => __( 'Service Code based on the Federal Law (LC 116), this is a federal code that will identify to the cityhall which type of service you are delivering.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_cityservicecode_desc'    => array(
					'title'    => __( 'Service Description', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'text',
					'label'    => __( 'Service Description', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'  => '',
					'desc_tip' => __( 'Put the description that will appear in the receipt. This description must explain in detail what service was delivered. Ask your accountant, if in doubt.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_rtc_title'               => array(
					'title'       => __( 'RTC tax reform settings', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'        => 'title',
					'description' => __( 'Fallback values used in RTC emission when variation or product fields are not filled.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_rtc_nbs_code'            => array(
					'title'    => __( 'NBS code (nbsCode)', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'text',
					'label'    => __( 'NBS code', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'  => '',
					'desc_tip' => __( 'Default NBS code used as global fallback for RTC emissions.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_rtc_operation_indicator' => array(
					'title'    => __( 'Operation indicator (ibsCbs.operationIndicator)', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'text',
					'label'    => __( 'Operation indicator', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'  => '',
					'desc_tip' => __( 'Default operation indicator used as global fallback for RTC emissions.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_rtc_class_code'          => array(
					'title'    => __( 'Class code (ibsCbs.classCode)', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'text',
					'label'    => __( 'Class code', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'  => '',
					'desc_tip' => __( 'Default class code used as global fallback for RTC emissions.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_rtc_validation_profile'  => array(
					'title'    => __( 'RTC validation profile', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'     => 'select',
					'label'    => __( 'RTC validation profile', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'  => 'equilibrado',
					'options'  => array(
						'compativel'  => __( 'Compatible (warn only for nbsCode)', 'nfe-io-nota-fiscal-for-woocommerce' ),
						'equilibrado' => __( 'Balanced (default)', 'nfe-io-nota-fiscal-for-woocommerce' ),
						'estrito'     => __( 'Strict (always require nbsCode)', 'nfe-io-nota-fiscal-for-woocommerce' ),
					),
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width:300px;',
					'desc_tip' => __( 'Controls nbsCode blocking behavior in RTC emissions.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'nfe_rtc_integration_note'    => array(
					'title'       => __( 'Advanced RTC fields (integration)', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'        => 'title',
					'description' => __( 'recipient and destinationIndicator are supported via payload integration filters in phase 1, without dedicated checkout/admin UI.', 'nfe-io-nota-fiscal-for-woocommerce' ),
				),
				'debug'                       => array(
					'title'       => __( 'Debug Log', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'nfe-io-nota-fiscal-for-woocommerce' ),
					'default'     => 'no',
					/* translators: %s: link to the WooCommerce system status logs screen. */
					'description' => sprintf( __( 'Log events such as API requests, you can check this log in %s.', 'nfe-io-nota-fiscal-for-woocommerce' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . $this->id . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . esc_html__( 'System Status - Logs', 'nfe-io-nota-fiscal-for-woocommerce' ) . '</a>' ),
				),
			);

			return apply_filters( 'nfeio_nf_settings_' . $this->id, $this->form_fields );
		}

		/**
		 * Displays notifications when the admin has something wrong with the NFE.io configuration.
		 */
		public function display_errors() {
			// Bail early.
			if ( ! $this->is_active() ) {
				return;
			}

			$settings_link = '<a href="' . esc_url( NFEIO_NF_SETTINGS_URL ) . '">';

			if ( ! $this->has_api_key() ) {
				echo wp_kses_post(
					$this->get_message(
						'<strong>' . esc_html__( 'WooCommerce NFe', 'nfe-io-nota-fiscal-for-woocommerce' ) . '</strong>: ' . sprintf(
							/* translators: %s: link to the plugin settings page. */
							__( 'Plugin is enabled but no API key was provided. You should inform your API Key. %s', 'nfe-io-nota-fiscal-for-woocommerce' ),
							$settings_link . esc_html__( 'Click here to configure!', 'nfe-io-nota-fiscal-for-woocommerce' ) . '</a>'
						)
					)
				);
			}

			$issue_past_notes = nfeio_nf_get_field( 'issue_past_notes' );
			if ( $issue_past_notes && $this->issue_past_days() === 'yes' ) {
				echo wp_kses_post(
					$this->get_message(
						'<strong>' . esc_html__( 'WooCommerce NFe', 'nfe-io-nota-fiscal-for-woocommerce' ) . '</strong>: ' . sprintf(
							/* translators: %s: link to the plugin settings page. */
							__( 'Enable Retroactive Issue is enabled, but no days was added. %s.', 'nfe-io-nota-fiscal-for-woocommerce' ),
							$settings_link . esc_html__( 'Add a date to calculate or disable it.', 'nfe-io-nota-fiscal-for-woocommerce' ) . '</a>'
						)
					)
				);
			}
		}

		/**
		 * Display message to user if there is an issue when fetching the companies.
		 */
		public function nfeio_nf_api_error_msg() {
			echo wp_kses_post( $this->get_message( '<strong>' . esc_html__( 'WooCommerce NFE.io', 'nfe-io-nota-fiscal-for-woocommerce' ) . '</strong>: ' . esc_html__( 'Unable to load the companies list from NFE.io.', 'nfe-io-nota-fiscal-for-woocommerce' ) ) );
		}

		/**
		 * Fetches companies via the NFe API.
		 *
		 * @return array|bool bail with error message | An array of companies
		 */
		protected function get_companies() {
			$key          = nfeio_nf_get_field( 'api_key' );
			$cache_key    = 'nfeio_nf_company_list_' . md5( $key );
			$company_list = get_transient( $cache_key );

			// If there is a list from cache, load it.
			if ( ! empty( $company_list ) && is_array( $company_list ) ) {
				return $company_list;
			}

			if ( empty( $key ) ) {
				return false;
			}

			// listAll() pages through the account for us. A failure surfaces as
			// an SDK exception rather than a message field on the result, so the
			// notice is raised from the catch instead of a shape check.
			try {
				$client    = new \Nfe\Client( apiKey: (string) $key, environment: \Nfe\Environment::Production );
				$companies = $client->companies->listAll();
			} catch ( \Nfe\Exception\ApiErrorException $e ) {
				add_action( 'admin_notices', array( $this, 'nfeio_nf_api_error_msg' ) );
				add_action( 'network_admin_notices', array( $this, 'nfeio_nf_api_error_msg' ) );

				return false;
			}

			if ( empty( $companies ) ) {
				add_action( 'admin_notices', array( $this, 'nfeio_nf_api_error_msg' ) );
				add_action( 'network_admin_notices', array( $this, 'nfeio_nf_api_error_msg' ) );

				return false;
			}

			$company_list = array();
			foreach ( $companies as $company ) {
				if ( empty( $company->id ) || empty( $company->name ) ) {
					continue;
				}

				$company_list[ $company->id ] = ucwords( strtolower( $company->name ) );
			}

			if ( empty( $company_list ) ) {
				return false;
			}

			// Save it for 30 days.
			set_transient( $cache_key, $company_list, 30 * DAY_IN_SECONDS );

			return $company_list;
		}

		/**
		 * Human-readable provisioning state of the webhook.
		 *
		 * @since 1.5.0
		 *
		 * @return string
		 */
		protected function get_webhook_status() {
			if ( '' === NFEIO_NF_Webhook_Provisioner::secret() ) {
				return __( 'Not set up yet - invoice status updates are not being received.', 'nfe-io-nota-fiscal-for-woocommerce' );
			}

			return __( 'Active and verifying signatures.', 'nfe-io-nota-fiscal-for-woocommerce' );
		}

		/**
		 * Description holding the (re)provisioning action.
		 *
		 * The secret itself is never rendered: it is the key that authenticates
		 * every incoming event, and showing it in a settings page serves nobody.
		 * Losing it is recovered by regenerating, not by reading it back.
		 *
		 * @since 1.5.0
		 *
		 * @return string
		 */
		protected function get_webhook_action_link() {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=nfeio_nf_provision_webhook' ), 'nfeio_nf_provision_webhook' );

			$label = '' === NFEIO_NF_Webhook_Provisioner::secret()
				? __( 'Set up the webhook', 'nfe-io-nota-fiscal-for-woocommerce' )
				: __( 'Regenerate the secret and re-register the webhook', 'nfe-io-nota-fiscal-for-woocommerce' );

			return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}

		/**
		 * URL that will receive the webhooks.
		 *
		 * @return string
		 */
		protected function get_events_url() {
			return sprintf( '%s/wc-api/%s', get_site_url(), NFEIO_NF_API_CALLBACK );
		}

		/**
		 * Issue past date check.
		 *
		 * @return bool
		 */
		protected function issue_past_days() {
			$days = nfeio_nf_get_field( 'issue_past_days' );

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
			$key = nfeio_nf_get_field( 'api_key' );

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
			$enabled = nfeio_nf_get_field( 'nfe_enable' );

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
		 * @param string $message Message markup, limited to the tags allowed by wp_kses_post().
		 * @param string $type    Message type, used as the wrapper CSS class.
		 *
		 * @return string Error
		 */
		private function get_message( $message, $type = 'error' ) {
			ob_start();
			?>
			<div class="<?php echo esc_attr( $type ); ?>">
				<p><?php echo wp_kses_post( $message ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}
