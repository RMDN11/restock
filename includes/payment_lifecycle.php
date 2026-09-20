<?php
declare(strict_types=1);

/**
 * Mark expired PENDING payments as EXPIRED.
 *
 * Expiration is applied lazily when payment-related pages are accessed,
 * so the application does not depend on a cron job just to keep status current.
 */
function restockExpirePendingPayments(PDO $pdo): int
{
    $stmt = $pdo->prepare(
        "UPDATE payments
         SET status = 'EXPIRED',
             updated_at = CURRENT_TIMESTAMP
         WHERE status = 'PENDING'
           AND expired_at IS NOT NULL
           AND expired_at <= CURRENT_TIMESTAMP"
    );

    $stmt->execute();

    return $stmt->rowCount();
}


/**
 * Return the latest payment that still requires the user's attention.
 *
 * PENDING means the proof/payment is awaiting verification.
 * REJECTED means the payment was reviewed but was not accepted.
 */
function restockGetLatestAttentionPayment(
    PDO $pdo,
    int $accountId,
    int $storeId
): ?array {
    if ($accountId <= 0 || $storeId <= 0) {
        return null;
    }

    restockExpirePendingPayments($pdo);

    $stmt = $pdo->prepare(
        "SELECT id, status
         FROM payments
         WHERE account_id = :account_id
           AND store_id = :store_id
           AND status IN ('PENDING', 'REJECTED')
         ORDER BY id DESC
         LIMIT 1"
    );

    $stmt->execute([
        ':account_id' => $accountId,
        ':store_id' => $storeId,
    ]);

    $payment = $stmt->fetch();

    return $payment ?: null;
}

/**
 * Keep users without an application entitlement out of the main dashboard.
 *
 * Pending/rejected payments go to their payment status page.
 * Other users without an entitlement continue to the subscription page.
 */
function restockRequireApplicationAccess(
    PDO $pdo,
    int $accountId,
    int $storeId
): void {
    $attentionPayment = restockGetLatestAttentionPayment($pdo, $accountId, $storeId);

    if ($attentionPayment) {
        header('Location: /payment-status.php?id=' . (int) $attentionPayment['id']);
        exit;
    }

    restockRequireActiveSubscription($pdo, $accountId, $storeId);
}
