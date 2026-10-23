---
target: views/properties/index.php
total_score: 23
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views\\properties\\index.php"
target_fingerprint: "sha256:ea5c4dcf6a07e07badf306b5ef7ac35160d4d51439fad610e730d88f339dd9dd"
target_path: "C:\\Docker\\plataforma_pinciara-teste_02\\views\\properties\\index.php"
timestamp: 2026-10-23T14-24-11Z
slug: views-properties-index-php
closed: true
---
# Crítica de design — Imóveis

**Veredito de especificidade:** a superfície tem boa aderência ao trabalho de uma imobiliária: reúne inventário, código, localização, preço, status e leads relacionados. A busca por nome de lead também faz sentido nesse contexto. Os padrões visuais, porém, ainda são genéricos de um CRM, e o formulário não organiza claramente os dados que a equipe precisa preencher.

## Notas de usabilidade

| # | Heurística | Nota | Motivo principal |
|---|---|---:|---|
| 1 | Visibilidade do estado do sistema | 2/4 | A lista mostra quantidade e página; o formulário apresenta erros de campo, mas o código analisado não confirma estados de salvamento ou sucesso. |
| 2 | Correspondência com o mundo real | 3/4 | Código, tipo, preço, endereço, características e observações são familiares à operação imobiliária. |
| 3 | Controle e liberdade | 3/4 | Há links de retorno e cancelamento, além de paginação; não há limpeza explícita dos filtros nem desfazer alterações. |
| 4 | Consistência e padrões | 2/4 | A busca por cidade usa seleção na lista, mas cadastro e edição usam campo de texto; a ficha exibe tipo e status diretamente, diferentemente dos rótulos da lista e do formulário. |
| 5 | Prevenção de erros | 2/4 | Código e título são obrigatórios e alguns campos têm limites; preço é texto sem orientação clara de formato. |
| 6 | Reconhecimento em vez de memorização | 3/4 | Campos têm rótulos e a busca contextual oferece exemplos; características permanecem em texto livre. |
| 7 | Flexibilidade e eficiência | 2/4 | Busca e filtros ajudam a localizar registros, mas não há atalhos ou ações em lote visíveis. |
| 8 | Design estético e minimalista | 3/4 | A interface é contida e orientada à tarefa. A ficha concentra detalhes e associação com leads na mesma tela. |
| 9 | Reconhecimento e recuperação de erros | 2/4 | Há validação inline para alguns campos; não há evidência de resumo de erros ou foco no primeiro campo inválido. |
| 10 | Ajuda e documentação | 1/4 | Há exemplos e breve orientação no formulário, mas não ajuda contextual para status ou tipo de relação. |
| **Total** |  | **23/40** | **Aceitável; organização e consistência precisam de atenção.** |

## Carga cognitiva

**3 de 8 falhas — carga moderada.** Na ficha, detalhes e associação de leads disputam foco. O formulário tem grids, mas falta nomear os grupos de campos. Quando a busca de leads retorna muitas opções, todas aparecem como caixas de seleção. A hierarquia da listagem funciona razoavelmente: busca e filtros antecedem a tabela. A seleção de leads é revelada depois da busca.

## Jornada emocional

A pessoa chega orientada pelo título “Imóveis” e pela ação de cadastro. Busca e tabela dão controle para localizar um registro. No cadastro, a falta de seções nomeadas pode causar hesitação sobre ordem e propósito dos campos. Na ficha, a associação pode competir com a consulta. O código analisado não confirma como a interface comunica sucesso após salvar.

## O que funciona bem

- Busca contextual e filtros familiares: código, endereço, bairro, cidade ou lead.
- Estados vazios distinguem base sem imóveis de busca sem correspondências e sugerem o próximo passo.
- Código e título destacam a identidade do registro; localização, preço e status ajudam na triagem.

## Problemas prioritários

1. **[P1] A ficha mistura consulta do imóvel e associação de leads.** Quem só quer revisar preço, endereço e características divide atenção com outra tarefa; a seleção pode crescer. Considerar uma seção recolhível ou ação dedicada. Comando sugerido: `$impeccable distill`.
2. **[P1] O formulário é longo e não tem grupos nomeados.** Código e título, classificação e preço, localização, características e observações aparecem sem títulos de seção. Adicionar grupos discretos para identificação, classificação, localização e detalhes. Comando sugerido: `$impeccable layout`.
3. **[P2] Cidade usa controles diferentes entre cadastro e busca.** Não fica claro se o corretor pode incluir novas cidades ou escolher apenas existentes. Alinhar o comportamento mantendo entrada de novas cidades compatível com o filtro. Comando sugerido: `$impeccable clarify`.
4. **[P2] Características críticas ficam em texto livre.** Como o brief confirmado mantém os campos atuais, preservar o texto, usar orientação consistente e apresentar os atributos em linhas legíveis na ficha. Comando sugerido: `$impeccable clarify`.

## Alertas por perfil

- **Alex, corretor experiente:** há paginação e filtros, mas a tabela só oferece “Editar” por registro; não há ações em lote visíveis.
- **Sam, teclado e leitor de tela:** os controles usam elementos HTML comuns e a paginação tem rótulo acessível. Estados de validação não parecem associados aos campos por `aria-describedby`, e não há evidência de foco nos erros ou anúncio do sucesso.

## Observações menores

- A busca considera bairro e cidade, enquanto a tabela exibe ambos.
- O preço usa `inputmode="decimal"` e exemplo, mas não esclarece se se espera valor aproximado ou exato.
- A coluna “Leads” indica quantidade sem esclarecer se permite abrir a relação.

## Evidências do detector e navegador

Detector Impeccable: nenhum achado em `index.php`, `form.php` ou `show.php`. Nenhuma regra de ignore estava configurada. A rota `/properties` redirecionou para `/login` na tentativa de inspeção em navegador; a API disponível não permitiu injetar o overlay. O servidor temporário foi encerrado.
