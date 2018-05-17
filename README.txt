=== WooCommerce NFe ===
Contributors: nfe, espellcaste
Tags: woocommerce, shop, receipt, nfe, nota fiscal, nota, receita, sefaz, nfse, emitir nfse, emitir nfe
Requires at least: 4.7
Tested up to: 4.9.4
Stable tag: 1.2.6
Requires PHP: 5.5
WC tested up to: 3.3.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WooCommerce extension for issuing invoices using the NFe.io API

== Description ==

WooCommerce NFe is a WooCommerce extension for issuing invoices using the NFe.io API.

**Included Translations:**

- English (default)
- Brazilian Portuguese

Thanks in advance for your help on any translation efforts!

== Frequently Asked Questions ==

== Installation ==

1. Upload woo-nfe.zip to wp-content/plugins
2. Click "Activate" in the WordPress plugins menu

== Changelog ==

= 1.0.0 =
* Initial commit

= 1.0.1 =
* Fix issue #6

== Upgrade Notice ==

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
