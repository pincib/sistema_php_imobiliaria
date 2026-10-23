-- Supports recent interaction counts for each lead without scanning all of its history.
-- The new index has lead_id as its leftmost column, so it also covers lead_id-only lookups.
CREATE INDEX IF NOT EXISTS idx_interactions_lead_contacted
    ON lead_interactions(lead_id, contacted_at);

DROP INDEX IF EXISTS idx_interactions_lead_id;
