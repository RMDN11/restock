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

function restockFreePlanTokenHash(string $token): string
{
    return hash('sha256', $token);
}

function restockFreePlanFindInvite(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT
            fpi.id,
            fpi.package_id,
            fpi.max_uses,
            fpi.used_count,
            fpi.access_scope,
            fpi.expires_at,
            fpi.status,
            p.name AS package_name,
            p.duration_days,
            p.status AS package_status
         FROM free_plan_invites fpi
         LEFT JOIN packages p ON p.id = fpi.package_id
         WHERE fpi.token_hash = :token_hash
         LIMIT 1"
    );
    $stmt->execute([':token_hash' => restockFreePlanTokenHash($token)]);
    $invite = $stmt->fetch();

    if (!$invite || $invite['status'] !== 'ACTIVE') {
        return null;
    }

    if ($invite['expires_at'] !== null && strtotime((string) $invite['expires_at']) <= time()) {
        $pdo->prepare(
            "UPDATE free_plan_invites
             SET status = 'EXPIRED', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'ACTIVE'"
        )->execute([':id' => (int) $invite['id']]);

        return null;
    }

    if ((int) $invite['used_count'] >= (int) $invite['max_uses']) {
        $pdo->prepare(
            "UPDATE free_plan_invites
             SET status = 'DEPLETED', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'ACTIVE'"
        )->execute([':id' => (int) $invite['id']]);

        return null;
    }

    if ($invite['package_id'] !== null && $invite['package_status'] !== 'ACTIVE') {
        return null;
    }

    return $invite;
}

function restockFreePlanRedeem(PDO $pdo, string $token, int $accountId): array
{
    if ($accountId <= 0) {
        return ['success' => false, 'message' => 'Account tidak valid.'];
    }

    $inviteStmt = $pdo->prepare(
        "SELECT
            id,
            package_id,
            max_uses,
            used_count,
            access_scope,
            expires_at,
            status
         FROM free_plan_invites
         WHERE token_hash = :token_hash
         LIMIT 1
         FOR UPDATE"
    );
    $inviteStmt->execute([':token_hash' => restockFreePlanTokenHash($token)]);
    $invite = $inviteStmt->fetch();

    if (!$invite || $invite['status'] !== 'ACTIVE') {
        return ['success' => false, 'message' => 'Link Free Plan sudah tidak aktif atau tidak ditemukan.'];
    }

    if ($invite['expires_at'] !== null && strtotime((string) $invite['expires_at']) <= time()) {
        $pdo->prepare(
            "UPDATE free_plan_invites
             SET status = 'EXPIRED', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'ACTIVE'"
        )->execute([':id' => (int) $invite['id']]);

        return ['success' => false, 'message' => 'Link Free Plan sudah kedaluwarsa.'];
    }

    if ((int) $invite['used_count'] >= (int) $invite['max_uses']) {
        $pdo->prepare(
            "UPDATE free_plan_invites
             SET status = 'DEPLETED', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'ACTIVE'"
        )->execute([':id' => (int) $invite['id']]);

        return ['success' => false, 'message' => 'Kuota Free Plan dari link ini sudah habis.'];
    }

    $accountStmt = $pdo->prepare(
        "SELECT id, plan_type, free_plan_expires_at
         FROM accounts
         WHERE id = :account_id
         LIMIT 1
         FOR UPDATE"
    );
    $accountStmt->execute([':account_id' => $accountId]);
    $account = $accountStmt->fetch();

    if (!$account) {
        return ['success' => false, 'message' => 'Account tidak ditemukan.'];
    }

    if (($account['plan_type'] ?? 'PAID') === 'FREE') {
        $existingExpiry = $account['free_plan_expires_at'];

        if ($existingExpiry === null || strtotime((string) $existingExpiry) > time()) {
            return ['success' => false, 'message' => 'Account ini sudah memiliki Free Plan yang aktif.'];
        }
    }

    $freePlanExpiresAt = null;

    if ($invite['package_id'] !== null) {
        $packageStmt = $pdo->prepare(
            "SELECT duration_days, status
             FROM packages
             WHERE id = :package_id
             LIMIT 1"
        );
        $packageStmt->execute([':package_id' => (int) $invite['package_id']]);
        $package = $packageStmt->fetch();

        if (!$package || $package['status'] !== 'ACTIVE') {
            return ['success' => false, 'message' => 'Benefit Free Plan tidak tersedia karena paket sudah tidak aktif.'];
        }

        $freePlanExpiresAt = date(
            'Y-m-d H:i:s',
            time() + ((int) $package['duration_days'] * 86400)
        );
    }

    $updateAccount = $pdo->prepare(
        "UPDATE accounts
         SET plan_type = 'FREE',
             free_plan_expires_at = :free_plan_expires_at,
             free_plan_source = :free_plan_source,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :account_id"
    );
    $updateAccount->execute([
        ':free_plan_expires_at' => $freePlanExpiresAt,
        ':free_plan_source' => 'invite:' . (int) $invite['id'],
        ':account_id' => $accountId,
    ]);

    $updateInvite = $pdo->prepare(
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
    $updateInvite->execute([':id' => (int) $invite['id']]);

    if ($updateInvite->rowCount() !== 1) {
        throw new RuntimeException('Kuota Free Plan berubah sebelum redeem selesai.');
    }

    return [
        'success' => true,
        'message' => 'Free Plan berhasil diaktifkan untuk account.',
        'invite_id' => (int) $invite['id'],
        'expires_at' => $freePlanExpiresAt,
    ];
}
