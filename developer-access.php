<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/special_access.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    exit('Link akses tidak valid.');
}

$hash = restockSpecialAccessTokenHash($token);

$stmt = $pdo->prepare(
    "SELECT
        sal.id,
        sal.account_id,
        sal.store_id,
        sal.expires_at,
        sal.status,
        s.name AS store_name,
        a.name AS account_name
     FROM special_access_links sal
     INNER JOIN stores s ON s.id = sal.store_id
     INNER JOIN accounts a ON a.id = sal.account_id
     WHERE sal.token_hash = :token_hash
       AND sal.status = 'ACTIVE'
       AND sal.expires_at > CURRENT_TIMESTAMP
       AND s.status = 'ACTIVE'
       AND a.status = 'ACTIVE'
     LIMIT 1"
);
$stmt->execute([':token_hash' => $hash]);
$link = $stmt->fetch();

if (!$link) {
    http_response_code(410);
    exit('Link akses sudah tidak aktif atau sudah kedaluwarsa.');
}

if ((int) ($_SESSION['user_id'] ?? 0) <= 0) {
    $_SESSION['pending_special_access_token'] = $token;
    header('Location: /login.php');
    exit;
}

/* Login session hanya boleh mengaktifkan link untuk membership store yang sesuai. */
$userId = (int) $_SESSION['user_id'];

$membershipStmt = $pdo->prepare(
    "SELECT su.store_id
     FROM store_users su
     INNER JOIN stores s ON s.id = su.store_id
     INNER JOIN accounts a ON a.id = s.account_id
     WHERE su.user_id = :user_id
       AND su.store_id = :store_id
       AND su.status = 'ACTIVE'
       AND s.status = 'ACTIVE'
       AND a.status = 'ACTIVE'
     LIMIT 1"
);
$membershipStmt->execute([
    ':user_id' => $userId,
    ':store_id' => (int) $link['store_id'],
]);

if (!$membershipStmt->fetch()) {
    http_response_code(403);
    exit('Akun ini tidak memiliki akses ke store tujuan.');
}

$_SESSION['store_id'] = (int) $link['store_id'];
$_SESSION['account_id'] = (int) $link['account_id'];
$_SESSION['special_access_token'] = $token;
$_SESSION['special_access_store_id'] = (int) $link['store_id'];

header('Location: /');
exit;
