ALTER TABLE signboards ADD COLUMN material TEXT NOT NULL DEFAULT 'outro';
ALTER TABLE signboards ADD COLUMN height_cm REAL;
ALTER TABLE signboards ADD COLUMN length_cm REAL;
ALTER TABLE signboards ADD COLUMN deal_type TEXT NOT NULL DEFAULT 'compra';
ALTER TABLE signboards ADD COLUMN price REAL NOT NULL DEFAULT 0;
ALTER TABLE signboards ADD COLUMN board_stage TEXT NOT NULL DEFAULT 'a_fazer';

UPDATE signboards SET board_stage='usadas' WHERE installed_at IS NOT NULL OR removed_at IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_signboards_board_stage ON signboards(board_stage);

CREATE TABLE IF NOT EXISTS signboard_stage_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    signboard_id INTEGER NOT NULL,
    from_stage TEXT NOT NULL,
    to_stage TEXT NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id INTEGER,
    FOREIGN KEY (signboard_id) REFERENCES signboards(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_signboard_stage_history_signboard ON signboard_stage_history(signboard_id, id DESC);
