<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/developer/subscriptions/index.php',
    $root . '/developer/includes/sidebar.php',
    $root . '/includes/subscription.php',
    $root . '/includes/auth.php',
    $root . '/database/migrations/008_subscription_activation.sql',
    $root . '/database/migrations/009_special_access_links.sql',
    $root . '/database/migrations/010_subscription_store_pricing_and_access_scope.sql',
    $root . '/includes/special_access.php',
    $root . '/renewal.php',
    $root . '/renewal/select.php',
    $root . '/developer-access.php',
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
$specialMigration = file_get_contents($files[5]);
$task13Migration = file_get_contents($files[6]);
$specialHelper = file_get_contents($files[7]);
$renewal = file_get_contents($files[8]);
$renewalSelect = file_get_contents($files[9]);
$accessPage = file_get_contents($files[10]);
$page = file_get_contents($files[11]);

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
    '/renewal.php',
    'subscriptionNotice',
    '3000',
] as $needle) {
    assertContract(str_contains($page, $needle), 'Subscription page tidak lengkap: ' . $needle);
}

echo "PASS: subscription management contract\n";

foreach ([
    'CREATE TABLE IF NOT EXISTS special_access_links',
    'token_hash CHAR(64)',
    'expires_at DATETIME NOT NULL',
    "status ENUM('ACTIVE','REVOKED','EXPIRED')",
    'created_by BIGINT UNSIGNED NOT NULL',
] as $needle) {
    assertContract(str_contains($specialMigration, $needle), 'Schema special access tidak lengkap: ' . $needle);
}

foreach ([
    'function restockSpecialAccessTokenHash',
    'function restockSyncSpecialAccess',
    'special_access_token',
    'token_hash',
    'expires_at > CURRENT_TIMESTAMP',
] as $needle) {
    assertContract(str_contains($specialHelper, $needle), 'Helper special access tidak lengkap: ' . $needle);
}

foreach ([
    'Perpanjang akses RESTOCK',
    '/renewal/select.php',
    'duration_days',
    'Pembayaran renewal',
] as $needle) {
    assertContract(str_contains($renewal, $needle), 'Renewal page tidak lengkap: ' . $needle);
}

foreach ([
    'selected_package_id',
    '/checkout.php',
    "status = 'ACTIVE'",
] as $needle) {
    assertContract(str_contains($renewalSelect, $needle), 'Renewal selection tidak lengkap: ' . $needle);
}

foreach ([
    'pending_special_access_token',
    '/login.php',
    'store tujuan',
    'special_access_token',
] as $needle) {
    assertContract(str_contains($accessPage, $needle), 'Special access gateway tidak lengkap: ' . $needle);
}

echo "PASS: renewal and special access contract\n";

foreach ([
    'min_store_count INT UNSIGNED',
    'max_store_count INT UNSIGNED',
    'access_scope ENUM',
    'MODIFY COLUMN expires_at DATETIME NULL',
] as $needle) {
    assertContract(str_contains($task13Migration, $needle), 'Migration Task 13 tidak lengkap: ' . $needle);
}
