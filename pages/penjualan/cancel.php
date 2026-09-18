<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function redirectWithError($message, $id = null)
{
    $url = '/pages/penjualan/';

    if ($id) {
        $url = '/pages/penjualan/view.php?id=' . (int) $id;
    }

    $url .= (strpos($url, '?') !== false ? '&' : '?')
        . 'error=' . urlencode($message);

    header('Location: ' . $url);
    exit;
}

function redirectWithSuccess($message, $id)
{
    header(
        'Location: /pages/penjualan/view.php?id='
        . (int) $id
        . '&success='
        . urlencode($message)
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| Hanya POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/penjualan/');
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    redirectWithError('Permintaan tidak valid. Silakan coba lagi.');
}


/*
|--------------------------------------------------------------------------
| ID transaksi
|--------------------------------------------------------------------------
*/

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    redirectWithError('Transaksi tidak ditemukan.');
}


/*
|--------------------------------------------------------------------------
| Mulai transaksi database
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock transaksi
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            invoice_number,
            status
        FROM sales
        WHERE id = :sale_id
          AND store_id = :sale_store_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':sale_id' => $id,
        ':sale_store_id' => $authStoreId
    ]);

    $sale = $stmt->fetch();

    if (!$sale) {
        throw new Exception('Transaksi tidak ditemukan.');
    }


    /*
    |--------------------------------------------------------------------------
    | Pastikan masih COMPLETED
    |--------------------------------------------------------------------------
    */

    if ($sale['status'] !== 'COMPLETED') {
        throw new Exception(
            'Transaksi ini sudah dibatalkan sebelumnya.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Ambil item transaksi
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            si.id,
            si.product_id,
            si.quantity,
            si.consignor_amount,
            p.name AS product_name,
            p.product_type
        FROM sale_items si
        INNER JOIN products p
            ON p.id = si.product_id
           AND p.store_id = si.store_id
        WHERE si.sale_id = :sale_id
          AND si.store_id = :item_store_id
        ORDER BY si.id ASC
        FOR UPDATE
    ");

    $stmt->execute([
        ':sale_id' => $id,
        ':item_store_id' => $authStoreId
    ]);

    $items = $stmt->fetchAll();

    if (empty($items)) {
        throw new Exception(
            'Transaksi tidak memiliki barang.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Siapkan pembalikan pembayaran penitip
    |--------------------------------------------------------------------------
    |
    | Jika sebagian/seluruh sale item sudah masuk settlement, settlement
    | tersebut ikut dibalik:
    |
    | - settlement_items untuk item penjualan ini dihapus
    | - total settlement dihitung ulang
    | - jika sudah tidak punya item, settlement ikut dihapus
    |
    | Dengan begitu uang bagi penitip tidak tetap tercatat setelah
    | transaksi penjualannya dibatalkan.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            cs.id,
            cs.settlement_number
        FROM consignor_settlement_items csi
        INNER JOIN consignor_settlements cs
            ON cs.id = csi.settlement_id
        INNER JOIN sale_items si
            ON si.id = csi.sale_item_id
        WHERE si.sale_id = :settlement_sale_id
          AND si.store_id = :settlement_item_store_id
          AND cs.store_id = :settlement_store_id
        FOR UPDATE
    ");

    $stmt->execute([
        ':settlement_sale_id'       => $id,
        ':settlement_item_store_id' => $authStoreId,
        ':settlement_store_id'      => $authStoreId
    ]);

    $affectedSettlements = $stmt->fetchAll();

    if (!empty($affectedSettlements)) {
        $stmtDeleteSettlementItems = $pdo->prepare("
            DELETE csi
            FROM consignor_settlement_items csi
            INNER JOIN sale_items si
                ON si.id = csi.sale_item_id
            WHERE si.sale_id = :delete_settlement_sale_id
              AND si.store_id = :delete_settlement_item_store_id
              AND csi.settlement_id = :delete_settlement_id
        ");

        $stmtRecalcSettlement = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM consignor_settlement_items
            WHERE settlement_id = :recalc_settlement_id
              AND store_id = :recalc_store_id
        ");

        $stmtDeleteSettlement = $pdo->prepare("
            DELETE FROM consignor_settlements
            WHERE id = :delete_settlement_header_id
              AND store_id = :delete_settlement_header_store_id
        ");

        $stmtUpdateSettlement = $pdo->prepare("
            UPDATE consignor_settlements
            SET total_amount = :new_settlement_total
            WHERE id = :update_settlement_id
              AND store_id = :update_settlement_store_id
        ");

        foreach ($affectedSettlements as $settlement) {
            $settlementId = (int) $settlement['id'];

            $stmtDeleteSettlementItems->execute([
                ':delete_settlement_sale_id'       => $id,
                ':delete_settlement_item_store_id' => $authStoreId,
                ':delete_settlement_id'             => $settlementId
            ]);

            $stmtRecalcSettlement->execute([
                ':recalc_settlement_id' => $settlementId,
                ':recalc_store_id'      => $authStoreId
            ]);

            $remainingAmount = (float) $stmtRecalcSettlement->fetchColumn();

            if ($remainingAmount <= 0) {
                $stmtDeleteSettlement->execute([
                    ':delete_settlement_header_id'    => $settlementId,
                    ':delete_settlement_header_store_id' => $authStoreId
                ]);
            } else {
                $stmtUpdateSettlement->execute([
                    ':new_settlement_total' => $remainingAmount,
                    ':update_settlement_id' => $settlementId,
                    ':update_settlement_store_id' => $authStoreId
                ]);
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Kembalikan stok
    |--------------------------------------------------------------------------
    */

    foreach ($items as $item) {

        /*
        |--------------------------------------------------------------------------
        | Lock product
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                current_stock
            FROM products
            WHERE id = :product_id
              AND store_id = :product_store_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            ':product_id' => $item['product_id'],
            ':product_store_id' => $authStoreId
        ]);

        $product = $stmt->fetch();

        if (!$product) {
            throw new Exception(
                'Produk "' . $item['product_name'] . '" tidak ditemukan.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Tambahkan kembali stok
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE products
            SET current_stock = current_stock + :qty_add
            WHERE id = :product_id
              AND store_id = :update_product_store_id
        ");

        $stmt->execute([
            ':qty_add'    => (int) $item['quantity'],
            ':product_id' => $item['product_id'],
            ':update_product_store_id' => $authStoreId
        ]);


        /*
        |--------------------------------------------------------------------------
        | Stock movement
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (
                store_id,
                product_id,
                movement_type,
                quantity,
                reference_type,
                reference_id,
                notes,
                created_by
            )
            VALUES (
                :store_id,
                :product_id,
                'RETUR',
                :quantity,
                'SALE_CANCEL',
                :reference_id,
                :notes,
                :created_by
            )
        ");

        $stmt->execute([
            ':store_id'    => $authStoreId,
            ':product_id'  => $item['product_id'],
            ':quantity'    => (int) $item['quantity'],
            ':reference_id'=> $id,
            ':notes'       => 'Pengembalian stok karena pembatalan penjualan ' . $sale['invoice_number'],
            ':created_by'  => $_SESSION['user_id'] ?? null
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Ubah status transaksi
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE sales
        SET status = 'CANCELLED'
        WHERE id = :sale_id
          AND store_id = :cancel_store_id
          AND status = 'COMPLETED'
    ");

    $stmt->execute([
        ':sale_id' => $id,
        ':cancel_store_id' => $authStoreId
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new Exception(
            'Status transaksi gagal diperbarui.'
        );
    }


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
            description
        )
        VALUES (
            :store_id,
            :user_id,
            'CANCEL',
            'sales',
            :record_id,
            :description
        )
    ");

    $stmt->execute([
        ':store_id'    => $authStoreId,
        ':user_id'     => $_SESSION['user_id'] ?? null,
        ':record_id'   => $id,
        ':description' => 'Membatalkan penjualan ' . $sale['invoice_number']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    redirectWithSuccess(
        'Transaksi ' . $sale['invoice_number'] . ' berhasil dibatalkan dan stok sudah dikembalikan.',
        $id
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirectWithError(
        'Penjualan gagal dibatalkan: ' . $e->getMessage(),
        $id
    );
}