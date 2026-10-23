CREATE TABLE IF NOT EXISTS owners (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT COLLATE NOCASE,
    phone TEXT,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_owners_name ON owners(name COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_owners_email ON owners(email COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_owners_phone ON owners(phone);
CREATE UNIQUE INDEX IF NOT EXISTS ux_owners_email_unique ON owners(email COLLATE NOCASE) WHERE email IS NOT NULL AND email <> '';
CREATE UNIQUE INDEX IF NOT EXISTS ux_owners_phone_unique ON owners(phone) WHERE phone IS NOT NULL AND phone <> '';

CREATE TABLE IF NOT EXISTS owner_properties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id INTEGER NOT NULL,
    property_id INTEGER NOT NULL,
    linked_by_user_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES owners(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (linked_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE(owner_id, property_id)
);
CREATE INDEX IF NOT EXISTS idx_owner_properties_owner ON owner_properties(owner_id);
CREATE INDEX IF NOT EXISTS idx_owner_properties_property ON owner_properties(property_id);

CREATE TABLE IF NOT EXISTS whatsapp_contact_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contact_type TEXT NOT NULL CHECK(contact_type IN ('lead','owner')),
    contact_id INTEGER NOT NULL,
    rule TEXT NOT NULL,
    message TEXT NOT NULL,
    result TEXT NOT NULL CHECK(result IN ('confirmado pelo corretor','não enviado','cancelado')),
    user_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_whatsapp_logs_contact ON whatsapp_contact_logs(contact_type, contact_id, created_at);
