<?php

require_once '../../../config/database.php';
require_once '../../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function staffRedirect() {
    header('Location: /pages/pengaturan/staff/');
    exit;
}

$ownerId = (int) ($authUserId ?? $_SESSION['user_id'] ?? 0);
$storeId = (int) ($authStoreId ?? $_SESSION['store_id'] ?? 0);

if ($ownerId <= 0 || $storeId <= 0 || ($authRole ?? $_SESSION['role'] ?? '') !== 'ADMIN') {
    header('Location: /pages/pengaturan/');
    exit;
}

$ownerCheck = $pdo->prepare("
    SELECT su.id
    FROM store_users su
    INNER JOIN stores s ON s.id = su.store_id
    INNER JOIN accounts a ON a.id = s.account_id
    WHERE su.user_id = :owner_user_id
      AND su.store_id = :owner_store_id
      AND su.role = 'ADMIN'
      AND su.status = 'ACTIVE'
      AND s.status = 'ACTIVE'
      AND a.status = 'ACTIVE'
    LIMIT 1
");
$ownerCheck->execute([
    ':owner_user_id' => $ownerId,
    ':owner_store_id' => $storeId
]);
if (!$ownerCheck->fetch()) {
    header('Location: /pages/pengaturan/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') staffRedirect();

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    staffRedirect();
}

$staffId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$staffId || $staffId <= 0) {
    $_SESSION['flash_error'] = 'ID Staff tidak valid.';
    staffRedirect();
}

$stmt = $pdo->prepare("
    SELECT
        u.id, u.name, u.username,
        su.role AS store_role,
        su.status AS membership_status
    FROM users u
    INNER JOIN store_users su ON su.user_id = u.id
    WHERE u.id = :staff_user_id
      AND su.store_id = :target_store_id
    LIMIT 1
");
$stmt->execute([
    ':staff_user_id' => $staffId,
    ':target_store_id' => $storeId
]);
$staff = $stmt->fetch();

if (!$staff || $staff['store_role'] !== 'STAFF') {
    $_SESSION['flash_error'] = 'Data Staff tidak ditemukan di Store aktif.';
    staffRedirect();
}

$newStatus = $staff['membership_status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE store_users
        SET status = :new_status,
            updated_at = CURRENT_TIMESTAMP
        WHERE store_id = :target_store_id
          AND user_id = :staff_user_id
          AND role = 'STAFF'
    ");
    $stmt->execute([
        ':new_status' => $newStatus,
        ':target_store_id' => $storeId,
        ':staff_user_id' => $staffId
    ]);

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Membership Staff tidak berhasil diperbarui.');
    }

    $audit = $pdo->prepare("
        INSERT INTO audit_logs
            (store_id, user_id, action, table_name, record_id, description, created_at)
        VALUES
            (:audit_store_id, :audit_user_id, 'STATUS', 'store_users', :record_id, :description, CURRENT_TIMESTAMP)
    ");
    $audit->execute([
        ':audit_store_id' => $storeId,
        ':audit_user_id' => $ownerId,
        ':record_id' => $staffId,
        ':description' => ($newStatus === 'ACTIVE' ? 'Mengaktifkan' : 'Menonaktifkan') .
            ' akses Staff di Store: ' . $staff['name'] . ' (' . $staff['username'] . ') → ' . $newStatus
    ]);

    $pdo->commit();

    $_SESSION['flash_success'] = $newStatus === 'ACTIVE'
        ? 'Staff berhasil diaktifkan di Store ini.'
        : 'Staff berhasil dinonaktifkan di Store ini.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = 'Status Staff gagal diperbarui. Silakan coba lagi.';
}

staffRedirect();
