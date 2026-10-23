# Mapa e organização do repositório

Este documento explica onde encontrar cada tipo de arquivo e orienta onde colocar novos materiais. A estrutura preserva os caminhos de execução da aplicação.

## Documentos

- `README.md`: visão geral, requisitos, instalação e operação inicial.
- `PRODUCT.md`: contexto do produto consumido pelas ferramentas de trabalho.
- `docs/architecture/`: arquitetura, modelo de dados e decisões técnicas.
- `docs/planning/`: roadmap e planejamento por fases.
- `docs/requirements/`: requisitos, solicitações e especificações funcionais.
- `docs/`: operação, restauração, revisão do MVP e descobertas.
- `tools/documents/`: scripts que geram ou apoiam documentos.

## Aplicação

- `src/`: código PHP, serviços, módulos, autenticação e infraestrutura.
- `views/`: templates PHP agrupados por área funcional.
- `public/`: único diretório servido pelo Apache; inclui entrada e assets.
- `database/migrations/`: alterações versionadas do esquema SQLite.
- `bin/`: comandos de manutenção e operação.
- `tests/`: testes de unidade e de funcionalidades.

## Dados e operação local

- `planilhas_csv_import/`: arquivos CSV de origem usados no fluxo de importação. Preserve os nomes esperados pelo importador.
- `storage/`: banco local, backups, imports e logs. Conteúdo operacional não deve ser tratado como código-fonte.
- `.env`: configuração local e segredos; não versionar nem compartilhar.
- `.env.example`: modelo seguro das variáveis necessárias.
- `Dockerfile` e `docker-compose.yml`: execução local em contêiner.

## Materiais gerados

- `output/pdf/`: PDFs finais gerados.
- `output/previews/checks/`: imagens de conferência intermediária.
- `output/previews/final/`: imagens de conferência da versão final.
- `output/previews/training/`: imagens de páginas do material de treinamento.
- `tools/documents/`: fonte do gerador do manual em PDF.
- `tmp/`: reservado a temporários descartáveis; não armazenar material de referência aqui.

## Configurações de ferramentas

Pastas iniciadas por ponto (`.agent/`, `.agents/`, `.claude/`, `.codex/`, `.cursor/`, `.gemini/`, `.opencode/`, `.impeccable/`) guardam configurações e instruções específicas de ferramentas. Mantenha-as na raiz para que as ferramentas continuem detectando-as.

## Item a classificar

`Processadas` é atualmente um arquivo vazio sem extensão na raiz. Foi mantido intacto porque não há informação suficiente para determinar se deveria ser um diretório, um marcador ou um arquivo de dados.
