# Novas informações e melhorias — CRM imobiliário Pinciara

## Objetivo

Evoluir o CRM existente para administrar leads, proprietários e imóveis em fluxos conectados, com importação dos três arquivos CSV fornecidos, correspondência de imóveis por critérios configuráveis e contato pelo WhatsApp Desktop. A interface deve adotar tema claro como padrão, mantendo a identidade operacional já existente.

> Esta especificação descreve comportamento e critérios de aceite. Não presume integrações com APIs externas. A abertura do WhatsApp Desktop depende do aplicativo/protocolo instalado e o CRM não consegue confirmar que a mensagem foi efetivamente enviada; o registro deve distinguir abertura do WhatsApp de confirmação manual do corretor.

## Fase 1 — Cadastro e gestão de leads e proprietários

### Cadastro

Adicionar ou ajustar o cadastro de leads e criar gestão de proprietários com painel/listagem, busca, detalhe, edição e vínculo com imóveis. Usar os mesmos campos de contato nos dois cadastros:

| Campo | Obrigatório | Regra |
|---|---:|---|
| Nome | Sim | Necessário para salvar. |
| Código do imóvel | Sim | Identifica o imóvel de interesse do lead ou o imóvel relacionado ao proprietário; validar existência e permitir selecionar/buscar pelo código. |
| Telefone | Não | Permitir vazio; quando informado, armazenar de forma normalizada e exibir em formato legível. |
| E-mail | Não | Permitir vazio; validar formato quando informado. |
| Observações | Não | Texto livre com limite adequado e proteção contra conteúdo inválido. |

No proprietário, permitir vincular mais de um imóvel e apresentar claramente o código de cada vínculo. Se o modelo atual exigir apenas um código, adequá-lo para refletir a relação proprietário–imóveis da planilha, sem duplicar o cadastro do mesmo proprietário.

### WhatsApp para leads e proprietários

Adicionar ação “WhatsApp” no detalhe e/ou nas ações do registro. O fluxo deve:

1. Solicitar a régua do contato: `1º contato`, `Pós-visita`, `Nova oportunidade`, `Follow-up` ou `Pós-fechamento de contrato`.
2. Montar uma mensagem inicial usando modelo associado à régua e dados disponíveis do contato e do(s) imóvel(is); apresentar a mensagem em campo editável antes de abrir o WhatsApp.
3. Exibir ação explícita para abrir/enviar pelo WhatsApp Desktop, usando o protocolo/link compatível com o aplicativo instalado e uma mensagem codificada corretamente.
4. Orientar o corretor a concluir o envio no WhatsApp. Após retorno ao CRM, permitir confirmar manualmente que enviou ou marcar que não enviou/cancelou.
5. Registrar no histórico a régua, o texto final, o usuário, data/hora e o resultado declarado (`confirmado pelo corretor`, `não enviado` ou `cancelado`). Não declarar envio confirmado automaticamente pela simples abertura do aplicativo.

Quando telefone estiver ausente, informar que é necessário selecionar/informar um número antes de abrir o WhatsApp. Não inventar integração de leitura de status do aplicativo.

## Fase 2 — Cadastro completo de imóveis

Implementar formulário e detalhe do imóvel com os seguintes campos e validações:

| Campo | Obrigatório | Observação |
|---|---:|---|
| Código do imóvel | Sim | Único; usar como referência de negócio e para links/importação. |
| Endereço/logradouro | Sim | Endereço digitado. |
| Link do Google Maps | Sim | Link do local; se necessário, esclarecer no formulário se o link é obrigatório ou gerado a partir do endereço. |
| Área construída | Sim | Numérica, maior que zero, unidade exibida em m². |
| Área do terreno | Não | Numérica não negativa, em m². |
| Intenção | Sim | `Venda`, `Locação` ou `Venda/Locação`. |
| Valor da intenção | Sim | Valor monetário correspondente à intenção; aceitar e exibir moeda em formato brasileiro. Para `Venda/Locação`, permitir informar um valor de venda e outro de locação ou definir explicitamente um único valor, conforme validação da regra de negócio antes de implementar. |
| Pretensão | Sim | `Residencial` ou `Comercial`. |
| Tipo de imóvel | Sim | `Loja`, `Casa`, `Galpão`, `Terreno`, `Área`, `Andar corporativo`, `Apartamento`, `Cobertura`, `Ponto`, `Prédio` ou `Sala`. |
| Preço do condomínio | Não | Valor monetário. |
| Preço do IPTU | Sim | Valor monetário; permitir zero quando isento, distinguindo zero de campo não preenchido. |
| Link do anúncio | Não | Se vazio, usar `https://www.pinciara.com.br/imovel/{{codigo_imovel}}`. |
| Observações | Não | Texto livre. |
| Bairro e cidade | Sim para correspondência | Embora não listados como obrigatórios no rascunho do formulário, são necessários para a correspondência regional pedida; coletar/importar e permitir correção. |

O link padrão deve substituir `{{codigo_imovel}}` pelo código real, sem chaves. Links externos devem ser apresentados de forma segura e reconhecível.

## Fase 3 — Correspondência de imóveis para leads

Na ficha do lead, apresentar separadamente:

- **Imóveis vinculados:** relações adicionadas/confirmadas pelo corretor, preservando o tipo de relação e o histórico existente.
- **Imóveis relacionados:** sugestões calculadas automaticamente que ainda não são vínculos confirmados. Disponibilizar ação para vincular uma sugestão ao lead.

A sugestão automática considera apenas região e preço; não filtra por tipo de imóvel. O corretor pode completar ou ajustar a preferência de busca do lead com bairros/regiões e faixa de preço. Sem preferências manuais, usar endereço/região e faixa de valor inferidos dos imóveis vinculados, quando houver dados suficientes; mostrar estado explicativo quando não houver base para sugerir.

Correspondência regional:

- Em Niterói, priorizar igualdade de logradouro e bairro; cidade deve ser compatível. Se logradouro não existir no perfil, usar bairro e cidade.
- Nas demais cidades, usar cidade como critério regional; bairro/logradouro podem ser exibidos como contexto, mas não devem restringir a busca.

Correspondência de preço usa a faixa informada pelo corretor; quando inferida, usar valores dos imóveis vinculados e deixar visível que é uma sugestão derivada. A ordenação deve priorizar correspondência regional e proximidade de preço, sem criar filtro por categoria/tipo. Cada sugestão deve indicar de forma compreensível por que apareceu (região e preço).

## Fase 4 — Importação e conferência das planilhas

Usar os três CSV anexados como fontes de importação: imóveis, relação de proprietários por imóvel e contatos/leads. Antes da gravação, apresentar prévia, mapeamento de cabeçalhos, avisos e erros por linha; permitir cancelar e corrigir o mapeamento. Não descartar colunas sem avisar. Preservar referências externas para rastrear a origem. Detectar registros repetidos e resumir inserções, atualizações, ignorados e inválidos antes e depois da confirmação.

### Mapeamento de imóveis

Importar, no mínimo, estas colunas, respeitando os cabeçalhos reais do CSV anexado:

| Coluna lógica solicitada | Cabeçalho observado no arquivo |
|---|---|
| Ref | `ref.` |
| Tipo | `tipo` |
| Venda | `venda` |
| Locação | `locação` |
| Logradouro | `logradouro` |
| Bairro | `bairro` |
| Cidade | `cidade` |

A referência (`Ref`) será o código do imóvel. O arquivo também possui CEP e IPTU, que devem ser mapeados quando disponíveis, sem substituir os campos solicitados. Converter valores no padrão brasileiro, incluindo vírgula decimal, e tratar `0,00` conforme o significado de ausência/isento confirmado no mapeamento.

### Mapeamento de leads

Usar as colunas solicitadas do arquivo de contatos:

- `First Name`, `Middle Name`, `Last Name` → compor `Nome` em ordem natural, ignorando partes vazias.
- `E-mail 1 - Value` e `E-mail 2 - Value` → e-mail principal e alternativo, quando o sistema suportar; não perder o segundo valor silenciosamente.
- `Phone 1 - Value` e `Phone 2 - Value` → telefone principal e alternativo, quando suportados; preservar ambos ou mostrar no relatório de importação qualquer valor não acomodado.
- Usar `Notes` como observações quando existir e estiver mapeada.
- O código do imóvel não aparece entre as colunas de referência fornecidas para leads. Não inventar associação: deixar sem vínculo e destacar registros que precisem de complementação, ou permitir mapear outra coluna caso ela exista no arquivo completo.

### Mapeamento de proprietários por imóvel

Usar `Nome`, `Telefones`, `E-mail` e `Ref. imóvel`. Associar proprietário ao imóvel pela referência, evitando duplicar o proprietário quando tiver mais de um imóvel. Os campos adicionais do CSV (por exemplo, status, endereço, valores e áreas) podem servir para validação/conferência, mas não devem sobrescrever dados sem regra de precedência explícita.

## Fase 5 — Tema claro e consistência visual

Alterar o tema padrão da aplicação para claro em todas as superfícies autenticadas e de autenticação, mantendo contraste, estados de foco, erro, sucesso e desabilitado. Se houver seletor de tema existente, definir claro como padrão e preservar a preferência do usuário; caso contrário, usar tema claro consistentemente sem criar um mecanismo adicional não solicitado. Seguir tokens e componentes já estabelecidos pelo projeto e o padrão operacional de leitura rápida.

## Critérios de aceite

- Lead e proprietário podem ser cadastrados com os campos obrigatórios indicados; campos opcionais vazios não impedem salvar.
- Um proprietário pode ser relacionado aos imóveis corretos por código e a lista de proprietários mostra seus imóveis.
- Imóvel não salva sem os campos obrigatórios e rejeita código duplicado; link de anúncio padrão é preenchido quando omitido.
- Lead mostra vínculos confirmados separados de sugestões automáticas; critérios regionais de Niterói e demais cidades são aplicados como descritos e tipo de imóvel não filtra resultados.
- Preferências manuais de bairro/região e faixa de preço alteram as sugestões e são visíveis/editáveis.
- WhatsApp abre com texto editável e número correto quando houver; o histórico só registra confirmação de envio após ação manual do corretor.
- Importação oferece prévia, mapeamento, validação, cancelamento e relatório, incluindo dados que não tenham destino no CRM.
- Tema claro é consistente em formulários, tabelas, feedbacks e estados vazios, com navegação por teclado.

## Sequência sugerida de execução

1. Confirmar as decisões pendentes listadas ao final, principalmente comportamento do valor em `Venda/Locação`, exigência do link do Maps, origem do código de imóvel para leads e política de valores zero nos CSVs.
2. Implementar e validar o modelo de imóveis e vínculos proprietário–imóveis.
3. Implementar os cadastros e ajustar as fichas de leads/proprietários.
4. Implementar a importação dos três CSVs com prévia e relatório.
5. Implementar preferências e sugestões de imóveis para leads.
6. Implementar fluxo WhatsApp, modelos editáveis e registro de resultado.
7. Aplicar tema claro e revisar as telas integradas.

## Fluxo Impeccable por fase

Executar os comandos de terminal na raiz do projeto. Em cada nova sessão visual, iniciar uma vez com `context --target <arquivo-ou-rota-existente>` (ou `context` sem alvo quando ainda não houver um alvo real). Não passar nomes inventados como alvo: substituir os espaços reservados abaixo por caminhos existentes após a criação de cada superfície. Para planejamento de cada novo fluxo, usar `$impeccable shape ...`; a skill retorna um brief e não implementa código.

### Preparação da sessão

```powershell
& 'C:\Users\berna\.agents\skills\impeccable\scripts\impeccable.cmd' context
```

### Fase 1 — Leads e proprietários

```text
$impeccable shape cadastro-e-gestão-de-leads-e-proprietários
$impeccable critique <alvo-real-de-leads-e-proprietários>
$impeccable audit <alvo-real-de-leads-e-proprietários>
$impeccable harden <alvo-real-de-leads-e-proprietários>
$impeccable adapt <alvo-real-de-leads-e-proprietários>
$impeccable polish <alvo-real-de-leads-e-proprietários>
```

### Fase 2 — Imóveis

```text
$impeccable shape cadastro-completo-de-imóveis
$impeccable critique <alvo-real-de-imóveis>
$impeccable audit <alvo-real-de-imóveis>
$impeccable harden <alvo-real-de-imóveis>
$impeccable adapt <alvo-real-de-imóveis>
$impeccable polish <alvo-real-de-imóveis>
```

### Fase 3 — Correspondência e sugestões

```text
$impeccable shape correspondência-de-imóveis-para-leads
$impeccable critique <alvo-real-da-ficha-do-lead>
$impeccable audit <alvo-real-da-ficha-do-lead>
$impeccable harden <alvo-real-da-ficha-do-lead>
$impeccable adapt <alvo-real-da-ficha-do-lead>
$impeccable polish <alvo-real-da-ficha-do-lead>
```

### Fase 4 — Importação CSV

```text
$impeccable shape importação-de-imóveis-leads-e-proprietários
$impeccable critique <alvo-real-da-importação>
$impeccable audit <alvo-real-da-importação>
$impeccable harden <alvo-real-da-importação>
$impeccable adapt <alvo-real-da-importação>
$impeccable polish <alvo-real-da-importação>
```

### Fase 5 — WhatsApp

```text
$impeccable shape fluxo-de-mensagem-whatsapp-para-contatos
$impeccable critique <alvo-real-do-fluxo-whatsapp>
$impeccable audit <alvo-real-do-fluxo-whatsapp>
$impeccable harden <alvo-real-do-fluxo-whatsapp>
$impeccable adapt <alvo-real-do-fluxo-whatsapp>
$impeccable polish <alvo-real-do-fluxo-whatsapp>
```

### Fase 6 — Tema claro e revisão integrada

```text
$impeccable shape adoção-de-tema-claro-no-crm
$impeccable critique <alvo-real-do-layout-e-superfícies>
$impeccable audit <alvo-real-do-layout-e-superfícies>
$impeccable adapt <alvo-real-do-layout-e-superfícies>
$impeccable polish <alvo-real-do-layout-e-superfícies>
```

`critique`, `audit`, `harden`, `adapt`, `polish` e `shape` são comandos da skill invocados na conversa com o agente. No terminal, o launcher local é usado para `context` e, ao final de mudanças de UI web, para o detector, conforme indicado pelo agente:

```powershell
& 'C:\Users\berna\.agents\skills\impeccable\scripts\impeccable.cmd' detect --json
```

## Decisões que precisam ser confirmadas antes da implementação

1. Para `Venda/Locação`, o cadastro terá dois valores independentes (venda e locação). Recomendação: sim, para manter correspondência de preço correta.
2. O link do Google Maps será obrigatório digitado ou gerado automaticamente a partir do endereço. Recomendação: gerar link e permitir edição, evitando exigir que o usuário pesquise e cole um URL.
3. Leads poderão ser importados sem código de imóvel e vinculados depois. Recomendação: sim, pois as colunas de lead informadas não incluem referência de imóvel.
4. `Preço do IPTU` obrigatório pode ser zero quando isento ou desconhecido? Recomendação: zero significa isento; desconhecido deve ficar pendente de complementação, sem converter automaticamente vazio em zero.
5. A confirmação de envio no WhatsApp será manual pelo corretor, porque abrir o aplicativo não comprova envio. Recomendação: manter confirmação manual e registrar o estado declarado.
