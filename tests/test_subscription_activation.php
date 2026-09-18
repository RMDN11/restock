<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/database/migrations/008_subscription_activation.sql',
    $root . '/developer/payments/view.php',
];

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

foreach ($files as $file) {
    assertContract(is_file($file), 'File Task 7 harus tersedia: ' . $file);
}

$migration = file_get_contents($files[0]);
$view = file_get_contents($files[1]);

foreach ([
    'CREATE TABLE IF NOT EXISTS subscriptions',
    'account_id',
    'store_id',
    'package_id',
    'payment_id',
    "status ENUM('ACTIVE','EXPIRED','CANCELLED')",
    'starts_at',
    'ends_at',
    'uq_subscriptions_payment',
    'fk_subscriptions_payment',
] as $needle) {
    assertContract(str_contains($migration, $needle), 'Schema subscription tidak lengkap: ' . $needle);
}

foreach ([
    '$pdo->beginTransaction()',
    "SET status='VERIFIED'",
    'proofStmt',
    'INSERT INTO subscriptions',
    'payment_id',
    'duration_days',
    '$pdo->commit()',
    '$pdo->rollBack()',
    'Subscription',
] as $needle) {
    assertContract(str_contains($view, $needle), 'Kontrak aktivasi subscription tidak ditemukan: ' . $needle);
}

echo "PASS: subscription activation contract\n";
