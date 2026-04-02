# Resumo Executivo - Adequação RTC (Reforma Tributária)

## Tracking

- Issue GitHub: https://github.com/nfe/woo-nfe/issues/84

## Contexto
O plugin Woo NFe atualmente opera com foco em payload legado de NFS-e. Para aderir ao layout RTC da NFe.io, é necessário incluir novos campos fiscais e regras de validação para reduzir rejeições e manter conformidade operacional.

## Objetivo da mudança
Adequar o fluxo de emissão de NFS-e no WooCommerce para suportar os campos centrais da Reforma Tributária com implantação controlada, baixo risco operacional e trilha clara de endurecimento de validações.

## Escopo aprovado (Fase 1)
- Incluir suporte a campos RTC no payload de emissão (com foco em nbsCode e ibsCbs com operationIndicator/classCode).
- Manter fallback de dados fiscais com precedência: variação -> produto -> configuração global.
- Implementar validação pré-envio com perfis:
  - Compatível: alerta ausência de nbsCode, sem bloqueio.
  - Equilibrado (padrão): bloqueio em cenários RTC críticos.
  - Estrito: bloqueio total sem nbsCode.
- Suportar recipient/destinationIndicator via integração/payload, sem UI dedicada no checkout/admin nesta fase.

## Decisões estratégicas
- Não hardcode de tabelas fiscais (operationIndicator/classCode) no plugin.
- Não diferenciar validação por ambiente (homologação/produção) na fase inicial.
- Adotar migração progressiva para maior rigor (perfil Estrito) com base em métricas de saneamento.

## Benefícios esperados
- Redução de rejeições por inconsistência de payload RTC.
- Menor atrito de rollout para clientes com cadastro ainda incompleto.
- Maior previsibilidade para suporte e operação, com mensagens e logs mais acionáveis.

## Riscos e mitigação
- Risco: parametrização fiscal incorreta.
  - Mitigação: validações claras, logs de campo faltante/inválido e orientação operacional.
- Risco: heterogeneidade municipal.
  - Mitigação: núcleo mínimo validado + evolução incremental por fases.
- Trade-off: sem UI de recipient na fase 1.
  - Mitigação: suporte via integração e documentação explícita da limitação.

## Plano de transição
1. Publicar campos novos sem remover legados.
2. Liberar com perfil Equilibrado como padrão.
3. Monitorar alertas/falhas de nbsCode por pedido/item.
4. Definir critério + data de corte recomendada para perfil Estrito.
5. Em incidente operacional, rebaixar temporariamente para perfil Compatível.

## Critérios de sucesso
- Queda de erros de validação RTC após parametrização inicial.
- Adoção estável do perfil Equilibrado sem impacto relevante em emissão.
- Evolução controlada para perfil Estrito com baixo volume de bloqueios inesperados.

## Próximo passo
Iniciar implementação conforme tasks da change e validar os cenários de homologação por perfil de validação.
