ALTER TABLE leads ADD COLUMN alternate_email TEXT COLLATE NOCASE;
ALTER TABLE leads ADD COLUMN alternate_phone TEXT;
ALTER TABLE properties ADD COLUMN postal_code TEXT;
ALTER TABLE properties ADD COLUMN external_data_json TEXT;
ALTER TABLE owners ADD COLUMN external_data_json TEXT;

CREATE TABLE IF NOT EXISTS import_source_rows (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id INTEGER,
    entity_type TEXT NOT NULL CHECK(entity_type IN ('lead','property','owner')),
    entity_id INTEGER,
    external_reference TEXT,
    source_filename TEXT NOT NULL,
    source_row INTEGER NOT NULL,
    source_data_json TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_import_source_rows_reference ON import_source_rows(entity_type, external_reference);
CREATE INDEX IF NOT EXISTS idx_import_source_rows_entity ON import_source_rows(entity_type, entity_id);
