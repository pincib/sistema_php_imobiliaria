---
version: 1
slug: "views-leads-import-php"
primary_target: "views/leads/import.php"
related_targets: [
  "src/Modules/Leads/Controllers/LeadImportController.php",
  "src/Modules/Leads/Services/LeadImportService.php",
  "views/leads/import/partials/*.php"
]
---

# Brief de Design — Assistente de Importação de Planilhas (CSV)

<!-- impeccable:surface-brief assistente-de-importação-csv -->

## 1. Job e Audiência
- **Público:** Gestores e administradores da imobiliária migrando bases históricas de leads (planilhas de corretores, campanhas antigas, backups de outros sistemas) para o CRM local.
- **Contexto de uso:** Tarefa de alta responsabilidade. Planilhas legadas contêm imperfeições frequentes (telefones com formatos divergentes, campos em branco, duplicidades internas e externas). O operador precisa de previsibilidade total antes de qualquer gravação no banco de dados.
- **Modo do visitante:** **Operate** (precisão, previsibilidade, controle total do operador, feedback analítico minucioso, reversibilidade e cancelamento explícito a qualquer momento).

## 2. Outcome e Verdade do Produto
- **Ação primária:** Conduzir o processo em 5 etapas claras: Upload ➔ Prévia & Análise ➔ Mapeamento de Colunas ➔ Validação de Tipos & Detecção de Impacto ➔ Confirmação & Importação em Lote.
- **Sucesso operacional:** 
  - O operador sabe com exatidão, antes de confirmar: quantos leads serão criados, quantos serão mesclados/atualizados, quantos serão ignorados e quantas linhas têm erros.
  - Zero gravações parciais silenciosas ou corrupção de dados: importação atômica em transação SQLite gerenciada.
  - Rastreabilidade garantida com identificador exclusivo de lote (ex: `LOTE-20261023-A7B2`), registro de auditoria completo (autor, data/hora, contagens) e hash SHA-256 da planilha original.
- **Verdade local-first:** Todo o processamento ocorre no servidor local sem dependência de internet ou APIs terceiras. O arquivo físico original é mantido temporariamente em staging apenas para permitir correções de mapeamento sem reenvio, sendo expurgado do disco logo após a confirmação ou cancelamento voluntário (ou limpeza após 24h).

## 3. Direção Visual e Estrutura
- **Autoridade visual:** Pico.css operacional, limpo e direto, respeitando a paleta nativa do sistema em modo claro e escuro. Tipografia tabular monoespaçada para amostragem de dados, cabeçalhos e números de linha.
- **Tese de composição e fluxo (Passos 1 a 5):**
  1. **Upload:** Zona com seleção de arquivo CSV, limites visíveis (máximo 5MB / 5.000 linhas) e detecção automática de delimitador (`,` ou `;`).
  2. **Prévia & Amostra:** Tabela inicial com as 3 primeiras linhas lidas para inspeção visual rápida do operador.
  3. **Mapeamento de Colunas:** Grid semântico associando colunas da planilha aos campos do sistema (`name` obrigatório, `phone`, `email`, `notes`, `source`, `user_id` / responsável).
  4. **Validação & Impacto Previsto:**
     - Cartões de impacto: *Novos Leads*, *Duplicidades Identificadas*, *Linhas Inválidas*.
     - Seletor explícito da política de duplicidade:
       - `(o) Mesclar dados e registrar evento no histórico (Recomendado/Padrão)`
       - `( ) Pular registros duplicados (não alterar registros existentes)`
     - Tabela analítica expansível com lista de linhas com problemas e motivo da recusa.
     - Detecção de reexecução de lote por hash SHA-256 com aviso ostensivo se o arquivo já foi importado anteriormente.
  5. **Confirmação & Sumário:**
     - Botão primário com resumo em destaque: `Confirmar e Importar X Leads`.
     - Botão secundário de escape seguro: `Cancelar e Descartar Arquivo` ou `Voltar ao Mapeamento`.
     - Tela de conclusão com identificador do lote, contadores finais consolidados e link direto para visualização da carteira.

## 4. Escopo e Limites
- **Superfície-alvo:** Rota `/leads/import` (`views/leads/import.php` com parciais HTMX em `views/leads/import/partials/`).
- **Controlador e Serviços:** `src/Modules/Leads/Controllers/LeadImportController.php` e `src/Modules/Leads/Services/LeadImportService.php`.
- **Anti-goals:** Sem upload de formatos binários proprietários (.xlsx/.xls) sem conversão prévia para CSV; sem complexidade desnecessária de filas assíncronas assíncronas via Redis (processamento síncrono/transacional rápido para o volume alvo de até 5.000 linhas).

## 5. Estados e Tratamento de Erros
- **Arquivo Inválido / Ilegível:** Mensagem contextual clara informando a incompatibilidade da codificação (esperado UTF-8 ou ISO-8859-1) ou estrutura corrompida.
- **Sem Coluna Obrigatória (Nome):** Bloqueio no passo de validação até que o operador selecione a coluna correspondente ao nome do lead.
- **Reexecução de Mesmo Arquivo:** Alerta semântico visível: *"Este arquivo já foi importado com sucesso em [Data/Hora] pelo usuário [Nome] (Lote [ID])."* com caixa de confirmação para evitar duplicidade acidental.
- **Cancelamento:** Botão de descarte imediato que remove os dados de staging da sessão/disco e retorna o operador à listagem de leads sem deixar resíduos.

## 6. Interação e Acessibilidade
- Indicador visual tipo stepper no topo comunicando claramente o progresso (1. Envio ➔ 2. Mapeamento ➔ 3. Validação ➔ 4. Importação).
- Formulários com navegação fluida por teclado (`Tab`, `Shift+Tab`, `Enter`).
- Rótulos `aria-label` e alertas com `role="alert"` e `aria-live="polite"`.
