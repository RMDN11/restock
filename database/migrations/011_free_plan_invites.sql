-- RESTOCK Task 1: Free Plan Invite / Onboarding
-- Developer can issue a limited number of free-plan owner invitations.

ALTER TABLE accounts
    ADD COLUMN plan_type ENUM('PAID','FREE') NOT NULL DEFAULT 'PAID' AFTER status;

ALTER TABLE accounts
    ADD COLUMN free_plan_expires_at DATETIME NULL AFTER plan_type;

ALTER TABLE accounts
    ADD COLUMN free_plan_source VARCHAR(50) NULL AFTER free_plan_expires_at;

CREATE TABLE IF NOT EXISTS free_plan_invites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    max_uses INT UNSIGNED NOT NULL DEFAULT 1,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    access_scope ENUM('ACCOUNT') NOT NULL DEFAULT 'ACCOUNT',
    expires_at DATETIME NULL,
    status ENUM('ACTIVE','REVOKED','EXPIRED','DEPLETED') NOT NULL DEFAULT 'ACTIVE',
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_free_plan_invite_token_hash (token_hash),
    INDEX idx_free_plan_invite_status (status, expires_at),
    CONSTRAINT fk_free_plan_invite_package
        FOREIGN KEY (package_id) REFERENCES packages(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_free_plan_invite_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
