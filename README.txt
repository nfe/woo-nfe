=== NFe for Woocommerce ===
Contributors: nfe, espellcaste
Tags: woocommerce, shop, receipt, nfe, nota fiscal, nota, receita, sefaz, nfse, emitir nfse, emitir nfe
Requires at least: 4.7
Tested up to: 5.9.3
Stable tag: 1.3.1
Requires PHP: 5.6
WC tested up to: 6.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Extension for issuing invoices using NFe.io API

== Description ==

NFe for Woocommerce is an extension for issuing invoices using NFe.io API.

**Included Translations:**

- English (default)
- Brazilian Portuguese

Thanks in advance for your help on any translation efforts!

== Installation ==

1. Go the *Plugins* menu and click *Add New*.
2. Search for *NFe for Woocommerce*.
3. Click *Install Now*.

or

1. Upload woo-nfe.zip to wp-content/plugins folder
2. Click "Activate" in the WordPress plugins menu

== Changelog ==

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