<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/barang/');
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrfToken = $_SESSION['csrf_token'] ?? '';
$postedToken = $_POST['csrf_token'] ?? '';

if (
    !$csrfToken ||
    !$postedToken ||
    !hash_equals($csrfToken, $postedToken)
) {
    $_SESSION['flash_error'] =
        'Sesi tidak valid. Silakan coba lagi.';

    header('Location: /pages/barang/');
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
        'Barang tidak ditemukan.';

    header('Location: /pages/barang/');
    exit;
}


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | AMBIL DATA BARANG
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            sku,
            product_type,
            status
        FROM products
        WHERE id = :id
          AND store_id = :store_id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id,
        ':store_id' => $authStoreId
    ]);

    $product = $stmt->fetch();


    if (!$product) {
        throw new Exception(
            'Barang tidak ditemukan.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */

    $newStatus =
        $product['status'] === 'ACTIVE'
            ? 'INACTIVE'
            : 'ACTIVE';


    $stmt = $pdo->prepare("
        UPDATE products
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
    | TITIPAN
    |--------------------------------------------------------------------------
    |
    | Jika produk titipan ikut diaktifkan/nonaktifkan,
    | status consignments juga mengikuti.
    |
    */

    if ($product['product_type'] === 'TITIPAN') {

        $stmt = $pdo->prepare("
            UPDATE consignments
            SET
                status = :status
            WHERE product_id = :product_id
              AND store_id = :store_id
        ");

        $stmt->execute([
            ':status' => $newStatus,
            ':product_id' => $id,
            ':store_id' => $authStoreId
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | AUDIT LOG
    |--------------------------------------------------------------------------
    */

    $action =
        $newStatus === 'ACTIVE'
            ? 'ACTIVATE'
            : 'DEACTIVATE';

    $description =
        ($newStatus === 'ACTIVE'
            ? 'Mengaktifkan barang: '
            : 'Menonaktifkan barang: ')
        . $product['name']
        . ' (SKU: '
        . $product['sku']
        . ')';


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
            'products',
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


    /*
    |--------------------------------------------------------------------------
    | FLASH
    |--------------------------------------------------------------------------
    */

    if ($newStatus === 'ACTIVE') {

        $_SESSION['flash_success'] =
            'Barang berhasil diaktifkan.';

    } else {

        $_SESSION['flash_success'] =
            'Barang berhasil dinonaktifkan.';
    }


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['flash_error'] =
        'Status barang gagal diubah.';
}


header('Location: /pages/barang/');
exit;