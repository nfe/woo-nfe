<?php
/**
 * WooCommerce NFe.io Integration.
 *
 * @author   NFe.io
 * @category Admin
 * @package  WooCommerce_NFe/Class/WC_NFe_Integration
 * @version  1.0.1
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( class_exists( 'WC_Integration' ) ) {

	/**
	 * WC_NFe_Integration Class.
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
				$custom_fields_plugin_message = __( 'instalado', 'woo-nfe' );
				$description                  = '';
			} else {
				$custom_fields_plugin         = 'no';
				$custom_fields_plugin_message = __( 'não instalado', 'woo-nfe' );
				$description                  = sprintf(
					'<a href="%1$s" aria-label="%2$s" data-title="Brazilian Market on WooCommerce">%3$s</a>',
					esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woocommerce-extra-checkout-fields-for-brazil' ) ),
					esc_attr__( 'Mais informações sobre Brazilian Market on WooCommerce', 'woo-nfe' ),
					esc_html__( 'Ver detalhes', 'woo-nfe' )
				);
			}

			if ( $this->has_api_key() ) {
				// Get companies. If no companies, return an empty array.
				$lists = $this->get_companies() ? $this->get_companies() : array();

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
				'custom_fields'               => array(
					'title'       => __( 'Custom Fields Plugin', 'woo-nfe' ),
					'type'        => 'checkbox',
					'label'       => $custom_fields_plugin_message,
					'default'     => $custom_fields_plugin,
					'disabled'    => true,
					'description' => $description,
				),
				'nfe_enable'                  => array(
					'title'   => __( 'Enable/Disable', 'woo-nfe' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable NFe.io', 'woo-nfe' ),
					'default' => 'yes',
				),
				'api_key'                     => array(
					'title'       => __( 'API Key', 'woo-nfe' ),
					'type'        => 'password',
					'label'       => __( 'API Key', 'woo-nfe' ),
					'default'     => '',
					/* translators: %s: link to the NFe.io API keys page. */
					'description' => sprintf( __( '%s to look up API Key', 'woo-nfe' ), '<a href="' . esc_url( 'https://app.nfe.io/account/apikeys' ) . '">' . esc_html__( 'Click here', 'woo-nfe' ) . '</a>' ),
				),
				'choose_company'              => array(
					'title'       => __( 'Choose the Company', 'woo-nfe' ),
					'type'        => 'select',
					'label'       => __( 'Choose the Company', 'woo-nfe' ),
					'default'     => '',
					'options'     => $company_list,
					'class'       => 'wc-enhanced-select',
					'css'         => 'min-width:300px;',
					'desc_tip'    => __( 'Choose one of your companies.', 'woo-nfe' ),
					/* translators: %s: link to the NFe.io companies page. */
					'description' => sprintf( __( '%s to check the registered companies', 'woo-nfe' ), '<a href="' . esc_url( 'https://app.nfe.io/companies' ) . '">' . esc_html__( 'Click here', 'woo-nfe' ) . '</a>' ),
				),
				'issue_when'                  => array(
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
				'issue_when_status'           => array(
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
				'require_address'             => array(
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
				'highlight_shipping_tax'      => array(
					'title'    => __( 'Highlight shipping from taxes', 'woo-nfe' ),
					'type'     => 'select',
					'label'    => __( 'Highlight shipping from taxes', 'woo-nfe' ),
					'default'  => 'include_shipping',
					'options'  => array(
						'include_shipping' => __( 'Include Shipping fees on tax calculation', 'woo-nfe' ),
						'exclude_shipping' => __( 'Exclude Shipping fees on tax calculation', 'woo-nfe' ),
					),
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width:300px;',
					'desc_tip' => __( 'Tax Formation: total + shipping will considerate ship value on tax calculation. Total - shipping will not considerate ship value on tax calculation.', 'woo-nfe' ),
				),
				'nfe_events_title'            => array(
					'title' => __( 'NFe.io Webhook Setup', 'woo-nfe' ),
					'type'  => 'title',
				),
				'nfe_webhook_url'             => array(
					'title'             => __( 'Webhook URL', 'woo-nfe' ),
					'type'              => 'text',
					'label'             => __( 'Webhook URL', 'woo-nfe' ),
					'default'           => $this->get_events_url(),
					'custom_attributes' => array(
						'readonly' => 'readonly',
					),
					'description'       => __( 'The address NFe.io delivers invoice status updates to. The plugin registers it for you; it is shown here for reference.', 'woo-nfe' ),
				),
				'nfe_webhook_status'          => array(
					'title'             => __( 'Webhook status', 'woo-nfe' ),
					'type'              => 'text',
					'default'           => $this->get_webhook_status(),
					'custom_attributes' => array(
						'readonly' => 'readonly',
					),
					'description'       => $this->get_webhook_action_link(),
				),
				'issue_past_title'            => array(
					'title' => __( 'Manual Retroactive Issue of NFe', 'woo-nfe' ),
					'type'  => 'title',
				),
				'issue_past_notes'            => array(
					'title'       => __( 'Enable Retroactive Issue', 'woo-nfe' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable to issue NFe.io in past products', 'woo-nfe' ),
					'default'     => 'no',
					'description' => __( 'Enabling this allows users to issue nfe.io notes on bought products in the past.', 'woo-nfe' ),
				),
				'issue_past_days'             => array(
					'title'    => __( 'Days in the past', 'woo-nfe' ),
					'type'     => 'number',
					'default'  => '60',
					'css'      => 'width:50px;',
					'desc_tip' => __( 'Days in the past to allow NFe manual issue.', 'woo-nfe' ),
				),
				'nfe_fiscal_title'            => array(
					'title'       => __( 'Receipt Service Settings', 'woo-nfe' ),
					'type'        => 'title',
					'description' => sprintf(
						/* translators: 1: support e-mail address used in the mailto link, 2: support e-mail address shown to the user. */
						__( 'If you are in doubt on how to fill the fields below, ask for help from you accountant or get in contact with our team via <a href="mailto:%1$s">%2$s</a>', 'woo-nfe' ),
						antispambot( 'suporte@nfe.io' ),
						antispambot( 'suporte@nfe.io' )
					),
				),
				'nfe_cityservicecode'         => array(
					'title'    => __( 'City Service Code (CityServiceCode)', 'woo-nfe' ),
					'type'     => 'text',
					'label'    => __( 'City Service Code', 'woo-nfe' ),
					'default'  => '',
					'desc_tip' => __( 'City Service Code, this is the code that will identify to the cityhall which type of service you are delivering.', 'woo-nfe' ),
				),
				'nfe_fedservicecode'          => array(
					'title'    => __( 'Federal Service Code LC 116 (FederalServiceCode)', 'woo-nfe' ),
					'type'     => 'text',
					'label'    => __( 'Federal Service Code', 'woo-nfe' ),
					'default'  => '',
					'desc_tip' => __( 'Service Code based on the Federal Law (LC 116), this is a federal code that will identify to the cityhall which type of service you are delivering.', 'woo-nfe' ),
				),
				'nfe_cityservicecode_desc'    => array(
					'title'    => __( 'Service Description', 'woo-nfe' ),
					'type'     => 'text',
					'label'    => __( 'Service Description', 'woo-nfe' ),
					'default'  => '',
					'desc_tip' => __( 'Put the description that will appear in the receipt. This description must explain in detail what service was delivered. Ask your accountant, if in doubt.', 'woo-nfe' ),
				),
				'nfe_rtc_title'               => array(
					'title'       => __( 'RTC tax reform settings', 'woo-nfe' ),
					'type'        => 'title',
					'description' => __( 'Fallback values used in RTC emission when variation or product fields are not filled.', 'woo-nfe' ),
				),
				'nfe_rtc_nbs_code'            => array(
					'title'    => __( 'NBS code (nbsCode)', 'woo-nfe' ),
					'type'     => 'text',
					'label'    => __( 'NBS code', 'woo-nfe' ),
					'default'  => '',
					'desc_tip' => __( 'Default NBS code used as global fallback for RTC emissions.', 'woo-nfe' ),
				),
				'nfe_rtc_operation_indicator' => array(
					'title'    => __( 'Operation indicator (ibsCbs.operationIndicator)', 'woo-nfe' ),
					'type'     => 'text',
					'label'    => __( 'Operation indicator', 'woo-nfe' ),
					'default'  => '',
					'desc_tip' => __( 'Default operation indicator used as global fallback for RTC emissions.', 'woo-nfe' ),
				),
				'nfe_rtc_class_code'          => array(
					'title'    => __( 'Class code (ibsCbs.classCode)', 'woo-nfe' ),
					'type'     => 'text',
					'label'    => __( 'Class code', 'woo-nfe' ),
					'default'  => '',
					'desc_tip' => __( 'Default class code used as global fallback for RTC emissions.', 'woo-nfe' ),
				),
				'nfe_rtc_validation_profile'  => array(
					'title'    => __( 'RTC validation profile', 'woo-nfe' ),
					'type'     => 'select',
					'label'    => __( 'RTC validation profile', 'woo-nfe' ),
					'default'  => 'equilibrado',
					'options'  => array(
						'compativel'  => __( 'Compatible (warn only for nbsCode)', 'woo-nfe' ),
						'equilibrado' => __( 'Balanced (default)', 'woo-nfe' ),
						'estrito'     => __( 'Strict (always require nbsCode)', 'woo-nfe' ),
					),
					'class'    => 'wc-enhanced-select',
					'css'      => 'min-width:300px;',
					'desc_tip' => __( 'Controls nbsCode blocking behavior in RTC emissions.', 'woo-nfe' ),
				),
				'nfe_rtc_integration_note'    => array(
					'title'       => __( 'Advanced RTC fields (integration)', 'woo-nfe' ),
					'type'        => 'title',
					'description' => __( 'recipient and destinationIndicator are supported via payload integration filters in phase 1, without dedicated checkout/admin UI.', 'woo-nfe' ),
				),
				'debug'                       => array(
					'title'       => __( 'Debug Log', 'woo-nfe' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'woo-nfe' ),
					'default'     => 'no',
					/* translators: %s: link to the WooCommerce system status logs screen. */
					'description' => sprintf( __( 'Log events such as API requests, you can check this log in %s.', 'woo-nfe' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . $this->id . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . esc_html__( 'System Status - Logs', 'woo-nfe' ) . '</a>' ),
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

			$settings_link = '<a href="' . esc_url( WOOCOMMERCE_NFE_SETTINGS_URL ) . '">';

			if ( ! $this->has_api_key() ) {
				echo wp_kses_post(
					$this->get_message(
						'<strong>' . esc_html__( 'WooCommerce NFe', 'woo-nfe' ) . '</strong>: ' . sprintf(
							/* translators: %s: link to the plugin settings page. */
							__( 'Plugin is enabled but no API key was provided. You should inform your API Key. %s', 'woo-nfe' ),
							$settings_link . esc_html__( 'Click here to configure!', 'woo-nfe' ) . '</a>'
						)
					)
				);
			}

			$issue_past_notes = nfe_get_field( 'issue_past_notes' );
			if ( $issue_past_notes && $this->issue_past_days() === 'yes' ) {
				echo wp_kses_post(
					$this->get_message(
						'<strong>' . esc_html__( 'WooCommerce NFe', 'woo-nfe' ) . '</strong>: ' . sprintf(
							/* translators: %s: link to the plugin settings page. */
							__( 'Enable Retroactive Issue is enabled, but no days was added. %s.', 'woo-nfe' ),
							$settings_link . esc_html__( 'Add a date to calculate or disable it.', 'woo-nfe' ) . '</a>'
						)
					)
				);
			}
		}

		/**
		 * Display message to user if there is an issue when fetching the companies.
		 */
		public function nfe_api_error_msg() {
			echo wp_kses_post( $this->get_message( '<strong>' . esc_html__( 'WooCommerce NFe.io', 'woo-nfe' ) . '</strong>: ' . esc_html__( 'Unable to load the companies list from NFe.io.', 'woo-nfe' ) ) );
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
				add_action( 'admin_notices', array( $this, 'nfe_api_error_msg' ) );
				add_action( 'network_admin_notices', array( $this, 'nfe_api_error_msg' ) );

				return false;
			}

			if ( empty( $companies ) ) {
				add_action( 'admin_notices', array( $this, 'nfe_api_error_msg' ) );
				add_action( 'network_admin_notices', array( $this, 'nfe_api_error_msg' ) );

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
			if ( '' === WC_NFe_Webhook_Provisioner::secret() ) {
				return __( 'Not set up yet - invoice status updates are not being received.', 'woo-nfe' );
			}

			return __( 'Active and verifying signatures.', 'woo-nfe' );
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
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=nfe_provision_webhook' ), 'nfe_provision_webhook' );

			$label = '' === WC_NFe_Webhook_Provisioner::secret()
				? __( 'Set up the webhook', 'woo-nfe' )
				: __( 'Regenerate the secret and re-register the webhook', 'woo-nfe' );

			return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
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
