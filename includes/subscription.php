<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RESTOCK - Subscription Lifecycle
|--------------------------------------------------------------------------
| Satu sumber untuk membaca subscription aktif dan menyinkronkan
| subscription yang sudah melewati ends_at menjadi EXPIRED.
*/

function restockSyncSubscriptionExpiry(PDO $pdo, int $accountId, int $storeId): void
{
    if ($accountId <= 0 || $storeId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        "UPDATE subscriptions
         SET status = 'EXPIRED',
             updated_at = CURRENT_TIMESTAMP
         WHERE account_id = :account_id
           AND store_id = :store_id
           AND status = 'ACTIVE'
           AND ends_at <= CURRENT_TIMESTAMP"
    );

    $stmt->execute([
        ':account_id' => $accountId,
        ':store_id'   => $storeId,
    ]);
}

function restockGetActiveSubscription(PDO $pdo, int $accountId, int $storeId): ?array
{
    if ($accountId <= 0 || $storeId <= 0) {
        return null;
    }

    restockSyncSubscriptionExpiry($pdo, $accountId, $storeId);

    $stmt = $pdo->prepare(
        "SELECT
            sub.id,
            sub.account_id,
            sub.store_id,
            sub.package_id,
            sub.pricing_tier_id,
            sub.store_count,
            sub.payment_id,
            sub.starts_at,
            sub.ends_at,
            sub.status,
            p.name AS package_name,
            p.slug AS package_slug,
            p.duration_days
         FROM subscriptions sub
         INNER JOIN packages p ON p.id = sub.package_id
         WHERE sub.account_id = :account_id
           AND sub.store_id = :store_id
           AND sub.status = 'ACTIVE'
           AND sub.ends_at > CURRENT_TIMESTAMP
         ORDER BY sub.ends_at DESC, sub.id DESC
         LIMIT 1"
    );

    $stmt->execute([
        ':account_id' => $accountId,
        ':store_id'   => $storeId,
    ]);

    $subscription = $stmt->fetch();

    if ($subscription) {
        return $subscription;
    }

    /*
     * Paid package entitlement is account-level for multi-store accounts.
     * If the current store has no subscription row, use the account's
     * latest active subscription so additional stores remain covered
     * within the purchased store-count range.
     */
    $accountStmt = $pdo->prepare(
        "SELECT
            sub.id,
            sub.account_id,
            sub.store_id,
            sub.package_id,
            sub.pricing_tier_id,
            sub.store_count,
            sub.payment_id,
            sub.starts_at,
            sub.ends_at,
            sub.status,
            p.name AS package_name,
            p.slug AS package_slug,
            p.duration_days
         FROM subscriptions sub
         INNER JOIN packages p ON p.id = sub.package_id
         WHERE sub.account_id = :account_id
           AND sub.status = 'ACTIVE'
           AND sub.ends_at > CURRENT_TIMESTAMP
         ORDER BY sub.ends_at DESC, sub.id DESC
         LIMIT 1"
    );

    $accountStmt->execute([
        ':account_id' => $accountId,
    ]);

    $accountSubscription = $accountStmt->fetch();

    return $accountSubscription ?: null;
}

function restockGetLatestSubscription(PDO $pdo, int $accountId, int $storeId): ?array
{
    if ($accountId <= 0 || $storeId <= 0) {
        return null;
    }

    restockSyncSubscriptionExpiry($pdo, $accountId, $storeId);

    $stmt = $pdo->prepare(
        "SELECT
            sub.id,
            sub.account_id,
            sub.store_id,
            sub.package_id,
            sub.pricing_tier_id,
            sub.store_count,
            sub.payment_id,
            sub.starts_at,
            sub.ends_at,
            sub.status,
            p.name AS package_name,
            p.slug AS package_slug,
            p.duration_days
         FROM subscriptions sub
         INNER JOIN packages p ON p.id = sub.package_id
         WHERE sub.account_id = :account_id
           AND sub.store_id = :store_id
         ORDER BY sub.ends_at DESC, sub.id DESC
         LIMIT 1"
    );

    $stmt->execute([
        ':account_id' => $accountId,
        ':store_id'   => $storeId,
    ]);

    $subscription = $stmt->fetch();

    if ($subscription) {
        return $subscription;
    }

    $accountStmt = $pdo->prepare(
        "SELECT
            sub.id,
            sub.account_id,
            sub.store_id,
            sub.package_id,
            sub.pricing_tier_id,
            sub.store_count,
            sub.payment_id,
            sub.starts_at,
            sub.ends_at,
            sub.status,
            p.name AS package_name,
            p.slug AS package_slug,
            p.duration_days
         FROM subscriptions sub
         INNER JOIN packages p ON p.id = sub.package_id
         WHERE sub.account_id = :account_id
           AND sub.status = 'ACTIVE'
           AND sub.ends_at > CURRENT_TIMESTAMP
         ORDER BY sub.ends_at DESC, sub.id DESC
         LIMIT 1"
    );
    $accountStmt->execute([':account_id' => $accountId]);
    $accountSubscription = $accountStmt->fetch();

    return $accountSubscription ?: null;
}

function restockHasActiveSubscription(PDO $pdo, int $accountId, int $storeId): bool
{
    return restockGetActiveSubscription($pdo, $accountId, $storeId) !== null;
}

function restockRequireActiveSubscription(
    PDO $pdo,
    int $accountId,
    int $storeId
): void {
    if (!restockHasActiveSubscription($pdo, $accountId, $storeId)) {
        header('Location: /subscription.php?reason=required');
        exit;
    }
}

function restockGetActiveStoreCount(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM stores
         WHERE account_id = :account_id
           AND status = 'ACTIVE'"
    );
    $stmt->execute([':account_id' => $accountId]);

    return (int) $stmt->fetchColumn();
}

function restockPackageCoversStoreCount(array $package, int $storeCount): bool
{
    if ($storeCount < 1) {
        return false;
    }

    if ($package['min_store_count'] !== null && $storeCount < (int) $package['min_store_count']) {
        return false;
    }

    if ($package['max_store_count'] !== null && $storeCount > (int) $package['max_store_count']) {
        return false;
    }

    return true;
}
