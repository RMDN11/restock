-- RESTOCK: align Standard package with the public 2–3 store tier.
UPDATE packages
SET min_store_count = 2,
    max_store_count = 3,
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'standard';
