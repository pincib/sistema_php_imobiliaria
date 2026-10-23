-- 006_create_import_batches.sql

-- Tabela de Lotes de Importação de Planilhas
CREATE TABLE IF NOT EXISTS import_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_code TEXT NOT NULL UNIQUE,
    user_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    file_hash TEXT NOT NULL,
    file_size INTEGER NOT NULL,
    total_rows INTEGER NOT NULL DEFAULT 0,
    created_count INTEGER NOT NULL DEFAULT 0,
    updated_count INTEGER NOT NULL DEFAULT 0,
    skipped_count INTEGER NOT NULL DEFAULT 0,
    error_count INTEGER NOT NULL DEFAULT 0,
    duplicate_policy TEXT NOT NULL DEFAULT 'merge',
    status TEXT NOT NULL DEFAULT 'completed',
    summary_json TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_import_batches_code ON import_batches(batch_code);
CREATE INDEX IF NOT EXISTS idx_import_batches_user ON import_batches(user_id);
CREATE INDEX IF NOT EXISTS idx_import_batches_hash ON import_batches(file_hash);
CREATE INDEX IF NOT EXISTS idx_import_batches_created_at ON import_batches(created_at);

-- Adiciona relacionamento opcional do lote na tabela de leads
ALTER TABLE leads ADD COLUMN import_batch_id INTEGER REFERENCES import_batches(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_leads_import_batch ON leads(import_batch_id);
