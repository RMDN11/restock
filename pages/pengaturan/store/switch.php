<?php
require_once __DIR__ . '/../../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/pengaturan/');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Permintaan tidak valid.';
    header('Location: /pages/pengaturan/');
    exit;
}

$targetStoreId = (int) ($_POST['store_id'] ?? 0);

if ($targetStoreId <= 0) {
    $_SESSION['flash_error'] = 'Store tidak valid.';
    header('Location: /pages/pengaturan/');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        su.store_id,
        su.role,
        s.account_id,
        s.name AS store_name,
        s.slug AS store_slug
    FROM store_users su
    INNER JOIN stores s ON s.id = su.store_id
    INNER JOIN accounts a ON a.id = s.account_id
    WHERE su.user_id = :user_id
      AND su.store_id = :store_id
      AND su.status = 'ACTIVE'
      AND s.status = 'ACTIVE'
      AND a.status = 'ACTIVE'
    LIMIT 1
");

$stmt->execute([
    ':user_id'  => $authUserId,
    ':store_id' => $targetStoreId,
]);

$membership = $stmt->fetch();

if (!$membership) {
    $_SESSION['flash_error'] = 'Kamu tidak memiliki akses ke Store tersebut.';
    header('Location: /pages/pengaturan/');
    exit;
}

session_regenerate_id(true);

$_SESSION['account_id'] = (int) $membership['account_id'];
$_SESSION['store_id'] = (int) $membership['store_id'];
$_SESSION['store_name'] = $membership['store_name'];
$_SESSION['store_slug'] = $membership['store_slug'];
$_SESSION['role'] = $membership['role'];
$_SESSION['store_switched_at'] = time();

$_SESSION['flash_success'] = 'Berhasil berpindah ke Store ' . $membership['store_name'] . '.';

header('Location: /');
exit;
