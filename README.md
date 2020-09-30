# WooCommerce NFe (NFe.io)

WooCommerce NFe é uma extensão do WooCommerce para emitir notas fiscais utilizando a API do NFe.io.

## Requisitos

* PHP >= 5.5
* WP >= 4.9
* WooCommerce >= 3.3.5

## Instalação

1. Vá ao menu *Plugins* e clique *Adicionar Novo*.
2. Pesquise por *WooCommerce NFe*.
3. Clique em *Instalar Agora*.
4. Ativar o plugin

Ou você pode colocar este plugin no diretório wp-content/plugins e ativá-lo.

## Changelog ##

### 1.0.0
* Initial release

### 1.0.1
* Fix issue #6

### 1.0.2
* Added trigger to issue invoices on specific status
* Fixed when issue invoices federal tax number must be only numbers

### 1.0.3
* Added support to issue invoices without all address fields filled

### 1.0.4
* Fix support to issue invoices without all address fields filled
* Fix trigger to issue invoices on specific status

### 1.2.5
* Added option to require an address when issuing an invoice.
* Fixed a bug where zero orders could be issued.
* Added notice in the order list when a order is zeroed.
* Added php require header on the readme.txt
* Fixed a bug that gave fatal error when on before PHP 5.5 versions.
* Fix - load_textdomain first from WP_LANG_DIR before load_plugin_textdomain
* Tweak - Tweak load_plugin_textdomain to be relative - this falls back to WP_LANG_DIR automatically. Can prevent "open_basedir restriction in effect".

### 1.2.6
* Fixing client-php folder conflict.

### 1.2.7
* Fixing how we verify the type of customer to output its information on the NFe receipt.

### 1.2.8
* Improved code documentation, PHPDoc.
* Started to use `[]` instead of `array()`.
* Started to use the new logger implementation, `wc_get_logger()`.
* Updated WordPress tested header to 3.5.1.
* Removed Extra Checkout plugin dependency.
* Removed Composer support for the client-php.
* Removed checks when on automatic issuing, as it was avoiding important log information to be saved.
* Added better labeling for the NFe.io `flowStatus`.

### 1.2.9
