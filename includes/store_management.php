<?php
declare(strict_types=1);

/*
 * RESTOCK - Account Store Management
 *
 * Store limits are account-level entitlements:
 * - FREE plan: maximum 1 active store.
 * - Paid plan: maximum comes from the active package subscription.
 */

function restockGetAccountStoreEntitlement(PDO $pdo, int $accountId): ?array
{
    if ($accountId <= 0) {
        return null;
    }

    $accountStmt = $pdo->prepare(
        "SELECT id, plan_type, free_plan_expires_at
         FROM accounts
         WHERE id = :account_id
         LIMIT 1"
    );
    $accountStmt->execute([':account_id' => $accountId]);
    $account = $accountStmt->fetch();

    if (!$account) {
        return null;
    }

    if (
        $account['plan_type'] === 'FREE' &&
        (
            empty($account['free_plan_expires_at']) ||
            strtotime((string) $account['free_plan_expires_at']) > time()
        )
    ) {
        return [
            'type' => 'FREE',
            'label' => 'Free',
            'package_name' => 'Free',
            'min_store_count' => 1,
            'max_store_count' => 1,
            'ends_at' => $account['free_plan_expires_at'],
        ];
    }

    $subscriptionStmt = $pdo->prepare(
        "SELECT
            sub.id,
            sub.ends_at,
            p.id AS package_id,
            p.name AS package_name,
            p.min_store_count,
            p.max_store_count
         FROM subscriptions sub
         INNER JOIN packages p ON p.id = sub.package_id
         WHERE sub.account_id = :account_id
           AND sub.status = 'ACTIVE'
           AND sub.ends_at > CURRENT_TIMESTAMP
           AND p.status = 'ACTIVE'
         ORDER BY sub.ends_at DESC, sub.id DESC
         LIMIT 1"
    );
    $subscriptionStmt->execute([':account_id' => $accountId]);
    $subscription = $subscriptionStmt->fetch();

    if (!$subscription) {
        return null;
    }

    return [
        'type' => 'PAID',
        'label' => 'Paid',
        'package_name' => $subscription['package_name'],
        'package_id' => (int) $subscription['package_id'],
        'min_store_count' => $subscription['min_store_count'] !== null
            ? (int) $subscription['min_store_count']
            : null,
        'max_store_count' => $subscription['max_store_count'] !== null
            ? (int) $subscription['max_store_count']
            : null,
        'ends_at' => $subscription['ends_at'],
    ];
}

function restockGetActiveAccountStoreCount(PDO $pdo, int $accountId): int
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

function restockCanAddAccountStore(PDO $pdo, int $accountId): array
{
    $entitlement = restockGetAccountStoreEntitlement($pdo, $accountId);
    $currentCount = restockGetActiveAccountStoreCount($pdo, $accountId);

    if (!$entitlement) {
        return [
            'allowed' => false,
            'reason' => 'Belum ada paket aktif untuk menambah toko.',
            'current_count' => $currentCount,
            'max_count' => 0,
            'entitlement' => null,
        ];
    }

    $maxCount = $entitlement['max_store_count'];

    if ($maxCount !== null && $currentCount >= $maxCount) {
        return [
            'allowed' => false,
            'reason' => 'Batas jumlah toko pada paket ' . $entitlement['package_name'] . ' sudah tercapai.',
            'current_count' => $currentCount,
            'max_count' => $maxCount,
            'entitlement' => $entitlement,
        ];
    }

    return [
        'allowed' => true,
        'reason' => '',
        'current_count' => $currentCount,
        'max_count' => $maxCount,
        'entitlement' => $entitlement,
    ];
}
