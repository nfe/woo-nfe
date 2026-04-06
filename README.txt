=== NFe for Woocommerce ===
Contributors: nfe, espellcaste
Tags: woocommerce, shop, receipt, nfe, nota fiscal, nota, receita, sefaz, nfse, emitir nfse, emitir nfe
Requires at least: 4.7
Tested up to: 5.9.3
Stable tag: 1.4.0-beta
Requires PHP: 7.0
WC tested up to: 6.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WooCommerce integration for issuing NFS-e with NFe.io

== Description ==

NFe for Woocommerce connects WooCommerce to NFe.io so your store can issue NFS-e from WooCommerce orders.

The plugin covers the operational flow for invoice issuance, webhook-based status synchronization, customer receipt visibility, and fiscal configuration required for emission, including support for RTC tax reform fields such as `nbsCode`, `ibsCbs.operationIndicator`, and `ibsCbs.classCode`.

Main features:

- Connect a WooCommerce store to NFe.io using an API Key.
- Select the issuing company from the WooCommerce integration settings.
- Issue invoices automatically by order status or manually.
- Configure fiscal defaults globally and override them per product or variation.
- Receive invoice status updates from NFe.io through webhook callbacks.
- Expose the receipt to customers in My Account and by email.
- Support RTC validation profiles for gradual rollout.

**Included Translations:**

- English (default)
- Brazilian Portuguese

Thanks in advance for your help on any translation efforts!

== Installation ==

1. Go to the *Plugins* menu and click *Add New*.
2. Search for *NFe for Woocommerce*.
3. Click *Install Now*.
4. Activate the plugin.

or

1. Upload woo-nfe.zip to wp-content/plugins folder
2. Click "Activate" in the WordPress plugins menu

After activation, go to WooCommerce > Settings > Integration > Receipts (NFE.io) to configure the API Key, company, issuance mode, and webhook URL.

== Frequently Asked Questions ==

= Where do I configure the NFe.io integration? =

Open WooCommerce > Settings > Integration > Receipts (NFE.io). This is where you configure the API Key, issuing company, invoice issuance mode, fiscal defaults, and webhook URL.

= Do I need to configure a webhook? =

Yes. The webhook keeps WooCommerce in sync with NFe.io invoice status changes, including issuance and cancellation events.

= Does the plugin support RTC tax reform fields? =

Yes. The plugin supports `nbsCode`, `ibsCbs.operationIndicator`, and `ibsCbs.classCode`, with fallback priority across variation, simple product, and global integration settings.

== Changelog ==

= 1.4.0-beta =
* Added support for RTC fiscal fields in NFS-e emission flows.
* Added RTC validation profiles for progressive rollout.
* Improved operational documentation for setup, webhook usage, and development workflow.

= 1.0.0 =
* Initial release

= 1.0.1 =
* Fix issue #6

= 1.0.2 =
* Added trigger to issue invoices on specific status
* Fixed when issue invoices federal tax number must be only numbers

= 1.0.3 =
* Added support to issue invoices without all address fields filled

= 1.0.4 =
* Fix support to issue invoices without all address fields filled
* Fix trigger to issue invoices on specific status

= 1.2.5 =
* Added option to require an address when issuing an invoice.
* Fixed a bug where zero orders could be issued.
* Added notice in the order list when a order is zeroed.
* Added php require header on the readme.txt
* Fixed a bug that gave fatal error when on before PHP 5.5 versions.
* Fix - load_textdomain first from WP_LANG_DIR before load_plugin_textdomain
* Tweak - Tweak load_plugin_textdomain to be relative - this falls back to WP_LANG_DIR automatically. Can prevent "open_basedir restriction in effect".

= 1.2.6 =
* Fixing client-php folder conflict.

= 1.2.7 =
* Fixing how we verify the type of customer to output its information on the NFe receipt.

= 1.2.8 =
* Improved code documentation, PHPDoc.
* Started to use `[]` instead of `array()`.
* Started to use the new logger implementation, `wc_get_logger()`.
* Updated WordPress tested header to 3.5.1.
* Removed Extra Checkout plugin dependency.
* Removed Composer support for the client-php.
* Removed checks when on automatic issuing, as it was avoiding important log information to be saved.
* Added better labeling for the NFe.io `flowStatus`.

= 1.2.9 =
* Refactoring classes

= 1.3.0 =
Testing support to newer wordpress versions

= 1.3.1 =
Adjusting trademarking issues