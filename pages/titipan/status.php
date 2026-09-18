<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/auth.php';
require_once '../../config/database.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) { http_response_code(403); exit('Store aktif tidak ditemukan.'); }


/*
|--------------------------------------------------------------------------
| POST ONLY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /pages/titipan/index.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
) {

    header(
        'Location: /pages/titipan/index.php?error=csrf'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/

$id =
    isset($_POST['id'])
        ? (int) $_POST['id']
        : 0;

if ($id <= 0) {

    header(
        'Location: /pages/titipan/index.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        product_type,
        status
    FROM products
    WHERE id = :id
    AND store_id = $storeId
    AND product_type = 'TITIPAN'
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$product =
    $stmt->fetch();


if (!$product) {

    header(
        'Location: /pages/titipan/index.php'
    );

    exit;
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


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | UPDATE PRODUCT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE products
        SET status = :status
        WHERE id = :id
        AND store_id = $storeId
    ");

    $stmt->execute([
        ':status' => $newStatus,
        ':id' => $id,
    ]);


    /*
    |--------------------------------------------------------------------------
    | UPDATE CONSIGNMENT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE consignments
        SET status = :status
        WHERE product_id = :product_id
        AND store_id = $storeId
    ");

    $stmt->execute([
        ':status' => $newStatus,
        ':product_id' => $id,
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
            ? 'Mengaktifkan kembali barang titipan: '
            : 'Menonaktifkan barang titipan: ';

    $description .=
        $product['name'];


    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (
            store_id,
            user_id,
            action,
            table_name,
            record_id,
            description
        ) VALUES (
            $storeId,
            :user_id,
            :action,
            'products',
            :record_id,
            :description
        )
    ");

    $stmt->execute([
        ':user_id' =>
            $_SESSION['user_id'] ?? null,

        ':action' =>
            $action,

        ':record_id' =>
            $id,

        ':description' =>
            $description,
    ]);


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */

    header(
        'Location: /pages/titipan/index.php?success=status'
    );

    exit;


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    header(
        'Location: /pages/titipan/index.php?error=status'
    );

    exit;
}