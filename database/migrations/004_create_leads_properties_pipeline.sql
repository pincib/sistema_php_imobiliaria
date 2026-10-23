-- 004_create_leads_properties_pipeline.sql

-- Tabela principal de Leads
CREATE TABLE IF NOT EXISTS leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT COLLATE NOCASE,
    phone TEXT,
    source TEXT NOT NULL DEFAULT 'manual',
    stage TEXT NOT NULL DEFAULT 'novo',
    loss_reason TEXT,
    user_id INTEGER,
    notes TEXT,
    next_follow_up_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_leads_stage ON leads(stage);
CREATE INDEX IF NOT EXISTS idx_leads_user_id ON leads(user_id);
CREATE INDEX IF NOT EXISTS idx_leads_email ON leads(email);
CREATE INDEX IF NOT EXISTS idx_leads_phone ON leads(phone);
CREATE INDEX IF NOT EXISTS idx_leads_created_at ON leads(created_at);

-- Tabela de Imóveis (estrutura preparada para N:N)
CREATE TABLE IF NOT EXISTS properties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'apartamento',
    price REAL,
    status TEXT NOT NULL DEFAULT 'disponivel',
    address TEXT,
    neighborhood TEXT,
    city TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_properties_code ON properties(code);
CREATE INDEX IF NOT EXISTS idx_properties_status ON properties(status);

-- Tabela de Associação N:N entre Leads e Imóveis
CREATE TABLE IF NOT EXISTS lead_properties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    property_id INTEGER NOT NULL,
    interest_type TEXT NOT NULL DEFAULT 'interesse',
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    UNIQUE(lead_id, property_id)
);

CREATE INDEX IF NOT EXISTS idx_lead_properties_lead ON lead_properties(lead_id);
CREATE INDEX IF NOT EXISTS idx_lead_properties_property ON lead_properties(property_id);

-- Histórico auditável de atribuição e transferências de responsável
CREATE TABLE IF NOT EXISTS lead_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    from_user_id INTEGER,
    to_user_id INTEGER NOT NULL,
    assigned_by_user_id INTEGER,
    reason TEXT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_lead_assignments_lead ON lead_assignments(lead_id);
CREATE INDEX IF NOT EXISTS idx_lead_assignments_to_user ON lead_assignments(to_user_id);

-- Carga inicial de imóveis para testes e associação imediata
INSERT OR IGNORE INTO properties (id, code, title, type, price, neighborhood, city) VALUES
(1, 'AP101', 'Apartamento 2 Qts com Varanda', 'apartamento', 450000, 'Icaraí', 'Niterói'),
(2, 'CA202', 'Casa Linear em Condomínio', 'casa', 890000, 'Piratininga', 'Niterói'),
(3, 'CO303', 'Cobertura Duplex Vista Mar', 'cobertura', 1650000, 'Boa Viagem', 'Niterói'),
(4, 'SL404', 'Sala Comercial Centro', 'comercial', 220000, 'Centro', 'Niterói');
