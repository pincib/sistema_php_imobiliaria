ALTER TABLE properties ADD COLUMN characteristics TEXT;
ALTER TABLE properties ADD COLUMN notes TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS idx_properties_code_nocase ON properties(code COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_properties_type ON properties(type);
CREATE INDEX IF NOT EXISTS idx_properties_city ON properties(city);

ALTER TABLE lead_properties ADD COLUMN linked_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_lead_properties_linked_by ON lead_properties(linked_by_user_id);
