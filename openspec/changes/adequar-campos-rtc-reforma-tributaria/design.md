## Context

O plugin WooCommerce NFe foi construído com foco no payload legado de NFS-e e hoje não cobre os campos centrais do layout RTC exigidos pela Reforma Tributária. A integração atual envia dados básicos de serviço e tomador, mas não contempla o grupo ibsCbs nem a estratégia de coleta de códigos fiscais novos por produto, variação e configuração global.

A mudança afeta três pontos principais:
- Captura e persistência de dados fiscais no admin.
- Montagem do payload de emissão para o endpoint RTC de NFS-e.
- Validação pré-envio para evitar rejeições por regra de negócio.

Restrições relevantes:
- Preservar padrões de plugin WordPress/WooCommerce já existentes.
- Evitar hardcode de classificações fiscais voláteis.
- Manter compatibilidade de operação para lojas que ainda não parametrizaram todo o novo conjunto de campos.

## Tracking

- Issue GitHub (RTC Fase 1): https://github.com/nfe/woo-nfe/issues/84
- Issue GitHub (activityEvent): https://github.com/nfe/woo-nfe/issues/87

## Goals / Non-Goals

**Goals:**
- Definir um modelo claro de origem de dados fiscais RTC com precedência entre configuração global, produto simples e variação.
- Garantir que o payload de emissão inclua os novos campos necessários para RTC de forma consistente.
- Definir validações de obrigatoriedade e consistência antes da chamada de emissão para reduzir rejeições.
- Padronizar mensagens de erro e logs operacionais para diagnóstico rápido.

**Non-Goals:**
- Implementar motor fiscal automático para inferir operationIndicator e classCode a partir de regras tributárias completas.
- Cobrir todos os subgrupos avançados de ibsCbs na primeira entrega (ex.: creditTransfer, presumedCredits, governmentPurchase).
- Alterar fluxos de webhook, cancelamento ou consulta além do necessário para emissão.
- Implementar fallback global para activityEvent (dados do evento são intrinsecamente por produto/serviço, sem default corporativo aplicável).

## Decisions

1. Modelo de dados com fallback em três níveis.
- Decisão: usar precedência variação > produto > configuração global para nbsCode, operationIndicator e classCode.
- Racional: mantém padrão já utilizado no plugin para códigos de serviço, reduz impacto operacional e evita bloqueio total para catálogos grandes.
- Alternativas consideradas:
  - Somente configuração global: simples, porém insuficiente para catálogos com regimes diferentes.
  - Somente por produto/variação: mais preciso, porém alto esforço inicial de parametrização.

2. Escopo inicial do ibsCbs limitado ao núcleo obrigatório.
- Decisão: estruturar o payload com foco em operationIndicator e classCode no grupo ibsCbs, com possibilidade de extensão posterior.
- Racional: atende exigência principal da Reforma com menor risco de regressão.
- Alternativas consideradas:
  - Implementar todos os subgrupos de ibsCbs agora: maior cobertura, mas alto risco e complexidade de validação.

3. Validação pré-envio com bloqueio explícito.
- Decisão: bloquear emissão quando campos obrigatórios do RTC não estiverem presentes para o cenário configurado.
- Racional: evita requisições inválidas, melhora experiência de suporte e reduz ruído em webhook de falha.
- Alternativas consideradas:
  - Delegar toda validação para API: menor esforço, porém pior feedback ao usuário e maior ciclo de correção.

4. Catálogo de códigos não embutido no plugin.
- Decisão: não hardcode de tabelas completas de operationIndicator/classCode no código.
- Racional: tabelas mudam por evolução normativa e devem ser tratadas por parametrização externa e documentação fiscal.
- Alternativas consideradas:
  - Embutir tabela estática: melhora UX inicial, mas cria dívida de manutenção alta.

5. Sem versionamento de validações por ambiente na fase inicial.
- Decisão: não diferenciar regras de validação estrita entre homologação e produção nesta entrega.
- Racional: manter o mesmo contrato de validação reduz divergência de comportamento, evita falso positivo em homologação e diminui risco de falhas ao promover para produção.
- Alternativas consideradas:
  - Relaxar validações em homologação: acelera testes iniciais, mas mascara erros de parametrização e aumenta risco de rejeição em produção.

6. Coleta universal de nbsCode com bloqueio progressivo.
- Decisão: coletar nbsCode em todos os fluxos RTC, mas aplicar bloqueio conforme perfil de validação (Compatível, Equilibrado, Estrito) e plano de endurecimento por fase.
- Racional: reduz fricção operacional no curto prazo sem perder direção regulatória, permitindo saneamento cadastral antes de bloquear toda emissão.
- Alternativas consideradas:
  - nbsCode sempre obrigatório desde o início: acelera conformidade formal, mas aumenta risco de bloqueio operacional e preenchimento artificial.

7. Perfis de validação com padrão inicial Equilibrado.
- Decisão: suportar três perfis de validação no plugin.
  - Compatível: alerta ausência de nbsCode, sem bloqueio.
  - Equilibrado (padrão): bloqueia em cenários RTC críticos definidos pela regra do plugin.
  - Estrito: bloqueia toda emissão RTC sem nbsCode.
- Racional: permite adoção controlada por maturidade fiscal da loja e reduz choque de implantação.
- Alternativas consideradas:
  - Perfil único rígido: simples de manter, porém inadequado para diferentes estágios de parametrização dos clientes.

8. recipient e destinationIndicator sem UI dedicada na fase inicial.
- Decisão: não expor campos de recipient e destinationIndicator no checkout/admin na fase 1; suportar esses campos via integração e validação de payload quando informados.
- Racional: reduz escopo e risco de rollout inicial, mantendo conformidade com a regra condicional da API para cenários avançados.
- Alternativas consideradas:
  - Expor UI completa na fase 1: amplia cobertura funcional imediata, porém aumenta complexidade de UX, suporte e validações para o primeiro ciclo.

## Risks / Trade-offs

- [Risco] Parametrização incorreta por usuários de negócio.
  - Mitigação: mensagens de validação claras, documentação de preenchimento e logs com campo exato em erro.

- [Risco] Diferenças de exigência por município e ambiente (nacional vs municipal).
  - Mitigação: validar campos nucleares e manter estratégia de extensão incremental com flags/configuração.

- [Trade-off] Escopo inicial não cobre todos subgrupos de ibsCbs.
  - Mitigação: desenhar payload extensível e backlog explícito para fases seguintes.

- [Trade-off] Dependência de parametrização manual para códigos fiscais.
  - Mitigação: manter fallback global para operação inicial e permitir override fino quando necessário.

- [Trade-off] Maior complexidade de produto ao introduzir perfis de validação.
  - Mitigação: definir perfil padrão único (Equilibrado), UX clara e documentação objetiva de migração entre perfis.

## Migration Plan

1. Introduzir novos campos de configuração sem remover os legados.
2. Publicar release com perfil Equilibrado como padrão e suporte aos perfis Compatível e Estrito.
3. Orientar clientes para parametrização inicial por fallback global e ajustes por produto/variação, com acompanhamento de alertas de nbsCode ausente.
4. Definir data de corte para migração recomendada ao perfil Estrito, baseada em métricas de saneamento cadastral.
5. Monitorar erros/alertas de emissão e ajustar regras condicionais em iterações curtas.
6. Rollback: rebaixar perfil para Compatível em caso de incidente operacional relevante.

9. Modelo de dados para activityEvent sem fallback global.
- Decisão: coletar os campos de activityEvent (name, beginOn, endOn, Code, address.*) apenas em produto simples e variação, com precedência variação > produto. Sem nível de configuração global.
- Racional: o endereço do evento e suas datas são específicos do produto/serviço comercializado (ex: ingresso de show). Não existe default corporativo de "endereço de evento" que faça sentido para todas as emissões. Segue o mesmo padrão de campos explícitos já usado para os demais campos fiscais.
- Alternativas consideradas:
  - Incluir configuração global de activityEvent: criaria false sense of security — um endereço global de evento seria aplicado a produtos sem relação com aquele evento.
  - Coletar activityEvent por pedido no admin do pedido (WC order): válido para operação manual pontual, mas inconsistente com o ciclo de cadastro de produto e não escala para volumes maiores. Descartado em favor do padrão produto/variação já estabelecido.

10. Campo name como gatilho de inclusão do bloco.
- Decisão: incluir o bloco activityEvent no payload somente quando name estiver preenchido. Campos parciais de address são enviados só se não vazios (array_filter).
- Racional: name identifica o evento de forma inequívoca. Bloco incompleto sem name não agrega semântica fiscal. Consistente com o comportamento dos demais grupos opcionais já implementados.
- Alternativas consideradas:
  - Exigir name + pelo menos um campo de address: mais restritivo, mas aumenta fricção para casos simples (evento sem endereço detalhado cadastrado).

## Open Questions

Sem pendências no momento.
