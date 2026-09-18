<?php
require_once __DIR__ . '/../../includes/developer_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /developer/packages/');
    exit;
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
    $_SESSION['flash_error'] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    header('Location: /developer/packages/');
    exit;
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$status = strtoupper(trim((string) ($_POST['status'] ?? '')));

if (!$id || $id < 1 || !in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
    $_SESSION['flash_error'] = 'Data paket tidak valid.';
    header('Location: /developer/packages/');
    exit;
}

$stmt = $pdo->prepare('UPDATE packages SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
$stmt->execute([':status' => $status, ':id' => $id]);

$_SESSION['flash_success'] = 'Status paket berhasil diperbarui.';
header('Location: /developer/packages/');
exit;
