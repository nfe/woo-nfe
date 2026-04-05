## ADDED Requirements

### Requirement: Coleta e persistência do grupo activityEvent por produto
O sistema MUST coletar e persistir os campos do grupo activityEvent no produto simples e na variação, sem fallback para configuração global.

#### Scenario: Campos de activityEvent em produto simples
- **WHEN** o operador preencher os campos de activityEvent no painel de edição do produto simples
- **THEN** o sistema MUST salvar name, beginOn, endOn, Code e os subcampos de address como meta do produto com sanitização adequada

#### Scenario: Campos de activityEvent em variação
- **WHEN** o operador preencher os campos de activityEvent no painel de edição da variação
- **THEN** o sistema MUST salvar name, beginOn, endOn, Code e os subcampos de address como meta da variação com sanitização adequada

#### Scenario: Precedência variação sobre produto para activityEvent
- **WHEN** um item do pedido for variação e existir name preenchido na variação
- **THEN** o sistema MUST usar os dados de activityEvent da variação no payload de emissão

#### Scenario: Fallback para produto simples em activityEvent
- **WHEN** não existir name preenchido na variação e existir name preenchido no produto
- **THEN** o sistema MUST usar os dados de activityEvent do produto no payload de emissão

#### Scenario: Ausência de activityEvent no produto e na variação
- **WHEN** não existir name preenchido nem na variação nem no produto de nenhum item do pedido
- **THEN** o sistema MUST omitir o bloco activityEvent do payload de emissão

### Requirement: Inclusão do grupo activityEvent no payload de emissão
O sistema MUST incluir o grupo activityEvent no payload RTC de NFS-e quando o campo name estiver preenchido no produto ou variação de pelo menos um item do pedido.

#### Scenario: Emissão com activityEvent completo
- **WHEN** o produto ou variação do pedido tiver name, beginOn, endOn, Code e address preenchidos
- **THEN** o payload MUST conter o bloco activityEvent com todos os campos informados antes do envio para a API

#### Scenario: Emissão com activityEvent parcial
- **WHEN** o produto ou variação do pedido tiver name preenchido e demais campos parcialmente preenchidos
- **THEN** o payload MUST conter o bloco activityEvent apenas com os campos não vazios, omitindo subcampos ausentes

#### Scenario: Emissão sem activityEvent
- **WHEN** nenhum item do pedido tiver name de activityEvent preenchido
- **THEN** o payload MUST NOT conter o bloco activityEvent

#### Scenario: Emissão sem regressão nos campos existentes
- **WHEN** o pedido não tiver activityEvent configurado
- **THEN** o sistema MUST emitir normalmente com nbsCode, ibsCbs e demais campos sem alteração de comportamento
