<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';


/*
|--------------------------------------------------------------------------
| HANYA POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /pages/barang/kategori/'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrfToken =
    $_SESSION['csrf_token'] ?? '';

$postedToken =
    $_POST['csrf_token'] ?? '';

if (
    !$csrfToken ||
    !$postedToken ||
    !hash_equals(
        $csrfToken,
        $postedToken
    )
) {

    $_SESSION['flash_error'] =
        'Sesi tidak valid. Silakan coba lagi.';

    header(
        'Location: /pages/barang/kategori/'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {

    $_SESSION['flash_error'] =
        'Kategori tidak ditemukan.';

    header(
        'Location: /pages/barang/kategori/'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| AMBIL KATEGORI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        status
    FROM product_categories
    WHERE id = :id
      AND store_id = :store_id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id,
    ':store_id' => $authStoreId
]);

$category = $stmt->fetch();


if (!$category) {

    $_SESSION['flash_error'] =
        'Kategori tidak ditemukan.';

    header(
        'Location: /pages/barang/kategori/'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| TOGGLE
|--------------------------------------------------------------------------
*/

$newStatus =
    $category['status'] === 'ACTIVE'
        ? 'INACTIVE'
        : 'ACTIVE';


/*
|--------------------------------------------------------------------------
| SIMPAN
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    $stmt = $pdo->prepare("
        UPDATE product_categories
        SET
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND store_id = :store_id
    ");

    $stmt->execute([
        ':status' => $newStatus,
        ':id' => $id,
        ':store_id' => $authStoreId
    ]);


    /*
    |--------------------------------------------------------------------------
    | AUDIT
    |--------------------------------------------------------------------------
    */

    $action =
        $newStatus === 'ACTIVE'
            ? 'ACTIVATE'
            : 'DEACTIVATE';


    $description =
        $newStatus === 'ACTIVE'
            ? 'Mengaktifkan kategori: '
                . $category['name']
            : 'Menonaktifkan kategori: '
                . $category['name'];


    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (
            store_id,
            user_id,
            action,
            table_name,
            record_id,
            description
        )
        VALUES (
            :store_id,
            :user_id,
            :action,
            'product_categories',
            :record_id,
            :description
        )
    ");

    $stmt->execute([
        ':store_id' =>
            $authStoreId,

        ':user_id' =>
            $_SESSION['user_id'] ?? null,

        ':action' =>
            $action,

        ':record_id' =>
            $id,

        ':description' =>
            $description
    ]);


    $pdo->commit();


    if ($newStatus === 'ACTIVE') {

        $_SESSION['flash_success'] =
            'Kategori berhasil diaktifkan.';

    } else {

        $_SESSION['flash_success'] =
            'Kategori berhasil dinonaktifkan.';
    }


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['flash_error'] =
        'Status kategori gagal diubah.';
}


header(
    'Location: /pages/barang/kategori/'
);

exit;