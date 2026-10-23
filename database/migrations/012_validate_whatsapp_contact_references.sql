-- Validate the polymorphic contact reference when a WhatsApp log is written.
-- Logs remain available as historical records when a contact is later removed.
CREATE TRIGGER IF NOT EXISTS trg_whatsapp_logs_valid_lead_insert
BEFORE INSERT ON whatsapp_contact_logs
FOR EACH ROW
WHEN NEW.contact_type = 'lead'
 AND NOT EXISTS (SELECT 1 FROM leads WHERE id = NEW.contact_id)
BEGIN
    SELECT RAISE(ABORT, 'whatsapp_lead_not_found');
END;

CREATE TRIGGER IF NOT EXISTS trg_whatsapp_logs_valid_owner_insert
BEFORE INSERT ON whatsapp_contact_logs
FOR EACH ROW
WHEN NEW.contact_type = 'owner'
 AND NOT EXISTS (SELECT 1 FROM owners WHERE id = NEW.contact_id)
BEGIN
    SELECT RAISE(ABORT, 'whatsapp_owner_not_found');
END;

CREATE TRIGGER IF NOT EXISTS trg_whatsapp_logs_valid_lead_update
BEFORE UPDATE OF contact_type, contact_id ON whatsapp_contact_logs
FOR EACH ROW
WHEN NEW.contact_type = 'lead'
 AND NOT EXISTS (SELECT 1 FROM leads WHERE id = NEW.contact_id)
BEGIN
    SELECT RAISE(ABORT, 'whatsapp_lead_not_found');
END;

CREATE TRIGGER IF NOT EXISTS trg_whatsapp_logs_valid_owner_update
BEFORE UPDATE OF contact_type, contact_id ON whatsapp_contact_logs
FOR EACH ROW
WHEN NEW.contact_type = 'owner'
 AND NOT EXISTS (SELECT 1 FROM owners WHERE id = NEW.contact_id)
BEGIN
    SELECT RAISE(ABORT, 'whatsapp_owner_not_found');
END;
