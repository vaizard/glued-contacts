-- migrate:up

CREATE TABLE contacts_cards (
                                uuid UUID GENERATED ALWAYS AS ((doc->>'uuid')::UUID) STORED PRIMARY KEY,
                                doc JSONB DEFAULT NULL,
                                ft TEXT,
                                private BOOLEAN GENERATED ALWAYS AS (COALESCE((doc->>'props.private')::INTEGER, 0) = 1) STORED,
                                nonce bytea generated always as ( decode( md5((doc - 'uuid')::text), 'hex')) stored,
                                created_at timestamp with time zone default CURRENT_TIMESTAMP,
                                updated_at timestamp with time zone default CURRENT_TIMESTAMP,
                                UNIQUE (nonce)
);

COMMENT ON COLUMN contacts_cards.uuid IS 'Card UUID';
COMMENT ON COLUMN contacts_cards.doc IS 'Card data as JSON';
COMMENT ON COLUMN contacts_cards.created_at IS 'Created at';
COMMENT ON COLUMN contacts_cards.updated_at IS 'Updated at';
COMMENT ON COLUMN contacts_cards.ft IS 'Fulltext search';
COMMENT ON COLUMN contacts_cards.private IS 'Exclude private data from ft search';
COMMENT ON COLUMN contacts_cards.nonce IS 'Generated MD5 hash of the doc JSON';

-- Create full-text search GIN index using the simple configuration
CREATE INDEX idx_ft ON contacts_cards USING GIN (to_tsvector('simple', ft));

CREATE TABLE contacts_refs (
                               uuid1 UUID NOT NULL,
                               uuid2 UUID NOT NULL,
                               label TEXT NOT NULL DEFAULT '_',
                               kind TEXT NOT NULL,
                               bind_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL,
                               part_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL,
                               PRIMARY KEY (uuid1, uuid2, label, kind)
);

COMMENT ON COLUMN contacts_refs.uuid1 IS 'Card UUID 1';
COMMENT ON COLUMN contacts_refs.uuid2 IS 'Card UUID 2';
COMMENT ON COLUMN contacts_refs.label IS 'Reference label';
COMMENT ON COLUMN contacts_refs.kind IS 'Reference kind';
COMMENT ON COLUMN contacts_refs.bind_at IS 'Connection bound at';
COMMENT ON COLUMN contacts_refs.part_at IS 'Connection parted at';

CREATE INDEX idx_uuid1 ON contacts_refs (uuid1);
CREATE INDEX idx_uuid2 ON contacts_refs (uuid2);

CREATE TABLE contacts_cards_audit (
                                      uuid UUID NOT NULL,
                                      nonce BYTEA GENERATED ALWAYS AS (decode(md5(doc::TEXT), 'hex')) STORED,
                                      doc JSONB DEFAULT NULL,
                                      updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON COLUMN contacts_cards_audit.uuid IS 'Card UUID';
COMMENT ON COLUMN contacts_cards_audit.nonce IS 'Generated MD5 hash of the doc JSON';
COMMENT ON COLUMN contacts_cards_audit.doc IS 'Card data as JSON';
COMMENT ON COLUMN contacts_cards_audit.updated_at IS 'Timestamp updated at';

CREATE OR REPLACE VIEW v_contacts_cards_audit AS
SELECT
    t.*,
    ROW_NUMBER() OVER (PARTITION BY t.uuid ORDER BY t.updated_at) AS rev,
    COUNT(*) OVER (PARTITION BY t.uuid) AS rev_count
FROM contacts_cards_audit t;

CREATE OR REPLACE FUNCTION before_update_contacts_cards()
    RETURNS TRIGGER AS $$
BEGIN
    IF (OLD.nonce <> NEW.nonce) THEN
        INSERT INTO contacts_cards_audit (uuid, doc, updated_at)
        VALUES (OLD.uuid, OLD.doc, NOW());
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER before_update_contacts_cards
    BEFORE UPDATE ON contacts_cards
    FOR EACH ROW
EXECUTE FUNCTION before_update_contacts_cards();


-- migrate:down

DROP TABLE IF EXISTS contacts_cards;
DROP TABLE IF EXISTS contacts_refs;

DROP VIEW IF EXISTS v_contacts_cards_audit;
DROP TABLE IF EXISTS contacts_cards_audit;

DROP TRIGGER IF EXISTS before_update_contacts_cards ON contacts_cards;
DROP FUNCTION IF EXISTS before_update_contacts_cards;
