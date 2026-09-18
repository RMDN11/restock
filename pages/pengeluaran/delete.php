<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/auth.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) {
    http_response_code(403);
    exit('Store aktif tidak ditemukan.');
}


/*
|--------------------------------------------------------------------------
| POST ONLY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /pages/pengeluaran/'
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
        'Location: /pages/pengeluaran/?error=csrf'
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
        'Location: /pages/pengeluaran/?error=invalid'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD DATA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        description,
        amount
    FROM expenses
    WHERE id = :expense_id
      AND store_id = :expense_store_id
    LIMIT 1
");

$stmt->execute([
    ':expense_id' => $id,
    ':expense_store_id' => $storeId
]);

$expense =
    $stmt->fetch();


if (!$expense) {

    header(
        'Location: /pages/pengeluaran/?error=notfound'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        DELETE FROM expenses
        WHERE id = :expense_id
          AND store_id = :expense_store_id
    ");

    $stmt->execute([
        ':expense_id' => $id,
        ':expense_store_id' => $storeId
    ]);


    /*
    |--------------------------------------------------------------------------
    | AUDIT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (
            store_id,
            user_id,
            action,
            table_name,
            record_id,
            description
        ) VALUES (
            :store_id,
            :user_id,
            'DELETE',
            'expenses',
            :record_id,
            :description
        )
    ");

    $stmt->execute([

        ':store_id' => $storeId,
        ':user_id' =>
            $_SESSION['user_id'] ?? null,

        ':record_id' =>
            $id,

        ':description' =>
            'Menghapus pengeluaran: ' .
            $expense['description'] .
            ' sebesar Rp' .
            number_format(
                (float) $expense['amount'],
                0,
                ',',
                '.'
            ),
    ]);


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */

    header(
        'Location: /pages/pengeluaran/?success=deleted'
    );

    exit;


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header(
        'Location: /pages/pengeluaran/?error=delete'
    );

    exit;
}