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
