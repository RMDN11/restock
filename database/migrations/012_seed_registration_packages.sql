-- RESTOCK: starter package choices for public registration
-- Run after the package table exists.
-- Existing packages are preserved. These rows are inserted only when their slug
-- does not already exist, so developers can customize them later.

INSERT INTO packages
    (name, slug, price, duration_days, min_store_count, max_store_count, description, status, created_at, updated_at)
SELECT
    'Standard',
    'standard',
    99000,
    30,
    1,
    3,
    'Untuk pengelolaan toko dengan kebutuhan lebih lengkap.',
    'ACTIVE',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM packages WHERE slug = 'standard'
);

INSERT INTO packages
    (name, slug, price, duration_days, min_store_count, max_store_count, description, status, created_at, updated_at)
SELECT
    'Pro',
    'pro',
    149000,
    30,
    4,
    10,
    'Untuk operasional beberapa toko dalam satu akun.',
    'ACTIVE',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM packages WHERE slug = 'pro'
);

INSERT INTO packages
    (name, slug, price, duration_days, min_store_count, max_store_count, description, status, created_at, updated_at)
SELECT
    'Business',
    'business',
    249000,
    30,
    11,
    NULL,
    'Untuk pengelolaan banyak toko tanpa batas jumlah toko pada paket ini.',
    'ACTIVE',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM packages WHERE slug = 'business'
);
