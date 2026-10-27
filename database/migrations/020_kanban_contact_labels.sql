ALTER TABLE owners ADD COLUMN contact_stage TEXT NOT NULL DEFAULT 'a_contatar' CHECK(contact_stage IN ('a_contatar','contatado','mantido','fora_do_ar'));
ALTER TABLE owners ADD COLUMN invalid_contact INTEGER NOT NULL DEFAULT 0 CHECK(invalid_contact IN (0,1));
ALTER TABLE leads ADD COLUMN invalid_contact INTEGER NOT NULL DEFAULT 0 CHECK(invalid_contact IN (0,1));
CREATE INDEX idx_owners_contact_stage ON owners(contact_stage);
