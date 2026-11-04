-- 021_add_owner_verification.sql
-- Adiciona suporte à rotina periódica de verificação e atualização de proprietários (critério de 30 dias)

ALTER TABLE owners ADD COLUMN last_verified_at DATETIME;
ALTER TABLE owners ADD COLUMN verified_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_owners_last_verified_at ON owners(last_verified_at);
