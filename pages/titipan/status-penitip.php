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
| HANYA POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/titipan/penitip.php');
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
        'Location: /pages/titipan/penitip.php?error=csrf'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/

$id = isset($_POST['id'])
    ? (int) $_POST['id']
    : 0;

if ($id <= 0) {
    header(
        'Location: /pages/titipan/penitip.php?error=invalid'
    );
    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | AMBIL DATA PENITIP
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            status
        FROM consignors
        WHERE id = :id
        AND store_id = $storeId
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $consignor = $stmt->fetch();

    if (!$consignor) {
        header(
            'Location: /pages/titipan/penitip.php?error=notfound'
        );
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS BARU
    |--------------------------------------------------------------------------
    */

    $oldStatus = $consignor['status'];

    $newStatus =
        $oldStatus === 'ACTIVE'
            ? 'INACTIVE'
            : 'ACTIVE';


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    $stmt = $pdo->prepare("
        UPDATE consignors
        SET status = :status
        WHERE id = :id
            AND store_id = $storeId
    ");

    $stmt->execute([
        ':status' => $newStatus,
        ':id'     => $id
    ]);


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
            ? 'Mengaktifkan penitip: '
            : 'Menonaktifkan penitip: ')
        . $consignor['name'];


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
            'consignors',
            :record_id,
            :description
        )
    ");

    $stmt->execute([
        ':user_id'    => $_SESSION['user_id'] ?? null,
        ':action'     => $action,
        ':record_id'  => $id,
        ':description'=> $description
    ]);


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */

    $message =
        $newStatus === 'ACTIVE'
            ? 'activated'
            : 'deactivated';


    header(
        'Location: /pages/titipan/penitip.php?success=' . $message
    );

    exit;


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header(
        'Location: /pages/titipan/penitip.php?error=database'
    );

    exit;
}
