-- RESTOCK Task 13: store-count pricing + lifetime/account-wide access
-- Run after 009_special_access_links.sql.

-- Package price can be tied to a store-count range.
ALTER TABLE packages
    ADD COLUMN min_store_count INT UNSIGNED NULL AFTER duration_days,
    ADD COLUMN max_store_count INT UNSIGNED NULL AFTER min_store_count;

-- Special access may target one store or the whole account.
ALTER TABLE special_access_links
    MODIFY COLUMN store_id BIGINT UNSIGNED NULL;

ALTER TABLE special_access_links
    ADD COLUMN access_scope ENUM('STORE','ACCOUNT') NOT NULL DEFAULT 'STORE' AFTER token_hash;

ALTER TABLE special_access_links
    MODIFY COLUMN expires_at DATETIME NULL;
