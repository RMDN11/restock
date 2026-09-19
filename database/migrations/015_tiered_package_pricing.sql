-- RESTOCK Task 14: tiered package pricing by intended store count
-- Run after 014_payment_accounts.sql.
--
-- package_price_tiers is the authoritative price lookup for paid packages.
-- Existing package price/range values are migrated into one starter tier so
-- current packages keep working without changing their existing price.

CREATE TABLE IF NOT EXISTS package_price_tiers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    min_store_count INT UNSIGNED NOT NULL,
    max_store_count INT UNSIGNED NULL,
    price DECIMAL(15,2) NOT NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_package_price_tiers_package_status (package_id, status, sort_order, id),
    INDEX idx_package_price_tiers_store_range (package_id, min_store_count, max_store_count),
    CONSTRAINT fk_package_price_tiers_package
        FOREIGN KEY (package_id) REFERENCES packages(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_package_price_tiers_range
        CHECK (max_store_count IS NULL OR max_store_count >= min_store_count),
    CONSTRAINT chk_package_price_tiers_price
        CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO package_price_tiers
    (package_id, min_store_count, max_store_count, price, status, sort_order)
SELECT
    p.id,
    COALESCE(p.min_store_count, 1),
    p.max_store_count,
    p.price,
    'ACTIVE',
    0
FROM packages p
LEFT JOIN package_price_tiers t
    ON t.package_id = p.id
WHERE t.id IS NULL;

ALTER TABLE payments
    ADD COLUMN pricing_tier_id BIGINT UNSIGNED NULL AFTER package_id,
    ADD COLUMN store_count INT UNSIGNED NULL AFTER pricing_tier_id,
    ADD INDEX idx_payments_pricing_tier_id (pricing_tier_id);

ALTER TABLE subscriptions
    ADD COLUMN pricing_tier_id BIGINT UNSIGNED NULL AFTER package_id,
    ADD COLUMN store_count INT UNSIGNED NULL AFTER pricing_tier_id,
    ADD INDEX idx_subscriptions_pricing_tier_id (pricing_tier_id);

ALTER TABLE payments
    ADD CONSTRAINT fk_payments_pricing_tier
        FOREIGN KEY (pricing_tier_id) REFERENCES package_price_tiers(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE subscriptions
    ADD CONSTRAINT fk_subscriptions_pricing_tier
        FOREIGN KEY (pricing_tier_id) REFERENCES package_price_tiers(id)
        ON UPDATE CASCADE ON DELETE SET NULL;
