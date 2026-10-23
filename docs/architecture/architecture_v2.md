# Architecture v2 — CRM Imobiliário Local-First

## Visão Executiva

CRM imobiliário voltado para equipes pequenas e médias, com foco inicial em acompanhamento de leads, histórico de relacionamento e lembretes de follow-up.

O sistema prioriza:

- Simplicidade operacional.
- Baixo custo de infraestrutura.
- Funcionamento em rede local.
- Facilidade de manutenção futura.
- Entrega incremental por módulos.

Não busca competir com CRMs SaaS completos. O objetivo é resolver de forma eficiente o fluxo operacional da imobiliária com o menor número possível de componentes.

# 1. Objetivos do Projeto

## Objetivos imediatos

- Centralizar leads atualmente dispersos em planilhas.
- Registrar histórico de interações.
- Controlar retornos e follow-ups.
- Facilitar migração via CSV.
- Permitir uso simultâneo por vários corretores.

## Objetivos futuros

- Cadastro de imóveis.
- Controle de placas.
- Relatórios gerenciais.
- Controle de contratos.
- Controle de comissões.

# 2. Premissas Operacionais

A arquitetura é construída sobre algumas hipóteses. Elas não são garantias.

## Cenário esperado

- Equipe pequena ou média.
- Algumas dezenas de acessos simultâneos no máximo.
- Uso predominante de leitura.
- Escritas rápidas e esporádicas.
- Hospedagem em notebook ou computador dedicado na imobiliária.

## Riscos assumidos

- O servidor local é um ponto único de falha.
- Se o host parar, o CRM para.
- Não existe redundância nativa.
- Não existe alta disponibilidade.

# 3. Princípios Arquiteturais

## Funcionalidade acima de sofisticação

Não serão utilizados SPA, React, Vue, build pipelines complexos ou microsserviços enquanto não houver necessidade real.

## Evolução incremental

Primeiro resolve-se Leads. Depois Imóveis, Placas, Relatórios e demais módulos.

# 4. Stack Tecnológica

| Camada | Tecnologia |
|----------|------------|
| Linguagem | PHP 8.x |
| Framework HTTP | Slim 4 |
| Banco | SQLite |
| Front-End | HTMX |
| Templates | PHP nativo |
| CSS | Pico.css ou equivalente |
| Infraestrutura | Docker |
| Servidor Web | Apache + mod_php |

# 5. Estrutura Modular

Os módulos representam organização lógica do domínio e não isolamento real.

```text
Modules/
├── Leads
├── Properties
├── Signboards
```

Uma estrutura modular simples será priorizada inicialmente.

# 6. Banco de Dados

SQLite foi escolhido por simplicidade operacional.

Diretrizes:

- WAL Mode
- Foreign Keys ON
- Busy Timeout
- Transações curtas
- Importações em lotes

# 7. Segurança

Requisitos mínimos:

- Login obrigatório
- Senhas com password_hash()
- Perfis Admin e Corretor
- CSRF obrigatório
- Sessões protegidas
- Auditoria de alterações

# 8. Modelo de Domínio de Leads

Entidades principais:

- Lead
- Interação
- Imóvel
- Corretor
- Follow-up

Relacionamentos:

```text
Lead N:N Imóvel
```

Trocas de responsável devem ser auditadas.

# 9. Gestão de Pipeline

O pipeline deve funcionar como máquina de estados configurável, permitindo retornos e renegociações.

Perdas exigem motivo obrigatório.

# 10. Follow-Up

Campo central:

```text
next_follow_up_at
```

A dashboard deve exibir contatos do dia, atrasados e futuros.

# 11. Importação CSV

Fluxo:

1. Upload
2. Prévia
3. Mapeamento
4. Validação
5. Importação

Deduplicação e identificação de importação são conceitos distintos.

# 12. Backup e Recuperação

Estratégia preferencial:

```sql
VACUUM INTO
```

Requisitos:

- Backup diário
- Teste mensal de restauração

# 13. Operação do Ambiente

Definir responsável por:

- Docker
- Usuários
- Backups
- Atualizações

# 14. Escalabilidade

Caso necessário no futuro:

- SQLite → PostgreSQL
- Armazenamento externo de arquivos
- Novos módulos

# 15. Trade-offs Assumidos

| Decisão | Ganho | Perda |
|----------|---------|---------|
| HTMX | Simplicidade | UX altamente dinâmica |
| SQLite | Operação simples | Concorrência limitada |
| Docker único | Deploy simples | Ponto único de falha |
| Módulos internos | Desenvolvimento rápido | Sem isolamento real |

# 16. Critérios de Sucesso do MVP

- Importação de leads funcionando
- Registro de interações
- Controle de follow-up
- Histórico auditável
- Backup restaurável

# 17. Roadmap

## Fase 1

Core + Segurança + Leads + CSV

## Fase 2

Imóveis

## Fase 3

Placas

## Fase 4

Relatórios

## Fase 5

Contratos, Agenda e Comissões

# 18. Decisões Pendentes

1. Quantidade de usuários simultâneos.
2. Política de permissões.
3. Regras de follow-up.
4. Política de duplicidade.
5. Plano de recuperação do host.
