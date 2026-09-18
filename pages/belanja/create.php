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
| GENERATE NOMOR BELANJA
|--------------------------------------------------------------------------
*/

function generatePurchaseNumber(PDO $pdo): string
{
    $prefix = 'BL-' . date('Ymd');

    $stmt = $pdo->prepare("
        SELECT purchase_number
        FROM purchases
        WHERE purchase_number LIKE :prefix
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':prefix' => $prefix . '-%'
    ]);

    $last = $stmt->fetchColumn();

    if ($last) {

        $parts = explode('-', $last);
        $number = (int) end($parts);
        $number++;

    } else {

        $number = 1;

    }

    return $prefix . '-' . str_pad(
        $number,
        3,
        '0',
        STR_PAD_LEFT
    );
}


/*
|--------------------------------------------------------------------------
| DATA BARANG
|--------------------------------------------------------------------------
*/

$stmtProduct = $pdo->prepare("
    SELECT
        id,
        sku,
        name,
        unit,
        buying_price,
        current_stock
    FROM products
    WHERE product_type = 'TOKO'
      AND status = 'ACTIVE'
      AND store_id = :products_store_id
    ORDER BY name ASC
");
$stmtProduct->execute([
    ':products_store_id' => $authStoreId
]);

$products = $stmtProduct->fetchAll();


/*
|--------------------------------------------------------------------------
| DEFAULT
|--------------------------------------------------------------------------
*/

$purchaseNumber = generatePurchaseNumber($pdo);

$purchaseDate = date('Y-m-d\TH:i');

$error = null;


/*
|--------------------------------------------------------------------------
| SIMPAN
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

        $purchaseNumber = trim(
            $_POST['purchase_number'] ?? ''
        );

        $purchaseDateInput = trim(
            $_POST['purchase_date'] ?? ''
        );

        $supplierName = trim(
            $_POST['supplier_name'] ?? ''
        );

        $notes = trim(
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
        | VALIDASI
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


        $timestamp = strtotime(
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

        $stmtCheck = $pdo->prepare("
            SELECT id
            FROM purchases
            WHERE purchase_number = :number
            LIMIT 1
        ");

        $stmtCheck->execute([
            ':number' => $purchaseNumber
        ]);

        if ($stmtCheck->fetch()) {

            throw new Exception(
                'Nomor belanja sudah digunakan.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | ITEMS
        |--------------------------------------------------------------------------
        */

        $items = [];
        $totalAmount = 0;


        foreach ($productIds as $index => $productId) {

            $productId = (int) $productId;

            if ($productId <= 0) {
                continue;
            }


            $quantity = (int) (
                $quantities[$index] ?? 0
            );


            $buyingPrice = parseMoney(
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
            | CEK PRODUCT
            |--------------------------------------------------------------------------
            */

            $stmtProduct = $pdo->prepare("
                SELECT
                    id,
                    name,
                    product_type,
                    status
                FROM products
                WHERE id = :id
                  AND store_id = :product_store_id
                LIMIT 1
            ");

            $stmtProduct->execute([
                ':id' => $productId,
                ':product_store_id' => $authStoreId
            ]);

            $product = $stmtProduct->fetch();


            if (!$product) {

                throw new Exception(
                    'Barang tidak ditemukan.'
                );

            }


            if ($product['product_type'] !== 'TOKO') {

                throw new Exception(
                    'Barang titipan tidak bisa dimasukkan ke transaksi belanja.'
                );

            }


            if ($product['status'] !== 'ACTIVE') {

                throw new Exception(
                    'Barang "' .
                    $product['name'] .
                    '" sedang tidak aktif.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | SUBTOTAL
            |--------------------------------------------------------------------------
            */

            $subtotal =
                $quantity *
                $buyingPrice;


            $totalAmount += $subtotal;


            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'buying_price' => $buyingPrice,
                'subtotal' => $subtotal
            ];

        }


        if (empty($items)) {

            throw new Exception(
                'Minimal tambahkan satu barang.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | SUPPLIER
        |--------------------------------------------------------------------------
        */

        $supplierId = null;


        if ($supplierName !== '') {

            /*
            | Cari supplier berdasarkan nama
            */

            $stmtSupplier = $pdo->prepare("
                SELECT id
                FROM suppliers
                WHERE LOWER(name) = LOWER(:name)
                  AND store_id = :supplier_store_id
                LIMIT 1
            ");

            $stmtSupplier->execute([
                ':name' => $supplierName,
                ':supplier_store_id' => $authStoreId
            ]);

            $existingSupplier =
                $stmtSupplier->fetch();


            if ($existingSupplier) {

                $supplierId =
                    (int) $existingSupplier['id'];

            } else {

                /*
                | Supplier baru otomatis dibuat
                */

                $stmtSupplierCreate = $pdo->prepare("
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

                $stmtSupplierCreate->execute([
                    ':supplier_store_id' => $authStoreId,
                    ':name' => $supplierName
                ]);

                $supplierId =
                    (int) $pdo->lastInsertId();

            }

        }


        /*
        |--------------------------------------------------------------------------
        | PURCHASE
        |--------------------------------------------------------------------------
        */

        $stmtPurchase = $pdo->prepare("
            INSERT INTO purchases (
                store_id,
                purchase_number,
                supplier_id,
                purchase_date,
                total_amount,
                notes,
                created_by
            )
            VALUES (
                :store_id,
                :purchase_number,
                :supplier_id,
                :purchase_date,
                :total_amount,
                :notes,
                :created_by
            )
        ");

        $stmtPurchase->execute([
            ':store_id' => $authStoreId,
            ':purchase_number' => $purchaseNumber,
            ':supplier_id' => $supplierId,
            ':purchase_date' => date(
                'Y-m-d H:i:s',
                $timestamp
            ),
            ':total_amount' => $totalAmount,
            ':notes' => $notes !== ''
                ? $notes
                : null,
            ':created_by' =>
                $_SESSION['user_id'] ?? null
        ]);


        $purchaseId =
            (int) $pdo->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | PREPARE ITEM
        |--------------------------------------------------------------------------
        */

        $stmtItem = $pdo->prepare("
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
        | UPDATE STOCK
        |--------------------------------------------------------------------------
        */

        $stmtStock = $pdo->prepare("
            UPDATE products
            SET
                current_stock = current_stock + :quantity,
                buying_price = :buying_price
            WHERE id = :product_id
              AND store_id = :stock_store_id
        ");


        /*
        |--------------------------------------------------------------------------
        | STOCK MOVEMENT
        |--------------------------------------------------------------------------
        */

        $stmtMovement = $pdo->prepare("
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

            /*
            | Purchase item
            */

            $stmtItem->execute([
                ':store_id' => $authStoreId,
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


            /*
            | Tambah stok
            */

            $stmtStock->execute([
                ':quantity' =>
                    $item['quantity'],
                ':buying_price' =>
                    $item['buying_price'],
                ':product_id' =>
                    $item['product_id'],
                ':stock_store_id' =>
                    $authStoreId
            ]);


            /*
            | Catat movement
            */

            $stmtMovement->execute([
                ':store_id' =>
                    $authStoreId,
                ':product_id' =>
                    $item['product_id'],
                ':quantity' =>
                    $item['quantity'],
                ':reference_id' =>
                    $purchaseId,
                ':notes' =>
                    'Belanja ' .
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

        $stmtAudit = $pdo->prepare("
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
                'CREATE',
                'purchases',
                :record_id,
                :description
            )
        ");

        $stmtAudit->execute([
            ':store_id' => $authStoreId,
            ':user_id' =>
                $_SESSION['user_id'] ?? null,
            ':record_id' =>
                $purchaseId,
            ':description' =>
                'Membuat transaksi belanja ' .
                $purchaseNumber .
                ' dengan total ' .
                rupiah($totalAmount)
        ]);


        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        $_SESSION['success'] =
            'Belanja berhasil disimpan. Stok barang sudah diperbarui.';


        header(
            'Location: /pages/belanja/'
        );

        exit;


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();

    }

}
$pageTitle = 'Tambah Belanja';
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
                    Tambah Belanja
                </h1>

                <p class="mt-2 text-sm text-neutral-500">
                    Catat barang yang baru dibeli dari supplier.
                </p>

            </div>


            <a
                href="/pages/belanja/"
                class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl border border-neutral-200 bg-white text-neutral-700 text-sm font-medium hover:bg-neutral-50 transition"
            >
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Kembali
            </a>

        </div>


        <!-- ERROR -->
        <?php if ($error): ?>

            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">

                <div class="flex items-start gap-3">

                    <i
                        data-lucide="circle-alert"
                        class="w-5 h-5 text-red-500 shrink-0 mt-0.5"
                    ></i>

                    <div>

                        <div class="font-medium text-red-700">
                            Belanja gagal disimpan
                        </div>

                        <div class="text-sm text-red-600 mt-1">
                            <?= htmlspecialchars($error) ?>
                        </div>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <form method="POST" id="purchaseForm">

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
                    ></textarea>

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
                            Tambahkan barang dan jumlah yang dibeli.
                        </p>

                    </div>


                    <button
                        type="button"
                        onclick="addItem()"
                        class="inline-flex items-center gap-2 h-10 px-3 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 transition"
                    >
                        <i data-lucide="plus" class="w-4 h-4"></i>
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
                    href="/pages/belanja/"
                    class="inline-flex items-center justify-center h-11 px-5 rounded-xl border border-neutral-200 bg-white text-neutral-700 text-sm font-medium hover:bg-neutral-50"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
                >
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Simpan Belanja
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

let itemIndex = 0;


/*
|--------------------------------------------------------------------------
| FORMAT RUPIAH
|--------------------------------------------------------------------------
*/

function formatRupiah(value) {

    value = Number(value) || 0;

    return 'Rp' +
        new Intl.NumberFormat('id-ID')
            .format(value);

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

    value = String(value)
        .replace(/\./g, '')
        .replace(',', '.');

    return Number(value) || 0;

}


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    return String(value)
        .replace(
            /[&<>"']/g,
            function (char) {

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

function addItem() {

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

    options += `
        <option
            value="${product.id}"
            data-price="${product.buying_price}"
        >
            ${escapeHtml(product.name)}
            ${
                product.sku
                    ? ' - ' + escapeHtml(product.sku)
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
                    value="1"
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
                    value="0"
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


            <div class="md:col-span-2">

                <div class="text-xs font-medium text-neutral-500 mb-2">
                    Subtotal
                </div>

                <div class="subtotal text-sm font-semibold">
                    Rp0
                </div>

            </div>


            <div class="md:col-span-12 flex justify-end">

                <button
                    type="button"
                    onclick="removeItem(this)"
                    class="inline-flex items-center gap-1.5 text-xs text-red-500 hover:text-red-700"
                >
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    Hapus
                </button>

            </div>

        </div>
    `;


    container.appendChild(row);


    if (typeof lucide !== 'undefined') {
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
        select.closest('.purchase-item');

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
        Number(price).toLocaleString(
            'id-ID'
        );

    calculateRow(priceInput);

}


/*
|--------------------------------------------------------------------------
| CALCULATE
|--------------------------------------------------------------------------
*/

function calculateRow(input) {

    const row =
        input.closest('.purchase-item');

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
        formatRupiah(subtotal);

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
        .querySelectorAll('.purchase-item')
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
| INITIAL
|--------------------------------------------------------------------------
*/

addItem();

</script>


<?php
require_once __DIR__ . '/../../includes/footer.php';
?>