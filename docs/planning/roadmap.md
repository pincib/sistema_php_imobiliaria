# Roadmap de construção — CRM Imobiliário Local-First

Roadmap por etapas baseado em [`architecture_v2.md`](../architecture/architecture_v2.md). Os comandos Impeccable estão alinhados à versão 4.7.0 instalada e indicam quando planejar, avaliar e refinar cada superfície de interface.

## Como usar os comandos Impeccable

- **No início de cada sessão de trabalho visual:** execute `context` na raiz do projeto. Com `--target`, indique uma rota ou arquivo-fonte para carregar o contexto correspondente. Não execute `context` repetidamente durante a mesma sessão.
- **Antes de construir uma tela ou fluxo novo:** execute `shape <fluxo>`. Ele produz um brief e para antes de implementação. Para uma superfície nova, o fluxo depende de `PRODUCT.md`; conclua `init` primeiro quando ainda não houver esse arquivo.
- **Depois de implementar:** use `critique` para avaliação de experiência e `audit` para problemas técnicos mensuráveis. Use `harden` em fluxos com formulários, permissões, erros, dados grandes ou concorrência; `adapt` quando houver requisitos de telas menores.
- **Antes de entregar:** resolva os achados e execute `polish` no caminho completo que será entregue.
- **Detector:** em UI web, execute `detect --json` nos arquivos alterados quando o contexto indicar varredura manual. Se os hooks automáticos estiverem ativos, siga o resultado do hook e não rode uma varredura duplicada.
- Alvos entre `<...>` são espaços reservados. Substitua-os pelo caminho real da rota/arquivo, disponível depois que a estrutura do app existir.
- Para fases exclusivamente de backend, dados ou infraestrutura, não há comando de interface a executar.

Exemplo de launcher no Windows, executado na raiz do projeto:

```powershell
& 'C:\Users\berna\.agents\skills\impeccable\scripts\impeccable.cmd' context
```

Em conversas com o agente, os comandos de skill são invocados como `$impeccable <comando>`. No terminal, use o launcher acima seguido do verbo, por exemplo `...\impeccable.cmd context`.

## Fase 0 — Decisões do produto e preparação

**Objetivo:** transformar as premissas em regras operacionais confirmadas e preparar uma base reproduzível.

**Entregas**

- Confirmar tamanho da equipe e pico esperado de usuários simultâneos.
- Definir papéis e permissões de Admin e Corretor, visibilidade de leads e regras de transferência de responsável.
- Definir etapas/transições do pipeline, motivos de perda, regras e fuso de follow-up, política de duplicidade e importação.
- Definir senha, expiração de sessão, retenção de auditoria, localização do banco, backups e recuperação do host.
- Criar esqueleto do app, convenções, configuração por ambiente, Docker Compose, Slim, autoload e rota de saúde.
- Criar `PRODUCT.md` com fatos confirmados e decisões em aberto, concluindo o fluxo Impeccable `init`.

**Critérios de saída**

- Uma instalação limpa sobe o serviço seguindo instruções documentadas.
- Decisões sobre autorização, dados e fluxo de negócio estão registradas; dúvidas permanecem explicitamente abertas.
- `PRODUCT.md` existe antes do trabalho de novas superfícies visuais.

**Comandos Impeccable**

```text
$impeccable init
```

`init` captura o contexto do produto; ele não define sozinho a identidade visual nem substitui o planejamento de cada tela. Não há outro comando Impeccable para scaffold, Docker ou decisões de backend.

## Fase 1 — Fundação, autenticação e segurança

**Objetivo:** permitir acesso seguro e estabelecer a base persistente do sistema.

**Entregas**

- Conexão SQLite centralizada, WAL, `foreign_keys=ON`, `busy_timeout`, migrações e transações curtas.
- Tabelas para usuários, papéis, sessões e auditoria, com índices e restrições.
- Login/logout, `password_hash()`/`password_verify()`, CSRF, cookies e sessões seguras, autorização por rota e mensagens de erro.
- Criação segura e documentada do primeiro administrador, sem senha padrão embutida.
- Estrutura modular simples (`Modules/Leads`, `Properties`, `Signboards`) e limites entre rotas, serviços e persistência.
- Auditoria de mudanças críticas e permissões.

**Critérios de saída**

- Rotas privadas exigem sessão válida e respeitam o papel do usuário.
- Segredos não estão versionados e o banco não é servido pelo Apache.
- Migrações podem ser aplicadas de forma repetível em um banco novo.

**Comandos Impeccable — tela de autenticação**

```text
$impeccable shape fluxo-de-login-e-acesso
$impeccable critique <arquivo-ou-rota-de-login>
$impeccable audit <arquivo-ou-rota-de-login>
$impeccable harden <arquivo-ou-rota-de-login>
$impeccable polish <arquivo-ou-rota-de-login>
```

Execute `context --target <arquivo-ou-rota-de-login>` no começo da sessão visual. Rode `shape` antes do código; rode os demais depois que login, erros e estados de permissão existirem. `harden` é indicado para sessão expirada, credenciais inválidas e acesso negado.

## Fase 2 — Leads e pipeline

**Objetivo:** entregar o núcleo de cadastro e acompanhamento de leads.

**Entregas**

- Cadastro, edição, visualização, busca, filtros e paginação de leads.
- Campos mínimos definidos pelo negócio, validação no servidor e normalização segura de telefone/e-mail.
- Pipeline com transições permitidas; perda exige motivo obrigatório.
- Associação N:N entre Leads e Imóveis preparada no modelo, mesmo que o cadastro de imóveis venha na Fase 6.
- Atribuição e troca de responsável com usuário, instante e motivo auditáveis.
- Estados da interface: carregamento, vazio, sem resultados, erro e sem permissão.

**Critérios de saída**

- Corretor opera apenas o fluxo autorizado.
- Mudanças de estágio, responsável e dados relevantes ficam auditáveis.
- Listas continuam utilizáveis com paginação no servidor.

**Comandos Impeccable — lista, detalhe e pipeline**

```text
$impeccable shape gestão-de-leads
$impeccable critique <arquivo-ou-rota-de-leads>
$impeccable audit <arquivo-ou-rota-de-leads>
$impeccable adapt <arquivo-ou-rota-de-leads>
$impeccable polish <arquivo-ou-rota-de-leads>
```

Execute `context --target <arquivo-ou-rota-de-leads>` no começo da sessão visual. A fase é uma superfície **Operate**: priorize leitura rápida, ações frequentes, teclado, filtros, paginação e telas menores.

## Fase 3 — Interações e follow-ups

**Objetivo:** tornar cada lead acompanhável e reduzir retornos esquecidos.

**Entregas**

- Registro cronológico de interações com tipo, observação, autor e data/hora.
- `next_follow_up_at`, responsável pelo retorno e conclusão/reagendamento.
- Dashboard com contatos do dia, atrasados e futuros, respeitando permissões.
- Filtros por período, corretor, estágio e situação do retorno.
- Feedback para ações concluídas, falhas, validação e ausência de resultados.

**Critérios de saída**

- O usuário localiza retornos vencidos e registra o resultado do contato.
- Datas seguem a regra de fuso horário definida.
- Reenvios ou ações concorrentes não criam registros duplicados inadvertidamente.

**Comandos Impeccable — histórico e dashboard**

```text
$impeccable shape dashboard-e-fluxo-de-follow-up
$impeccable critique <arquivo-ou-rota-de-follow-up>
$impeccable audit <arquivo-ou-rota-de-follow-up>
$impeccable harden <arquivo-ou-rota-de-follow-up>
$impeccable polish <arquivo-ou-rota-de-follow-up>
```

Execute `context --target <arquivo-ou-rota-de-follow-up>` no início da sessão visual. Use `clarify <alvo>` se rótulos, motivos e próximos passos estiverem ambíguos; use `adapt <alvo>` se o uso em telas menores fizer parte do escopo.

## Fase 4 — Importação CSV

**Objetivo:** migrar planilhas com previsibilidade, validação e rastreabilidade.

**Entregas**

- Fluxo: upload, prévia, mapeamento de colunas, validação e importação.
- Limites de tamanho/linhas, validação de cabeçalhos/tipos e erros identificados por linha.
- Política de duplicidade visível antes da confirmação; distinguir deduplicação de identificação da execução.
- Lotes e transações com resultado resumido e identificador do lote.
- Auditoria de autor, data e contagens; retenção do arquivo limitada à política definida.
- Correção de mapeamentos sem perder a prévia e opção de cancelar antes de gravar.

**Critérios de saída**

- Arquivo inválido não deixa gravação parcial silenciosa.
- O operador vê o impacto previsto antes de confirmar.
- Reexecução do mesmo lote é identificável e tratada conforme a regra definida.

**Comandos Impeccable — assistente de importação**

```text
$impeccable shape assistente-de-importação-csv
```

Execute `context --target <caminho-real>` no início da sessão visual, depois de existir um arquivo/rota que possa ser carregado. Como o projeto ainda não tem aplicação nem UI de importação, não rode `critique` agora: o nome `assistente-de-importação-csv` é apenas o assunto do brief, não um alvo existente.

### Ordem correta nesta fase

1. **Planejar antes do código:** `$impeccable shape assistente-de-importação-csv`. Confirme o brief da experiência Operate: quem importa, tamanho e formato dos CSVs, campos, política de duplicidade, confirmação, cancelamento e estados de erro/sucesso. `shape` termina no brief; não implementa a tela.
2. **Implementar:** construir a rota/arquivos reais e o fluxo de upload → prévia → mapeamento → validação → confirmação. Cobrir prévia vazia, arquivo inválido, erro de mapeamento, processamento e resultado parcial/total. Essa implementação é uma etapa de desenvolvimento do projeto, não um comando Impeccable.
3. **Avaliar depois que houver alvo real:** `$impeccable critique <caminho-real-da-rota-ou-arquivo>`, por exemplo `$impeccable critique src/Modules/Leads/ImportPage.php` somente se esse for o caminho que de fato existir. Substitua o exemplo pelo arquivo/rota real; não use os sinais `<...>` literalmente.
4. **Revisar robustez e qualidade técnica:** `$impeccable audit <caminho-real>` e `$impeccable harden <caminho-real>`; em seguida corrigir os achados.
5. **Fechar a interface:** `$impeccable polish <caminho-real>` sobre o fluxo completo implementado. Use `$impeccable onboard <caminho-real>` apenas se o primeiro uso exigir orientação adicional.

`critique` é um comando da skill usado na conversa com o agente, **não** um verbo aceito pelo executável `impeccable.cmd` instalado no terminal. O launcher local aceita, entre outros, `context` e `detect`; por isso o terminal responde `Unknown command: "critique"`. No terminal, a forma correspondente para contexto é:

```powershell
& 'C:\Users\berna\.agents\skills\impeccable\scripts\impeccable.cmd' context --target <caminho-real>
```

Se ainda não houver arquivo/rota para avaliar, avance com `shape` e implemente a superfície primeiro; não invente uma crítica sem evidência. `onboard <alvo>` é opcional e serve para orientar usuários novos no primeiro uso do fluxo.

## Fase 5 — Backup, restauração e operação do MVP

**Objetivo:** tornar o CRM recuperável e operável por uma pessoa responsável.

**Entregas**

- Backup diário consistente do SQLite, preferencialmente com `VACUUM INTO`, em destino definido e protegido.
- Rotação/retenção e registro de sucesso/falha; cópia fora do host quando a política permitir.
- Restauração documentada e teste mensal em ambiente separado.
- Procedimentos para atualização, migração, usuários e falha do host.
- Monitoramento básico de espaço, disponibilidade, logs e integridade do banco.
- Revisão dos critérios do MVP: CSV, interações, follow-up, auditoria e backup restaurável.

**Critérios de saída do MVP**

- Uma restauração foi demonstrada e validada.
- Há responsável definido por Docker, contas, atualizações e backups.
- Fluxos principais foram percorridos com perfis Admin e Corretor, incluindo falhas comuns.

**Comandos Impeccable — revisão das superfícies entregues**

```text
$impeccable critique <rota-principal-do-mvp>
$impeccable audit <rota-principal-do-mvp>
$impeccable polish <rota-principal-do-mvp>
```

Execute `context --target <rota-principal-do-mvp>` no início da sessão visual. Repita a sequência para as rotas centrais do MVP, não para a implementação de backup em si. Não existe comando Impeccable para backup, Docker ou recuperação de banco.

## Fase 6 — Cadastro de Imóveis

**Objetivo:** relacionar oportunidades a imóveis e permitir busca contextual.

**Entregas**

- Cadastro e edição de imóveis, características, status e observações definidos pelo negócio.
- Busca e filtros paginados; associação/desassociação com múltiplos leads.
- Histórico auditável de mudanças e permissões coerentes com Leads.

**Critérios de saída**

- Corretor autorizado encontra imóveis e os relaciona sem duplicar registros.
- Vínculo N:N pode ser consultado e removido com rastreabilidade.

**Comandos Impeccable — cadastro e busca**

```text
$impeccable shape cadastro-e-busca-de-imóveis
$impeccable critique <arquivo-ou-rota-de-imóveis>
$impeccable audit <arquivo-ou-rota-de-imóveis>
$impeccable adapt <arquivo-ou-rota-de-imóveis>
$impeccable polish <arquivo-ou-rota-de-imóveis>
```

Execute `context --target <arquivo-ou-rota-de-imóveis>` no início da sessão visual. Se o módulo apenas estender componentes e padrões já estabelecidos em Leads, herde o sistema existente e não inicie uma nova identidade visual.

## Fase 7 — Placas

**Objetivo:** controlar placas associadas a imóveis e sua situação operacional.

**Entregas**

- Cadastro de placa, imóvel, localização, responsável, status, datas de instalação/retirada e observações confirmadas.
- Busca de placas ativas, vencidas ou pendentes e histórico de movimentações.
- Permissões e auditoria de alterações.

**Critérios de saída**

- Situação atual e mudanças de uma placa são consultáveis.
- Os campos e regras operacionais foram confirmados antes de fixar o modelo.

**Premissas de primeira implementação (validar com a operação antes do uso em produção)**

- Imóvel obrigatório, ponto/localização em texto livre e responsável vinculado a usuário ativo.
- `pendente` = aguardando instalação; `ativa` = instalada sem validade passada; `vencida` = instalada com validade passada; `retirada` = retirada confirmada. O estado é derivado, não selecionado manualmente.
- Validade é opcional e usa a data local configurada. Instalação e retirada efetivas exigem confirmação textual e autor registrado; retirada só após instalação; não se apagam registros.
- Corretores e administradores consultam e operam placas. Auditoria global permanece restrita ao administrador, e o histórico individual está no detalhe da placa.

**Comandos Impeccable — controle de placas**

```text
$impeccable shape controle-de-placas
$impeccable critique <arquivo-ou-rota-de-placas>
$impeccable audit <arquivo-ou-rota-de-placas>
$impeccable harden <arquivo-ou-rota-de-placas>
$impeccable polish <arquivo-ou-rota-de-placas>
```

Execute `context --target <arquivo-ou-rota-de-placas>` no início da sessão visual. Use `harden` se o fluxo tiver estados de vencimento, perda, transferência, erros ou conflitos de atualização.

## Fase 8 — Relatórios

**Objetivo:** oferecer visão gerencial coerente com os dados e definições da operação.

**Entregas**

- Indicadores aprovados, com definição escrita de cada métrica e período.
- Filtros por data, estágio, corretor e dimensões necessárias.
- Exportação segura e limites para consultas pesadas.

**Critérios de saída**

- Cada indicador pode ser conferido contra registros de origem.
- Acesso aos relatórios segue as permissões definidas.

**Comandos Impeccable — relatórios gerenciais**

```text
$impeccable shape relatórios-gerenciais
$impeccable critique <arquivo-ou-rota-de-relatórios>
$impeccable audit <arquivo-ou-rota-de-relatórios>
$impeccable optimize <arquivo-ou-rota-de-relatórios>
$impeccable polish <arquivo-ou-rota-de-relatórios>
```

Execute `context --target <arquivo-ou-rota-de-relatórios>` no início da sessão visual. `optimize` é pertinente quando consultas, tabelas ou gráficos apresentarem lentidão medida; não é obrigatório sem esse problema.

## Fase 9 — Contratos, agenda e comissões

**Objetivo:** ampliar o produto após validar a operação do CRM central.

**Entregas candidatas**

- Contratos e vínculos com leads/imóveis.
- Agenda e compromissos associados ao histórico de relacionamento.
- Cálculo e acompanhamento de comissões com regras explícitas.

**Critérios de entrada**

- Regras jurídicas/comerciais, papéis, cálculos e histórico foram levantados com responsáveis.
- Cada domínio tem critérios de aceite e plano de migração.
- Gate de entrada: registrar as respostas e aprovações no checklist de [`docs/PHASE9_DISCOVERY.md`](docs/PHASE9_DISCOVERY.md). Enquanto houver regra sem responsável/aprovação, esta fase permanece em descoberta; não criar tabelas, endpoints ou dados de exemplo que presumam regras de negócio.

**Sequenciamento recomendado**

Tratar contratos, agenda e comissões como domínios separáveis. Fechar descoberta, aceite e migração de cada domínio antes de iniciar sua implementação; dependências entre domínios devem ser documentadas pelos responsáveis. A agenda pode reaproveitar o histórico de relacionamento existente, mas não deve alterar o ciclo de follow-up sem decisão explícita.

**Comandos Impeccable — aplicar por módulo e superfície**

```text
$impeccable shape <fluxo-de-contratos-agenda-ou-comissões>
$impeccable critique <arquivo-ou-rota-do-módulo>
$impeccable audit <arquivo-ou-rota-do-módulo>
$impeccable harden <arquivo-ou-rota-do-módulo>
$impeccable polish <arquivo-ou-rota-do-módulo>
```

Execute `context --target <arquivo-ou-rota-do-módulo>` no início da sessão visual. `harden` é essencial para formulário, permissão e cálculo com erro/limite; a lógica de negócio e validações de servidor não são substituídas por essa revisão de interface.

## Comandos condicionais da skill

Use estes comandos somente quando o caso aparecer no projeto:

- `$impeccable document` — registrar em `DESIGN.md` a identidade visual observada em uma interface já implementada. A skill não recomenda criá-lo antes de existir evidência visual.
- `$impeccable extract <alvo>` — extrair tokens/componentes que já se repetem de verdade; não antecipar abstrações.
- `$impeccable clarify <alvo>` — melhorar rótulos, instruções e mensagens quando a linguagem estiver ambígua.
- `$impeccable onboard <alvo>` — primeiro uso e ativação, por exemplo no assistente CSV.
- `$impeccable adapt <alvo>` — adaptar uma interface a tamanhos/dispositivos incluídos no escopo.
- `$impeccable optimize <alvo>` — investigar gargalo de interface medido.
- `$impeccable layout`, `$impeccable typeset`, `$impeccable colorize`, `$impeccable quieter`, `$impeccable bolder`, `$impeccable distill`, `$impeccable animate`, `$impeccable delight` ou `$impeccable overdrive` — melhorias pontuais correspondentes ao problema encontrado; não são etapas fixas do roadmap.
- `$impeccable live` e `$impeccable generate ...` — iteração/variações visuais quando o app web estiver executável e o fluxo de trabalho visual estiver configurado.

## Decisões pendentes para fechar

1. Quantidade de usuários simultâneos e expectativa de crescimento.
2. Permissões detalhadas e visibilidade de leads.
3. Etapas do pipeline, transições e motivos de perda.
4. Regras, fuso e lembretes de follow-up.
5. Política de duplicidade e idempotência de importações CSV.
6. Recuperação do host, retenção e destino externo de backup.
7. Campos obrigatórios de leads, imóveis e interações.

## Dependências e riscos

- A concorrência de escrita do SQLite deve ser acompanhada; reavaliar PostgreSQL se o uso real exceder as premissas.
- O host local é ponto único de falha; backup sem teste de restauração não atende ao requisito de recuperação.
- Permissões, duplicidade e pipeline afetam o esquema e precisam ser definidos cedo.
- Importações precisam de limites, transações e relatórios para evitar dados parciais ou confusão na base.
- Iniciar módulos futuros quando houver necessidade operacional validada.

