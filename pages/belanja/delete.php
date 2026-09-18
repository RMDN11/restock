<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';


/*
|--------------------------------------------------------------------------
| HANYA POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /pages/belanja/'
    );

    exit;
}


try {

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

        throw new Exception(
            'Token keamanan tidak valid.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | ID
    |--------------------------------------------------------------------------
    */

    $purchaseId =
        (int) ($_POST['id'] ?? 0);


    if ($purchaseId <= 0) {

        throw new Exception(
            'Transaksi tidak valid.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOCK PURCHASE
    |--------------------------------------------------------------------------
    */

    $stmtPurchase =
        $pdo->prepare("
            SELECT
                id,
                purchase_number
            FROM purchases
            WHERE id = :id
              AND store_id = :purchase_store_id
            LIMIT 1
            FOR UPDATE
        ");

    $stmtPurchase->execute([
        ':id' =>
            $purchaseId,
        ':purchase_store_id' =>
            $authStoreId
    ]);

    $purchase =
        $stmtPurchase->fetch();


    if (!$purchase) {

        throw new Exception(
            'Transaksi belanja tidak ditemukan.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | LOCK ITEMS
    |--------------------------------------------------------------------------
    */

    $stmtItems =
        $pdo->prepare("
            SELECT
                pi.product_id,
                pi.quantity,
                pr.name,
                pr.current_stock
            FROM purchase_items pi
            INNER JOIN products pr
                ON pr.id = pi.product_id
               AND pr.store_id = pi.store_id
            WHERE pi.purchase_id = :purchase_id
              AND pi.store_id = :item_store_id
            FOR UPDATE
        ");

    $stmtItems->execute([
        ':purchase_id' =>
            $purchaseId,
        ':item_store_id' =>
            $authStoreId
    ]);

    $items =
        $stmtItems->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | CEK STOK
    |--------------------------------------------------------------------------
    |
    | Jangan sampai menghapus transaksi lalu stok menjadi minus.
    |--------------------------------------------------------------------------
    */

    foreach ($items as $item) {

        if (
            (int) $item['current_stock']
            <
            (int) $item['quantity']
        ) {

            throw new Exception(
                'Stok "' .
                $item['name'] .
                '" tidak mencukupi untuk menghapus transaksi ini.'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | KURANGI STOK
    |--------------------------------------------------------------------------
    */

    $stmtStock =
        $pdo->prepare("
            UPDATE products
            SET current_stock =
                current_stock - :quantity
            WHERE id = :product_id
              AND store_id = :stock_store_id
        ");


    foreach ($items as $item) {

        $stmtStock->execute([
            ':quantity' =>
                $item['quantity'],
            ':product_id' =>
                $item['product_id'],
            ':stock_store_id' =>
                $authStoreId
        ]);

    }


    /*
    |--------------------------------------------------------------------------
    | HAPUS STOCK MOVEMENT
    |--------------------------------------------------------------------------
    */

    $stmtMovement =
        $pdo->prepare("
            DELETE FROM stock_movements
            WHERE movement_type = 'BELANJA'
              AND reference_type = 'PURCHASE'
              AND reference_id = :purchase_id
              AND store_id = :movement_store_id
        ");

    $stmtMovement->execute([
        ':purchase_id' =>
            $purchaseId,
        ':movement_store_id' =>
            $authStoreId
    ]);


    /*
    |--------------------------------------------------------------------------
    | HAPUS PURCHASE
    |--------------------------------------------------------------------------
    |
    | purchase_items ikut terhapus karena FK cascade.
    |--------------------------------------------------------------------------
    */

    $stmtDelete =
        $pdo->prepare("
            DELETE FROM purchases
            WHERE id = :id
              AND store_id = :delete_store_id
        ");

    $stmtDelete->execute([
        ':id' =>
            $purchaseId,
        ':delete_store_id' =>
            $authStoreId
    ]);


    /*
    |--------------------------------------------------------------------------
    | AUDIT
    |--------------------------------------------------------------------------
    */

    $stmtAudit =
        $pdo->prepare("
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
                'DELETE',
                'purchases',
                :record_id,
                :description
            )
        ");

    $stmtAudit->execute([
        ':store_id' =>
            $authStoreId,
        ':user_id' =>
            $_SESSION['user_id'] ?? null,
        ':record_id' =>
            $purchaseId,
        ':description' =>
            'Menghapus transaksi belanja ' .
            $purchase['purchase_number']
    ]);


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    $_SESSION['success'] =
        'Transaksi belanja berhasil dihapus dan stok sudah disesuaikan.';


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    $_SESSION['error'] =
        $e->getMessage();

}


header(
    'Location: /pages/belanja/'
);

exit;