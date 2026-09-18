<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/special_access.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

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
        sal.access_scope,
        s.name AS store_name,
        a.name AS account_name
     FROM special_access_links sal
     INNER JOIN stores s ON s.id = sal.store_id
     INNER JOIN accounts a ON a.id = sal.account_id
     WHERE sal.token_hash = :token_hash
       AND sal.status = 'ACTIVE'
       AND (sal.expires_at IS NULL OR sal.expires_at > CURRENT_TIMESTAMP)
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

$membershipSql = "SELECT su.store_id
    FROM store_users su
    INNER JOIN stores s ON s.id = su.store_id
    INNER JOIN accounts a ON a.id = s.account_id
    WHERE su.user_id = :user_id
      AND s.account_id = :account_id
      AND su.status = 'ACTIVE'
      AND s.status = 'ACTIVE'
      AND a.status = 'ACTIVE'";

if ($link['access_scope'] === 'STORE') {
    $membershipSql .= " AND su.store_id = :store_id";
}

$membershipSql .= " LIMIT 1";

$membershipStmt = $pdo->prepare($membershipSql);
$membershipParams = [
    ':user_id' => $userId,
    ':account_id' => (int) $link['account_id'],
];

if ($link['access_scope'] === 'STORE') {
    $membershipParams[':store_id'] = (int) $link['store_id'];
}

$membershipStmt->execute($membershipParams);
$membership = $membershipStmt->fetch();

if (!$membership) {
    http_response_code(403);
    exit($link['access_scope'] === 'ACCOUNT'
        ? 'Akun ini belum memiliki toko aktif pada account tujuan.'
        : 'Akun ini tidak memiliki akses ke store tujuan.');
}

$_SESSION['store_id'] = (int) $membership['store_id'];
$_SESSION['account_id'] = (int) $link['account_id'];
$_SESSION['special_access_token'] = $token;
$_SESSION['special_access_store_id'] = (int) $membership['store_id'];

header('Location: /');
exit;
