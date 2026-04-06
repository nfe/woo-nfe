# WooCommerce NFe (NFe.io)

WooCommerce NFe é um plugin para integrar sua loja WooCommerce com a NFe.io e emitir NFS-e a partir dos pedidos da loja.

O projeto cobre o fluxo operacional de emissão, acompanhamento de status via webhook, exibição do recibo para o cliente e configurações fiscais necessárias para a emissão, incluindo os campos mais recentes da Reforma Tributária no layout RTC.

## Visão geral

Com este plugin você pode:

- conectar a loja a uma conta da NFe.io usando API Key;
- selecionar a empresa emissora dentro da integração do WooCommerce;
- emitir notas automaticamente por status do pedido ou manualmente;
- configurar códigos fiscais globais e sobrescritas por produto ou variação;
- receber atualizações da NFe.io por webhook e refletir o status no pedido;
- disponibilizar o recibo ao cliente na área Minha Conta e por e-mail;
- trabalhar com campos RTC como `nbsCode`, `ibsCbs.operationIndicator` e `ibsCbs.classCode`.

## Requisitos

Os requisitos abaixo refletem o estado atual do repositório:

- PHP 7 ou superior;
- WordPress com WooCommerce ativo;
- acesso a uma conta da NFe.io com API Key e empresa cadastrada;
- ambiente capaz de receber callbacks HTTP da NFe.io para o webhook de status.

Observação: o cabeçalho do plugin informa compatibilidade histórica do WooCommerce, mas o desenvolvimento atual do repositório usa dependências de PHP 7+.

## Instalação

### Via painel do WordPress

1. No menu Plugins, clique em Adicionar novo.
2. Procure por WooCommerce NFe.
3. Instale o plugin.
4. Ative o plugin.

### Via código-fonte

1. Copie este repositório para o diretório `wp-content/plugins/woo-nfe`.
2. Ative o plugin no painel do WordPress.

## Configuração inicial

Depois de ativar o plugin, acesse WooCommerce > Settings > Integration > Receipts (NFE.io).

Fluxo recomendado de configuração:

1. Ative a integração da NFe.io.
2. Informe a API Key da sua conta.
3. Selecione a empresa emissora.
4. Defina se a emissão será automática ou manual.
5. Escolha o status do pedido que deve disparar a emissão automática.
6. Configure se o endereço é obrigatório para emitir.
7. Revise os campos fiscais padrão do serviço.
8. Copie a URL de webhook exibida pela integração e cadastre-a na NFe.io.

## Recursos principais

### Emissão automática ou manual

O plugin permite emitir NFS-e automaticamente em mudanças de status do pedido ou manualmente, de acordo com a configuração da integração.

### Controle de emissão por status do pedido

Você pode escolher o status que dispara a emissão automática, como pendente, processando, em espera ou concluído.

### Emissão retroativa

Existe suporte para habilitar emissão manual de pedidos antigos dentro de uma janela configurável de dias.

### Configuração fiscal global

Os campos fiscais principais podem ser definidos na integração para servir como fallback de emissão quando o produto não tiver override específico.

### Override por produto e variação

O plugin suporta configuração específica em produto simples e variação, permitindo sobrescrever dados fiscais quando necessário.

### Webhook de status

A NFe.io pode notificar o plugin sobre alterações de status da nota. O pedido é atualizado com os dados retornados pela plataforma, inclusive status, número e código de verificação.

### Experiência do cliente

O cliente consegue acompanhar o recibo na área Minha Conta e também pode receber comunicação por e-mail quando o recibo é emitido.

## Campos fiscais básicos

Na integração administrativa, o plugin expõe configurações para:

- código de serviço municipal;
- código federal de serviço LC 116;
- descrição do serviço;
- destaque ou exclusão de frete na formação tributária;
- obrigatoriedade de endereço para emissão.

Esses campos devem ser preenchidos com apoio do time fiscal ou do contador responsável pela operação.

## Reforma tributária (RTC)

### Campos suportados

O plugin suporta os seguintes campos fiscais do fluxo RTC:

- `nbsCode`
- `ibsCbs.operationIndicator`
- `ibsCbs.classCode`

Prioridade de origem dos valores:

1. variação do produto;
2. produto simples;
3. configuração global da integração.

### Perfil de validação

O perfil de validação é configurado em WooCommerce > Settings > Integration > Receipts (NFE.io).

- `Compatível`: emite alerta para ausência de `nbsCode`, sem bloqueio.
- `Equilibrado`: bloqueia ausência de `nbsCode` em cenários RTC críticos.
- `Estrito`: bloqueia emissão RTC sem `nbsCode`.

### Migração recomendada

1. Comece em `Compatível` para saneamento cadastral.
2. Migre para `Equilibrado` quando a maior parte do catálogo estiver consistente.
3. Adote `Estrito` quando a ausência de `nbsCode` estiver residual e sob controle.

Checklist operacional:

- preencher fallback global de `nbsCode`, `operationIndicator` e `classCode`;
- revisar produtos simples com configuração própria;
- revisar variações com override RTC;
- validar emissão com e sem fallback;
- ajustar o perfil de validação ao nível de maturidade fiscal da operação.

## Webhook e atualização de status

O plugin gera uma URL de webhook na tela de integração. Essa URL deve ser cadastrada na NFe.io para que os eventos de emissão e cancelamento atualizem automaticamente o pedido no WooCommerce.

Quando um evento chega, o plugin registra os dados da nota no pedido e atualiza informações como:

- identificador da nota;
- status de fluxo;
- data de emissão;
- valor líquido;
- código de verificação;
- número do documento.

Sem webhook, a loja perde parte importante da sincronização automática entre WooCommerce e NFe.io.

## Uso avançado

### recipient e destinationIndicator

Não existe UI dedicada no checkout ou no admin para `recipient` e `destinationIndicator` nesta fase.
Esses campos podem ser ajustados via filtro de payload `woo_nfe_rtc_payload`.

Regras importantes:

- `destinationIndicator` aceita `SameAsBuyer` e `DifferentFromBuyer`;
- quando `DifferentFromBuyer` for usado, o bloco `recipient` com pelo menos `name` é obrigatório;
- em contexto RTC crítico nos perfis `Equilibrado` e `Estrito`, `nbsCode` é exigido;
- se `ibsCbs` for enviado, `operationIndicator` e `classCode` devem estar presentes.

Exemplo:

```php
add_filter( 'woo_nfe_rtc_payload', function( $payload, $order_id, $order ) {
	$payload['destinationIndicator'] = 'DifferentFromBuyer';
	$payload['recipient']            = array(
		'name' => 'Nome do destinatário',
	);

	return $payload;
}, 10, 3 );
```

## Desenvolvimento local

### Dependências

Para trabalhar no repositório localmente:

```bash
composer install
npm install
```

### Ambiente WordPress local

O projeto usa `wp-env` para subir um ambiente local com WordPress e WooCommerce:

```bash
npm run wp-env start
npm run wp-env stop
npm run wp-env run tests-cli wp --info
```

### Verificações úteis

Geração e verificação de traduções:

```bash
npm run grunt
npx grunt checktextdomain
npx grunt makepot
```

Análise de padrão de código PHP:

```bash
./vendor/bin/phpcs --standard=WordPress includes/ woo-nfe.php
```

### Empacotamento

Para gerar um arquivo ZIP distribuível do plugin:

```bash
./bin/build-zip.sh
```

Opcionalmente, você pode informar uma versão customizada:

```bash
./bin/build-zip.sh 1.4.0-beta
```

## Estrutura do projeto

- `woo-nfe.php`: bootstrap principal do plugin.
- `includes/admin/`: integração administrativa, API, webhook, AJAX e e-mails.
- `includes/frontend/`: comportamentos expostos ao cliente na loja.
- `includes/nfe-functions.php`: funções compartilhadas.
- `templates/emails/`: templates de e-mail do WooCommerce.
- `li/client-php/`: SDK embarcado da NFe.io.
- `openspec/`: artefatos de especificação usados nas mudanças recentes.

## Suporte

- Issues do GitHub: https://github.com/nfe/woo-nfe/issues
- Fórum no WordPress.org: https://wordpress.org/support/plugin/woo-nfe
- Site da NFe.io: https://nfe.io

## Releases

O histórico antigo de changelog permanece no `README.txt`, que atende ao formato do diretório de plugins do WordPress. O `README.md` prioriza documentação de uso, operação e desenvolvimento do projeto.
