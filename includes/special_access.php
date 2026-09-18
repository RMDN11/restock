<?php
declare(strict_types=1);

function restockSpecialAccessTokenHash(string $token): string
{
    return hash('sha256', $token);
}

function restockSpecialAccess(PDO $pdo, int $userId, int $accountId, int $storeId): ?array
{
    if ($userId <= 0 || $accountId <= 0 || $storeId <= 0) {
        return null;
    }

    $token = trim((string) ($_SESSION['special_access_token'] ?? ''));
    if ($token === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT
            sal.id,
            sal.account_id,
            sal.store_id,
            sal.access_scope,
            sal.expires_at,
            sal.status
         FROM special_access_links sal
         WHERE sal.token_hash = :token_hash
           AND sal.account_id = :account_id
           AND sal.status = 'ACTIVE'
           AND (sal.expires_at IS NULL OR sal.expires_at > CURRENT_TIMESTAMP)
           AND (
                (sal.access_scope = 'STORE' AND sal.store_id = :store_id)
                OR sal.access_scope = 'ACCOUNT'
           )
         LIMIT 1"
    );

    $stmt->execute([
        ':token_hash' => restockSpecialAccessTokenHash($token),
        ':account_id' => $accountId,
        ':store_id' => $storeId,
    ]);

    $access = $stmt->fetch();

    if (!$access) {
        unset($_SESSION['special_access_token'], $_SESSION['special_access_store_id']);
        return null;
    }

    $membership = $pdo->prepare(
        "SELECT su.store_id
         FROM store_users su
         INNER JOIN stores s ON s.id = su.store_id
         INNER JOIN accounts a ON a.id = s.account_id
         WHERE su.user_id = :user_id
           AND su.status = 'ACTIVE'
           AND s.status = 'ACTIVE'
           AND a.status = 'ACTIVE'
           AND s.account_id = :account_id
         " . ($access['access_scope'] === 'STORE' ? "AND su.store_id = :store_id" : "") . "
         LIMIT 1"
    );

    $membershipParams = [
        ':user_id' => $userId,
        ':account_id' => $accountId,
    ];

    if ($access['access_scope'] === 'STORE') {
        $membershipParams[':store_id'] = $storeId;
    }

    $membership->execute($membershipParams);

    if (!$membership->fetch()) {
        return null;
    }

    $update = $pdo->prepare(
        "UPDATE special_access_links
         SET last_used_at = CURRENT_TIMESTAMP,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id"
    );
    $update->execute([':id' => (int) $access['id']]);

    return $access;
}

function restockHasSpecialAccess(PDO $pdo, int $userId, int $accountId, int $storeId): bool
{
    return restockSpecialAccess($pdo, $userId, $accountId, $storeId) !== null;
}
