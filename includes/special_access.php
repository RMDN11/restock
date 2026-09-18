<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RESTOCK - Developer Special Access
|--------------------------------------------------------------------------
| Token hanya disimpan dalam bentuk hash di database.
| Akses tetap terikat pada user + store yang sudah terautentikasi.
|--------------------------------------------------------------------------
*/

function restockSpecialAccessTokenHash(string $token): string
{
    return hash('sha256', $token);
}

function restockSyncSpecialAccess(PDO $pdo, int $userId, int $storeId): ?array
{
    if ($userId <= 0 || $storeId <= 0) {
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
            sal.expires_at,
            sal.status,
            s.name AS store_name
         FROM special_access_links sal
         INNER JOIN stores s ON s.id = sal.store_id
         INNER JOIN accounts a ON a.id = sal.account_id
         INNER JOIN store_users su
            ON su.store_id = sal.store_id
           AND su.user_id = :user_id
           AND su.status = 'ACTIVE'
         WHERE sal.token_hash = :token_hash
           AND sal.store_id = :store_id
           AND sal.status = 'ACTIVE'
           AND sal.expires_at > CURRENT_TIMESTAMP
           AND s.status = 'ACTIVE'
           AND a.status = 'ACTIVE'
         LIMIT 1"
    );

    $stmt->execute([
        ':user_id' => $userId,
        ':store_id' => $storeId,
        ':token_hash' => restockSpecialAccessTokenHash($token),
    ]);

    $access = $stmt->fetch();

    if (!$access) {
        unset($_SESSION['special_access_token'], $_SESSION['special_access_store_id']);
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

function restockHasSpecialAccess(PDO $pdo, int $userId, int $storeId): bool
{
    return restockSyncSpecialAccess($pdo, $userId, $storeId) !== null;
}
