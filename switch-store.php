<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /stores.php');
    exit;
}

$csrfToken = (string) ($_SESSION['csrf_token'] ?? '');
$postedToken = (string) ($_POST['csrf_token'] ?? '');

if ($csrfToken === '' || $postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
    $_SESSION['flash_error'] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    header('Location: /stores.php');
    exit;
}

$storeId = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);

if ($storeId === false || $storeId === null || $storeId < 1) {
    $_SESSION['flash_error'] = 'Toko tidak valid.';
    header('Location: /stores.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT s.id, s.name, s.slug, s.account_id
     FROM stores s
     INNER JOIN store_users su ON su.store_id = s.id
     WHERE s.id = :store_id
       AND s.account_id = :account_id
       AND su.user_id = :user_id
       AND su.status = 'ACTIVE'
       AND s.status = 'ACTIVE'
     LIMIT 1"
);
$stmt->execute([
    ':store_id' => $storeId,
    ':account_id' => $authAccountId,
    ':user_id' => $authUserId,
]);
$store = $stmt->fetch();

if (!$store) {
    $_SESSION['flash_error'] = 'Kamu tidak memiliki akses ke toko tersebut.';
    header('Location: /stores.php');
    exit;
}

$_SESSION['store_id'] = (int) $store['id'];
$_SESSION['store_name'] = $store['name'];
$_SESSION['store_slug'] = $store['slug'];

header('Location: /');
exit;
