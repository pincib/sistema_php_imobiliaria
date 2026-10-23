---
target: controle-de-placas
total_score: 29
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\signboards\\index.php"
target_fingerprint: "sha256:0451b0c0245023bb782299463d9fc987ee8944e4cce882f3ce74731f7900ca78"
target_path: "C:\\Docker\\plataforma_pinciara-teste_02\\views\\signboards\\index.php"
timestamp: 2026-10-23T14-44-05Z
slug: views-signboards-index-php
closed: true
---
# Crítica de design — Controle de placas

**Método:** dual-agent (A: `/root/critique_design` · B: `/root/critique_evidence`)

## Design Health Score

| # | Heurística | Nota | Ponto observado |
|---|---|---:|---|
| 1 | Visibilidade do estado | 3/4 | Resumo e status ajudam; o cadastro pode gerar confirmação genérica sem nota. |
| 2 | Correspondência com o mundo real | 4/4 | Vocabulário e etapas refletem o trabalho com placas em imóveis. |
| 3 | Controle e liberdade | 3/4 | Há voltar/cancelar e filtros; retirada é terminal e não oferece desfazer. |
| 4 | Consistência e padrões | 3/4 | Controles seguem o sistema; confirmação varia entre cadastro, instalação e retirada. |
| 5 | Prevenção de erros | 3/4 | Há validações e limites de data, mas a nota inicial não é obrigatória. |
| 6 | Reconhecimento em vez de memorização | 4/4 | Status, dados do imóvel e histórico estão visíveis no detalhe. |
| 7 | Flexibilidade e eficiência | 2/4 | Busca e paginação ajudam, mas operações são uma placa por vez. |
| 8 | Design estético e minimalista | 3/4 | Interface operacional e contida; composição próxima de CRUD genérico. |
| 9 | Recuperação de erros | 3/4 | Erros de campo preservam dados; erros de operação aparecem após redirecionamento. |
| 10 | Ajuda e documentação | 1/4 | Há exemplos, mas falta explicar como validade determina “vencida”. |
| **Total** |  | **29/40 — Bom** | |

## Veredito de especificidade

A experiência é bem ancorada no fluxo imobiliário: relaciona imóvel, ponto físico, responsável, validade, instalação, retirada e histórico. Sua principal oportunidade é explicar melhor o ciclo operacional; visualmente, a tela ainda usa padrões CRUD genéricos.

O detector retornou zero achados para `views/signboards/index.php`. Isso valida apenas verificações determinísticas do markup; não substitui a revisão visual.

## Impressão geral

A lista permite localizar e comparar placas rapidamente, e o detalhe reúne situação e histórico. A maior lacuna está na diferença entre o que a interface promete registrar e o que exige no cadastro.

## O que funciona

- Os quatro estados e seus totais tornam o inventário fácil de escanear.
- O detalhe reúne imóvel, localização, responsável, datas e histórico cronológico.
- Erros do formulário mantêm os valores preenchidos e mostram mensagens específicas.

## Problemas prioritários

1. **[P1] Confirmação inicial opcional, apesar da promessa de histórico.** O campo de confirmação do cadastro não é obrigatório; vazio, o serviço cria o evento genérico “Cadastro confirmado.” Isso reduz a qualidade probatória do histórico. Torne o campo obrigatório ou deixe explícito que a nota é opcional e que o evento será genérico. **Comando sugerido:** `$impeccable clarify`.
2. **[P1] A busca promete localizar pelo imóvel, mas não consulta o endereço.** O filtro considera localização da placa, observações, código e título do imóvel, além do responsável; não pesquisa endereço, bairro ou cidade. Amplie a consulta ou delimite o rótulo e o placeholder. **Comando sugerido:** `$impeccable harden`.
3. **[P2] Retirada é uma transição terminal sem etapa explícita de revisão.** A retirada preserva o registro, mas a placa não pode ser reativada e o formulário não confirma a intenção antes de encerrar o ciclo. Informe esse efeito junto à ação ou adicione confirmação. **Comando sugerido:** `$impeccable harden`.
4. **[P2] A tabela fica mais larga que a tela em uma faixa sem aviso.** A largura mínima é 48rem, mas a dica de rolagem só aparece até 42rem. Alinhe o breakpoint da dica à largura mínima da tabela. **Comando sugerido:** `$impeccable adapt`.
5. **[P2] O cadastro combina campos essenciais e complementares na mesma etapa visual.** Imóvel, localização e responsável dividem atenção com validade, observações e confirmação. Separe identificação das informações opcionais. **Comando sugerido:** `$impeccable distill`.

## Alertas por perfil

- **Alex, usuário experiente:** opera e consulta uma placa por vez; com 20 registros por página, inventários grandes podem exigir navegação repetitiva.
- **Jordan, pessoa nova na ferramenta:** “vencida” depende de validade opcional. Sem explicação contextual, pode não saber por que uma placa instalada mudou de estado.
- **Sam, uso por teclado ou baixa visão:** rótulos, escopos da tabela e foco visível aparecem no código; contraste e larguras intermediárias não foram conferidos visualmente.

## Observações menores

O estado vazio orienta o próximo passo. A descrição do formulário pode sugerir que toda placa tem validade, embora o campo seja opcional. Não houve renderização autenticada; contraste e composição permanecem sem verificação visual.

## Perguntas que podem orientar melhorias

- A equipe precisa registrar uma confirmação específica no cadastro, ou basta identificar autor e data?
- A busca deve encontrar imóveis por endereço, bairro e cidade?
- A retirada deve ser sempre definitiva, ou a operação precisa permitir reinstalação da mesma placa?
