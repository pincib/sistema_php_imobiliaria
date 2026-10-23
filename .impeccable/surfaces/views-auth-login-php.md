---
version: 1
slug: "views-auth-login-php"
primary_target: "views/auth/login.php"
related_targets: ["views/layouts/auth.php"]
---

# Brief de Design — Fluxo de Login e Acesso

<!-- impeccable:surface-brief fluxo-de-login-e-acesso -->

## Job e Audiência
- **Público**: Corretores e gestores da imobiliária iniciando o expediente em computadores desktop ou notebooks conectados à rede local, frequentemente com ligações ou clientes em atendimento imediato.
- **Contexto de uso**: Acesso matinal ágil para consulta instantânea de contatos, agenda de retornos do dia e registro de atendimentos.
- **Modo do visitante**: **Operate** (ergonomia operacional, máxima legibilidade, zero ruído visual e navegação fluida 100% via teclado).

## Outcome e Verdade do Produto
- **Ação primária**: Autenticação em menos de 5 segundos (`E-mail` ➔ `Tab` ➔ `Senha` ➔ `Enter`).
- **Sucesso operacional**: Redirecionamento imediato para a rota pretendida (ou Dashboard) com sessão contínua de 12 horas (43.200s, cobrindo o expediente da loja sem interrupções indesejadas de logout).
- **Verdade local-first**: Sistema estritamente interno e privado. Não há criação pública de contas ("Cadastre-se"), nem fluxos de recuperação de senha via internet externa (a gestão de credenciais e permissões é feita diretamente pelo administrador local).

## Direção Visual e Estrutura
- **Autoridade visual**: Design utilitário e contido ancorado no Pico.css v2, com suporte nativo limpo a temas claro e escuro.
- **Tese de composição**: Cartão compacto centralizado (~440px), tipografia institucional sóbria, campos com rótulos explícitos e botão de ação primária em largura total.
- **Momento focal**: Foco visual claro nos campos e feedback contextual imediato em alertas semânticos no topo do cartão.

## Escopo e Limites
- **Superfície-alvo**: Rota `/login` (`views/auth/login.php` e layout isolado `views/layouts/auth.php`).
- **Anti-goals**: Sem ilustrações ou banners promocionais dispersivos, sem logins sociais de terceiros (Google/OAuth) e sem caixas de seleção desnecessárias.

## Estados e Tratamento de Erros
- **Repouso**: Foco automático (`autofocus`) no campo de e-mail, campos vazios e limpos.
- **Credenciais Incorretas**: Alerta direto e discreto: *"E-mail ou senha incorretos."* preservando o e-mail digitado no campo para correção imediata.
- **Usuário Desativado**: Alerta: *"Sua conta está desativada. Consulte a administração."*
- **Sessão Expirada / Rota Privada**: Alerta informativo: *"Por favor, faça login para continuar."* preservando a URL pretendida para retorno pós-login.
- **Logout Concluído**: Alerta informativo: *"Você encerrou sua sessão com segurança."*

## Interação e Acessibilidade
- `autocomplete` semântico ativo (`email` e `current-password`).
- Atributos `required` com validação nativa do navegador antes do envio.
- Alertas com papéis `role="alert"` para leitores de tela e contraste semântico conforme diretrizes WCAG AA.
