-- RESTOCK Phase 2I-7: Subscription Activation
-- One active subscription represents the paid package period for an account/store.

CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id INT UNSIGNED NOT NULL,
    store_id INT UNSIGNED NOT NULL,
    package_id INT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('ACTIVE','EXPIRED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscriptions_payment (payment_id),
    INDEX idx_subscriptions_account_status (account_id, status),
    INDEX idx_subscriptions_store_status (store_id, status),
    INDEX idx_subscriptions_ends_at (ends_at),
    CONSTRAINT fk_subscriptions_account
        FOREIGN KEY (account_id) REFERENCES accounts(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscriptions_store
        FOREIGN KEY (store_id) REFERENCES stores(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscriptions_package
        FOREIGN KEY (package_id) REFERENCES packages(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscriptions_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
