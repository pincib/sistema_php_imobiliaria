CREATE TABLE IF NOT EXISTS signboards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    location TEXT NOT NULL,
    responsible_user_id INTEGER NOT NULL,
    installed_at TEXT,
    expires_at TEXT,
    removed_at TEXT,
    notes TEXT,
    install_notes TEXT,
    removal_notes TEXT,
    created_by_user_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE RESTRICT,
    FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CHECK (removed_at IS NULL OR installed_at IS NOT NULL)
);

CREATE INDEX IF NOT EXISTS idx_signboards_property ON signboards(property_id);
CREATE INDEX IF NOT EXISTS idx_signboards_responsible ON signboards(responsible_user_id);
CREATE INDEX IF NOT EXISTS idx_signboards_installed ON signboards(installed_at);
CREATE INDEX IF NOT EXISTS idx_signboards_expires ON signboards(expires_at);
CREATE INDEX IF NOT EXISTS idx_signboards_removed ON signboards(removed_at);

CREATE TABLE IF NOT EXISTS signboard_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    signboard_id INTEGER NOT NULL,
    movement_type TEXT NOT NULL CHECK (movement_type IN ('created','updated','installed','removed')),
    moved_at TEXT NOT NULL,
    notes TEXT NOT NULL,
    user_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (signboard_id) REFERENCES signboards(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_signboard_movements_signboard ON signboard_movements(signboard_id, id DESC);
