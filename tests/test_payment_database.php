<?php
/**
 * Static contract test for Phase 2I-5 payment database foundation.
 *
 * Runtime PHP/MySQL is intentionally not required here. When run against a
 * checkout containing the migration, this verifies the migration contract.
 */
$path = __DIR__ . '/../database/migrations/007_payment_foundation.sql';

if (!is_file($path)) {
    fwrite(STDERR, "RED: migration 007_payment_foundation.sql does not exist yet.\n");
    exit(1);
}

$sql = file_get_contents($path);

$required = [
    'CREATE TABLE IF NOT EXISTS packages',
    'CREATE TABLE IF NOT EXISTS payments',
    'account_id',
    'store_id',
    'package_id',
    'payment_method',
    'proof_file',
    'verified_by',
    'verified_at',
    'rejection_reason',
    'expired_at',
    "ENUM('ACTIVE','INACTIVE')",
    "ENUM('BANK_TRANSFER')",
    "ENUM('PENDING','VERIFIED','REJECTED','EXPIRED')",
    'FOREIGN KEY (account_id) REFERENCES accounts(id)',
    'FOREIGN KEY (store_id) REFERENCES stores(id)',
    'FOREIGN KEY (package_id) REFERENCES packages(id)',
    'FOREIGN KEY (verified_by) REFERENCES users(id)',
    'INDEX idx_payments_account_id (account_id)',
    'INDEX idx_payments_store_id (store_id)',
    'INDEX idx_payments_package_id (package_id)',
    'INDEX idx_payments_status (status)',
    'INDEX idx_payments_created_at (created_at)',
];

$failures = [];
foreach ($required as $needle) {
    if (strpos($sql, $needle) === false) {
        $failures[] = $needle;
    }
}

if ($failures) {
    fwrite(STDERR, "FAIL: missing migration contract:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

if (preg_match('/CREATE TABLE IF NOT EXISTS payments[\s\S]*?FOREIGN KEY \(account_id\) REFERENCES accounts\(id\)[\s\S]*?FOREIGN KEY \(store_id\) REFERENCES stores\(id\)/', $sql) !== 1) {
    fwrite(STDERR, "FAIL: payments must define account/store foreign keys.\n");
    exit(1);
}

echo "Payment database foundation contract: PASS\n";
