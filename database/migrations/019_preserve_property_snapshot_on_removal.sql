ALTER TABLE signboard_property_history RENAME TO signboard_property_history_legacy;

CREATE TABLE signboard_property_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    signboard_id INTEGER NOT NULL,
    property_id INTEGER,
    property_code_snapshot TEXT NOT NULL DEFAULT '',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id INTEGER,
    FOREIGN KEY (signboard_id) REFERENCES signboards(id) ON DELETE RESTRICT,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO signboard_property_history (id,signboard_id,property_id,property_code_snapshot,assigned_at,user_id)
SELECT h.id,h.signboard_id,h.property_id,COALESCE(p.code,''),h.assigned_at,h.user_id
FROM signboard_property_history_legacy h
LEFT JOIN properties p ON p.id=h.property_id;

DROP TABLE signboard_property_history_legacy;

CREATE INDEX idx_signboard_property_history_signboard
    ON signboard_property_history(signboard_id,id DESC);
