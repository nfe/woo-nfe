# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A WordPress/WooCommerce plugin that integrates with the NFE.io API to issue Brazilian service invoices (NFS-e) from WooCommerce orders. The plugin is loaded via `plugins_loaded` and follows the WordPress plugin singleton pattern.

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
PHPUnit requires a WordPress test environment. Tests live in `/var/www/html/wp-content/plugins/nfe-io-nota-fiscal-for-woocommerce/tests` (inside the wp-env container). Bootstrap is `tests/bootstrap.php`. There is no standalone `tests/` directory in this repo yet.

### Translations / i18n
```bash
npm run grunt              # Runs all Grunt tasks
npx grunt checktextdomain  # Check text domain usage
npx grunt makepot          # Generate .pot file
```

## Architecture

### Initialization flow (`nfe-io-nota-fiscal-for-woocommerce.php`)
The `NFEIO_NF_Plugin` singleton initializes via `plugins_loaded` in this order:
1. `setup_globals()` — defines paths and constants (`NFEIO_NF_VERSION`, `NFEIO_NF_API_CALLBACK`, `NFEIO_NF_SETTINGS_URL`, `NFEIO_NF_PATH`, `NFEIO_NF_FILE`)
2. `dependencies()` — checks the Composer autoload, the required PHP extensions and WooCommerce
3. `includes()` — requires the SDK autoload and all class files
4. `setup_hooks()` — registers the maintenance callbacks (upgrade, backfill, claim sweep), the WooCommerce integration filter and the plugin action links

### Key classes

| File | Class | Role |
|---|---|---|
| `includes/admin/class-nfeio-nf-integration.php` | `NFEIO_NF_Integration` | WooCommerce Integration settings page; stores API key, company ID, and all plugin options |
| `includes/admin/class-nfeio-nf-api.php` | `NFEIO_NF_API` | Singleton; wraps all NFE.io API calls (issue/cancel invoices, fetch companies). Uses the `nfe/nfe` SDK |
| `includes/admin/class-nfeio-nf-admin.php` | `NFEIO_NF_Admin` | Admin UI: order metaboxes, order list columns, order actions for issuing/downloading invoices |
| `includes/admin/class-nfeio-nf-ajax.php` | `NFEIO_NF_Ajax` | AJAX handlers for the front-end invoice actions |
| `includes/admin/class-nfeio-nf-webhook-provisioner.php` | `NFEIO_NF_Webhook_Provisioner` | Creates the signed NFE.io account webhook and owns the shared secret |
| `includes/admin/class-nfeio-nf-webhook-handler.php` | `NFEIO_NF_Webhook_Handler` | Listens on `woocommerce_api_nfeio_nf_webhook`; verifies the signature, claims the event and updates order meta/notes |
| `includes/admin/class-nfeio-nf-emails.php` | `NFEIO_NF_Emails` | Hooks into WooCommerce email system; adds NFe receipt PDF link to order emails |
| `includes/admin/emails/class-nfeio-nf-email-receipt-issued.php` | `NFEIO_NF_Email_Receipt_Issued` | Custom WooCommerce email class sent to customer when a receipt is issued |
| `includes/frontend/class-nfeio-nf-frontend.php` | `NFEIO_NF_Frontend` | Frontend: receipt column and actions on the customer's account pages |
| `includes/nfe-functions.php` | — | Shared helper functions, the upgrade/migration routine and the scheduled maintenance |

### Bundled SDK
`nfe/nfe` is the official NFE.io PHP SDK, installed by Composer into `vendor/`. It is vendored code — do not modify it unless the task is specifically about the SDK.

### Webhook endpoint
The plugin registers a WooCommerce API callback at `/?wc-api=nfeio_nf_webhook`. NFE.io posts status updates (issued, cancelled, error) to this URL. `NFEIO_NF_Webhook_Handler` verifies the HMAC signature over the raw body, claims the `X-Hook-Id`, and updates the corresponding WooCommerce order.

### Prefixo global

**Todo** identificador que o plugin publica em espaço de nomes compartilhado usa `nfeio_nf_` / `NFEIO_NF_`
— opções, transients, eventos de cron, ações `admin_post`, nonces, o callback `wc-api`, chaves de ação
de pedido, ids de coluna, fontes de log, handles de asset e classes CSS. Um segundo prefixo em escopo
global reprova a revisão do diretório: o analisador reduz ao prefixo comum entre os grupos que encontra,
e foi assim que `nfe_` (3 caracteres) pendenciou a rodada 2.

Ficam de fora, por decisão registrada na change `revisao-wporg-rodada-2`:

- **metadados de pedido e de produto** (`nfe_issued`, `_nfe_invoice_id`, `_simple_nfe_*`) — a diretriz
  cobre funções, classes, defines e opções; renomear `postmeta` significaria migrar o registro fiscal
  linha a linha;
- **chaves do formulário de configuração** (`nfe_enable`, `nfe_rtc_*`) — vivem dentro do array de uma
  única opção;
- **`woocommerce_woo-nfe_settings`** — nome montado pelo `WC_Integration` a partir do prefixo do
  WooCommerce; trocar o `id` da integração muda a URL da tela e arrisca órfã de credencial.

Nomes antigos que ainda aparecem no código estão todos em `nfeio_nf_migrate_legacy_names()`, que os lê
para apagá-los.

## Code Conventions

- All files start with `defined( 'ABSPATH' ) || exit;`
- Hook registration happens in class constructors
- Use WordPress escaping helpers (`esc_html`, `esc_url`, `sanitize_text_field`, etc.) at output/input boundaries
- Text domain is `woo-nfe` — always use `__( '...', 'woo-nfe' )` for translatable strings
- PHP 7+ is required; avoid PHP 8-only syntax for compatibility
- Follow WooCommerce integration points already in the repo (order actions, email hooks, webhook callbacks) rather than creating new architectural layers
- This repo uses the OpenSpec workflow (`.github/prompts/`, `.github/skills/`, `openspec/config.yaml`) — use it for spec-driven tasks

### PHPCS baseline (gate de conformidade)

O padrão é `WordPress` (WPCS 3.x). O `composer install` **funciona** — as dependências de desenvolvimento foram modernizadas na change `elevar-piso-php-e-sdk-nfe` (PHPUnit 9.6, PHPCS 3.11, WPCS 3.4, PHPCompatibilityWP 2.1) e `composer audit` não reporta advisories.

```bash
composer install
composer run lint       # phpcs --standard=WordPress --runtime-set testVersion 8.2-
composer run lint:fix   # phpcbf
```

Sem PHP no host, rode pelo container:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli \
  php vendor/bin/phpcs --standard=WordPress --runtime-set testVersion 8.2- \
  includes/ templates/ woo-nfe.php
```

**Baseline vigente: 0 erros, 0 avisos.** Os 9 erros de `WordPress.Files.FileName.InvalidClassFileName` caíram com a renomeação dos arquivos de classe. Qualquer erro novo é regressão e deve ser corrigido antes do merge.

Toda supressão `phpcs:ignore` precisa de justificativa inline explicando por que a regra não se aplica naquele ponto.

### Piso de runtime

O plugin exige **PHP 8.2** (piso do SDK `nfe/nfe`). `woo-nfe.php` precisa continuar **parseável em PHP 7.x**: o gate de versão no topo só consegue mostrar o aviso se o arquivo inteiro fizer parse no runtime que ele está recusando. Verifique com:

```bash
for V in 7.0-cli 7.4-cli 8.2-cli; do
  docker run --rm -v "$PWD":/app -w /app php:$V php -l woo-nfe.php
done
```

Deprecations do PHP 8.2 (propriedade dinâmica, `null` em parâmetro `string`) são tratadas como erro nesta base: o plugin declara 8.2 e não deve poluir o log de quem roda 8.2.

### Build do pacote

```bash
bash bin/build-zip.sh
```

O script faz staging em diretório limpo, roda `composer install --no-dev --optimize-autoloader --classmap-authoritative` lá dentro e falha se alguma dependência de desenvolvimento entrar no pacote. O `vendor/` do repo (com ferramentas de dev) **não** é copiado. O zip é removido antes de ser recriado — `zip -r` acrescenta a um arquivo existente, e sem isso cada build herdava o conteúdo do anterior.

O script exige `rsync`, `zip` e `composer` no PATH e checa isso antes de começar. Em host sem PHP, forneça um shim como o `bin/docker-compose` já versionado:

```bash
printf '#!/usr/bin/env bash\nexec docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 composer "$@"\n' > bin/composer
chmod +x bin/composer
PATH="$PWD/bin:$PATH" bash bin/build-zip.sh
```

`composer.json` e `composer.lock` **ficam** no pacote: o Plugin Check reporta `missing_composer_json_file` quando o `vendor/` viaja sem o manifesto, e é por ele que o revisor confere a procedência de cada arquivo empacotado.

### Gate do Plugin Check

O PCP roda sobre o **pacote extraído**, nunca sobre a árvore de desenvolvimento (que carrega `openspec/`, `docs/`, `node_modules/` e faria o `file_type` disparar). O diretório do plugin dentro do WordPress precisa ter **exatamente o nome do slug** — com outro nome o PCP acusa `TextDomainMismatch` em cada string traduzível.

Receita, sem precisar de PHP no host:

```bash
PATH="$PWD/bin:$PATH" bash bin/build-zip.sh
rm -rf dist && mkdir dist && unzip -q nfe-io-nota-fiscal-for-woocommerce-*.zip -d dist
cat > .wp-env.override.json <<'JSON'
{ "mappings": { "wp-content/plugins/nfe-io-nota-fiscal-for-woocommerce": "./dist/nfe-io-nota-fiscal-for-woocommerce" } }
JSON
PATH="$PWD/bin:$PATH" npx wp-env start
PATH="$PWD/bin:$PATH" npx wp-env run cli wp plugin check nfe-io-nota-fiscal-for-woocommerce --format=csv
rm -f .wp-env.override.json && PATH="$PWD/bin:$PATH" npx wp-env start   # volta para a árvore de desenvolvimento
```

`.wp-env.override.json` e `dist/` são ignorados pelo git. `bin/docker-compose` é o shim que o `wp-env` precisa neste host.

Baseline vigente: **0 erros, 3 avisos** — `NonPrefixedHooknameFound` para `woocommerce_email_header`, `woocommerce_email_footer` e `woocommerce_email_footer_text`. São hooks do próprio WooCommerce, disparados pelos nossos templates de e-mail como os do core fazem; declarados como falso positivo na resposta da rodada 1.
