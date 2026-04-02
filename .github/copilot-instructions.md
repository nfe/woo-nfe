# Project Guidelines

## Architecture

- The plugin bootstrap lives in `woo-nfe.php`; keep changes localized and follow the existing initialization flow: `setup_globals()`, `dependencies()`, `includes()`, then `setup_hooks()`.
- Admin behavior lives under `includes/admin/`, frontend behavior under `includes/frontend/`, shared helpers in `includes/nfe-functions.php`, and WooCommerce email templates in `templates/emails/`.
- `li/client-php/` is a bundled NFe.io SDK loaded directly by the plugin. Treat it as vendored code and avoid changing it unless the task is explicitly about the SDK.

## Code Style

- Preserve the existing WordPress/WooCommerce style: `defined( 'ABSPATH' ) || exit;` guards, hook registration in constructors, and WordPress escaping/sanitization helpers.
- Keep the text domain as `woo-nfe` and use the existing translation flow in `languages/`.
- Maintain PHP 7 compatibility and the current WordPress plugin patterns. Prefer focused changes over broad refactors or introducing new architectural layers.

## Build and Test

- Install PHP dev dependencies with `composer install` and JavaScript tooling with `npm install` when a task needs them.
- Use `npm run grunt` for translation-related checks and `.pot` generation. `Gruntfile.js` defines `checktextdomain` and `makepot`.
- PHPUnit is configured in `phpunit.xml.dist`, but it assumes a WordPress test environment rooted at `/var/www/html/wp-content/plugins/woo-nfe` and bootstraps from `tests/bootstrap.php`. Verify or adapt that setup before running tests.
- WordPress test setup is bootstrapped by `bin/install-wp-tests.sh`. `phpspec.yml` only configures coverage output and does not define a substantial spec suite on its own.

## Conventions

- Follow the existing hook-based boundaries instead of moving logic into the bootstrap file. Extend the relevant admin, frontend, webhook, API, or email class where possible.
- Keep changes consistent with WooCommerce integration points already used in the repo, especially order actions, email classes, and webhook callbacks.
- This repo already includes OpenSpec workflow assets in `.github/prompts/`, `.github/skills/`, and `openspec/config.yaml`. When the user asks for spec-driven work, use and update those artifacts instead of duplicating the workflow here.

## References

- See `README.md` for installation, release context, and general project background.
- There is currently no separate `docs/`, `CONTRIBUTING.md`, or `ARCHITECTURE.md`, so keep any future project-wide AI guidance concise and link back to source files.