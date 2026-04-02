## Why

O plugin atual de emissão de NFS-e envia um payload legado e não contempla os campos centrais da Reforma Tributária no layout RTC, o que aumenta risco de rejeição e inconsistência fiscal. A mudança é necessária agora para manter conformidade com a API atual da NFe.io e permitir emissão com regras de IBS/CBS.

## Tracking

- Issue GitHub: https://github.com/nfe/woo-nfe/issues/84

## What Changes

- Adicionar suporte no plugin para informar os campos fiscais RTC exigidos para o novo fluxo de NFS-e, com foco em nbsCode e no grupo ibsCbs.
- Incluir mapeamento de operationIndicator e classCode no payload de emissão, respeitando regras condicionais relacionadas ao destinatário.
- Criar estratégia de preenchimento com padrão global e possibilidade de override por produto/variação para reduzir fricção operacional.
- Introduzir validações pré-envio para evitar combinações inválidas e reduzir erros de integração.
- Atualizar camada de configuração administrativa para entrada de novos dados fiscais necessários ao layout RTC.

## Capabilities

### New Capabilities
- nfse-rtc-tax-fields: Define coleta, persistência e envio dos novos campos fiscais RTC no payload de NFS-e (nbsCode e grupo ibsCbs).
- nfse-rtc-fiscal-validation: Define validações de consistência fiscal antes do envio, incluindo regras condicionais e combinações de códigos.

### Modified Capabilities
- Nenhuma.

## Impact

- Código afetado: montagem do payload de emissão, telas de configuração administrativa e campos fiscais de produto/variação.
- Integração externa: endpoint de emissão de NFS-e RTC da NFe.io e regras de validação do layout vigente.
- Operação: necessidade de parametrização fiscal inicial para novos campos da Reforma Tributária.
- Risco funcional: rejeição de emissão caso campos obrigatórios ou condicionais não estejam corretamente preenchidos.
