<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/developer/subscriptions/index.php',
    $root . '/developer/includes/sidebar.php',
    $root . '/includes/subscription.php',
    $root . '/includes/auth.php',
    $root . '/database/migrations/008_subscription_activation.sql',
    $root . '/subscription.php',
];

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

foreach ($files as $file) {
    assertContract(is_file($file), 'File subscription Task 9 harus tersedia: ' . $file);
}

$index = file_get_contents($files[0]);
$sidebar = file_get_contents($files[1]);
$helper = file_get_contents($files[2]);
$auth = file_get_contents($files[3]);
$migration = file_get_contents($files[4]);
$page = file_get_contents($files[5]);

foreach ([
    "UPDATE subscriptions",
    "status = 'EXPIRED'",
    "ends_at <= CURRENT_TIMESTAMP",
    "FROM subscriptions sub",
    "sub.status = :status",
    "LIMIT 200",
] as $needle) {
    assertContract(str_contains($index, $needle), 'Developer subscription console tidak lengkap: ' . $needle);
}

foreach ([
    '$isSubscriptions',
    '/developer/subscriptions/',
    'badge-check',
] as $needle) {
    assertContract(str_contains($sidebar, $needle), 'Navigasi subscription tidak lengkap: ' . $needle);
}

foreach ([
    'function restockSyncSubscriptionExpiry',
    'function restockGetActiveSubscription',
    'function restockHasActiveSubscription',
    'function restockRequireActiveSubscription',
    "status = 'EXPIRED'",
    'ends_at <= CURRENT_TIMESTAMP',
    'sub.ends_at > CURRENT_TIMESTAMP',
] as $needle) {
    assertContract(str_contains($helper, $needle), 'Lifecycle helper tidak lengkap: ' . $needle);
}

foreach ([
    "require_once __DIR__ . '/subscription.php';",
    'restockRequireActiveSubscription(',
    '$subscriptionGatedPrefixes',
] as $needle) {
    assertContract(str_contains($auth, $needle), 'Access control subscription tidak terintegrasi: ' . $needle);
}

foreach ([
    'account_id BIGINT UNSIGNED NOT NULL',
    'store_id BIGINT UNSIGNED NOT NULL',
    'package_id INT UNSIGNED NOT NULL',
    'payment_id BIGINT UNSIGNED NOT NULL',
] as $needle) {
    assertContract(str_contains($migration, $needle), 'Migration FK subscription tidak sesuai schema production: ' . $needle);
}

foreach ([
    'includes/auth.php',
    'Subscription',
    'reason',
    '/checkout.php',
    '/paket/',
    'subscriptionNotice',
    '3000',
] as $needle) {
    assertContract(str_contains($page, $needle), 'Subscription page tidak lengkap: ' . $needle);
}

echo "PASS: subscription management contract\n";
