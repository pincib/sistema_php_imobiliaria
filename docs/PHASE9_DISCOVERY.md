# Descoberta e gate de entrada — Fase 9

Este documento prepara o levantamento da expansão do CRM para contratos, agenda e comissões. Ele não define regras jurídicas ou comerciais. Preencha e valide cada domínio com os responsáveis antes de aprovar implementação ou migração.

## Estado da fase

- **Estado:** aguardando descoberta com responsáveis.
- **Decisão de entrada:** pendente.
- **Responsável pelo aceite do negócio:** a definir.
- **Responsável técnico pela migração:** a definir.
- **Data e evidência da aprovação:** a registrar.

Um domínio só pode sair de `aguardando descoberta` quando suas decisões aplicáveis, critérios de aceite, papéis e plano de migração estiverem preenchidos e aprovados. Itens não aplicáveis devem ser marcados como tais com justificativa e responsável.

## Registro por domínio

Duplicar este bloco para cada domínio e preencher em reunião com os responsáveis.

```text
Domínio:
Estado: aguardando descoberta | pronto para implementação | bloqueado
Responsável de negócio:
Responsável técnico:
Participantes consultados:
Data da validação:
Evidência/aprovação:

Regras e decisões confirmadas:
-

Papéis e permissões (consultar, criar, editar, cancelar, excluir/arquivar, exportar):
-

Histórico e auditoria (eventos, autor, horário/fuso, imutabilidade, retenção):
-

Critérios de aceite verificáveis:
-

Dados legados e plano de migração (origem, mapeamento, validação, conflitos,
idempotência, rollback, reconciliação e responsável pela conferência):
-

Dependências de outros domínios:
-

Riscos e decisões ainda abertas:
-
```

## Perguntas de descoberta

### Contratos e vínculos

- Quais tipos e estados de contrato existem? Quem pode criar, revisar, assinar, alterar, encerrar ou cancelar cada estado?
- Quais partes, leads e imóveis podem ser associados? Um contrato pode cobrir mais de um imóvel ou lead? Como tratar substituições e relações históricas?
- Quais datas, identificadores, documentos e campos são obrigatórios por tipo? Quais dados sensíveis precisam de acesso restrito?
- Quais eventos devem compor o histórico e quais alterações exigem justificativa?
- De onde virão contratos existentes, como serão identificados e como a equipe validará a migração?

### Agenda e compromissos

- Quais tipos de compromisso existem e como se relacionam com leads, imóveis, contratos e interações?
- Quem pode criar, atribuir, reagendar, concluir ou cancelar? Como são tratados participantes e indisponibilidade?
- Qual fuso, duração, recorrência, lembretes, antecedência e política para compromissos passados?
- O que deve ser registrado no histórico do lead e em que momento? Como evitar duplicidade entre compromisso e follow-up?
- Há agenda legada a importar? Qual a regra de correspondência, validação e deduplicação?

### Comissões

- Quais eventos geram comissão e qual base de cálculo se aplica em cada tipo de negócio?
- Quais percentuais, faixas, rateios, arredondamentos, moedas e datas de competência são válidos? Quem aprova alterações de regra?
- Como funcionam parcelas, retenções, estornos, cancelamentos, inadimplência e correções retroativas?
- Que papéis podem consultar, calcular, aprovar, ajustar ou exportar valores? Quem pode ver valores individuais?
- Como os valores calculados serão conferidos contra a fonte financeira e quais registros precisam permanecer imutáveis?
- Há dados históricos para migrar? Como preservar cálculo original e regra/versão usada sem recalcular silenciosamente?

## Gate final da fase

Marcar a decisão como `aprovada` somente quando todos os domínios no escopo tiverem:

1. Responsáveis de negócio e técnico identificados e aprovação registrada.
2. Regras aplicáveis, papéis, permissões e histórico definidos.
3. Critérios de aceite observáveis, incluindo erros, cancelamentos e correções.
4. Plano de migração com origem, mapeamento, validação, idempotência, rollback e reconciliação.
5. Dependências e sequência entre domínios acordadas.

| Domínio | Descoberta validada | Aceite aprovado | Migração aprovada | Pode iniciar |
|---|---|---|---|---|
| Contratos | Pendente | Pendente | Pendente | Não |
| Agenda | Pendente | Pendente | Pendente | Não |
| Comissões | Pendente | Pendente | Pendente | Não |

