<?php
/**
 * WooCommerce NFe webhook provisioning.
 *
 * Creates the NFe.io account webhook that feeds this store, and owns the shared
 * secret its signatures are verified against. The store owner never types the
 * secret: it is generated here, sent on creation and kept in an option.
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Webhook_Provisioner
 * @version  1.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provisions and maintains the NFe.io account webhook.
 */
class WC_NFe_Webhook_Provisioner {

	/**
	 * Option holding the HMAC secret shared with NFe.io.
	 *
	 * @var string
	 */
	const SECRET_OPTION = 'nfe_webhook_secret';

	/**
	 * Option holding the id of the webhook this store provisioned.
	 *
	 * @var string
	 */
	const WEBHOOK_OPTION = 'nfe_webhook_id';

	/**
	 * Option holding the last provisioning outcome, for the admin notice.
	 *
	 * @var string
	 */
	const NOTICE_OPTION = 'nfe_webhook_notice';

	/**
	 * Transient set while a creation call is in flight.
	 *
	 * @var string
	 */
	const PROVISIONING_TRANSIENT = 'nfe_webhook_provisioning';

	/**
	 * Bootstraps the class and hooks required actions.
	 *
	 * @return void
	 */
	public static function init() {
		// Runs once per version bump, which is where an existing store picks up
		// the signed webhook.
		add_action( 'nfe_upgraded', array( __CLASS__, 'maybe_provision' ) );

		// A store upgraded before an API key was configured gets provisioned on
		// the first save that supplies one.
		add_action( 'update_option_woocommerce_woo-nfe_settings', array( __CLASS__, 'maybe_provision' ) );

		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
		add_action( 'admin_post_nfe_provision_webhook', array( __CLASS__, 'handle_manual_request' ) );
	}

	/**
	 * The HMAC secret shared with NFe.io.
	 *
	 * @return string Empty when the webhook has not been provisioned yet.
	 */
	public static function secret() {
		return (string) get_option( self::SECRET_OPTION, '' );
	}

	/**
	 * Whether a creation call is in flight right now.
	 *
	 * NFe.io POSTs to the endpoint while creating a webhook and requires a 2xx
	 * answer, so a refusal at that moment would fail the very call that
	 * installs the secret. The handler treats this window as "answer 200, do
	 * nothing", and it closes on its own within minutes.
	 *
	 * @return bool
	 */
	public static function is_provisioning() {
		return (bool) get_transient( self::PROVISIONING_TRANSIENT );
	}

	/**
	 * The URL NFe.io posts events to.
	 *
	 * @return string
	 */
	public static function endpoint_url() {
		return sprintf( '%s/wc-api/%s', get_site_url(), WC_API_CALLBACK );
	}

	/**
	 * Event filters this plugin subscribes to.
	 *
	 * The seven published `service_invoice` events. What the account actually
	 * accepts is confirmed against fetchEventTypes() before the call, so an
	 * account without one of them does not fail the whole provisioning.
	 *
	 * @return array
	 */
	public static function wanted_filters() {
		return array(
			'service_invoice.issued_successfully',
			'service_invoice.issued_error',
			'service_invoice.issued_failed',
			'service_invoice.cancelled_successfully',
			'service_invoice.cancelled_error',
			'service_invoice.cancelled_failed',
			'service_invoice.pulled',
		);
	}

	/**
	 * Provisions the webhook when it is missing.
	 *
	 * @param bool $force Recreate even when one is already provisioned.
	 *
	 * @return bool
	 */
	public static function maybe_provision( $force = false ) {
		$force = true === $force;

		if ( ! $force && '' !== self::secret() && '' !== (string) get_option( self::WEBHOOK_OPTION, '' ) ) {
			return true;
		}

		$api_key = (string) nfe_get_field( 'api_key' );

		if ( '' === $api_key ) {
			// Nothing to do yet. The settings-save hook brings us back here the
			// moment a key is entered.
			return false;
		}

		return self::provision( $api_key );
	}

	/**
	 * Creates the webhook and stores its secret.
	 *
	 * The secret is written to the option *before* the call, because NFe.io
	 * posts to the endpoint as part of creating the webhook and the handler has
	 * to be able to verify that delivery. It is rolled back if the call fails,
	 * so a failed attempt cannot leave a secret behind that matches nothing.
	 *
	 * @param string $api_key NFe.io API key.
	 *
	 * @return bool
	 */
	protected static function provision( $api_key ) {
		$previous_secret  = self::secret();
		$previous_webhook = (string) get_option( self::WEBHOOK_OPTION, '' );

		// 48 hex characters, inside the 32-64 range the API requires.
		$secret = bin2hex( random_bytes( 24 ) );

		update_option( self::SECRET_OPTION, $secret, false );
		set_transient( self::PROVISIONING_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS );

		try {
			$client = new \Nfe\Client( apiKey: $api_key, environment: \Nfe\Environment::Production );

			$filters = self::wanted_filters();

			try {
				$available = $client->webhooks->fetchEventTypes();

				if ( ! empty( $available ) ) {
					$supported = array_values( array_intersect( $filters, $available ) );

					// Only narrow the list when the intersection found something;
					// an unexpected shape must not silently unsubscribe us.
					if ( ! empty( $supported ) ) {
						$filters = $supported;
					}
				}
			} catch ( \Nfe\Exception\ApiErrorException $e ) {
				// Advisory only - fall through with the documented list.
				WC_NFe_Webhook_Handler::logger( 'Could not read the event type catalogue; using the documented filters.' );
			}

			$webhook = $client->webhooks->createAccountWebhook(
				array(
					'uri'         => self::endpoint_url(),
					'contentType' => 'json',
					'secret'      => $secret,
					'filters'     => $filters,
				)
			);
		} catch ( \Nfe\Exception\ApiErrorException $e ) {
			delete_transient( self::PROVISIONING_TRANSIENT );

			// Put back whatever was working before, if anything.
			if ( '' === $previous_secret ) {
				delete_option( self::SECRET_OPTION );
			} else {
				update_option( self::SECRET_OPTION, $previous_secret, false );
			}

			update_option(
				self::NOTICE_OPTION,
				array(
					'type'    => 'error',
					'message' => $e->getMessage(),
				),
				false
			);

			return false;
		}

		delete_transient( self::PROVISIONING_TRANSIENT );

		update_option( self::WEBHOOK_OPTION, (string) $webhook->id, false );

		// Only now that the replacement is live does the old one go away.
		self::remove_stale_webhooks( $client, (string) $webhook->id, $previous_webhook );

		// Cached here rather than looked up per event: the handler compares it
		// against each payload's `environment` so a development invoice cannot
		// be recorded as a fiscal one, and doing that lookup on every delivery
		// would put an API round trip in the way of a 2xx we owe quickly.
		self::cache_company_environment( $client );

		update_option(
			self::NOTICE_OPTION,
			array(
				'type'    => 'success',
				'message' => '',
			),
			false
		);

		return true;
	}

	/**
	 * Records which environment the configured company issues in.
	 *
	 * @since 1.5.0
	 *
	 * @param \Nfe\Client $client API client.
	 *
	 * @return void
	 */
	protected static function cache_company_environment( $client ) {
		$company_id = (string) nfe_get_field( 'choose_company' );

		if ( '' === $company_id ) {
			return;
		}

		try {
			$company = $client->companies->retrieve( $company_id );
		} catch ( \Nfe\Exception\ApiErrorException $e ) {
			return;
		}

		if ( ! empty( $company->environment ) ) {
			update_option( 'nfe_company_environment', (string) $company->environment, false );
		}
	}

	/**
	 * Deletes webhooks pointing at this store other than the current one.
	 *
	 * Called only after the replacement exists, so there is no window where the
	 * store has no webhook at all. Deliveries from an old, unsigned webhook are
	 * refused by the handler in the meantime rather than acted on.
	 *
	 * @param \Nfe\Client $client     API client.
	 * @param string      $keep_id    id of the webhook to preserve.
	 * @param string      $previous   id previously stored, if any.
	 *
	 * @return void
	 */
	protected static function remove_stale_webhooks( $client, $keep_id, $previous ) {
		$endpoint = self::endpoint_url();

		try {
			$existing = $client->webhooks->listAccountWebhooks();
			$existing = is_object( $existing ) && isset( $existing->data ) ? $existing->data : array();
		} catch ( \Nfe\Exception\ApiErrorException $e ) {
			WC_NFe_Webhook_Handler::logger( 'Could not list account webhooks to retire the previous one.' );

			return;
		}

		foreach ( $existing as $webhook ) {
			$id = isset( $webhook->id ) ? (string) $webhook->id : '';

			if ( '' === $id || $id === $keep_id ) {
				continue;
			}

			// Only ever touch webhooks aimed at this store's own endpoint, or
			// the exact id this plugin provisioned before.
			$uri = isset( $webhook->uri ) ? (string) $webhook->uri : '';

			if ( $uri !== $endpoint && $id !== $previous ) {
				continue;
			}

			try {
				$client->webhooks->deleteAccountWebhook( $id );
			} catch ( \Nfe\Exception\ApiErrorException $e ) {
				WC_NFe_Webhook_Handler::logger( 'Could not delete a superseded webhook; it will keep being refused by signature.' );
			}
		}
	}

	/**
	 * Handles the "re-provision" button from the settings screen.
	 *
	 * @return void
	 */
	public static function handle_manual_request() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to perform this action.', 'nota-fiscal-nfe-io-for-woocommerce' ),
				esc_html__( 'Forbidden', 'nota-fiscal-nfe-io-for-woocommerce' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'nfe_provision_webhook' );

		self::maybe_provision( true );

		wp_safe_redirect( WOOCOMMERCE_NFE_SETTINGS_URL );
		exit;
	}

	/**
	 * Renders the provisioning notice.
	 *
	 * @return void
	 */
	public static function render_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$notice = get_option( self::NOTICE_OPTION, array() );

		// A store with an API key but no secret cannot receive status updates
		// at all, so that state is reported until it is resolved.
		if ( '' === self::secret() && '' !== (string) nfe_get_field( 'api_key' ) ) {
			$action = wp_nonce_url( admin_url( 'admin-post.php?action=nfe_provision_webhook' ), 'nfe_provision_webhook' );

			echo '<div class="notice notice-warning"><p><strong>';
			echo esc_html__( 'NFe for WooCommerce', 'nota-fiscal-nfe-io-for-woocommerce' );
			echo '</strong> ';
			echo esc_html__( 'has no signed webhook yet, so invoice status updates are not being applied.', 'nota-fiscal-nfe-io-for-woocommerce' );

			if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
				echo ' <em>' . esc_html( $notice['message'] ) . '</em>';
			}

			echo ' <a class="button button-primary" href="' . esc_url( $action ) . '">';
			echo esc_html__( 'Set up the webhook', 'nota-fiscal-nfe-io-for-woocommerce' );
			echo '</a></p></div>';

			return;
		}

		if ( is_array( $notice ) && isset( $notice['type'] ) && 'success' === $notice['type'] ) {
			delete_option( self::NOTICE_OPTION );

			echo '<div class="notice notice-success is-dismissible"><p><strong>';
			echo esc_html__( 'NFe for WooCommerce', 'nota-fiscal-nfe-io-for-woocommerce' );
			echo '</strong> ';
			echo esc_html__( 'is now receiving signed invoice status updates from NFe.io.', 'nota-fiscal-nfe-io-for-woocommerce' );
			echo '</p></div>';
		}
	}
}

WC_NFe_Webhook_Provisioner::init();
