-- 005_create_interactions_and_follow_ups.sql

-- Tabela de Histórico Cronológico de Interações
CREATE TABLE IF NOT EXISTS lead_interactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    notes TEXT NOT NULL,
    contacted_at DATETIME NOT NULL,
    idempotency_key TEXT UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_interactions_lead_id ON lead_interactions(lead_id);
CREATE INDEX IF NOT EXISTS idx_interactions_user_id ON lead_interactions(user_id);
CREATE INDEX IF NOT EXISTS idx_interactions_contacted_at ON lead_interactions(contacted_at);
CREATE INDEX IF NOT EXISTS idx_interactions_type ON lead_interactions(type);

-- Tabela de Follow-ups e Retornos Agendados
CREATE TABLE IF NOT EXISTS lead_follow_ups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created_by_user_id INTEGER NOT NULL,
    scheduled_at DATETIME NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'rescheduled', 'cancelled')),
    notes TEXT,
    completed_at DATETIME,
    completed_by_user_id INTEGER,
    result_notes TEXT,
    interaction_id INTEGER,
    idempotency_key TEXT UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (completed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (interaction_id) REFERENCES lead_interactions(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_follow_ups_lead_id ON lead_follow_ups(lead_id);
CREATE INDEX IF NOT EXISTS idx_follow_ups_user_id ON lead_follow_ups(user_id);
CREATE INDEX IF NOT EXISTS idx_follow_ups_scheduled_at ON lead_follow_ups(scheduled_at);
CREATE INDEX IF NOT EXISTS idx_follow_ups_status ON lead_follow_ups(status);
