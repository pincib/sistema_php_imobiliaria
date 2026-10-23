---
target: views/leads/import.php
total_score: 26
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\leads\\import.php"
target_fingerprint: "sha256:6606bb025fcb94c805381ce039743f19ec4ee3e8b20a38877f7d54dbeca0e302"
target_path: "C:\\Docker\\plataforma_pinciara-teste_02\\views\\leads\\import.php"
timestamp: 2026-10-23T18-56-56Z
slug: views-leads-import-php
closed: true
---
# Critique — Importação CSV

## Design Health Score
| # | Heurística | Nota | Principal achado |
|---|---|---:|---|
| 1 | Visibilidade do estado do sistema | 3/4 | Stepper, prévia e contagens dão contexto; mensagens dinâmicas não têm anúncio aria-live explícito na view. |
| 2 | Compatibilidade com o mundo real | 3/4 | Usa termos de CSV e operação imobiliária; a origem dos arquivos disponíveis no projeto pode não ser óbvia. |
| 3 | Controle e liberdade | 3/4 | É possível voltar, cancelar e corrigir o mapeamento; cancelar descarta o staging. |
| 4 | Consistência e padrões | 3/4 | Controles conhecidos e progressão clara; alguns rótulos e etapas divergem do brief. |
| 5 | Prevenção de erros | 3/4 | Há limites, campos obrigatórios e detecção de reexecução; lotes extensos tornam a confirmação difícil de revisar. |
| 6 | Reconhecimento em vez de memorização | 3/4 | Cabeçalhos, amostra e opções de mapeamento ficam visíveis; a quantidade de opções depende do CSV. |
| 7 | Flexibilidade e eficiência | 2/4 | O fluxo é guiado e seguro, mas mapear muitos cabeçalhos continua trabalhoso. |
| 8 | Estética e minimalismo | 2/4 | A validação concentra cartões, política, alertas e tabelas; estilos inline extensos podem enfraquecer a consistência. |
| 9 | Recuperação de erros | 2/4 | Os motivos aparecem, mas falta um caminho claro para corrigir a planilha e retomar sem perder o trabalho. |
| 10 | Ajuda e documentação | 2/4 | Limites e modelo ajudam no início; falta orientação contextual nas decisões posteriores. |
| **Total** | | **26/40** | **Aceitável; há melhorias relevantes para tornar a revisão de lotes mais segura e clara.** |

## Especificidade do design
O fluxo é específico ao CRM: prévia CSV, mapeamento, duplicidades, impacto e identificação do lote. A estrutura está alinhada ao trabalho de migração, embora o acabamento se apoie em padrões operacionais genéricos do Pico.css.

**Detector:** impeccable detect --json views/leads/import.php retornou [] — nenhum achado determinístico, portanto sem regras, locais ou falsos positivos a relatar.

**Evidência visual:** uma aba nova exibiu a tela autenticada e o stepper de quatro etapas. Uma verificação sem sessão recebeu redirecionamento para login. Não foram inseridas credenciais. A API de navegador disponível não permite injetar scripts, então não há overlay visual; nenhum servidor auxiliar foi iniciado.

## Impressão geral
A interface transmite cuidado antes de gravar dados, especialmente com amostra, validação e contagens. O trecho mais difícil é a etapa de impacto: o operador precisa compreender duplicidades e exceções enquanto vários blocos e tabelas competem pela atenção. A recuperação de erros também não fica tão explícita quanto a prevenção.

## O que funciona
- A amostra e os cabeçalhos ajudam a reconhecer a origem dos dados antes de avançar.
- As contagens distinguem registros novos, repetidos e inválidos antes da gravação.
- Voltar, cancelar e corrigir o mapeamento oferecem saídas para um fluxo de alto impacto.

## Prioridades
1. **P1 — Alinhar o stepper ao fluxo real.** A view apresenta quatro etapas e combina prévia com mapeamento; o brief fala em cinco. Isso pode deixar o progresso pouco fiel ao trabalho executado.
2. **P1 — Explicitar a retomada de erros.** A tela mostra linhas e motivos, mas não orienta claramente como corrigir a fonte e reiniciar sem refazer o mapeamento.
3. **P2 — Organizar a etapa de validação.** Separar impacto, exceções e confirmação ajudaria a revisar cada decisão antes de gravar.
4. **P2 — Explicar a origem dos CSVs disponíveis.** Diferenciar arquivos do projeto de arquivos enviados pelo operador pode evitar selecionar uma fonte errada.
5. **P2 — Revisar anúncios assistivos.** Há alertas semânticos, mas feedback de mudança de etapa e resultado merece validação com leitor de tela e teclado.

## Carga cognitiva
A maior concentração ocorre na validação, que reúne seis cartões de impacto, política de duplicidade, alertas, listas de erros e duplicidades e ações finais. Não identifiquei mais de quatro opções simultaneamente visíveis no primeiro passo. No mapeamento, cada seletor pode ter muitos cabeçalhos; a contagem depende do arquivo e não foi confirmada visualmente.

Há duas divergências em relação ao brief da superfície: quatro etapas na interface, em vez de cinco, e amostra de cinco linhas, em vez de três.

## Jornada emocional
Prévia e validação reduzem o receio inicial de perda de dados. O ponto de maior tensão é decidir como tratar repetidos e confirmar um lote grande. A tela de conclusão ajuda com o identificador e as contagens, mas a transição entre exceções e confirmação poderia dar mais segurança.

## Personas
- **Jordan, primeira utilização:** pode não saber por que existem CSVs já disponíveis nem como escolher um cabeçalho quando ele não corresponde exatamente ao campo do CRM.
- **Sam, navegação assistiva:** deve conseguir acompanhar mudanças de etapa e mensagens sem depender apenas da apresentação visual.
- **Alex, operador experiente:** precisa repetir vários lotes e mapear colunas; faltam atalhos claros para reduzir esse trabalho repetitivo.

## Observações menores
- O fluxo é descrito como importação de leads em alguns trechos, embora também inclua imóveis e proprietários.
- Os rótulos variam entre “Novos”, “Inserções” e “Criados”.
- O modelo CSV pode ajudar a evitar erros de formato, mas sua aplicabilidade aos três tipos de arquivo precisa ficar clara.
