<?php
/**
 * Nota Fiscal NFe.io for WooCommerce.
 *
 * @author            NFe.io
 *
 * @see              https://github.com/nfe/woo-nfe
 * @since             1.0.8
 * @package          Woo_Nfe
 *
 * @wordpress-plugin
 * Plugin Name:       Nota Fiscal NFe.io for WooCommerce
 * Plugin URI:        https://github.com/nfe/woo-nfe
 * Description:       Issue Brazilian service invoices (NFS-e) from WooCommerce orders through the NFe.io API.
 * Version:           1.5.0
 * Author:            NFe.io
 * Author URI:        https://nfe.io
 * Developer:         Project contributors
 * Developer URI:     https://github.com/nfe/woo-nfe/graphs/contributors
 * Text Domain:       nota-fiscal-nfe-io-for-woocommerce
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 9.0
 * WC tested up to: 11.0.1
 *
 * Copyright: © 2016-2026 NFe.io
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/*
 * The main plugin file is named after the plugin slug, which is what the
 * WordPress.org directory requires and what WordPress itself looks for. That
 * convention wins over the class-file naming sniff, which would want this file
 * called class-woocommerce-nfe.php -- a name WordPress would not recognise.
 */
// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Main plugin file must be named after the slug.

/**
 * Minimum PHP version the plugin runs on, dictated by the NFe.io SDK.
 *
 * Everything from here down to the early return is written in PHP 5-compatible
 * syntax on purpose: this file still has to *parse* on the very runtime it is
 * refusing to run on. A single PHP 8 token up here would turn the notice below
 * into the parse error it exists to prevent.
 */
if ( ! defined( 'WOO_NFE_MINIMUM_PHP' ) ) {
	define( 'WOO_NFE_MINIMUM_PHP', '8.2' );
}

if ( version_compare( PHP_VERSION, WOO_NFE_MINIMUM_PHP, '<' ) ) {

	// A closure, not a named function: this file already declares woo_nfe()
	// alongside the plugin class, and a second named function would break the
	// one-kind-of-declaration-per-file rule for no benefit.
	add_action(
		'admin_notices',
		function () {
			echo '<div class="error"><p><strong>';
			echo esc_html__( 'NFe for WooCommerce', 'nota-fiscal-nfe-io-for-woocommerce' );
			echo '</strong> ';
			printf(
				/* translators: 1: required PHP version, 2: PHP version currently running. */
				esc_html__( 'requires PHP %1$s or higher. This site runs PHP %2$s, so the plugin stayed inactive to avoid breaking it. Ask your host to upgrade PHP.', 'nota-fiscal-nfe-io-for-woocommerce' ),
				esc_html( WOO_NFE_MINIMUM_PHP ),
				esc_html( PHP_VERSION )
			);
			echo '</p></div>';
		}
	);

	return;
}

if ( ! class_exists( 'WooCommerce_NFe' ) ) {
	/**
	 * WooCommerce NFe.io Main Class.
	 *
	 * @since 1.0.0
	 */
	final class WooCommerce_NFe {
		/**
		 * Flag to indicate whether this extension is running already.
		 *
		 * @var bool
		 */
		private $is_running = false;

		/**
		 * PHP extensions found missing by dependencies(), for the notice.
		 *
		 * @var array
		 */
		private $missing_extensions = array();

		/**
		 * Basename of the previous release, when it is still active.
		 *
		 * @var string
		 */
		private $legacy_plugin = '';

		/*
		 * Everything setup_globals() assigns is declared here. PHP 8.2 -- the
		 * floor this plugin now targets -- deprecated dynamic properties, and
		 * without these declarations the class emitted seven deprecation
		 * notices on every single request.
		 */

		/**
		 * Text domain.
		 *
		 * @var string
		 */
		private $domain = '';

		/**
		 * Human-readable plugin name.
		 *
		 * @var string
		 */
		private $name = '';

		/**
		 * Absolute path to the plugin's main file.
		 *
		 * @var string
		 */
		private $file = '';

		/**
		 * Plugin basename, as WordPress refers to it.
		 *
		 * @var string
		 */
		private $basename = '';

		/**
		 * Absolute path to the plugin directory, with trailing slash.
		 *
		 * @var string
		 */
		private $plugin_dir = '';

		/**
		 * URL of the plugin directory, with trailing slash.
		 *
		 * @var string
		 */
		private $plugin_url = '';

		/**
		 * Absolute path to the includes directory, with trailing slash.
		 *
		 * @var string
		 */
		private $includes_dir = '';

		/**
		 * A dummy constructor to prevent WooCommerce_NFe from being loaded more than once.
		 *
		 * @since 1.0.0
		 * @see WooCommerce_NFe::instance()
		 */
		public function __construct() {
			// Do nothing here.
		}

		/**
		 * Main instance.
		 *
		 * @return instance
		 * @since 1.0.0
		 */
		public static function instance() {
			// Store the instance locally to avoid private static replication.
			static $instance = null;

			// Only run these methods if they haven't been run previously.
			if ( null === $instance ) {
				$instance = new WooCommerce_NFe();
				$instance->setup_globals();

				// A dependency failure now actually stops the load. It used to
				// only register a notice and fall through to includes(), which
				// meant a store missing WooCommerce got the warning *and* the
				// fatal it was warning about.
				if ( $instance->dependencies() ) {
					$instance->includes();
					$instance->setup_hooks();
				}
			}

			// Always return the instance.
			return $instance;
		}

		//
		// **
		// * Constructor.
		// *
		// * @param string $file    The full path and filename of the file of plugin's
		// *                        main file.
		// * @param string $version The full path and filename of the file of plugin's
		// *                        main file.
		// */
		// public function __construct( $file, $version ) {
		// $this->file    = $file;
		// $this->version = $version;
		// }

		/**
		 * Run the extension.
		 *
		 * @return bool Returns true when it's running
		 */
		public function run() {
			if ( $this->is_running ) {
				return false;
			}

			$this->is_running = true;

			return $this->is_running;
		}


		/**
		 * Load Localisation files.
		 *
		 * Plugin Check flags this call as unnecessary for WordPress.org-hosted
		 * plugins, and for language packs that is true: those land in
		 * WP_LANG_DIR and load on their own. It is not true for the pt_BR
		 * translation shipped inside this plugin, which does not load without
		 * this call -- verified on WordPress 7.1. Until language packs exist for
		 * the new slug, removing this would hand Brazilian merchants an English
		 * interface, so the warning is accepted deliberately.
		 *
		 * Hooked on `init` rather than `plugins_loaded`: since WordPress 6.7,
		 * loading a text domain earlier than `init` raises a notice of its own.
		 *
		 * @return void
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain(
				'nota-fiscal-nfe-io-for-woocommerce',
				false,
				dirname( plugin_basename( $this->file ) ) . '/languages'
			);
		}

		/**
		 * Adds our custom WC_NFe_Integration integration to WooCommerce.
		 *
		 * @param array $integrations wooCommerce Integrations.
		 *
		 * @return array
		 * @since 1.0.0
		 */
		public function nfe_integration( $integrations ) {
			$integrations[] = 'WC_NFe_Integration';

			return $integrations;
		}

		/**
		 * PHP extensions missing notice.
		 *
		 * @since 1.5.0 Replaces the SOAPClient notice; the SDK needs cURL and JSON.
		 */
		public function extensions_missing_notice() {
			$missing_extensions = $this->missing_extensions;

			include $this->includes_dir . 'admin/views/html-notice-missing-extensions.php';
		}

		/**
		 * Composer autoloader missing notice.
		 *
		 * @since 1.5.0
		 */
		public function autoload_missing_notice() {
			include $this->includes_dir . 'admin/views/html-notice-missing-autoload.php';
		}

		/**
		 * WooCommerce missing notice.
		 *
		 * @since 1.0.0
		 */
		public function woocommerce_missing_notice() {
			include $this->includes_dir . 'admin/views/html-notice-missing-woocommerce.php';
		}

		/**
		 * Action links.
		 *
		 * @param array $links links.
		 *
		 * @return array
		 * @since 1.0.0
		 */
		public function plugin_action_links( $links ) {
			return array_merge(
				array(
					'<a href="' . esc_url( WOOCOMMERCE_NFE_SETTINGS_URL ) . '">' . __( 'Settings', 'nota-fiscal-nfe-io-for-woocommerce' ) . '</a>',
				),
				$links
			);
		}

		/**
		 * Sets some globals for the plugin.
		 *
		 * @since 1.0.0
		 */
		private function setup_globals() {
			$this->domain       = 'nota-fiscal-nfe-io-for-woocommerce';
			$this->name         = 'Nota Fiscal NFe.io for WooCommerce';
			$this->file         = __FILE__;
			$this->basename     = plugin_basename( $this->file );
			$this->plugin_dir   = plugin_dir_path( $this->file );
			$this->plugin_url   = plugin_dir_url( $this->file );
			$this->includes_dir = trailingslashit( $this->plugin_dir . 'includes' );

			// Drives the upgrade routine. Keep in step with the Version header.
			if ( ! defined( 'WOO_NFE_VERSION' ) ) {
				define( 'WOO_NFE_VERSION', '1.5.0' );
			}

			// WooCommerce Webhook Callback.
			if ( ! defined( 'WC_API_CALLBACK' ) ) {
				define( 'WC_API_CALLBACK', 'nfe_webhook' );
			}
		}

		/**
		 * Include needed files.
		 *
		 * @since 1.0.0
		 */
		private function includes() {
			// NFe.io SDK (nfe/nfe) plus anything else Composer manages.
			// dependencies() already proved this file exists.
			require $this->plugin_dir . 'vendor/autoload.php';

			// Admin.
			require $this->includes_dir . 'nfe-functions.php';

			require $this->includes_dir . 'admin/class-wc-nfe-integration.php';

			require $this->includes_dir . 'admin/class-wc-nfe-ajax.php';

			require $this->includes_dir . 'admin/class-wc-nfe-admin.php';

			require $this->includes_dir . 'admin/class-nfe-woo.php';

			require $this->includes_dir . 'admin/class-wc-nfe-emails.php';

			require $this->includes_dir . 'admin/class-wc-nfe-webhook-provisioner.php';

			require $this->includes_dir . 'admin/class-wc-nfe-webhook-handler.php';

			// Front-end.
			require $this->includes_dir . 'frontend/class-wc-nfe-frontend.php';
		}

		/**
		 * Finds the previous release of this plugin, if it is active.
		 *
		 * Identified by the file name of its entry point rather than by folder,
		 * because the folder can be anything -- a manual install, a rename, a
		 * copy left behind. Network-activated plugins are checked too.
		 *
		 * @since 1.5.0
		 *
		 * @return string|false Plugin basename of the old install, or false.
		 */
		private function legacy_plugin() {
			$active = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
			}

			$self = plugin_basename( $this->file );

			foreach ( $active as $plugin ) {
				$plugin = (string) $plugin;

				if ( $plugin === $self ) {
					continue;
				}

				if ( 'woo-nfe.php' !== basename( $plugin ) ) {
					continue;
				}

				// The option can name a plugin that is no longer on disk --
				// WordPress leaves the entry behind when a folder is renamed or
				// removed by hand, and it simply skips it at load time. Blocking
				// on that phantom would keep this plugin off forever, on a store
				// where nothing is actually running twice.
				if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
					continue;
				}

				return $plugin;
			}

			return false;
		}

		/**
		 * Previous release still active notice.
		 *
		 * @since 1.5.0
		 */
		public function legacy_plugin_notice() {
			$legacy_plugin = $this->legacy_plugin;

			include $this->includes_dir . 'admin/views/html-notice-legacy-plugin.php';
		}

		/**
		 * Class dependencies.
		 *
		 * Each failure registers its own notice and reports back, so instance()
		 * can stop before including code that would fatal on the missing piece.
		 *
		 * @since 1.5.0 Returns whether the plugin may load.
		 *
		 * @return bool True when every dependency is satisfied.
		 */
		private function dependencies() {
			// Two copies of this plugin must never run side by side. The slug
			// changed, so WordPress treats the previous release as a different
			// plugin: it is not replaced on update and can stay active in
			// parallel, registering the issuing hooks a second time. The damage
			// is a duplicate NFS-e -- a real fiscal document that has to be
			// cancelled by hand -- so the safe answer is to load nothing and say
			// why, rather than deactivate someone else's plugin unasked.
			$legacy = $this->legacy_plugin();

			if ( false !== $legacy ) {
				$this->legacy_plugin = $legacy;

				add_action( 'admin_notices', array( $this, 'legacy_plugin_notice' ) );

				return false;
			}

			// The NFe.io SDK talks HTTP over cURL and speaks JSON. These
			// replaced the old SoapClient check, which guarded a transport the
			// plugin has not used since the legacy client was dropped.
			$missing = array();

			if ( ! function_exists( 'curl_init' ) ) {
				$missing[] = 'cURL';
			}

			if ( ! function_exists( 'json_decode' ) ) {
				$missing[] = 'JSON';
			}

			if ( ! empty( $missing ) ) {
				$this->missing_extensions = $missing;

				add_action( 'admin_notices', array( $this, 'extensions_missing_notice' ) );

				return false;
			}

			// The SDK ships inside vendor/. A zip built without running
			// `composer install --no-dev` would land here, so say so plainly
			// instead of fataling on a missing class later.
			if ( ! file_exists( $this->plugin_dir . 'vendor/autoload.php' ) ) {
				add_action( 'admin_notices', array( $this, 'autoload_missing_notice' ) );

				return false;
			}

			// Checks if WooCommerce is installed and with the proper version.
			if ( ! $this->version_check() ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );

				return false;
			}

			return true;
		}

		/**
		 * Set hooks.
		 *
		 * @since 1.0.0
		 */
		private function setup_hooks() {
			$settings_url = admin_url( 'admin.php?page=woocommerce_settings&tab=integration&section=woo-nfe' );
			if ( $this->version_check( '2.1' ) ) {
				$settings_url = admin_url( 'admin.php?page=wc-settings&tab=integration&section=woo-nfe' );
			}

			if ( ! defined( 'WOOCOMMERCE_NFE_SETTINGS_URL' ) ) {
				define( 'WOOCOMMERCE_NFE_SETTINGS_URL', $settings_url );
			}

			if ( ! defined( 'WOOCOMMERCE_NFE_PATH' ) ) {
				define( 'WOOCOMMERCE_NFE_PATH', plugin_dir_path( $this->file ) );
			}

			// Anything that needs a URL into this plugin derives it from here,
			// so a future rename of the folder cannot break asset paths again.
			if ( ! defined( 'WOOCOMMERCE_NFE_FILE' ) ) {
				define( 'WOOCOMMERCE_NFE_FILE', $this->file );
			}

			// Backfills '_nfe_invoice_id' on orders issued before the flat meta
			// existed, so the webhook can still find them. Hooked on 'init' and
			// not 'admin_init' so a store updated over WP-CLI, with nobody ever
			// opening wp-admin, still gets the migration.
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
			add_action( 'init', 'nfe_maybe_upgrade' );
			add_action( 'init', 'nfe_maybe_schedule_backfill' );
			add_action( NFE_BACKFILL_HOOK, 'nfe_run_invoice_id_backfill' );
			register_activation_hook( $this->file, 'nfe_maybe_upgrade' );
			register_activation_hook( $this->file, 'nfe_maybe_schedule_backfill' );
			register_deactivation_hook( $this->file, 'nfe_clear_backfill_schedule' );

			// Filters.
			add_filter( 'woocommerce_integrations', array( $this, 'nfe_integration' ) );
			add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'plugin_action_links' ) );
		}

		/**
		 * Version check.
		 *
		 * @param string $version version to check against.
		 *
		 * @return bool
		 */
		private function version_check( $version = '9.0' ) {
			if ( class_exists( 'WooCommerce' ) ) {
				global $woocommerce;
				if ( version_compare( $woocommerce->version, $version, '>=' ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Version check 1.
		 *
		 * @param string $version version to check against.
		 *
		 * @return bool
		 */
		private function version_check1( $version ) {
			if ( class_exists( 'WooCommerce' ) ) {
				global $woocommerce;
				if ( version_compare( $version, $woocommerce->version, '>=' ) ) {
					return true;
				}
			}

			return false;
		}
	}

	/**
	 * The main function responsible for returning the one true WooCommerce_NFe Instance.
	 *
	 * @return WooCommerce_NFe
	 * @since 1.0.0
	 */
	function woo_nfe() { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- Public accessor lives beside the class it bootstraps.
		return WooCommerce_NFe::instance();
	}

	add_action( 'plugins_loaded', 'woo_nfe' );

	/*
	 * Declares compatibility with WooCommerce features.
	 *
	 * - `custom_order_tables` (HPOS): all order data is read and written through the
	 *   WooCommerce CRUD API, so the plugin works with both HPOS and the legacy
	 *   post-based order storage.
	 * - `cart_checkout_blocks`: the plugin renders no cart or checkout UI of its own
	 *   (CPF/CNPJ collection belongs to the Brazilian extra checkout fields plugin),
	 *   so the block-based cart and checkout are unaffected.
	 */
	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			}
		}
	);


}
