CREATE TABLE signboards_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER,
    location TEXT NOT NULL DEFAULT '',
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
    material TEXT NOT NULL DEFAULT 'outro',
    height_cm REAL,
    length_cm REAL,
    deal_type TEXT NOT NULL DEFAULT 'compra',
    price REAL NOT NULL DEFAULT 0,
    board_stage TEXT NOT NULL DEFAULT 'a_fazer',
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE RESTRICT,
    FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CHECK (removed_at IS NULL OR installed_at IS NOT NULL)
);

INSERT INTO signboards_new (
    id,property_id,location,responsible_user_id,installed_at,expires_at,removed_at,notes,install_notes,removal_notes,
    created_by_user_id,created_at,updated_at,material,height_cm,length_cm,deal_type,price,board_stage
)
SELECT
    id,property_id,location,responsible_user_id,installed_at,expires_at,removed_at,notes,install_notes,removal_notes,
    created_by_user_id,created_at,updated_at,material,height_cm,length_cm,deal_type,price,board_stage
FROM signboards;

CREATE TABLE signboard_movements_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    signboard_id INTEGER NOT NULL,
    movement_type TEXT NOT NULL CHECK (movement_type IN ('created','updated','installed','removed')),
    moved_at TEXT NOT NULL,
    notes TEXT NOT NULL,
    user_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (signboard_id) REFERENCES signboards_new(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO signboard_movements_new (id,signboard_id,movement_type,moved_at,notes,user_id,created_at)
SELECT id,signboard_id,movement_type,moved_at,notes,user_id,created_at FROM signboard_movements;

CREATE TABLE signboard_stage_history_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    signboard_id INTEGER NOT NULL,
    from_stage TEXT NOT NULL,
    to_stage TEXT NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id INTEGER,
    FOREIGN KEY (signboard_id) REFERENCES signboards_new(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO signboard_stage_history_new (id,signboard_id,from_stage,to_stage,changed_at,user_id)
SELECT id,signboard_id,from_stage,to_stage,changed_at,user_id FROM signboard_stage_history;

DROP TABLE signboard_movements;
DROP TABLE signboard_stage_history;
DROP TABLE signboards;

ALTER TABLE signboards_new RENAME TO signboards;
ALTER TABLE signboard_movements_new RENAME TO signboard_movements;
ALTER TABLE signboard_stage_history_new RENAME TO signboard_stage_history;

CREATE INDEX IF NOT EXISTS idx_signboards_property ON signboards(property_id);
CREATE INDEX IF NOT EXISTS idx_signboards_responsible ON signboards(responsible_user_id);
CREATE INDEX IF NOT EXISTS idx_signboards_installed ON signboards(installed_at);
CREATE INDEX IF NOT EXISTS idx_signboards_expires ON signboards(expires_at);
CREATE INDEX IF NOT EXISTS idx_signboards_removed ON signboards(removed_at);
CREATE INDEX IF NOT EXISTS idx_signboards_board_stage ON signboards(board_stage);
CREATE INDEX IF NOT EXISTS idx_signboard_movements_signboard ON signboard_movements(signboard_id, id DESC);
CREATE INDEX IF NOT EXISTS idx_signboard_stage_history_signboard ON signboard_stage_history(signboard_id, id DESC);

CREATE TABLE signboard_property_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    signboard_id INTEGER NOT NULL,
    property_id INTEGER NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id INTEGER,
    FOREIGN KEY (signboard_id) REFERENCES signboards(id) ON DELETE RESTRICT,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_signboard_property_history_signboard ON signboard_property_history(signboard_id, id DESC);
