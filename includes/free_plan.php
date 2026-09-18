<?php
declare(strict_types=1);

function restockFreePlanIsActive(array $account): bool
{
    if (($account['plan_type'] ?? 'PAID') !== 'FREE') {
        return false;
    }

    if (empty($account['free_plan_expires_at'])) {
        return true;
    }

    return strtotime((string) $account['free_plan_expires_at']) > time();
}

function restockFreePlanConsumeInvite(PDO $pdo, int $inviteId): ?array
{
    if ($inviteId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT
            id,
            package_id,
            max_uses,
            used_count,
            access_scope,
            expires_at,
            status
         FROM free_plan_invites
         WHERE id = :id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([':id' => $inviteId]);
    $invite = $stmt->fetch();

    if (!$invite || $invite['status'] !== 'ACTIVE') {
        return null;
    }

    if ($invite['expires_at'] !== null && strtotime((string) $invite['expires_at']) <= time()) {
        $pdo->prepare(
            "UPDATE free_plan_invites
             SET status = 'EXPIRED',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'ACTIVE'"
        )->execute([':id' => $inviteId]);

        return null;
    }

    if ((int) $invite['used_count'] >= (int) $invite['max_uses']) {
        $pdo->prepare(
            "UPDATE free_plan_invites
             SET status = 'DEPLETED',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'ACTIVE'"
        )->execute([':id' => $inviteId]);

        return null;
    }

    return $invite;
}

function restockFreePlanUseInvite(PDO $pdo, int $inviteId): void
{
    $stmt = $pdo->prepare(
        "UPDATE free_plan_invites
         SET used_count = used_count + 1,
             status = CASE
                WHEN used_count + 1 >= max_uses THEN 'DEPLETED'
                ELSE 'ACTIVE'
             END,
             last_used_at = CURRENT_TIMESTAMP,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id
           AND status = 'ACTIVE'
           AND used_count < max_uses"
    );
    $stmt->execute([':id' => $inviteId]);
}
