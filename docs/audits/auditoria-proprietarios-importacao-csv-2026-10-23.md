# Auditoria técnica: proprietários e importação CSV

**Data:** 23/10/2026  
**Escopo:** erro de `/owners` e importação de imóveis, proprietários e leads.  
**Fora do escopo:** avaliação visual, layout, aparência ou UX.

## Resultado

O erro da página de proprietários foi reproduzido nos logs do contêiner. A causa era o banco persistente estar na migração 010 enquanto o código implantado dependia das migrações 011–014. A ausência da tabela `owners` causava HTTP 500; a ausência de `postal_code` também impedia a gravação de imóveis pelo importador.

A importação dos três conjuntos já está implementada no código. Depois de aplicar as migrações, o fluxo dos arquivos reais foi executado em uma cópia isolada do banco: imóveis foram gravados e proprietários foram associados. Nenhum CSV foi importado no banco ativo.

## Baseline e limites da auditoria

- O Git tem um único commit (`d855f9d`, `feat: first commit`), igual a `origin/main`. Não há histórico anterior confiável para atribuir os problemas a uma alteração recente.
- A workspace já tinha seis arquivos modificados antes desta auditoria (`bin/test-restore.php`, `docs/RESTORATION.md`, `src/Backup/BackupService.php`, `src/Modules/Leads/Services/LeadImportService.php` e dois testes). Foram preservados; não há evidência para atribuir o erro de `/owners` a essas mudanças.
- O contêiner ativo é `pinciara_crm_app`, porta 8086, com `./storage` persistente. O erro e o schema foram conferidos nesse banco.
- O backup e as migrações modificaram o banco ativo de forma aditiva. Não alterei o código nem gravei os CSVs de origem no banco ativo.

## Achado confirmado

### AUD-001 — Schema do banco ativo estava atrás do código implantado

- **Categoria:** Edge / dados e infraestrutura.
- **Gravidade:** Alta — bloqueava a página de proprietários e a importação de imóveis no runtime.
- **Confiança:** Alta; causa e recuperação verificadas.
- **Classificação de escopo:** bloqueador da missão; defeito de estado operacional, sem baseline para atribuir a um commit recente.
- **Impacto:** `GET /owners` falhava com `PDOException: no such table: owners`. O `INSERT` de imóveis usa `properties.postal_code`, coluna ausente no mesmo banco. Proprietários e vínculos também não podiam ser importados.
- **Evidência:** `src/Modules/Owners/Controllers/OwnersController.php:25-28` consulta `owners` e `owner_properties`; `src/Modules/Leads/Services/MultiDatasetImportService.php:97-104` lê/grava colunas e tabelas acrescentadas por migrações posteriores; `database/migrations/011_create_owners_and_whatsapp_logs.sql`, `012_validate_whatsapp_contact_references.sql` e `013_csv_source_preservation.sql` fornecem essas estruturas. Os logs Apache/Slim mostravam o erro de tabela inexistente. Antes da correção, o banco registrava somente 001–010; `owners`, `owner_properties` e `import_source_rows` não existiam e `properties.postal_code` estava ausente.
- **Condição de disparo:** iniciar o código atual com o arquivo SQLite persistente sem executar as migrações pendentes.
- **Reprodução:** abrir `http://localhost:8086/owners`; reproduzido nos logs do contêiner. A inspeção do schema confirmou a tabela ausente. O caminho de importação de imóveis referenciava `postal_code`, também ausente.
- **Correção aplicada:** gerei backup consistente sem rotação e executei `docker compose exec -T app php bin/migrate.php`. Foram aplicadas 011, 012, 013 CSV, 013 campos de imóvel e 014. A tabela de proprietários, a pivot e as colunas agora existem.
- **Verificação:** backup `storage/backups/backup_2026-10-23_205338.sqlite`, integridade `ok`; migração terminou com `[SUCESSO]`; banco registra 001–014; health check `[OK]`; `GET /owners` autenticado retornou 200 e renderizou a listagem numa cópia consistente do banco atualizado.
- **Dependências:** em outros ambientes, executar backup e migração no arquivo SQLite que o serviço realmente usa. O `docker-compose.yml` persiste `./storage`; atualizar o código sem migrar esse volume reproduz o problema.

## Caminhos causais e invariantes verificados

| Invariante | Entrada e caminho | Evidência/resultado |
|---|---|---|
| Só usuários administrativos acessam importação | `/leads/import` → rotas protegidas em `src/Application.php:149-155` → `LeadImportController` | Rotas registradas no grupo administrativo e POSTs usam CSRF. O teste de workflow cobre sessão e autorização para importação. |
| Preview e análise não gravam entidades | upload ou fonte empacotada → staging → detecção/mapeamento → `analyze()` | Dry-run dos CSVs reais executado; entidades não foram gravadas no banco ativo. |
| Imóvel é importado antes do vínculo de proprietário | CSV de imóveis → `properties` → CSV de proprietários resolve `Ref. imóvel` → `owners` + `owner_properties` | A análise de proprietário rejeita referências sem imóvel correspondente. O teste isolado respeitou a ordem e confirmou os vínculos. |
| Escrita do lote é atômica e preserva origem | `confirm()` → `MultiDatasetImportService::execute()` → transação, lote, entidade, vínculo e `import_source_rows` | Fluxo real executado em snapshot isolado: 264 imóveis e 264 vínculos criados; sem gravação no banco ativo. |
| Código e schema ativo estão alinhados | Controllers/importador → arquivo SQLite do volume Docker → migrações | Antes havia drift comprovado; migrações aplicadas e schema conferido após a correção. |

## Dry-run dos arquivos CSV reais

O primeiro passo de cada arquivo foi validado sem importar os dados no banco ativo. Para testar as gravações ponta a ponta, usei uma cópia consistente e descartável do banco.

| Arquivo | Detecção e resultado | Observação |
|---|---|---|
| `Imóveis-20261020.csv` | Detectado como `properties`; 266 linhas, 264 prontas e 2 inválidas. No snapshot, 264 imóveis foram gravados. | Uma linha tem valor monetário inválido e outra está sem logradouro. São linhas de origem para revisar, não falha confirmada do parser. |
| `Planilha sem título - Proprietários_por_imóveis-20261023 (2).csv` | Detectado como `owners`; antes de importar imóveis, todas as 268 linhas apontavam referências inexistentes no banco ativo. Depois dos imóveis no snapshot: 264 prontas, 4 referências não encontradas, 144 duplicatas; gravação no snapshot criou 120 proprietários e 264 vínculos. | A ordem necessária é importar imóveis antes de proprietários. As 4 referências não foram alteradas ou inferidas. |
| `contacts_filtrados.csv` | Detectado como `leads`; 1.768 linhas, 1.145 prontas, 623 com erro e 32 duplicatas. | Entre os erros, 598 foram classificados como nome contendo apenas código/telefone; 22 como telefone inválido; 3 sem nome. É necessário revisar essas linhas e o mapeamento na prévia antes de confirmar. |

Os cabeçalhos dos arquivos fornecidos coincidem com as regras atuais de detecção em `src/Modules/Leads/Services/MultiDatasetImportService.php:23-39`. Portanto, a ausência de um seletor manual de dataset para cabeçalhos diferentes não explica este incidente. Arquivos com nomes de colunas distintos podem ser classificados como leads; sem um exemplo concreto com cabeçalhos alternativos, isso fica como limitação condicional, não como defeito confirmado neste relatório.

## Alteração/operação necessária em outros ambientes

Use o migrador existente; não crie as tabelas manualmente nem importe proprietários antes dos imóveis. Faça backup antes de migrar o volume persistente:

```powershell
docker compose exec -T app php bin/backup.php --no-rotation
docker compose exec -T app php bin/migrate.php
docker compose exec -T app php bin/health-check.php
```

Depois, acesse `/leads/import`, prepare e confira o CSV de imóveis, confirme a importação, e só então prepare o CSV de proprietários. Revise e corrija as linhas inválidas e referências não encontradas antes da confirmação. O relatório não executou essa importação no banco ativo.

Para aceitar planilhas com cabeçalhos não padronizados, a alteração recomendada é permitir seleção explícita de tipo (`leads`, `properties`, `owners`) no upload, manter a detecção atual como sugestão, validar os campos obrigatórios por tipo e cobrir cabeçalhos alternativos. Essa alteração foi implementada no adendo abaixo. A necessidade de mapear formatos específicos ainda depende das planilhas adicionais que serão usadas.

## Skills e comandos para repetir o trabalho

No Codex, invoque a skill pelo marcador `$audit-code` e descreva missão, caminhos e limite da auditoria; a skill não fornece um comando shell próprio. Exemplo:

```text
$audit-code
Audite /owners e a importação CSV (leads, imóveis e proprietários), seguindo o contrato técnico, sem avaliar layout/UX. Verifique Git, logs, migrações, dry-run e testes. Corrija apenas falhas reproduzidas e documente limitações.
```

Para carregar o contexto do projeto/superfície antes de trabalho funcional de interface, use o script Impeccable no diretório raiz do projeto:

```powershell
& 'C:\Users\berna\.agents\skills\impeccable\scripts\impeccable.cmd' context
& 'C:\Users\berna\.agents\skills\impeccable\scripts\impeccable.cmd' context --target views/leads/import.php
```

Quando houver mudança funcional nessa superfície, os comandos de skill listados no roadmap são `$impeccable audit <alvo-real>` e `$impeccable harden <alvo-real>`. Não usar `critique`, `polish` ou outras etapas visuais para esta auditoria técnica.

## Verificações finais

- `composer test` — **aprovado:** 101 testes, 529 assertions.
- `docker compose exec -T app php bin/health-check.php` — **aprovado:** banco, integridade, backup recente, permissões e logs OK.
- `docker compose exec -T app php bin/migrate.php` — **aprovado:** 5 migrações pendentes aplicadas.
- GET autenticado `/owners` contra cópia do banco atualizado — **aprovado:** HTTP 200 e conteúdo da listagem renderizado.
- Dry-run dos três CSVs reais — **executado:** detecção e análise concluídas; contagens e dados inválidos acima.
- Importação de imóveis e proprietários — **aprovada em snapshot descartável:** 264 imóveis, 120 proprietários, 264 vínculos; não foi executada no banco de trabalho.
- HTTP no runtime ativo sem sessão — redirecionou para `/login` (302), comportamento esperado; não foi usado como prova de sessão autenticada no runtime.

## Pendências e limites

- Corrigir/revisar as 2 linhas inválidas do CSV de imóveis e as 4 referências de imóvel não encontradas no CSV de proprietários.
- Revisar as 623 linhas inválidas do CSV de leads antes de importar; o conteúdo foi tratado como dado e não reproduzido neste relatório.
- A importação real no banco ativo não foi confirmada nem executada; apenas schema/migrações foram alterados nesse banco.
- Cabeçalhos alternativos aos arquivos de origem não foram fornecidos para reproduzir a limitação de detecção automática.
- A ausência de histórico Git anterior impede classificar os defeitos como introduzidos pelo commit inicial ou localizar a regressão original.

## Adendo de implementação — seleção de tipo no upload

Após a auditoria, o upload passou a oferecer a seleção de contatos/leads, imóveis ou proprietários nos dois caminhos (arquivo enviado e CSV empacotado). A detecção pelos cabeçalhos continua como sugestão; quando não há cabeçalhos reconhecidos, o modo automático interrompe o envio e pede a seleção manual. O mapeamento valida no servidor os índices das colunas e exige os campos obrigatórios do tipo antes de avançar para o dry-run.

Foram adicionados testes de fluxo para cabeçalhos alternativos de imóveis e proprietários, rejeição de mapeamento obrigatório ausente e recusa de detecção automática sem evidência. Verificação após a alteração: `composer test` aprovado — 105 testes, 547 assertions; `php -l` nos arquivos alterados e `git diff --check` aprovados. Os testes usaram banco isolado e não importaram dados no banco ativo.
