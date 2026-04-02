## Tracking

- Issue GitHub: https://github.com/nfe/woo-nfe/issues/84

## 1. Preparação e mapeamento fiscal

- [ ] 1.1 Confirmar regras de obrigatoriedade por perfil e cenário para nbsCode, operationIndicator e classCode
- [ ] 1.2 Definir matriz de precedência de origem dos campos (variação, produto, global)
- [ ] 1.3 Revisar mensagens de validação e padrão de logging para falhas pré-envio

## 2. Campos administrativos e persistência

- [ ] 2.1 Adicionar campos globais de RTC na integração do plugin (nbsCode, operationIndicator, classCode)
- [ ] 2.2 Adicionar campos de RTC em produto simples e variação com sanitização e persistência
- [ ] 2.3 Garantir fallback de leitura dos campos com precedência definida no design

## 3. Montagem de payload RTC de NFS-e

- [ ] 3.1 Atualizar montagem de payload para incluir nbsCode no nível principal da requisição
- [ ] 3.2 Incluir grupo ibsCbs com operationIndicator e classCode no payload de emissão
- [ ] 3.3 Preservar comportamento atual para campos legados e evitar regressão no fluxo de emissão

## 4. Validações pré-envio

- [ ] 4.1 Implementar perfis de validação (Compatível, Equilibrado, Estrito) com Equilibrado como padrão
- [ ] 4.2 Implementar validação condicional de destinationIndicator e recipient
- [ ] 4.3 Implementar regras de bloqueio/alerta de nbsCode conforme perfil ativo e registrar contexto em log
- [ ] 4.4 Garantir suporte de recipient/destinationIndicator via payload de integração, sem exigir UI dedicada na fase 1

## 5. Migração progressiva e observabilidade

- [ ] 5.1 Definir indicador operacional para monitorar ausência de nbsCode por pedido/item
- [ ] 5.2 Publicar orientação de migração de perfil (Compatível -> Equilibrado -> Estrito)
- [ ] 5.3 Definir critério e data de corte recomendada para adoção do perfil Estrito

## 6. Verificação funcional e documentação

- [ ] 6.1 Validar cenários de homologação: operação padrão, destinatário diferente e ausência de parametrização
- [ ] 6.2 Validar cenários por perfil de validação (Compatível, Equilibrado, Estrito)
- [ ] 6.3 Revisar textos de ajuda no admin para orientar preenchimento fiscal
- [ ] 6.4 Atualizar documentação de uso e checklist de ativação da reforma tributária
- [ ] 6.5 Documentar limitação de UI da fase 1 para recipient/destinationIndicator e instrução de uso via integração
