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

function redirectError($id, $message)
{
    if ($id) {
        header(
            'Location: /pages/penjualan/view.php?id=' .
            (int) $id .
            '&error=' .
            urlencode($message)
        );
    } else {
        header(
            'Location: /pages/penjualan/?error=' .
            urlencode($message)
        );
    }

    exit;
}


function redirectSuccess($id, $message)
{
    header(
        'Location: /pages/penjualan/view.php?id=' .
        (int) $id .
        '&success=' .
        urlencode($message)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| POST ONLY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/penjualan/');
    exit;
}


/*
|--------------------------------------------------------------------------
| SALE ID
|--------------------------------------------------------------------------
*/

$saleId = filter_input(
    INPUT_POST,
    'sale_id',
    FILTER_VALIDATE_INT
);

if (!$saleId) {
    redirectError(
        0,
        'Transaksi tidak ditemukan.'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $csrfToken
    )
) {
    redirectError(
        $saleId,
        'Permintaan tidak valid. Silakan coba lagi.'
    );
}


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

$saleDate =
    trim($_POST['sale_date'] ?? '');

$paymentMethod =
    strtoupper(
        trim($_POST['payment_method'] ?? 'CASH')
    );

$notes =
    trim($_POST['notes'] ?? '');


$allowedPayments = [
    'CASH',
    'QRIS',
    'TRANSFER',
    'OTHER'
];

if (!in_array(
    $paymentMethod,
    $allowedPayments,
    true
)) {
    redirectError(
        $saleId,
        'Metode pembayaran tidak valid.'
    );
}


/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

$timestamp = strtotime($saleDate);

if ($timestamp === false) {
    redirectError(
        $saleId,
        'Tanggal transaksi tidak valid.'
    );
}

$saleDateSql =
    date(
        'Y-m-d H:i:s',
        $timestamp
    );


/*
|--------------------------------------------------------------------------
| ITEMS
|--------------------------------------------------------------------------
*/

$postedItems =
    $_POST['items'] ?? [];

if (
    !is_array($postedItems) ||
    empty($postedItems)
) {
    redirectError(
        $saleId,
        'Minimal harus ada satu barang.'
    );
}


$cleanItems = [];


foreach ($postedItems as $item) {

    $productId =
        (int) (
            $item['product_id'] ?? 0
        );

    $quantity =
        (int) (
            $item['quantity'] ?? 0
        );

    if ($productId <= 0) {
        continue;
    }


    if ($quantity <= 0) {
        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | Produk tidak boleh muncul dua kali
    |--------------------------------------------------------------------------
    */

    if (isset($cleanItems[$productId])) {

        redirectError(
            $saleId,
            'Satu barang tidak boleh dimasukkan dua kali.'
        );
    }


    $cleanItems[$productId] = [
        'product_id' =>
            $productId,

        'quantity' =>
            $quantity
    ];
}


$cleanItems =
    array_values($cleanItems);


if (empty($cleanItems)) {
    redirectError(
        $saleId,
        'Minimal harus ada satu barang.'
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOCK SALE
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
        ':sale_id' => $saleId,
        ':sale_store_id' => $authStoreId
    ]);

    $sale =
        $stmt->fetch();


    if (!$sale) {
        throw new Exception(
            'Transaksi tidak ditemukan.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Hanya transaksi COMPLETED
    |--------------------------------------------------------------------------
    */

    if ($sale['status'] !== 'COMPLETED') {

        throw new Exception(
            'Transaksi yang sudah dibatalkan tidak dapat diedit.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CEK SETTLEMENT PENITIP
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total
        FROM consignor_settlement_items csi
        INNER JOIN sale_items si
            ON si.id = csi.sale_item_id
        WHERE si.sale_id = :sale_id
          AND si.store_id = :settlement_store_id
    ");

    $stmt->execute([
        ':sale_id' => $saleId,
        ':settlement_store_id' => $authStoreId
    ]);

    $settled =
        (int) (
            $stmt->fetch()['total'] ?? 0
        );


    if ($settled > 0) {

        throw new Exception(
            'Transaksi tidak dapat diedit karena sudah masuk pembayaran ke penitip.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ITEM LAMA
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            product_id,
            quantity,
            selling_price
        FROM sale_items
        WHERE sale_id = :sale_id
          AND store_id = :old_items_store_id
        ORDER BY id ASC
        FOR UPDATE
    ");

    $stmt->execute([
        ':sale_id' => $saleId,
        ':old_items_store_id' => $authStoreId
    ]);

    $oldItems =
        $stmt->fetchAll();

    // Harga jual transaksi lama adalah harga yang sah untuk edit.
    // Jangan menerima harga dari browser agar markup tidak bisa terjadi.
    $oldSellingPrices = [];
    foreach ($oldItems as $oldItem) {
        $oldSellingPrices[(int) $oldItem['product_id']] = (float) $oldItem['selling_price'];
    }


    if (empty($oldItems)) {

        throw new Exception(
            'Barang transaksi lama tidak ditemukan.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | KEMBALIKAN STOK LAMA
    |--------------------------------------------------------------------------
    */

    foreach ($oldItems as $oldItem) {

        $stmt = $pdo->prepare("
            UPDATE products
            SET current_stock =
                current_stock + :qty_restore
            WHERE id = :product_id
              AND store_id = :restore_store_id
        ");

        $stmt->execute([
            ':qty_restore' =>
                (int) $oldItem['quantity'],

            ':product_id' =>
                (int) $oldItem['product_id'],
            ':restore_store_id' =>
                $authStoreId
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | HAPUS MOVEMENT PENJUALAN LAMA
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        DELETE FROM stock_movements
        WHERE reference_type = 'SALE'
          AND reference_id = :sale_id
          AND movement_type = 'PENJUALAN'
          AND store_id = :movement_store_id
    ");

    $stmt->execute([
        ':sale_id' => $saleId,
        ':movement_store_id' => $authStoreId
    ]);


    /*
    |--------------------------------------------------------------------------
    | HITUNG ITEM BARU
    |--------------------------------------------------------------------------
    */

    $calculatedItems = [];

    $totalAmount = 0;


    foreach ($cleanItems as $item) {

        /*
        |--------------------------------------------------------------------------
        | LOCK PRODUCT
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                product_type,
                buying_price,
                current_stock
            FROM products
            WHERE id = :product_id
              AND store_id = :product_store_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            ':product_id' =>
                $item['product_id'],
            ':product_store_id' =>
                $authStoreId
        ]);

        $product =
            $stmt->fetch();


        if (!$product) {

            throw new Exception(
                'Produk tidak ditemukan.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | STOK
        |--------------------------------------------------------------------------
        */

        if (
            (int) $product['current_stock']
            <
            (int) $item['quantity']
        ) {

            throw new Exception(
                'Stok "' .
                $product['name'] .
                '" tidak cukup.'
            );
        }


        if (!array_key_exists((int) $item['product_id'], $oldSellingPrices)) {
            throw new Exception(
                'Barang baru tidak dapat ditambahkan saat mengedit transaksi. Gunakan Master Data Barang dan buat transaksi baru jika diperlukan.'
            );
        }

        $sellingPrice =
            $oldSellingPrices[(int) $item['product_id']];

        $buyingPrice =
            (float) $product['buying_price'];

        $quantity =
            (int) $item['quantity'];

        $subtotal =
            $sellingPrice * $quantity;


        $profit = 0;

        $consignorAmount = 0;

        $storeAmount = 0;


        /*
        |--------------------------------------------------------------------------
        | PRODUK TOKO
        |--------------------------------------------------------------------------
        */

        if (
            $product['product_type'] === 'TOKO'
        ) {

            $profit =
                (
                    $sellingPrice -
                    $buyingPrice
                ) * $quantity;

            $storeAmount =
                $profit;
        }


        /*
        |--------------------------------------------------------------------------
        | PRODUK TITIPAN
        |--------------------------------------------------------------------------
        */

        elseif (
            $product['product_type'] === 'TITIPAN'
        ) {

            $stmt = $pdo->prepare("
                SELECT
                    cs.id,
                    cs.initial_price,
                    cs.fee_type,
                    cs.fee_value
                FROM consignments cs
                WHERE cs.product_id = :product_id
                  AND cs.store_id = :consignment_store_id
                  AND cs.status = 'ACTIVE'
                ORDER BY cs.id DESC
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                ':product_id' =>
                    $item['product_id'],
                ':consignment_store_id' =>
                    $authStoreId
            ]);

            $consignment =
                $stmt->fetch();


            if (!$consignment) {

                throw new Exception(
                    'Data penitipan untuk "' .
                    $product['name'] .
                    '" tidak ditemukan.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Fee persentase
            |--------------------------------------------------------------------------
            */

            if (
                $consignment['fee_type']
                === 'PERCENTAGE'
            ) {

                $storeAmount =
                    $subtotal *
                    (
                        (float)
                        $consignment['fee_value']
                        / 100
                    );

            }


            /*
            |--------------------------------------------------------------------------
            | Fee nominal
            |--------------------------------------------------------------------------
            */

            else {

                $storeAmount =
                    (float)
                    $consignment['fee_value']
                    *
                    $quantity;
            }


            $consignorAmount =
                $subtotal -
                $storeAmount;

            $profit =
                $storeAmount;
        }


        $totalAmount +=
            $subtotal;


        $calculatedItems[] = [

            'product_id' =>
                $item['product_id'],

            'quantity' =>
                $quantity,

            'selling_price' =>
                $sellingPrice,

            'buying_price' =>
                $buyingPrice,

            'subtotal' =>
                $subtotal,

            'profit' =>
                $profit,

            'consignor_amount' =>
                $consignorAmount,

            'store_amount' =>
                $storeAmount
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE SALES
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE sales
        SET
            sale_date = :sale_date,
            total_amount = :total_amount,
            payment_method = :payment_method,
            notes = :notes
        WHERE id = :sale_id
          AND store_id = :update_sale_store_id
          AND status = 'COMPLETED'
    ");

    $stmt->execute([

        ':sale_date' =>
            $saleDateSql,

        ':total_amount' =>
            $totalAmount,

        ':payment_method' =>
            $paymentMethod,

        ':notes' =>
            $notes,

        ':sale_id' =>
            $saleId,
        ':update_sale_store_id' =>
            $authStoreId
    ]);


    /*
    |--------------------------------------------------------------------------
    | HAPUS ITEM LAMA
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        DELETE FROM sale_items
        WHERE sale_id = :sale_id
          AND store_id = :delete_items_store_id
    ");

    $stmt->execute([
        ':sale_id' => $saleId,
        ':delete_items_store_id' => $authStoreId
    ]);


    /*
    |--------------------------------------------------------------------------
    | INSERT ITEM BARU
    |--------------------------------------------------------------------------
    */

    foreach (
        $calculatedItems
        as $item
    ) {


        /*
        |--------------------------------------------------------------------------
        | SALE ITEM
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO sale_items (
                store_id,
                sale_id,
                product_id,
                quantity,
                selling_price,
                buying_price,
                subtotal,
                profit,
                consignor_amount,
                store_amount
            )
            VALUES (
                :store_id,
                :sale_id,
                :product_id,
                :quantity,
                :selling_price,
                :buying_price,
                :subtotal,
                :profit,
                :consignor_amount,
                :store_amount
            )
        ");

        $stmt->execute([

            ':store_id' =>
                $authStoreId,

            ':sale_id' =>
                $saleId,

            ':product_id' =>
                $item['product_id'],

            ':quantity' =>
                $item['quantity'],

            ':selling_price' =>
                $item['selling_price'],

            ':buying_price' =>
                $item['buying_price'],

            ':subtotal' =>
                $item['subtotal'],

            ':profit' =>
                $item['profit'],

            ':consignor_amount' =>
                $item['consignor_amount'],

            ':store_amount' =>
                $item['store_amount']
        ]);


        /*
        |--------------------------------------------------------------------------
        | KURANGI STOK
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE products
            SET current_stock =
                current_stock - :qty_sale
            WHERE id = :product_id
              AND store_id = :sale_stock_store_id
              AND current_stock >= :qty_check
        ");

        $stmt->execute([

            ':qty_sale' =>
                $item['quantity'],

            ':product_id' =>
                $item['product_id'],

            ':qty_check' =>
                $item['quantity'],
            ':sale_stock_store_id' =>
                $authStoreId
        ]);


        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                'Stok berubah saat transaksi diedit. Silakan coba lagi.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | STOCK MOVEMENT
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
                'PENJUALAN',
                :quantity,
                'SALE',
                :reference_id,
                :notes,
                :created_by
            )
        ");

        $stmt->execute([

            ':store_id' =>
                $authStoreId,

            ':product_id' =>
                $item['product_id'],

            ':quantity' =>
                $item['quantity'],

            ':reference_id' =>
                $saleId,

            ':notes' =>
                'Penjualan ' .
                $sale['invoice_number'] .
                ' - diperbarui',

            ':created_by' =>
                $_SESSION['user_id'] ?? null
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | AUDIT LOG
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
            'UPDATE',
            'sales',
            :record_id,
            :description
        )
    ");

    $stmt->execute([

        ':store_id' =>
            $authStoreId,

        ':user_id' =>
            $_SESSION['user_id'] ?? null,

        ':record_id' =>
            $saleId,

        ':description' =>
            'Mengubah transaksi penjualan ' .
            $sale['invoice_number']
    ]);


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    redirectSuccess(
        $saleId,
        'Transaksi berhasil diperbarui.'
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    redirectError(
        $saleId,
        'Penjualan gagal diedit: ' .
        $e->getMessage()
    );
}
