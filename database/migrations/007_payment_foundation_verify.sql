-- RESTOCK Phase 2I-5 verification
-- Read-only checks. Run after 007_payment_foundation.sql.

SELECT
    TABLE_NAME,
    ENGINE,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('packages', 'payments')
ORDER BY TABLE_NAME;

SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('packages', 'payments')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'payments'
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY CONSTRAINT_NAME;

SELECT
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('packages', 'payments')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT
    'payments_without_matching_store_account' AS check_name,
    COUNT(*) AS violations
FROM payments p
INNER JOIN stores s ON s.id = p.store_id
WHERE p.account_id <> s.account_id;

SELECT
    'payment_status_summary' AS check_name,
    status,
    COUNT(*) AS total
FROM payments
GROUP BY status
ORDER BY status;
