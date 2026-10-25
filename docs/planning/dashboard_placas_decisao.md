# Decisão — dashboard de Placas

**Etapa:** 5 — Planejamento da dashboard de Placas  
**Validação operacional:** a área atual é suficiente; a necessidade citada é comparar volumes por etapa.  
**Decisão:** não criar uma dashboard gerencial separada nem novos estados ou métricas. Manter a área operacional existente em `/signboards`.

## Por que a área atual atende

- `/signboards` já apresenta contagens das situações existentes: **Ativa**, **Vencida**, **Pendente** e **Retirada**.
- O quadro já mostra as cinco etapas atuais — **A fazer**, **Pago**, **Pronto**, **Disponível** e **Usadas** — com a contagem correspondente a cada raia. As contagens acompanham a busca e o filtro de situação aplicados.
- Cada cartão abre a ficha em `/signboards/{id}`, que mostra dados da placa e mantém o **Histórico de movimentações** individual.
- Corretores e administradores autenticados consultam e operam placas. O histórico individual fica na ficha; a auditoria global continua restrita a administradores em `/admin/audit`.

## Navegação existente e limite identificado

A ficha mostra identificação e endereço do imóvel associado, mas o título do imóvel não é atualmente um link direto para `/properties/{id}`. Como não foi confirmada a necessidade de uma dashboard separada, não há um resumo novo ao qual acrescentar esse link. Se a operação também precisar desse atalho direto, ele pode ser avaliado no módulo de Placas sem criar outra superfície.

## Limites

A situação operacional e a etapa do quadro permanecem conforme as regras existentes. Nenhuma consulta gerencial, agregação, filtro ou estado novo está aprovado nesta etapa.

