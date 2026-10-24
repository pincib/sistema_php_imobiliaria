ALTER TABLE import_batches ADD COLUMN data_kind TEXT;
ALTER TABLE import_batches ADD COLUMN cleanup_supported INTEGER NOT NULL DEFAULT 0;
ALTER TABLE import_batches ADD COLUMN cleaned_at DATETIME;

ALTER TABLE properties ADD COLUMN import_batch_id INTEGER REFERENCES import_batches(id) ON DELETE SET NULL;
ALTER TABLE owners ADD COLUMN import_batch_id INTEGER REFERENCES import_batches(id) ON DELETE SET NULL;
ALTER TABLE owner_properties ADD COLUMN import_batch_id INTEGER REFERENCES import_batches(id) ON DELETE SET NULL;
ALTER TABLE lead_properties ADD COLUMN import_batch_id INTEGER REFERENCES import_batches(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_properties_import_batch ON properties(import_batch_id);
CREATE INDEX IF NOT EXISTS idx_owners_import_batch ON owners(import_batch_id);
CREATE INDEX IF NOT EXISTS idx_import_batches_kind_cleanup ON import_batches(data_kind, cleanup_supported);
