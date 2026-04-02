## ADDED Requirements

### Requirement: Coleta e precedência de campos fiscais RTC
O sistema MUST coletar e persistir nbsCode, ibsCbs.operationIndicator e ibsCbs.classCode com precedência entre fontes de dados, usando ordem variação, produto e configuração global.

#### Scenario: Precedência por variação
- **WHEN** um item do pedido for variação e existir valor fiscal na variação
- **THEN** o sistema MUST usar o valor da variação no payload de emissão

#### Scenario: Fallback para produto simples
- **WHEN** não existir valor fiscal na variação e existir valor no produto
- **THEN** o sistema MUST usar o valor do produto no payload de emissão

#### Scenario: Fallback para configuração global
- **WHEN** não existir valor fiscal na variação nem no produto e existir valor global
- **THEN** o sistema MUST usar o valor global no payload de emissão

### Requirement: Inclusão dos campos RTC no payload de emissão
O sistema MUST incluir os novos campos fiscais no payload de emissão de NFS-e RTC, incluindo grupo ibsCbs com operationIndicator e classCode, e enviar nbsCode quando disponível no mapeamento fiscal.

#### Scenario: Emissão com nbsCode disponível
- **WHEN** nbsCode estiver disponível no mapeamento fiscal do item
- **THEN** o payload MUST conter nbsCode e ibsCbs com operationIndicator e classCode antes do envio para a API

#### Scenario: Emissão sem nbsCode no payload
- **WHEN** nbsCode não estiver disponível no mapeamento fiscal do item
- **THEN** o tratamento MUST seguir a regra do perfil de validação ativo antes do envio

#### Scenario: Emissão sem dados de subgrupos avançados
- **WHEN** subgrupos avançados de ibsCbs não forem informados
- **THEN** o sistema MUST enviar apenas o núcleo obrigatório sem inventar valores padrão fiscais
