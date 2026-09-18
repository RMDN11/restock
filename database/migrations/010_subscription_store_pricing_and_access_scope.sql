-- RESTOCK Task 13: Store-count subscription pricing + lifetime developer access
--
-- Package pricing can be customized by the number of stores covered.
-- min_store_count <= store_count <= max_store_count (NULL max = no upper bound).
-- A package with no range remains usable for any store count.

ALTER TABLE packages
    ADD COLUMN min_store_count INT UNSIGNED NULL AFTER duration_days,
    ADD COLUMN max_store_count INT UNSIGNED NULL AFTER min_store_count;

CREATE TABLE IF NOT EXISTS special_access_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    access_scope ENUM('STORE','ACCOUNT') NOT NULL DEFAULT 'STORE',
    expires_at DATETIME NULL,
    status ENUM('ACTIVE','REVOKED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_special_access_token_hash (token_hash),
    INDEX idx_special_access_account_status (account_id, status),
    INDEX idx_special_access_store_status (store_id, status),
    INDEX idx_special_access_expires_at (expires_at),
    CONSTRAINT fk_special_access_account
        FOREIGN KEY (account_id) REFERENCES accounts(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_special_access_store
        FOREIGN KEY (store_id) REFERENCES stores(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_special_access_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convert the previous Task 10 schema safely when the table already exists.
ALTER TABLE special_access_links
    MODIFY COLUMN store_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS access_scope ENUM('STORE','ACCOUNT') NOT NULL DEFAULT 'STORE' AFTER token_hash,
    MODIFY COLUMN expires_at DATETIME NULL;
