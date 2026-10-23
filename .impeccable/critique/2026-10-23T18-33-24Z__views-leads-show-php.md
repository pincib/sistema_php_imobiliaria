---
target: ficha do lead / correspondência de imóveis
total_score: 24
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\leads\\show.php"
target_fingerprint: "sha256:ffedd6228146608f095eba7a355f6479f1d0b0c2e8f144d290db4f7c56088137"
target_path: "C:\\Docker\\plataforma_pinciara-teste_02\\views\\leads\\show.php"
timestamp: 2026-10-23T18-33-24Z
slug: views-leads-show-php
closed: true
---
## Saúde de design

| # | Heurística | Nota | Observação principal |
|---|---|---:|---|
| 1 | Visibilidade do estado do sistema | 3/4 | Estágio, follow-up e motivos das sugestões são visíveis; feedback após salvar não pôde ser observado. |
| 2 | Correspondência com o mundo real | 3/4 | Vocabulário de corretagem, valores em reais e canais de contato são familiares. |
| 3 | Controle e liberdade | 3/4 | Preferências, vínculos e follow-ups são editáveis; desvincular não oferece desfazer visível. |
| 4 | Consistência e padrões | 2/4 | Controles Pico são familiares, mas estilos e hierarquia variam ao longo da ficha. |
| 5 | Prevenção de erros | 3/4 | Campos obrigatórios, motivo de perda e confirmação antes de excluir ajudam a evitar erros. |
| 6 | Reconhecimento em vez de lembrança | 3/4 | Dados e justificativas ficam na tela, embora a ficha longa exija bastante rolagem. |
| 7 | Flexibilidade e eficiência | 2/4 | Ações rápidas ajudam, mas a ficha depende de fluxos sequenciais e não evidencia atalhos. |
| 8 | Estética e minimalismo | 2/4 | A contenção visual favorece leitura; artigos e tabelas sucessivos tornam a tela extensa. |
| 9 | Recuperação de erros | 2/4 | Há validações, mas não foi possível confirmar feedback persistente; não há desfazer aparente para ações destrutivas. |
| 10 | Ajuda e documentação | 1/4 | Há instruções curtas em linha, sem ajuda contextual mais ampla. |
| **Total** |  | **24/40 — Aceitável (60%)** | A estrutura funcional está presente; hierarquia e eficiência são os maiores ganhos possíveis. |

## Veredito de especificidade

A ficha tem conteúdo próprio de um CRM imobiliário: histórico auditável, estágio comercial, follow-up e explicações regionais/de preço. A nova separação entre **Imóveis vinculados** e **Imóveis relacionados** deixa claro que uma recomendação não é ainda uma relação confirmada. A ação **Vincular** mantém a decisão com o corretor.

A composição geral, porém, é uma longa sequência de artigos, formulários e tabelas com aparência operacional genérica. O conteúdo é específico do produto; a hierarquia visual ainda não destaca suficientemente a próxima ação do corretor.

**Varredura determinística:** o detector encontrou **0 achados** em `views/leads/show.php`. Isso não detecta os problemas de hierarquia, densidade e extensão da página observados na análise de UX. Nenhum falso positivo foi reportado.

**Inspeção visual:** a rota redirecionou para a tela de login. A API de navegador disponível não permite injeção mutável; portanto, a ficha não foi inspecionada visualmente e não há overlay confiável para mostrar.

## Impressão geral

A ficha contém os dados e operações importantes para conduzir um lead, e a nova área de sugestões tem uma fronteira de confiança clara. A principal oportunidade é ajudar o corretor a encontrar o próximo passo sem percorrer uma página longa nem tratar todas as ações como igualmente urgentes.

## O que funciona bem

- A distinção entre vínculo confirmado e sugestão automática evita alterar a relação sem ação explícita do corretor.
- Cada sugestão informa o motivo regional e de preço, reduzindo o caráter de “caixa-preta” da ordenação.
- O follow-up mostra data, situação, responsável e objetivo; isso conversa diretamente com a rotina de retorno comercial.

## Problemas prioritários

1. **[P1] A ficha é longa e coloca tarefas de importância diferente em sequência.** Pipeline, follow-up, interações, vínculos e outras áreas competem pela atenção. Um corretor que entra para retornar um contato pode precisar rolar bastante até localizar a ação. **Correção:** destacar no topo um resumo de próxima ação e follow-up; adicionar âncoras internas para as seções principais. Comando sugerido: `$impeccable layout`.
2. **[P1] Há muitos controles e opções no mesmo percurso.** A ficha pode exibir até cinco ações no cabeçalho; seletores de interação e relação oferecem várias opções; o seletor de estágio também pode crescer. **Correção:** dar precedência visual a uma próxima ação contextual e agrupar opções secundárias por tarefa, mantendo os controles padrão. Comando sugerido: `$impeccable distill`.
3. **[P2] As tabelas de imóveis são densas e largas.** A tabela de sugestões tem seis colunas; a de vínculos, oito. Em telas estreitas, o usuário precisa rolar horizontalmente e pode perder o contexto da ação. **Correção:** preservar código, imóvel, região, preço, justificativa e ação como informação prioritária; recolher tipo, notas e data em layout adaptável ou detalhes. Comando sugerido: `$impeccable adapt`.
4. **[P2] Desvincular não tem recuperação visível.** A confirmação nativa previne um clique acidental, mas depois da remoção não há ação de desfazer evidente na ficha. **Correção:** após desvincular, oferecer retorno rápido que refaça a associação, preservando o tipo e as notas registrados. Comando sugerido: `$impeccable harden`.

## Alertas por persona

- **Alex, corretor experiente:** encontra ações úteis, mas precisa navegar por uma ficha extensa; não há atalhos ou ações em lote evidentes para uso repetido.
- **Jordan, corretor novo:** a explicação da sugestão ajuda, mas termos como “Pipeline” e tipos de relação podem exigir interpretação. Os campos de preferências ficam recolhidos em “Preferências de busca”.
- **Sam, teclado/leitor de tela:** os grupos de imóveis têm títulos acessíveis e os formulários usam rótulos. As tabelas horizontais e a ausência de confirmação acessível de sucesso/recuperação merecem verificação renderizada; foco e contraste não foram verificáveis sem acesso à ficha.

## Observações menores

- A dica sobre correspondência por cidade aparece em texto pequeno perto das preferências; pode ficar separada da decisão quando a seção está fechada.
- A tabela de vínculos inclui notas e data, úteis para auditoria, mas secundárias para leitura rápida.
- A nova interface oferece explicações úteis de preferência inferida, porém essa origem poderia continuar visível quando o formulário de preferências está recolhido.
