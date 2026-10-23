# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

PHP 8.x, Slim 4, SQLite, HTMX, Templates PHP nativo, Pico.css (ou equivalente), Apache + mod_php via Docker.

## Users

- **Corretores:** Equipe comercial de pequenas e médias imobiliárias. Utilizam computadores desktop e notebooks na rede local da empresa. Precisam de consulta ágil a leads, registro imediato de conversas/visitas e controle pontual de follow-ups diários, sem a lentidão ou complexidade de CRMs corporativos.
- **Administradores:** Gestores da imobiliária responsáveis pela integridade da base, importação de dados legados via CSV, acompanhamento geral de contatos, auditoria de movimentações e gestão do ambiente operacional.

## Product Purpose

Substituir planilhas dispersas e anotações isoladas por um CRM imobiliário local-first centralizado, confiável e descomplicado. O propósito é assegurar que nenhum lead seja esquecido por falta de retorno, oferecendo histórico cronológico de interações e controle claro de follow-ups. O sucesso é medido pela adesão natural da equipe de corretores, zero perda de negócios por abandono de contato e manutenção técnica mínima.

## Positioning

CRM imobiliário local-first, minimalista e de alta agilidade, projetado especificamente para rodar na rede local da imobiliária (em máquina dedicada ou notebook local). Enquanto sistemas SaaS impõem custos recorrentes elevados, dependência de internet externa e sobrecarga de menus, esta plataforma prioriza autonomia total, velocidade com renderização server-side via HTMX e foco absoluto nos módulos essenciais da operação imobiliária.

## Operating Context

- **Ambiente:** Rede local da imobiliária, servido por um contêiner Docker único (Apache + mod_php + SQLite) hospedado localmente.
- **Concorrência e Escala:** Equipe pequena/média (geralmente entre 5 e algumas dezenas de acessos simultâneos).
- **Padrão de Uso:** Predominância de leituras rápidas durante o dia, com escritas frequentes mas pontuais (registro de notas de atendimento, agendamento de retorno e troca de status).
- **Rotina:** Consulta diária na dashboard aos contatos atrasados, do dia e futuros antes de ligações, mensagens de WhatsApp ou reuniões presenciais.

## Capabilities and Constraints

### Capacidades Confirmadas
- **Política de Acesso e Permissões:** Acesso total e colaborativo da equipe comercial. Qualquer corretor pode visualizar todos os leads e interagir/registrar contatos livremente, mantendo registro auditável de quem realizou cada ação. Administradores possuem controle geral de dados e importações.
- **Política de Duplicidade:** Leads com telefone ou e-mail coincidente (no cadastro manual ou na importação CSV) são mesclados/atualizados no registro existente, registrando o novo contato como evento no histórico de interações.
- **Pipeline de Vendas:** Máquina de estados para condução do lead no funil, com obrigatoriedade de motivo detalhado em caso de perda ou descarte.
- **Controle de Follow-up:** Campo estruturado `next_follow_up_at` com visão segregada na dashboard (do dia, atrasados e futuros).
- **Importação CSV:** Fluxo seguro em 5 passos (Upload → Prévia → Mapeamento → Validação → Importação em lote).
- **Auditoria e Histórico:** Rastreabilidade de criação, alterações cadastrais, trocas de estágio e interações com carimbo de data, hora e usuário autor.
- **Arquitetura Modular:** Separação lógica em módulos (`Modules/Leads`, `Modules/Properties`, `Modules/Signboards`).
- **Operação de Placas (premissas implementadas para a Fase 7):** Placa sempre vinculada a um imóvel e a responsável que seja usuário ativo; localização do ponto é texto livre. `pendente` significa sem data de instalação, `ativa` instalada sem validade vencida, `vencida` instalada com validade anterior à data local, e `retirada` quando a retirada é confirmada. Validade é opcional. Corretores e administradores podem consultar e registrar alterações e movimentos; cada evento exige confirmação textual na instalação/retirada e preserva autor/data no histórico. Registros não são apagados; histórico global fica na área administrativa de auditoria.

### Restrições Técnicas
- **Simplicidade de Frontend:** Sem SPAs, bundlers ou frameworks JS pesados (sem React/Vue). Front-end baseado em HTMX com templates PHP nativos e estilização funcional.
- **Banco de Dados Local:** SQLite configurado com WAL Mode, `foreign_keys = ON`, `busy_timeout` e transações curtas.
- **Backup e Tolerância a Falhas:** Backup rotineiro via `VACUUM INTO`. O servidor local é ponto único de falha assumido na fase inicial.

### Decisões em Aberto
- Destino secundário para cópia externa de backups (ex: pendrive/disco externo ou sincronização secundária).
- Alertas visuais e sonoros específicos para follow-ups vencidos.

## Brand Commitments

- Estética operacional, limpa e utilitária (superfície **Operate**), focada em alta legibilidade e densidade de dados equilibrada.
- Respeito aos padrões de design nativo do Pico.css (suporte limpo a modo claro e escuro).

## Evidence on Hand

- `docs/architecture/architecture_v2.md`: Especificação arquitetural do CRM local-first, modelo de dados, stack e premissas.
- `docs/planning/roadmap.md`: Roadmap estruturado por fases e guia de aplicação dos comandos Impeccable (versão 4.7.0).

## Product Principles

1. **Funcionalidade e Prontidão Acima de Sofisticação:** O sistema deve abrir instantaneamente e permitir registrar um retorno em menos de 10 segundos.
2. **Soberania dos Dados (Local-First):** A imobiliária mantém posse integral da sua base em disco local, sem depender de conexão externa para operar.
3. **Colaboração com Transparência:** A equipe trabalha junta na mesma base de oportunidades, com histórico transparente e auditável de cada ação.
4. **Crescimento Incremental Seguro:** Cada módulo (Leads, Imóveis, Placas, Relatórios) é estabilizado e adotado na rotina antes da próxima camada de expansão.

## Accessibility & Inclusion

- Navegação eficiente por teclado para formulários e tabelas operacionais.
- Contraste semântico em conformidade para uso prolongado em telas de trabalho.

