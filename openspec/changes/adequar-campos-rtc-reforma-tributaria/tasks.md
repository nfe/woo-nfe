## Tracking

- Issue GitHub (RTC Fase 1): https://github.com/nfe/woo-nfe/issues/84
- Issue GitHub (activityEvent): https://github.com/nfe/woo-nfe/issues/87

## 1. Preparação e mapeamento fiscal

- [x] 1.1 Confirmar regras de obrigatoriedade por perfil e cenário para nbsCode, operationIndicator e classCode
- [x] 1.2 Definir matriz de precedência de origem dos campos (variação, produto, global)
- [x] 1.3 Revisar mensagens de validação e padrão de logging para falhas pré-envio

## 2. Campos administrativos e persistência

- [x] 2.1 Adicionar campos globais de RTC na integração do plugin (nbsCode, operationIndicator, classCode)
- [x] 2.2 Adicionar campos de RTC em produto simples e variação com sanitização e persistência
- [x] 2.3 Garantir fallback de leitura dos campos com precedência definida no design

## 3. Montagem de payload RTC de NFS-e

- [x] 3.1 Atualizar montagem de payload para incluir nbsCode no nível principal da requisição
- [x] 3.2 Incluir grupo ibsCbs com operationIndicator e classCode no payload de emissão
- [x] 3.3 Preservar comportamento atual para campos legados e evitar regressão no fluxo de emissão

## 4. Validações pré-envio

- [x] 4.1 Implementar perfis de validação (Compatível, Equilibrado, Estrito) com Equilibrado como padrão
- [x] 4.2 Implementar validação condicional de destinationIndicator e recipient
- [x] 4.3 Implementar regras de bloqueio/alerta de nbsCode conforme perfil ativo e registrar contexto em log
- [x] 4.4 Garantir suporte de recipient/destinationIndicator via payload de integração, sem exigir UI dedicada na fase 1

## 5. Migração progressiva e observabilidade

- [x] 5.1 Definir indicador operacional para monitorar ausência de nbsCode por pedido/item
- [x] 5.2 Publicar orientação de migração de perfil (Compatível -> Equilibrado -> Estrito)
- [x] 5.3 Definir critério e data de corte recomendada para adoção do perfil Estrito

## 6. Verificação funcional e documentação

- [x] 6.1 Validar cenários de homologação: operação padrão, destinatário diferente e ausência de parametrização
- [x] 6.2 Validar cenários por perfil de validação (Compatível, Equilibrado, Estrito)
- [x] 6.3 Revisar textos de ajuda no admin para orientar preenchimento fiscal
- [x] 6.4 Atualizar documentação de uso e checklist de ativação da reforma tributária
- [x] 6.5 Documentar limitação de UI da fase 1 para recipient/destinationIndicator e instrução de uso via integração

## 7. activityEvent — Campos administrativos e persistência

- [x] 7.1 Adicionar campos de activityEvent (name, beginOn, endOn, Code, address.*) em produto simples na aba NFe do produto
- [x] 7.2 Adicionar campos de activityEvent em variação com os mesmos subcampos
- [x] 7.3 Implementar save de produto simples e variação com sanitização (sanitize_text_field para textos, sanitize_key para datetime)
- [x] 7.4 Garantir precedência variação > produto na leitura dos campos de activityEvent por item do pedido

## 8. activityEvent — Montagem de payload

- [x] 8.1 Implementar método activity_event_info( $order_id ) que itera os itens e retorna o primeiro produto/variação com name preenchido
- [x] 8.2 Incluir bloco activityEvent no array de dados do payload RTC, condicionado à presença de name
- [x] 8.3 Garantir que subcampos vazios do address sejam removidos via array_filter antes do envio
- [x] 8.4 Validar ausência de regressão nos campos nbsCode, ibsCbs e fluxo legado

## 9. Verificação funcional — activityEvent

- [x] 9.1 Validar cenário: produto com activityEvent completo — payload contém bloco com todos os campos
- [x] 9.2 Validar cenário: produto com activityEvent parcial (só name) — payload contém bloco apenas com name
- [x] 9.3 Validar cenário: pedido sem activityEvent — bloco ausente do payload sem regressão
- [x] 9.4 Validar precedência: variação com activityEvent prevalece sobre produto com activityEvent diferente
