<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';


/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function rupiah($value): string
{
    return 'Rp' . number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}


function parseMoney($value): float
{
    if ($value === null || $value === '') {
        return 0;
    }

    $value = str_replace('.', '', (string) $value);
    $value = str_replace(',', '.', $value);

    return (float) $value;
}


/*
|--------------------------------------------------------------------------
| GET ID
|--------------------------------------------------------------------------
*/

$purchaseId =
    (int) ($_GET['id'] ?? 0);

if ($purchaseId <= 0) {

    header(
        'Location: /pages/belanja/'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| AMBIL TRANSAKSI
|--------------------------------------------------------------------------
*/

$stmtPurchase = $pdo->prepare("
    SELECT
        p.*,
        s.name AS supplier_name
    FROM purchases p
    LEFT JOIN suppliers s
        ON s.id = p.supplier_id
       AND s.store_id = p.store_id
    WHERE p.id = :id
      AND p.store_id = :purchase_store_id
    LIMIT 1
");

$stmtPurchase->execute([
    ':id' => $purchaseId,
    ':purchase_store_id' => $authStoreId
]);

$purchase =
    $stmtPurchase->fetch();


if (!$purchase) {

    header(
        'Location: /pages/belanja/'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| AMBIL ITEMS
|--------------------------------------------------------------------------
*/

$stmtItems = $pdo->prepare("
    SELECT
        pi.*,
        pr.name AS product_name,
        pr.sku,
        pr.unit,
        pr.status AS product_status
    FROM purchase_items pi
    INNER JOIN products pr
        ON pr.id = pi.product_id
       AND pr.store_id = pi.store_id
    WHERE pi.purchase_id = :purchase_id
      AND pi.store_id = :item_store_id
    ORDER BY pi.id ASC
");

$stmtItems->execute([
    ':purchase_id' => $purchaseId,
    ':item_store_id' => $authStoreId
]);

$existingItems =
    $stmtItems->fetchAll();


/*
|--------------------------------------------------------------------------
| DATA PRODUCT
|--------------------------------------------------------------------------
|
| Barang aktif + barang yang sedang digunakan
| pada transaksi lama.
|--------------------------------------------------------------------------
*/

$stmtProducts = $pdo->prepare("
    SELECT
        id,
        sku,
        name,
        unit,
        buying_price,
        current_stock,
        status
    FROM products
    WHERE product_type = 'TOKO'
      AND store_id = :products_store_id
      AND (
          status = 'ACTIVE'
          OR id IN (
              SELECT product_id
              FROM purchase_items
              WHERE purchase_id = :products_purchase_id
                AND store_id = :products_item_store_id
          )
      )
    ORDER BY name ASC
");

$stmtProducts->execute([
    ':products_store_id' => $authStoreId,
    ':products_purchase_id' => $purchaseId,
    ':products_item_store_id' => $authStoreId
]);

$products =
    $stmtProducts->fetchAll();


/*
|--------------------------------------------------------------------------
| DEFAULT FORM
|--------------------------------------------------------------------------
*/

$purchaseNumber =
    $purchase['purchase_number'];

$purchaseDate =
    date(
        'Y-m-d\TH:i',
        strtotime(
            $purchase['purchase_date']
        )
    );

$supplierName =
    $purchase['supplier_name'] ?? '';

$notes =
    $purchase['notes'] ?? '';

$error = null;


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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
        | INPUT
        |--------------------------------------------------------------------------
        */

        $purchaseNumber =
            trim(
                $_POST['purchase_number'] ?? ''
            );

        $purchaseDateInput =
            trim(
                $_POST['purchase_date'] ?? ''
            );

        $supplierName =
            trim(
                $_POST['supplier_name'] ?? ''
            );

        $notes =
            trim(
                $_POST['notes'] ?? ''
            );


        $productIds =
            $_POST['product_id'] ?? [];

        $quantities =
            $_POST['quantity'] ?? [];

        $buyingPrices =
            $_POST['buying_price'] ?? [];


        /*
        |--------------------------------------------------------------------------
        | VALIDASI HEADER
        |--------------------------------------------------------------------------
        */

        if ($purchaseNumber === '') {

            throw new Exception(
                'Nomor belanja wajib diisi.'
            );

        }


        if ($purchaseDateInput === '') {

            throw new Exception(
                'Tanggal belanja wajib diisi.'
            );

        }


        $timestamp =
            strtotime(
                $purchaseDateInput
            );


        if ($timestamp === false) {

            throw new Exception(
                'Tanggal belanja tidak valid.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | CEK NOMOR
        |--------------------------------------------------------------------------
        */

        $stmtNumber = $pdo->prepare("
            SELECT id
            FROM purchases
            WHERE purchase_number = :number
              AND id != :id
              AND store_id = :number_store_id
            LIMIT 1
        ");

        $stmtNumber->execute([
            ':number' =>
                $purchaseNumber,
            ':id' =>
                $purchaseId,
            ':number_store_id' =>
                $authStoreId
        ]);


        if ($stmtNumber->fetch()) {

            throw new Exception(
                'Nomor belanja sudah digunakan.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | BUILD ITEMS
        |--------------------------------------------------------------------------
        */

        $items = [];
        $totalAmount = 0;


        foreach ($productIds as $index => $productId) {

            $productId =
                (int) $productId;


            if ($productId <= 0) {
                continue;
            }


            $quantity =
                (int) (
                    $quantities[$index] ?? 0
                );


            $buyingPrice =
                parseMoney(
                    $buyingPrices[$index] ?? 0
                );


            if ($quantity <= 0) {

                throw new Exception(
                    'Jumlah barang harus lebih dari 0.'
                );

            }


            if ($buyingPrice < 0) {

                throw new Exception(
                    'Harga modal tidak boleh minus.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | PRODUCT CHECK
            |--------------------------------------------------------------------------
            */

            $stmtProductCheck = $pdo->prepare("
                SELECT
                    id,
                    name,
                    product_type,
                    status
                FROM products
                WHERE id = :id
                  AND store_id = :product_check_store_id
                LIMIT 1
                FOR UPDATE
            ");

            $stmtProductCheck->execute([
                ':id' =>
                    $productId,
                ':product_check_store_id' =>
                    $authStoreId
            ]);

            $product =
                $stmtProductCheck->fetch();


            if (!$product) {

                throw new Exception(
                    'Barang tidak ditemukan.'
                );

            }


            if ($product['product_type'] !== 'TOKO') {

                throw new Exception(
                    'Barang titipan tidak dapat digunakan dalam belanja.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | BARANG TIDAK AKTIF
            |--------------------------------------------------------------------------
            |
            | Barang lama yang sudah tidak aktif masih boleh
            | dipertahankan dalam transaksi edit.
            |--------------------------------------------------------------------------
            */

            if (
                $product['status'] !== 'ACTIVE'
                &&
                !in_array(
                    $productId,
                    array_map(
                        'intval',
                        array_column(
                            $existingItems,
                            'product_id'
                        )
                    ),
                    true
                )
            ) {

                throw new Exception(
                    'Barang "' .
                    $product['name'] .
                    '" sedang tidak aktif.'
                );

            }


            $subtotal =
                $quantity *
                $buyingPrice;


            $totalAmount +=
                $subtotal;


            $items[] = [
                'product_id' =>
                    $productId,
                'quantity' =>
                    $quantity,
                'buying_price' =>
                    $buyingPrice,
                'subtotal' =>
                    $subtotal
            ];

        }


        if (empty($items)) {

            throw new Exception(
                'Minimal tambahkan satu barang.'
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

        $stmtLockPurchase =
            $pdo->prepare("
                SELECT
                    id,
                    purchase_number
                FROM purchases
                WHERE id = :id
                  AND store_id = :lock_store_id
                FOR UPDATE
            ");

        $stmtLockPurchase->execute([
            ':id' =>
                $purchaseId,
            ':lock_store_id' =>
                $authStoreId
        ]);

        $lockedPurchase =
            $stmtLockPurchase->fetch();


        if (!$lockedPurchase) {

            throw new Exception(
                'Transaksi tidak ditemukan.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | LOCK OLD ITEMS
        |--------------------------------------------------------------------------
        */

        $stmtOldItems =
            $pdo->prepare("
                SELECT
                    product_id,
                    quantity
                FROM purchase_items
                WHERE purchase_id = :purchase_id
                  AND store_id = :old_items_store_id
                FOR UPDATE
            ");

        $stmtOldItems->execute([
            ':purchase_id' =>
                $purchaseId,
            ':old_items_store_id' =>
                $authStoreId
        ]);

        $oldItems =
            $stmtOldItems->fetchAll();


        /*
        |--------------------------------------------------------------------------
        | CEK STOK SEBELUM PEMBALIKAN
        |--------------------------------------------------------------------------
        |
        | Stok saat ini harus minimal sama dengan jumlah
        | yang sebelumnya dimasukkan transaksi.
        |--------------------------------------------------------------------------
        */

        foreach ($oldItems as $oldItem) {

            $stmtStockCheck =
                $pdo->prepare("
                    SELECT
                        id,
                        name,
                        current_stock
                    FROM products
                    WHERE id = :id
                      AND store_id = :old_stock_store_id
                    FOR UPDATE
                ");

            $stmtStockCheck->execute([
                ':id' =>
                    $oldItem['product_id'],
                ':old_stock_store_id' =>
                    $authStoreId
            ]);

            $stockProduct =
                $stmtStockCheck->fetch();


            if (!$stockProduct) {

                throw new Exception(
                    'Barang dari transaksi lama tidak ditemukan.'
                );

            }


            if (
                (int) $stockProduct['current_stock']
                <
                (int) $oldItem['quantity']
            ) {

                throw new Exception(
                    'Stok "' .
                    $stockProduct['name'] .
                    '" tidak mencukupi untuk mengedit transaksi ini.'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | REVERSE OLD STOCK
        |--------------------------------------------------------------------------
        */

        $stmtReverseStock =
            $pdo->prepare("
                UPDATE products
                SET current_stock =
                    current_stock - :quantity
                WHERE id = :product_id
                  AND store_id = :reverse_stock_store_id
            ");


        foreach ($oldItems as $oldItem) {

            $stmtReverseStock->execute([
                ':quantity' =>
                    $oldItem['quantity'],
                ':product_id' =>
                    $oldItem['product_id'],
                ':reverse_stock_store_id' =>
                    $authStoreId
            ]);

        }


        /*
        |--------------------------------------------------------------------------
        | DELETE OLD MOVEMENTS
        |--------------------------------------------------------------------------
        */

        $stmtDeleteMovement =
            $pdo->prepare("
                DELETE FROM stock_movements
                WHERE movement_type = 'BELANJA'
                  AND reference_type = 'PURCHASE'
                  AND reference_id = :purchase_id
                  AND store_id = :old_movement_store_id
            ");

        $stmtDeleteMovement->execute([
            ':purchase_id' =>
                $purchaseId,
            ':old_movement_store_id' =>
                $authStoreId
        ]);


        /*
        |--------------------------------------------------------------------------
        | SUPPLIER
        |--------------------------------------------------------------------------
        */

        $supplierId = null;


        if ($supplierName !== '') {

            $stmtSupplier =
                $pdo->prepare("
                    SELECT id
                    FROM suppliers
                    WHERE LOWER(name) = LOWER(:name)
                      AND store_id = :supplier_store_id
                    LIMIT 1
                ");

            $stmtSupplier->execute([
                ':name' =>
                    $supplierName,
                ':supplier_store_id' =>
                    $authStoreId
            ]);

            $existingSupplier =
                $stmtSupplier->fetch();


            if ($existingSupplier) {

                $supplierId =
                    (int) $existingSupplier['id'];

            } else {

                $stmtCreateSupplier =
                    $pdo->prepare("
                        INSERT INTO suppliers (
                            store_id,
                            name,
                            status
                        )
                        VALUES (
                            :supplier_store_id,
                            :name,
                            'ACTIVE'
                        )
                    ");

                $stmtCreateSupplier->execute([
                    ':supplier_store_id' =>
                        $authStoreId,
                    ':name' =>
                        $supplierName
                ]);

                $supplierId =
                    (int) $pdo->lastInsertId();

            }

        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE PURCHASE
        |--------------------------------------------------------------------------
        */

        $stmtUpdatePurchase =
            $pdo->prepare("
                UPDATE purchases
                SET
                    purchase_number = :purchase_number,
                    supplier_id = :supplier_id,
                    purchase_date = :purchase_date,
                    total_amount = :total_amount,
                    notes = :notes
                WHERE id = :id
                  AND store_id = :update_store_id
            ");

        $stmtUpdatePurchase->execute([
            ':purchase_number' =>
                $purchaseNumber,
            ':supplier_id' =>
                $supplierId,
            ':purchase_date' =>
                date(
                    'Y-m-d H:i:s',
                    $timestamp
                ),
            ':total_amount' =>
                $totalAmount,
            ':notes' =>
                $notes !== ''
                    ? $notes
                    : null,
            ':id' =>
                $purchaseId,
            ':update_store_id' =>
                $authStoreId
        ]);


        /*
        |--------------------------------------------------------------------------
        | DELETE OLD ITEMS
        |--------------------------------------------------------------------------
        */

        $stmtDeleteItems =
            $pdo->prepare("
                DELETE FROM purchase_items
                WHERE purchase_id = :purchase_id
                  AND store_id = :delete_items_store_id
            ");

        $stmtDeleteItems->execute([
            ':purchase_id' =>
                $purchaseId,
            ':delete_items_store_id' =>
                $authStoreId
        ]);


        /*
        |--------------------------------------------------------------------------
        | INSERT NEW ITEMS
        |--------------------------------------------------------------------------
        */

        $stmtInsertItem =
            $pdo->prepare("
                INSERT INTO purchase_items (
                    store_id,
                    purchase_id,
                    product_id,
                    quantity,
                    buying_price,
                    subtotal
                )
                VALUES (
                    :store_id,
                    :purchase_id,
                    :product_id,
                    :quantity,
                    :buying_price,
                    :subtotal
                )
            ");


        /*
        |--------------------------------------------------------------------------
        | ADD STOCK
        |--------------------------------------------------------------------------
        */

        $stmtAddStock =
            $pdo->prepare("
                UPDATE products
                SET
                    current_stock =
                        current_stock + :quantity,
                    buying_price =
                        :buying_price
                WHERE id = :product_id
                  AND store_id = :add_stock_store_id
            ");


        /*
        |--------------------------------------------------------------------------
        | NEW MOVEMENT
        |--------------------------------------------------------------------------
        */

        $stmtNewMovement =
            $pdo->prepare("
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
                    'BELANJA',
                    :quantity,
                    'PURCHASE',
                    :reference_id,
                    :notes,
                    :created_by
                )
            ");


        foreach ($items as $item) {

            $stmtInsertItem->execute([
                ':store_id' =>
                    $authStoreId,
                ':purchase_id' =>
                    $purchaseId,
                ':product_id' =>
                    $item['product_id'],
                ':quantity' =>
                    $item['quantity'],
                ':buying_price' =>
                    $item['buying_price'],
                ':subtotal' =>
                    $item['subtotal']
            ]);


            $stmtAddStock->execute([
                ':quantity' =>
                    $item['quantity'],
                ':buying_price' =>
                    $item['buying_price'],
                ':product_id' =>
                    $item['product_id'],
                ':add_stock_store_id' =>
                    $authStoreId
            ]);


            $stmtNewMovement->execute([
                ':store_id' =>
                    $authStoreId,
                ':product_id' =>
                    $item['product_id'],
                ':quantity' =>
                    $item['quantity'],
                ':reference_id' =>
                    $purchaseId,
                ':notes' =>
                    'Edit belanja ' .
                    $purchaseNumber,
                ':created_by' =>
                    $_SESSION['user_id'] ?? null
            ]);

        }


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
                    'UPDATE',
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
                'Mengubah transaksi belanja ' .
                $purchaseNumber .
                ' menjadi total ' .
                rupiah($totalAmount)
        ]);


        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        $_SESSION['success'] =
            'Transaksi belanja berhasil diperbarui dan stok sudah disesuaikan.';


        header(
            'Location: /pages/belanja/view.php?id=' .
            $purchaseId
        );

        exit;


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error =
            $e->getMessage();

    }

}
$pageTitle = 'Edit Belanja';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';


?>


<div class="main-content">


    <!-- MOBILE HEADER -->
<main class="p-4 md:p-6 lg:p-8">


        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">

            <div>

                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                    Edit Belanja
                </h1>

                <p class="mt-2 text-sm text-neutral-500">
                    Ubah transaksi dan barang yang dibeli.
                </p>

            </div>


            <a
                href="/pages/belanja/view.php?id=<?= $purchaseId ?>"
                class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl border border-neutral-200 bg-white text-neutral-700 text-sm font-medium hover:bg-neutral-50 transition"
            >

                <i
                    data-lucide="arrow-left"
                    class="w-4 h-4"
                ></i>

                Kembali

            </a>

        </div>


        <!-- ERROR -->
        <?php if ($error): ?>

            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">

                <div class="flex items-start gap-3">

                    <i
                        data-lucide="circle-alert"
                        class="w-5 h-5 text-red-500 shrink-0"
                    ></i>

                    <div>

                        <div class="font-medium text-red-700">
                            Belanja gagal diperbarui
                        </div>

                        <div class="text-sm text-red-600 mt-1">
                            <?= htmlspecialchars($error) ?>
                        </div>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            id="purchaseForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


            <!-- INFORMASI -->
            <section class="bento-card p-5 md:p-6 mb-5">

                <h2 class="font-semibold mb-5">
                    Informasi Belanja
                </h2>


                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">


                    <!-- NOMOR -->
                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Nomor Belanja
                        </label>

                        <input
                            type="text"
                            name="purchase_number"
                            value="<?= htmlspecialchars(
                                $purchaseNumber
                            ) ?>"
                            required
                            class="w-full h-11 px-4 rounded-xl border border-neutral-200 bg-neutral-50 text-sm outline-none focus:bg-white focus:border-neutral-400"
                        >

                    </div>


                    <!-- TANGGAL -->
                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Tanggal Belanja
                        </label>

                        <input
                            type="datetime-local"
                            name="purchase_date"
                            value="<?= htmlspecialchars(
                                $purchaseDate
                            ) ?>"
                            required
                            class="w-full h-11 px-4 rounded-xl border border-neutral-200 bg-white text-sm outline-none focus:border-neutral-400"
                        >

                    </div>


                    <!-- SUPPLIER -->
                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Supplier
                        </label>

                        <input
                            type="text"
                            name="supplier_name"
                            value="<?= htmlspecialchars(
                                $supplierName
                            ) ?>"
                            placeholder="Ketik nama supplier..."
                            autocomplete="off"
                            class="w-full h-11 px-4 rounded-xl border border-neutral-200 bg-white text-sm outline-none focus:border-neutral-400"
                        >

                        <p class="text-xs text-neutral-400 mt-2">
                            Supplier baru akan otomatis tersimpan.
                        </p>

                    </div>

                </div>


                <!-- CATATAN -->
                <div class="mt-4">

                    <label class="block text-sm font-medium mb-2">
                        Catatan
                    </label>

                    <textarea
                        name="notes"
                        rows="3"
                        placeholder="Catatan belanja, jika ada..."
                        class="w-full px-4 py-3 rounded-xl border border-neutral-200 bg-white text-sm outline-none resize-none focus:border-neutral-400"
                    ><?= htmlspecialchars(
                        $notes
                    ) ?></textarea>

                </div>

            </section>


            <!-- ITEMS -->
            <section class="bento-card overflow-hidden mb-5">

                <div class="px-5 md:px-6 py-5 border-b border-neutral-100 flex items-center justify-between gap-3">

                    <div>

                        <h2 class="font-semibold">
                            Barang yang Dibeli
                        </h2>

                        <p class="text-sm text-neutral-500 mt-1">
                            Ubah barang, jumlah, atau harga modal.
                        </p>

                    </div>


                    <button
                        type="button"
                        onclick="addItem()"
                        class="inline-flex items-center gap-2 h-10 px-3 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 transition"
                    >

                        <i
                            data-lucide="plus"
                            class="w-4 h-4"
                        ></i>

                        Tambah

                    </button>

                </div>


                <div id="itemsContainer"></div>


                <!-- TOTAL -->
                <div class="border-t border-neutral-100 px-5 md:px-6 py-5">

                    <div class="flex items-center justify-between">

                        <span class="text-sm text-neutral-500">
                            Total Belanja
                        </span>

                        <span
                            id="grandTotal"
                            class="text-xl font-semibold"
                        >
                            Rp0
                        </span>

                    </div>

                </div>

            </section>


            <!-- ACTION -->
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">

                <a
                    href="/pages/belanja/view.php?id=<?= $purchaseId ?>"
                    class="inline-flex items-center justify-center h-11 px-5 rounded-xl border border-neutral-200 bg-white text-neutral-700 text-sm font-medium hover:bg-neutral-50"
                >
                    Batal
                </a>


                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
                >

                    <i
                        data-lucide="save"
                        class="w-4 h-4"
                    ></i>

                    Simpan Perubahan

                </button>

            </div>

        </form>

    </main>

</div>


<script>

const products = <?= json_encode(
    $products,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;


const existingItems = <?= json_encode(
    $existingItems,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;


let itemIndex = 0;


/*
|--------------------------------------------------------------------------
| FORMAT RUPIAH
|--------------------------------------------------------------------------
*/

function formatRupiah(value) {

    value =
        Number(value) || 0;

    return 'Rp' +
        new Intl.NumberFormat(
            'id-ID'
        ).format(value);

}


/*
|--------------------------------------------------------------------------
| PARSE MONEY
|--------------------------------------------------------------------------
*/

function parseMoney(value) {

    if (!value) {
        return 0;
    }

    value =
        String(value)
            .replace(/\./g, '')
            .replace(',', '.');

    return Number(value) || 0;

}


/*
|--------------------------------------------------------------------------
| ESCAPE
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    return String(value)
        .replace(
            /[&<>"']/g,
            function(char) {

                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };

                return map[char];

            }
        );

}


/*
|--------------------------------------------------------------------------
| ADD ITEM
|--------------------------------------------------------------------------
*/

function addItem(
    selectedProductId = '',
    quantity = 1,
    buyingPrice = 0
) {

    const container =
        document.getElementById(
            'itemsContainer'
        );


    const row =
        document.createElement('div');


    row.className =
        'purchase-item p-5 md:px-6 border-b border-neutral-100';


    let options = `
        <option value="">
            Pilih barang
        </option>
    `;


    products.forEach(product => {

        const selected =
            Number(product.id) ===
            Number(selectedProductId)
                ? 'selected'
                : '';


        options += `
            <option
                value="${product.id}"
                data-price="${product.buying_price}"
                ${selected}
            >
                ${escapeHtml(product.name)}
                ${
                    product.sku
                        ? ' - ' +
                          escapeHtml(product.sku)
                        : ''
                }
            </option>
        `;

    });


    row.innerHTML = `

        <div class="
            grid
            grid-cols-1
            md:grid-cols-12
            gap-3
            items-end
        ">


            <!-- BARANG -->
            <div class="md:col-span-5">

                <label class="
                    block
                    text-xs
                    font-medium
                    text-neutral-500
                    mb-2
                ">
                    Barang
                </label>

                <select
                    name="product_id[]"
                    required
                    onchange="productChanged(this)"
                    class="
                        product-select
                        w-full
                        h-11
                        px-3
                        rounded-xl
                        border
                        border-neutral-200
                        bg-white
                        text-sm
                        outline-none
                        focus:border-neutral-400
                    "
                >
                    ${options}
                </select>

            </div>


            <!-- JUMLAH -->
            <div class="md:col-span-2">

                <label class="
                    block
                    text-xs
                    font-medium
                    text-neutral-500
                    mb-2
                ">
                    Jumlah
                </label>

                <input
                    type="number"
                    name="quantity[]"
                    value="${quantity}"
                    min="1"
                    required
                    oninput="calculateRow(this)"
                    class="
                        quantity-input
                        w-full
                        h-11
                        px-3
                        rounded-xl
                        border
                        border-neutral-200
                        bg-white
                        text-sm
                        outline-none
                        focus:border-neutral-400
                    "
                >

            </div>


            <!-- HARGA -->
            <div class="md:col-span-3">

                <label class="
                    block
                    text-xs
                    font-medium
                    text-neutral-500
                    mb-2
                ">
                    Harga Modal
                </label>

                <input
                    type="text"
                    name="buying_price[]"
                    value="${Number(
                        buyingPrice
                    ).toLocaleString('id-ID')}"
                    required
                    oninput="calculateRow(this)"
                    class="
                        price-input
                        w-full
                        h-11
                        px-3
                        rounded-xl
                        border
                        border-neutral-200
                        bg-white
                        text-sm
                        outline-none
                        focus:border-neutral-400
                    "
                >

            </div>


            <!-- SUBTOTAL -->
            <div class="md:col-span-2">

                <div class="
                    text-xs
                    font-medium
                    text-neutral-500
                    mb-2
                ">
                    Subtotal
                </div>

                <div class="
                    subtotal
                    text-sm
                    font-semibold
                ">
                    Rp0
                </div>

            </div>


            <!-- HAPUS -->
            <div class="md:col-span-12 flex justify-end">

                <button
                    type="button"
                    onclick="removeItem(this)"
                    class="
                        inline-flex
                        items-center
                        gap-1.5
                        text-xs
                        text-red-500
                        hover:text-red-700
                    "
                >

                    <i
                        data-lucide="trash-2"
                        class="w-3.5 h-3.5"
                    ></i>

                    Hapus

                </button>

            </div>

        </div>

    `;


    container.appendChild(row);


    const priceInput =
        row.querySelector(
            '.price-input'
        );


    calculateRow(priceInput);


    if (
        typeof lucide !==
        'undefined'
    ) {

        lucide.createIcons();

    }

}


/*
|--------------------------------------------------------------------------
| PRODUCT CHANGED
|--------------------------------------------------------------------------
*/

function productChanged(select) {

    const row =
        select.closest(
            '.purchase-item'
        );


    const option =
        select.options[
            select.selectedIndex
        ];


    const price =
        option.dataset.price || 0;


    const priceInput =
        row.querySelector(
            '.price-input'
        );


    priceInput.value =
        Number(price)
            .toLocaleString(
                'id-ID'
            );


    calculateRow(
        priceInput
    );

}


/*
|--------------------------------------------------------------------------
| CALCULATE ROW
|--------------------------------------------------------------------------
*/

function calculateRow(input) {

    const row =
        input.closest(
            '.purchase-item'
        );


    const quantity =
        Number(
            row.querySelector(
                '.quantity-input'
            ).value
        ) || 0;


    const price =
        parseMoney(
            row.querySelector(
                '.price-input'
            ).value
        );


    const subtotal =
        quantity * price;


    row.querySelector(
        '.subtotal'
    ).textContent =
        formatRupiah(
            subtotal
        );


    calculateTotal();

}


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

function calculateTotal() {

    let total = 0;


    document
        .querySelectorAll(
            '.purchase-item'
        )
        .forEach(row => {

            const quantity =
                Number(
                    row.querySelector(
                        '.quantity-input'
                    ).value
                ) || 0;


            const price =
                parseMoney(
                    row.querySelector(
                        '.price-input'
                    ).value
                );


            total +=
                quantity * price;

        });


    document.getElementById(
        'grandTotal'
    ).textContent =
        formatRupiah(total);

}


/*
|--------------------------------------------------------------------------
| REMOVE
|--------------------------------------------------------------------------
*/

function removeItem(button) {

    const row =
        button.closest(
            '.purchase-item'
        );


    row.remove();


    calculateTotal();

}


/*
|--------------------------------------------------------------------------
| LOAD OLD ITEMS
|--------------------------------------------------------------------------
*/

if (existingItems.length > 0) {

    existingItems.forEach(item => {

        addItem(
            item.product_id,
            item.quantity,
            item.buying_price
        );

    });

} else {

    addItem();

}

</script>


<?php
require_once __DIR__ . '/../../includes/footer.php';
?>