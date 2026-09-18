<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/database/migrations/008_subscription_activation.sql',
    $root . '/includes/subscription.php',
    $root . '/includes/auth.php',
];

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

foreach ($files as $file) {
    assertContract(is_file($file), 'File subscription Task 8 harus tersedia: ' . $file);
}

$migration = file_get_contents($files[0]);
$subscription = file_get_contents($files[1]);
$auth = file_get_contents($files[2]);

assertContract(
    str_contains($migration, 'account_id BIGINT UNSIGNED NOT NULL'),
    'account_id harus BIGINT UNSIGNED.'
);
assertContract(
    str_contains($migration, 'store_id BIGINT UNSIGNED NOT NULL'),
    'store_id harus BIGINT UNSIGNED.'
);
assertContract(
    str_contains($migration, 'package_id INT UNSIGNED NOT NULL'),
    'package_id harus INT UNSIGNED.'
);
assertContract(
    str_contains($migration, 'payment_id BIGINT UNSIGNED NOT NULL'),
    'payment_id harus BIGINT UNSIGNED.'
);

foreach ([
    'function restockSyncSubscriptionExpiry',
    "status = 'EXPIRED'",
    'ends_at <= CURRENT_TIMESTAMP',
    'function restockGetActiveSubscription',
    "sub.status = 'ACTIVE'",
    'sub.ends_at > CURRENT_TIMESTAMP',
    'function restockHasActiveSubscription',
    'function restockRequireActiveSubscription',
    '/subscription.php?reason=required',
] as $needle) {
    assertContract(str_contains($subscription, $needle), 'Lifecycle helper tidak lengkap: ' . $needle);
}

foreach ([
    "require_once __DIR__ . '/subscription.php';",
    'restockRequireActiveSubscription(',
    "'/checkout.php'",
    "'/subscription.php'",
] as $needle) {
    assertContract(str_contains($auth, $needle), 'Auth subscription integration tidak ditemukan: ' . $needle);
}

assertContract(
    str_contains($auth, "$subscriptionGatedPrefixes = ["),
    'Route gate belum didefinisikan.'
);

echo "PASS: subscription lifecycle contract\n";
