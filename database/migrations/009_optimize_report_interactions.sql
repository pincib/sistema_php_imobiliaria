-- Supports bounded report queries by contact date and lead source record.
CREATE INDEX IF NOT EXISTS idx_interactions_contacted_lead
    ON lead_interactions(contacted_at, lead_id);
