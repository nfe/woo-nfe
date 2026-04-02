# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A WordPress/WooCommerce plugin that integrates with the NFe.io API to issue Brazilian service invoices (NFS-e) from WooCommerce orders. The plugin is loaded via `plugins_loaded` and follows the WordPress plugin singleton pattern.

## Commands

### Setup
```bash
composer install       # Install PHP dev dependencies (PHPUnit, PHPCS, WPCS)
npm install            # Install JS tooling (Grunt, wp-env)
```

### Local WordPress Environment
```bash
npm run wp-env start   # Start local WP + WooCommerce environment (Docker-based)
npm run wp-env stop
npm run wp-env run tests-cli wp --info   # Run WP CLI in test container
```

The `.wp-env.json` config maps the repo root to `wp-content/plugins/woo-nfe` inside the container, with PHP 8.1, WooCommerce, and the Brazilian checkout fields plugin.

### Linting
```bash
./vendor/bin/phpcs --standard=WordPress includes/ woo-nfe.php   # PHP code style
```

### Tests
PHPUnit requires a WordPress test environment. Tests live in `/var/www/html/wp-content/plugins/woo-nfe/tests` (inside the wp-env container). Bootstrap is `tests/bootstrap.php`. There is no standalone `tests/` directory in this repo yet.

### Translations / i18n
```bash
npm run grunt              # Runs all Grunt tasks
npx grunt checktextdomain  # Check text domain usage
npx grunt makepot          # Generate .pot file
```

## Architecture

### Initialization flow (`woo-nfe.php`)
The `WooCommerce_NFe` singleton initializes via `plugins_loaded` in this order:
1. `setup_globals()` — defines paths and constants (`WOOCOMMERCE_NFE_SETTINGS_URL`, `WOOCOMMERCE_NFE_PATH`, `WC_API_CALLBACK`)
2. `dependencies()` — checks for SoapClient and WooCommerce
3. `includes()` — requires all class files
4. `setup_hooks()` — registers WooCommerce integration filter and plugin action links

### Key classes

| File | Class | Role |
|---|---|---|
| `includes/admin/class-settings.php` | `WC_NFe_Integration` | WooCommerce Integration settings page; stores API key, company ID, and all plugin options |
| `includes/admin/class-api.php` | `NFe_Woo` | Singleton; wraps all NFe.io API calls (issue/cancel invoices, fetch companies). Uses `li/client-php` SDK |
| `includes/admin/class-admin.php` | `WC_NFe_Admin` | Admin UI: order metaboxes, order list columns, bulk actions for issuing/canceling invoices |
| `includes/admin/class-ajax.php` | `WC_NFe_Ajax` | AJAX handlers for admin invoice actions |
| `includes/admin/class-webhook.php` | `WC_NFe_Webhook_Handler` | Listens on `woocommerce_api_nfe_webhook`; processes NFe.io status callbacks and updates order meta/notes |
| `includes/admin/class-emails.php` | `WC_NFe_Emails` | Hooks into WooCommerce email system; adds NFe receipt PDF link to order emails |
| `includes/admin/emails/class-nfe-email-receipt-issued.php` | `NFe_Email_Receipt_Issued` | Custom WooCommerce email class sent to customer when a receipt is issued |
| `includes/frontend/class-frontend.php` | `WC_NFe_Frontend` | Frontend: adds CPF/CNPJ fields to checkout, stores them in order meta |
| `includes/nfe-functions.php` | — | Shared helper functions used across admin and frontend classes |

### Bundled SDK
`li/client-php/lib/` is the NFe.io PHP client SDK, loaded directly via `require`. It is treated as vendored code — do not modify it unless the task is specifically about the SDK.

### Webhook endpoint
The plugin registers a WooCommerce API callback at `/?wc-api=nfe_webhook`. NFe.io posts status updates (issued, cancelled, error) to this URL. `WC_NFe_Webhook_Handler` reads the JSON body and updates the corresponding WooCommerce order.

## Code Conventions

- All files start with `defined( 'ABSPATH' ) || exit;`
- Hook registration happens in class constructors
- Use WordPress escaping helpers (`esc_html`, `esc_url`, `sanitize_text_field`, etc.) at output/input boundaries
- Text domain is `woo-nfe` — always use `__( '...', 'woo-nfe' )` for translatable strings
- PHP 7+ is required; avoid PHP 8-only syntax for compatibility
- Follow WooCommerce integration points already in the repo (order actions, email hooks, webhook callbacks) rather than creating new architectural layers
- This repo uses the OpenSpec workflow (`.github/prompts/`, `.github/skills/`, `openspec/config.yaml`) — use it for spec-driven tasks
