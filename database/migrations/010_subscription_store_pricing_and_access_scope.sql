-- RESTOCK Task 13: store-count pricing + lifetime/account-wide access
-- Run after 009_special_access_links.sql.
--
-- NOTE:
-- The repository's existing parent IDs use BIGINT UNSIGNED for accounts/stores,
-- matching the current subscription/special-access tables.

ALTER TABLE packages
    ADD COLUMN min_store_count INT UNSIGNED NULL AFTER duration_days,
    ADD COLUMN max_store_count INT UNSIGNED NULL AFTER min_store_count;

ALTER TABLE special_access_links
    MODIFY COLUMN store_id BIGINT UNSIGNED NULL,
    ADD COLUMN access_scope ENUM('STORE','ACCOUNT') NOT NULL DEFAULT 'STORE' AFTER token_hash,
    MODIFY COLUMN expires_at DATETIME NULL;
