---
target_identity: "file:C:\\Docker\\plataforma_pinciara-teste_02\\views-auth-login-php"
timestamp: 2026-10-22T19-46-18Z
slug: views-auth-login-php
closed: true
---
Method: dual-agent (A: 09c93ef6-e65d-4d36-b8d3-84fa739507d7 · B: 13a3cdc9-d0c8-40b9-b634-5e0f8a62aedf)

# Design Critique: Fluxo de Login e Acesso
**Alvo:** `views/auth/login.php` & `views/layouts/auth.php`  
**Modo:** `Operate`  

## Design Health Score

| # | Heurística de Nielsen | Nota (0-4) | Diagnóstico Concreto na Superfície |
|---|-----------------------|:----------:|------------------------------------|
| 1 | Visibilidade do Status do Sistema | 2 | Alertas flash existem, mas falta feedback de submissão (`aria-busy`) e indicador de status da rede local. |
| 2 | Compatibilidade com o Mundo Real | 3 | Vocabulário correto, porém linguagem corporativa excessivamente formal para imobiliária ágil. |
| 3 | Controle e Liberdade do Usuário | 2 | Sem opção de revelar senha digitada; campos são limpos cegamente após erro. |
| 4 | Consistência e Padrões | 3 | Anatomia básica correta, mas com excesso de estilos inline e quebra de tokens de tema. |
| 5 | Prevenção de Erros | 2 | HTML5 `required` presente, mas sem aviso de Caps Lock ativo nem bloqueio de duplo clique. |
| 6 | Reconhecimento em vez de Recordação | 1 | **Grave:** E-mail não é preservado após falha de senha, exigindo redigitação completa. |
| 7 | Flexibilidade e Eficiência de Uso | 3 | Bom fluxo de teclado (`autofocus`, `Tab`, `Enter`), mas sem atalhos operacionais rápidos. |
| 8 | Design Estético e Minimalista | 3 | Foco limpo sem marketing, mas com duplo título redundante e sombra cromática artificial (`dark-glow`). |
| 9 | Ajuda no Reconhecimento de Erros | 2 | Mensagens claras, porém estilos de alerta sem suporte a tema escuro e sem `aria-invalid`. |
| 10| Ajuda e Documentação | 1 | Nenhuma orientação sobre como recuperar acesso físico em caso de senha esquecida. |
| **Total** | | **22/40** | **Precisa de Refinamento (Needs Work)** |

## Design Specificity Verdict

- **LLM Assessment**: A interface resolve a mecânica funcional básica, mas comporta-se como um formulário genérico de framework em vez de um sistema imobiliário local-first. Falta identidade visual da Pinciara, microcópia acolhedora da rotina comercial e orientação explícita sobre a governança de senhas no escritório.
- **Deterministic Scan**: O scanner automatizado identificou 1 alerta de severidade média (`dark-glow`) no layout `views/layouts/auth.php:31` devido ao uso de estilo inline injetando `--pico-card-box-shadow` com matiz azulado artificial (`#8191b5`).
- **Visual Overlays**: Inspeção executada via motor headless Chromium local; sobreposição interativa dispensada em ambiente não gráfico.

## Overall Impression
Uma tela funcional, limpa e ágil, mas que ainda carrega traços de rascunho de código: estilos inline, perda de e-mail ao errar a senha, quebra de contraste no tema escuro e ausência de orientação ao corretor sobre como obter suporte na imobiliária.

## What's Working
1. **Fluxo de Teclado Rápido**: `autofocus` funcional, ordem sequencial de tabulação e submissão ágil via `Enter`.
2. **Semântica e Autenticação Segura**: `autocomplete="email"` e `autocomplete="current-password"`, proteção CSRF ativa e sem dependências externas.
3. **Foco e Zero Distração**: Ausência total de banners, ilustrações vazias ou botões de login social, mantendo foco no trabalho.

## Priority Issues
1. **Preservação de E-mail**: Repassar o e-mail digitado de volta ao formulário em caso de falha de login.
2. **Tema Escuro nos Alertas**: Corrigir classes `.alert-*` para respeitar variáveis semânticas do Pico.css sob `[data-theme="dark"]`.
3. **Remoção do Duplo Título e Estilos Inline**: Eliminar redundância de `h1` e `h2`, retirando a sombra azulada artificial (`dark-glow`) e centralizando estilos no `app.css`.
4. **Prevenção e Feedback**: Adicionar estado `aria-busy="true"` ao submeter o formulário e alternador de visualização de senha.
5. **Microcópia de Suporte Local**: Inserir instrução clara no rodapé sobre contato com o administrador para senhas ou novos acessos.

## Persona Red Flags
- **Corretor**: Perde o e-mail digitado ao errar a senha em meio a um atendimento corrido; falta de toggle de senha induz erros repetidos em notebooks.
- **Administrador**: Recebe interrupções frequentes de corretores perguntando como recuperar acesso, pois a tela não documenta a governança física local.

## Minor Observations
- O placeholder `corretor@pinciara.com` contextualiza bem o ambiente da imobiliária.
- O título da aba (`title`) pode ser unificado para `Entrar — Plataforma Pinciara`.
