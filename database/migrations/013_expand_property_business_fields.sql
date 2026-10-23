ALTER TABLE properties ADD COLUMN google_maps_url TEXT;
ALTER TABLE properties ADD COLUMN built_area REAL;
ALTER TABLE properties ADD COLUMN land_area REAL;
ALTER TABLE properties ADD COLUMN intention TEXT;
ALTER TABLE properties ADD COLUMN sale_price REAL;
ALTER TABLE properties ADD COLUMN rent_price REAL;
ALTER TABLE properties ADD COLUMN aspiration TEXT;
ALTER TABLE properties ADD COLUMN condominium_price REAL;
ALTER TABLE properties ADD COLUMN iptu_price REAL;
ALTER TABLE properties ADD COLUMN listing_url TEXT;

UPDATE properties SET sale_price = price WHERE price IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_properties_intention ON properties(intention);
CREATE INDEX IF NOT EXISTS idx_properties_aspiration ON properties(aspiration);
