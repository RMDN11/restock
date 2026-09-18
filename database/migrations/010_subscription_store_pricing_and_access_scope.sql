-- RESTOCK Task 13
-- Store-count subscription pricing + developer special access scope.

ALTER TABLE packages
    ADD COLUMN min_store_count INT UNSIGNED NULL AFTER duration_days,
    ADD COLUMN max_store_count INT UNSIGNED NULL AFTER min_store_count;

ALTER TABLE special_access_links
    MODIFY COLUMN store_id BIGINT UNSIGNED NULL,
    ADD COLUMN access_scope ENUM('STORE','ACCOUNT') NOT NULL DEFAULT 'STORE' AFTER token_hash,
    MODIFY COLUMN expires_at DATETIME NULL;

UPDATE special_access_links
SET access_scope = 'STORE'
WHERE access_scope IS NULL;

ALTER TABLE special_access_links
    MODIFY COLUMN access_scope ENUM('STORE','ACCOUNT') NOT NULL DEFAULT 'STORE';
