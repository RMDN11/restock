<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/
$userId = (int) ($authUserId ?? $_SESSION['user_id'] ?? 0);
$storeId = (int) ($authStoreId ?? $_SESSION['store_id'] ?? 0);

if ($userId <= 0 || $storeId <= 0) {
    header('Location: /login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Only POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/pengaturan/');
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate CSRF
|--------------------------------------------------------------------------
*/
$postedToken = $_POST['csrf_token'] ?? '';

if (
    empty($postedToken) ||
    !hash_equals($csrfToken, $postedToken)
) {
    $_SESSION['flash_error'] =
        'Sesi keamanan tidak valid. Silakan coba lagi.';

    header('Location: /pages/pengaturan/');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Password Data
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        username,
        password,
        role,
        status
    FROM users
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $userId
]);

$user = $stmt->fetch();

if (!$user) {

    session_destroy();

    header('Location: /login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Form Data
|--------------------------------------------------------------------------
*/
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$errors = [];

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/
if ($currentPassword === '') {

    $errors[] =
        'Password saat ini wajib diisi.';
}

if ($newPassword === '') {

    $errors[] =
        'Password baru wajib diisi.';

} elseif (strlen($newPassword) < 8) {

    $errors[] =
        'Password baru minimal 8 karakter.';
}

if ($confirmPassword === '') {

    $errors[] =
        'Konfirmasi password wajib diisi.';

} elseif ($newPassword !== $confirmPassword) {

    $errors[] =
        'Konfirmasi password tidak sama.';
}

/*
|--------------------------------------------------------------------------
| Verify Current Password
|--------------------------------------------------------------------------
*/
if (
    empty($errors) &&
    !password_verify($currentPassword, $user['password'])
) {

    $errors[] =
        'Password saat ini salah.';
}

/*
|--------------------------------------------------------------------------
| Prevent Same Password
|--------------------------------------------------------------------------
*/
if (
    empty($errors) &&
    password_verify($newPassword, $user['password'])
) {

    $errors[] =
        'Password baru harus berbeda dari password saat ini.';
}

/*
|--------------------------------------------------------------------------
| Update Password
|--------------------------------------------------------------------------
*/
if (empty($errors)) {

    try {

        $pdo->beginTransaction();

        $newPasswordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        /*
        |--------------------------------------------------------------------------
        | Update Password
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            UPDATE users
            SET
                password = :password,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            ':password' => $newPasswordHash,
            ':id' => $userId
        ]);

        $stmt = $pdo->prepare("UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE user_id = :reset_user_id AND used_at IS NULL");
        $stmt->execute([':reset_user_id' => $userId]);

        /*
        |--------------------------------------------------------------------------
        | Audit Log
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                store_id,
                user_id,
                action,
                table_name,
                record_id,
                description,
                created_at
            )
            VALUES (
                :audit_store_id,
                :user_id,
                'UPDATE',
                'users',
                :record_id,
                :description,
                CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':audit_store_id' => $storeId,
            ':user_id' => $userId,
            ':record_id' => $userId,
            ':description' =>
                'Mengubah password akun sendiri.'
        ]);

        $pdo->commit();

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */
        $_SESSION['flash_success'] =
            'Password berhasil diubah.';

        header('Location: /pages/pengaturan/');
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['flash_error'] =
            'Password gagal diubah. Silakan coba lagi.';

        header('Location: /pages/pengaturan/');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Validation Error
|--------------------------------------------------------------------------
*/
$_SESSION['flash_error'] = implode(' ', $errors);

header('Location: /pages/pengaturan/');
exit;