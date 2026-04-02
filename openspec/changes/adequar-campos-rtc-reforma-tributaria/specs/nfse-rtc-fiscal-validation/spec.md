## ADDED Requirements

### Requirement: Validação obrigatória pré-envio para RTC
O sistema MUST validar os campos obrigatórios de reforma tributária antes de enviar a requisição de emissão de NFS-e RTC com base no perfil de validação configurado.

#### Scenario: Perfil Compatível sem bloqueio por ausência de nbsCode
- **WHEN** o perfil de validação for Compatível e nbsCode estiver ausente na emissão RTC
- **THEN** o sistema MUST registrar alerta e permitir o envio da requisição

#### Scenario: Perfil Equilibrado com bloqueio em cenário crítico
- **WHEN** o perfil de validação for Equilibrado e o cenário RTC crítico exigir nbsCode
- **THEN** o sistema MUST bloquear a emissão sem nbsCode e informar mensagem de erro indicando o campo faltante

#### Scenario: Perfil Estrito com bloqueio total
- **WHEN** o perfil de validação for Estrito e nbsCode estiver ausente na emissão RTC
- **THEN** o sistema MUST bloquear a emissão e informar mensagem de erro indicando o campo faltante

#### Scenario: Requisição liberada após validação
- **WHEN** os campos obrigatórios do perfil ativo estiverem válidos
- **THEN** o sistema MUST permitir o envio da emissão para a API

### Requirement: Validação de regras condicionais de destinatário
O sistema MUST validar a consistência entre destinationIndicator e recipient quando houver destinatário diferente do tomador.

#### Scenario: Recipient obrigatório para destinatário diferente
- **WHEN** destinationIndicator for DifferentFromBuyer
- **THEN** o sistema MUST exigir o bloco recipient antes do envio

#### Scenario: Destinatário igual ao tomador
- **WHEN** destinationIndicator for SameAsBuyer
- **THEN** o sistema MUST permitir emissão sem bloco recipient

### Requirement: Observabilidade de falhas de validação
O sistema MUST registrar eventos de validação rejeitada com contexto suficiente para suporte operacional.

#### Scenario: Registro de erro de validação
- **WHEN** a emissão for bloqueada por inconsistência fiscal
- **THEN** o sistema MUST registrar em log o identificador da operação e os campos inválidos ou ausentes
