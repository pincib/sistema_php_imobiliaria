ALTER TABLE signboards ADD COLUMN deleted_at DATETIME;
ALTER TABLE signboards ADD COLUMN deleted_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_signboards_deleted ON signboards(deleted_at);
