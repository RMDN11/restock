<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value)
{
    return 'Rp ' . number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}

/*
|--------------------------------------------------------------------------
| DEFAULT
|--------------------------------------------------------------------------
*/

$old = [
    'sale_date'      => date('Y-m-d\TH:i'),
    'payment_method' => 'CASH',
    'notes'          => '',
];

$errors = [];

/*
|--------------------------------------------------------------------------
| GENERATE NOMOR INVOICE
|--------------------------------------------------------------------------
*/

function generateInvoiceNumber(PDO $pdo, int $storeId)
{
    $prefix = 'PJ-' . date('Ymd') . '-';

    /*
     * invoice_number saat ini UNIQUE secara global pada tabel sales,
     * bukan UNIQUE per store. Karena itu nomor harus dicari secara
     * global agar dua toko tidak menghasilkan invoice yang sama.
     */
    $stmt = $pdo->prepare("
        SELECT invoice_number
        FROM sales
        WHERE invoice_number LIKE :prefix
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':prefix' => $prefix . '%'
    ]);

    $last = $stmt->fetchColumn();

    $number = 1;

    if ($last) {
        $lastNumber = (int) substr((string) $last, -3);
        $number = $lastNumber + 1;
    }

    /*
     * Pengaman tambahan bila nomor hasil generate ternyata sudah ada.
     * Ini menangani data lama dan mengurangi kemungkinan benturan.
     */
    do {
        $candidate = $prefix . str_pad(
            $number,
            3,
            '0',
            STR_PAD_LEFT
        );

        $check = $pdo->prepare("
            SELECT COUNT(*)
            FROM sales
            WHERE invoice_number = :candidate
        ");
        $check->execute([
            ':candidate' => $candidate
        ]);

        $exists = (int) $check->fetchColumn() > 0;
        if ($exists) {
            $number++;
        }
    } while ($exists);

    return $candidate;
}

/*
|--------------------------------------------------------------------------
| PROSES PENJUALAN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        $errors[] =
            'Sesi keamanan tidak valid. Silakan coba lagi.';
    }

    /*
    |--------------------------------------------------------------------------
    | HEADER
    |--------------------------------------------------------------------------
    */

    $saleDate = trim(
        $_POST['sale_date'] ?? ''
    );

    $paymentMethod = strtoupper(
        trim($_POST['payment_method'] ?? 'CASH')
    );

    $notes = trim(
        $_POST['notes'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | ITEMS
    |--------------------------------------------------------------------------
    */

    $productIds = $_POST['product_id'] ?? [];

    $quantities = $_POST['quantity'] ?? [];

    if (!is_array($productIds)) {
        $productIds = [];
    }

    if (!is_array($quantities)) {
        $quantities = [];
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI HEADER
    |--------------------------------------------------------------------------
    */

    if ($saleDate === '') {

        $errors[] =
            'Tanggal penjualan wajib diisi.';

    } else {

        $dateCheck = DateTime::createFromFormat(
            'Y-m-d\TH:i',
            $saleDate
        );

        if (
            !$dateCheck ||
            $dateCheck->format('Y-m-d\TH:i') !== $saleDate
        ) {
            $errors[] =
                'Tanggal penjualan tidak valid.';
        }
    }

    if (!in_array(
        $paymentMethod,
        ['CASH', 'QRIS', 'TRANSFER', 'OTHER'],
        true
    )) {
        $errors[] =
            'Metode pembayaran tidak valid.';
    }

    if (empty($productIds)) {

        $errors[] =
            'Minimal pilih satu barang.';
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI ITEM DASAR
    |--------------------------------------------------------------------------
    */

    $cleanItems = [];
    $usedProducts = [];

    foreach ($productIds as $index => $productId) {

        $productId = (int) $productId;

        $quantity = isset($quantities[$index])
            ? (int) $quantities[$index]
            : 0;

        if ($productId <= 0) {

            $errors[] =
                'Ada barang yang tidak valid.';

            continue;
        }

        if ($quantity <= 0) {

            $errors[] =
                'Jumlah barang harus lebih dari 0.';

            continue;
        }

        if (isset($usedProducts[$productId])) {

            $errors[] =
                'Barang yang sama tidak boleh dimasukkan dua kali.';

            continue;
        }

        $usedProducts[$productId] = true;

        $cleanItems[] = [
            'product_id' => $productId,
            'quantity'   => $quantity,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN TRANSAKSI
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | NOMOR INVOICE
            |--------------------------------------------------------------------------
            */

            $invoiceNumber =
                generateInvoiceNumber($pdo, $authStoreId);

            /*
            |--------------------------------------------------------------------------
            | VALIDASI & LOCK PRODUCT
            |--------------------------------------------------------------------------
            */

            $preparedItems = [];

            $grandTotal = 0;

            foreach ($cleanItems as $item) {

                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.name,
                        p.sku,
                        p.product_type,
                        p.unit,
                        p.buying_price,
                        p.selling_price,
                        p.current_stock,
                        p.status,

                        cs.id AS consignment_id,
                        cs.initial_price,
                        cs.fee_type,
                        cs.fee_value,
                        cs.status AS consignment_status

                    FROM products p

                    LEFT JOIN consignments cs
                        ON cs.id = (
                            SELECT c1.id
                            FROM consignments c1
                            WHERE c1.product_id = p.id
                              AND c1.store_id = p.store_id
                            ORDER BY
                                CASE
                                    WHEN c1.status = 'ACTIVE'
                                    THEN 0
                                    ELSE 1
                                END,
                                c1.id DESC
                            LIMIT 1
                        )

                    WHERE p.id = :id
                      AND p.store_id = :product_store_id

                    LIMIT 1

                    FOR UPDATE
                ");

                $stmt->execute([
                    ':id' => $item['product_id'],
                    ':product_store_id' => $authStoreId
                ]);

                $product = $stmt->fetch();

                if (!$product) {

                    throw new Exception(
                        'Barang tidak ditemukan.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | STATUS
                |--------------------------------------------------------------------------
                */

                if ($product['status'] !== 'ACTIVE') {

                    throw new Exception(
                        'Barang "' .
                        $product['name'] .
                        '" sedang tidak aktif.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | STOK
                |--------------------------------------------------------------------------
                */

                $stock =
                    (int) $product['current_stock'];

                $quantity =
                    (int) $item['quantity'];

                if ($quantity > $stock) {

                    throw new Exception(
                        'Stok "' .
                        $product['name'] .
                        '" tidak cukup. ' .
                        'Tersedia ' .
                        $stock .
                        ' ' .
                        $product['unit'] .
                        '.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | HARGA JUAL
                |--------------------------------------------------------------------------
                */

                $sellingPrice =
                    (float) $product['selling_price'];

                $buyingPrice =
                    (float) $product['buying_price'];

                $subtotal =
                    $sellingPrice * $quantity;

                /*
                |--------------------------------------------------------------------------
                | TOKO
                |--------------------------------------------------------------------------
                */

                $profit = 0;
                $consignorAmount = 0;
                $storeAmount = 0;

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
                | TITIPAN
                |--------------------------------------------------------------------------
                */

                if (
                    $product['product_type'] === 'TITIPAN'
                ) {

                    if (
                        empty($product['consignment_id']) ||
                        $product['consignment_status'] !== 'ACTIVE'
                    ) {

                        throw new Exception(
                            'Data titipan "' .
                            $product['name'] .
                            '" belum memiliki titipan aktif.'
                        );
                    }

                    $feeType =
                        $product['fee_type'];

                    $feeValue =
                        (float) $product['fee_value'];

                    if ($feeType === 'PERCENTAGE') {

                        $storeAmount =
                            $subtotal *
                            ($feeValue / 100);

                    } else {

                        $storeAmount =
                            $feeValue *
                            $quantity;

                    }

                    if ($storeAmount > $subtotal) {
                        $storeAmount = $subtotal;
                    }

                    $consignorAmount =
                        $subtotal -
                        $storeAmount;

                    /*
                    |--------------------------------------------------------------------------
                    | PROFIT TOKO = FEE TOKO
                    |--------------------------------------------------------------------------
                    */

                    $profit =
                        $storeAmount;

                }

                $grandTotal += $subtotal;

                $preparedItems[] = [

                    'product_id' =>
                        (int) $product['id'],

                    'name' =>
                        $product['name'],

                    'sku' =>
                        $product['sku'],

                    'product_type' =>
                        $product['product_type'],

                    'unit' =>
                        $product['unit'],

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
                        $storeAmount,

                ];
            }

            /*
            |--------------------------------------------------------------------------
            | INSERT SALES
            |--------------------------------------------------------------------------
            */

            $saleDateDb =
                str_replace(
                    'T',
                    ' ',
                    $saleDate
                ) . ':00';

            $stmt = $pdo->prepare("
                INSERT INTO sales (
                    invoice_number,
                    sale_date,
                    total_amount,
                    payment_method,
                    status,
                    notes,
                    created_by,
                    store_id
                ) VALUES (
                    :invoice_number,
                    :sale_date,
                    :total_amount,
                    :payment_method,
                    'COMPLETED',
                    :notes,
                    :created_by,
                    :store_id
                )
            ");

            $stmt->execute([

                ':invoice_number' =>
                    $invoiceNumber,

                ':sale_date' =>
                    $saleDateDb,

                ':total_amount' =>
                    $grandTotal,

                ':payment_method' =>
                    $paymentMethod,

                ':notes' =>
                    $notes !== ''
                        ? $notes
                        : null,

                ':created_by' =>
                    $_SESSION['user_id'] ?? null,

                ':store_id' =>
                    $authStoreId,

            ]);

            $saleId =
                (int) $pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | INSERT SALE ITEMS + STOCK
            |--------------------------------------------------------------------------
            */

            foreach ($preparedItems as $item) {

                /*
                |--------------------------------------------------------------------------
                | SALE ITEM
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO sale_items (
                        sale_id,
                        product_id,
                        quantity,
                        selling_price,
                        buying_price,
                        subtotal,
                        profit,
                        consignor_amount,
                        store_amount,
                        store_id
                    ) VALUES (
                        :sale_id,
                        :product_id,
                        :quantity,
                        :selling_price,
                        :buying_price,
                        :subtotal,
                        :profit,
                        :consignor_amount,
                        :store_amount,
                        :store_id
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
                        $item['store_amount'],

                ]);

                /*
                |--------------------------------------------------------------------------
                | KURANGI STOK
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
    UPDATE products
    SET current_stock =
        current_stock - :qty_update
    WHERE id = :id
    AND store_id = :stock_store_id
    AND current_stock >= :qty_check
");

$stmt->execute([

    ':qty_update' =>
        $item['quantity'],

    ':id' =>
        $item['product_id'],

    ':qty_check' =>
        $item['quantity'],

    ':stock_store_id' =>
        $authStoreId,

]);

                if ($stmt->rowCount() !== 1) {

                    throw new Exception(
                        'Stok barang "' .
                        $item['name'] .
                        '" berubah. Silakan ulangi transaksi.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | STOCK MOVEMENT
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO stock_movements (
                        product_id,
                        movement_type,
                        quantity,
                        reference_type,
                        reference_id,
                        notes,
                        created_by,
                        store_id
                    ) VALUES (
                        :product_id,
                        'PENJUALAN',
                        :quantity,
                        'SALE',
                        :reference_id,
                        :notes,
                        :created_by,
                        :store_id
                    )
                ");

                $stmt->execute([

                    ':product_id' =>
                        $item['product_id'],

                    ':quantity' =>
                        $item['quantity'],

                    ':reference_id' =>
                        $saleId,

                    ':notes' =>
                        'Penjualan ' .
                        $invoiceNumber,

                    ':created_by' =>
                        $_SESSION['user_id'] ?? null,

                    ':store_id' =>
                        $authStoreId,

                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | AUDIT LOG
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (
                    user_id,
                    action,
                    table_name,
                    record_id,
                    description,
                    store_id
                ) VALUES (
                    :user_id,
                    'CREATE',
                    'sales',
                    :record_id,
                    :description,
                    :store_id
                )
            ");

            $stmt->execute([

                ':user_id' =>
                    $_SESSION['user_id'] ?? null,

                ':record_id' =>
                    $saleId,

                ':description' =>
                    'Membuat penjualan ' .
                    $invoiceNumber .
                    ' dengan total ' .
                    rupiah($grandTotal),

                ':store_id' =>
                    $authStoreId,

            ]);

            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            /*
            |--------------------------------------------------------------------------
            | FLASH SUKSES UNTUK HALAMAN PENJUALAN
            |--------------------------------------------------------------------------
            */

            $_SESSION['sale_flash'] = [
                'type'  => 'created',
                'names' => array_values(
                    array_map(
                        static fn($item) => $item['name'],
                        $preparedItems
                    )
                ),
                'total' => $grandTotal,
            ];

            header(
                'Location: /pages/penjualan/'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'Penjualan gagal disimpan: ' .
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| AMBIL BARANG AKTIF
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.sku,
        p.barcode,
        p.name,
        p.product_type,
        p.unit,
        p.buying_price,
        p.selling_price,
        p.current_stock,

        c.name AS consignor_name,

        cs.initial_price,
        cs.fee_type,
        cs.fee_value,
        cs.status AS consignment_status

    FROM products p

    LEFT JOIN consignments cs
        ON cs.id = (
            SELECT c1.id
            FROM consignments c1
            WHERE c1.product_id = p.id
            ORDER BY
                CASE
                    WHEN c1.status = 'ACTIVE'
                    THEN 0
                    ELSE 1
                END,
                c1.id DESC
            LIMIT 1
        )

    LEFT JOIN consignors c
        ON c.id = cs.consignor_id
       AND c.store_id = p.store_id

    WHERE p.status = 'ACTIVE'
      AND p.store_id = :products_store_id

    ORDER BY p.name ASC
");

$stmt->execute([
    ':products_store_id' => $authStoreId
]);

$products = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| DATA UNTUK JAVASCRIPT
|--------------------------------------------------------------------------
*/

$productData = [];

foreach ($products as $product) {

    $productData[] = [

        'id' =>
            (int) $product['id'],

        'sku' =>
            $product['sku'],

        'barcode' =>
            $product['barcode'],

        'name' =>
            $product['name'],

        'product_type' =>
            $product['product_type'],

        'unit' =>
            $product['unit'],

        'buying_price' =>
            (float) $product['buying_price'],

        'selling_price' =>
            (float) $product['selling_price'],

        'current_stock' =>
            (int) $product['current_stock'],

        'consignor_name' =>
            $product['consignor_name'],

        'initial_price' =>
            (float) $product['initial_price'],

        'fee_type' =>
            $product['fee_type'],

        'fee_value' =>
            (float) $product['fee_value'],

        'consignment_status' =>
            $product['consignment_status'],

    ];
}
$pageTitle = 'Penjualan Baru';

?>

<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>


<main class="main-content">

<div class="p-4 sm:p-6 lg:p-8">


        <!-- HEADER -->

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">

            <div>

                <a
                    href="/pages/penjualan/"
                    class="inline-flex items-center gap-2 text-sm text-neutral-500 hover:text-neutral-900 mb-4"
                >

                    <i
                        data-lucide="arrow-left"
                        class="w-4 h-4"
                    ></i>

                    Kembali ke Penjualan

                </a>

                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">
                    Penjualan Baru
                </h1>

                <p class="mt-1 text-sm text-neutral-500">
                    Pilih barang, atur jumlah, lalu simpan transaksi.
                </p>

            </div>

        </div>


        <!-- ERROR -->

        <?php if (!empty($errors)): ?>

            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4">

                <div class="flex gap-3">

                    <i
                        data-lucide="circle-alert"
                        class="w-5 h-5 text-red-500 flex-shrink-0"
                    ></i>

                    <div>

                        <div class="font-semibold text-red-800 mb-1">
                            Penjualan belum disimpan
                        </div>

                        <ul class="text-sm text-red-700 space-y-1">

                            <?php foreach ($errors as $error): ?>

                                <li>
                                    • <?= e($error) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            id="sale-form"
            autocomplete="off"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >


            <div class="grid grid-cols-1 xl:grid-cols-[1fr_390px] gap-6">


                <!-- LEFT -->

                <div class="space-y-6">


                    <!-- CARI BARANG -->

                    <div class="bento-card p-5 sm:p-6">

                        <div class="mb-5">

                            <h2 class="font-semibold text-lg">
                                Pilih Barang
                            </h2>

                            <p class="text-sm text-neutral-500 mt-1">
                                Cari berdasarkan nama, SKU, atau barcode.
                            </p>

                        </div>


                        <div class="relative">

                            <i
                                data-lucide="search"
                                class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400"
                            ></i>

                            <input
                                type="search"
                                id="product-search"
                                placeholder="Cari barang..."
                                class="
                                    w-full
                                    rounded-2xl
                                    border
                                    border-neutral-200
                                    pl-12
                                    pr-4
                                    py-4
                                    text-sm
                                    outline-none
                                    focus:border-neutral-400
                                    focus:ring-2
                                    focus:ring-neutral-100
                                "
                            >

                        </div>


                        <!-- SEARCH RESULTS -->

                        <div
                            id="product-results"
                            class="mt-3 space-y-2 max-h-[420px] overflow-y-auto"
                        ></div>

                    </div>


                    <!-- KERANJANG -->

                    <div class="bento-card overflow-hidden">

                        <div class="px-5 sm:px-6 py-5 border-b border-neutral-100">

                            <div class="flex items-center justify-between">

                                <div>

                                    <h2 class="font-semibold">
                                        Keranjang
                                    </h2>

                                    <p
                                        id="cart-count-label"
                                        class="text-xs text-neutral-400 mt-1"
                                    >
                                        Belum ada barang
                                    </p>

                                </div>

                                <div
                                    id="cart-total-small"
                                    class="text-sm font-semibold"
                                >
                                    Rp 0
                                </div>

                            </div>

                        </div>


                        <div
                            id="cart"
                            class="divide-y divide-neutral-100"
                        >

                            <div
                                id="cart-empty"
                                class="px-6 py-14 text-center"
                            >

                                <div class="w-14 h-14 mx-auto rounded-2xl bg-neutral-100 flex items-center justify-center mb-4">

                                    <i
                                        data-lucide="shopping-cart"
                                        class="w-6 h-6 text-neutral-400"
                                    ></i>

                                </div>

                                <h3 class="font-semibold">
                                    Keranjang masih kosong
                                </h3>

                                <p class="text-sm text-neutral-400 mt-1">
                                    Pilih barang di atas untuk memulai transaksi.
                                </p>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- RIGHT -->

                <div class="space-y-6">


                    <!-- DETAIL TRANSAKSI -->

                    <div class="bento-card p-5 sm:p-6">

                        <h2 class="font-semibold text-lg mb-5">
                            Detail Transaksi
                        </h2>


                        <!-- TANGGAL -->

                        <div class="mb-4">

                            <label class="block text-sm font-medium mb-2">
                                Tanggal & Waktu
                            </label>

                            <input
                                type="datetime-local"
                                name="sale_date"
                                value="<?= e($old['sale_date']) ?>"
                                required
                                class="
                                    w-full
                                    rounded-xl
                                    border
                                    border-neutral-200
                                    px-3
                                    py-3
                                    text-sm
                                    outline-none
                                    focus:border-neutral-400
                                "
                            >

                        </div>


                        <!-- PEMBAYARAN -->

                        <div>

                            <label class="block text-sm font-medium mb-2">
                                Pembayaran
                            </label>

                            <div class="grid grid-cols-2 gap-2">

                                <?php
                                $payments = [
                                    'CASH' => 'Cash',
                                    'QRIS' => 'QRIS',
                                    'TRANSFER' => 'Transfer',
                                    'OTHER' => 'Lainnya',
                                ];
                                ?>

                                <?php foreach ($payments as $value => $label): ?>

                                    <label class="payment-option">

                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="<?= e($value) ?>"
                                            <?= $old['payment_method'] === $value ? 'checked' : '' ?>
                                            class="sr-only"
                                        >

                                        <span>
                                            <?= e($label) ?>
                                        </span>

                                    </label>

                                <?php endforeach; ?>

                            </div>

                        </div>


                        <!-- CATATAN -->

                        <div class="mt-5">

                            <label class="block text-sm font-medium mb-2">
                                Catatan
                            </label>

                            <textarea
                                name="notes"
                                rows="3"
                                placeholder="Opsional..."
                                class="
                                    w-full
                                    rounded-xl
                                    border
                                    border-neutral-200
                                    px-3
                                    py-3
                                    text-sm
                                    outline-none
                                    resize-none
                                    focus:border-neutral-400
                                "
                            ><?= e($old['notes']) ?></textarea>

                        </div>

                    </div>


                    <!-- TOTAL -->

                    <div class="bento-card p-5 sm:p-6 xl:sticky xl:top-6">

                        <div class="flex items-center justify-between text-sm text-neutral-500">

                            <span>
                                Jumlah Barang
                            </span>

                            <span id="summary-qty">
                                0
                            </span>

                        </div>


                        <div class="flex items-center justify-between mt-3 text-sm text-neutral-500">

                            <span>
                                Jenis Barang
                            </span>

                            <span id="summary-items">
                                0
                            </span>

                        </div>


                        <div class="border-t border-neutral-100 mt-5 pt-5">

                            <div class="flex items-end justify-between gap-4">

                                <span class="font-semibold">
                                    Total
                                </span>

                                <span
                                    id="grand-total"
                                    class="text-2xl font-bold tracking-tight"
                                >
                                    Rp 0
                                </span>

                            </div>

                        </div>


                        <button
                            type="submit"
                            id="submit-sale"
                            disabled
                            class="
                                w-full
                                mt-6
                                inline-flex
                                items-center
                                justify-center
                                gap-2
                                rounded-xl
                                bg-neutral-900
                                px-5
                                py-3.5
                                text-sm
                                font-semibold
                                text-white
                                hover:bg-neutral-800
                                disabled:opacity-40
                                disabled:cursor-not-allowed
                                transition
                            "
                        >

                            <i
                                data-lucide="check"
                                class="w-4 h-4"
                            ></i>

                            Simpan Penjualan

                        </button>


                        <a
                            href="/pages/penjualan/"
                            class="
                                w-full
                                mt-2
                                inline-flex
                                items-center
                                justify-center
                                rounded-xl
                                px-5
                                py-3
                                text-sm
                                font-medium
                                text-neutral-500
                                hover:bg-neutral-50
                            "
                        >
                            Batal
                        </a>

                    </div>

                </div>

            </div>

        </form>

    </div>

</main>


<style>

.payment-option {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 10px 12px;
    border: 1.5px solid #e5e5e5;
    border-radius: 12px;
    background: #fff;
    color: #525252;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: .18s ease;
}

.payment-option:hover {
    background: #fafafa;
    border-color: #d4d4d4;
}

.payment-option:has(input:checked) {
    border-color: #171717;
    background: #fafafa;
    color: #171717;
}

.product-result {
    width: 100%;
    text-align: left;
    padding: 13px 14px;
    border: 1px solid #eeeeee;
    border-radius: 14px;
    background: #fff;
    transition: .15s ease;
}

.product-result:hover {
    background: #fafafa;
    border-color: #d4d4d4;
}

.product-result.disabled {
    opacity: .5;
    cursor: not-allowed;
}

.cart-item {
    padding: 18px 20px;
}

.qty-control {
    display: inline-flex;
    align-items: center;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    overflow: hidden;
}

.qty-control button {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #525252;
}

.qty-control button:hover {
    background: #f5f5f5;
}

.qty-control span {
    min-width: 35px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
}

@media (max-width: 1279px) {

    .xl\:sticky {
        position: static !important;
    }

}

</style>


<script>

const products = <?= json_encode(
    $productData,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;

let cart = {};


/*
|--------------------------------------------------------------------------
| FORMAT RUPIAH
|--------------------------------------------------------------------------
*/

function rupiah(value) {

    return 'Rp ' +
        Number(value || 0).toLocaleString(
            'id-ID'
        );

}


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    const div =
        document.createElement('div');

    div.textContent =
        value ?? '';

    return div.innerHTML;

}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

const searchInput =
    document.getElementById(
        'product-search'
    );

const productResults =
    document.getElementById(
        'product-results'
    );


function renderSearchResults() {

    const keyword =
        searchInput.value
            .trim()
            .toLowerCase();

    if (keyword === '') {

        productResults.innerHTML = '';

        return;
    }


    const results =
        products
            .filter(product => {

                const text =
                    [
                        product.name,
                        product.sku,
                        product.barcode
                    ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                return text.includes(keyword);

            })
            .slice(0, 20);


    if (results.length === 0) {

        productResults.innerHTML = `
            <div class="rounded-xl bg-neutral-50 p-4 text-center text-sm text-neutral-400">
                Barang tidak ditemukan.
            </div>
        `;

        return;
    }


    productResults.innerHTML =
        results.map(product => {

            const already =
                !!cart[product.id];

            const noStock =
                product.current_stock <= 0;

            const disabled =
                already || noStock;

            let stockText =
                product.current_stock +
                ' ' +
                escapeHtml(product.unit);

            if (noStock) {
                stockText =
                    'Stok habis';
            }

            let typeText =
                product.product_type === 'TITIPAN'
                    ? 'Titipan'
                    : 'Toko';

            return `

                <button
                    type="button"
                    class="product-result ${disabled ? 'disabled' : ''}"
                    ${disabled ? 'disabled' : ''}
                    onclick="addToCart(${product.id})"
                >

                    <div class="flex items-center justify-between gap-3">

                        <div class="min-w-0">

                            <div class="font-medium text-sm text-neutral-900 truncate">
                                ${escapeHtml(product.name)}
                            </div>

                            <div class="flex flex-wrap items-center gap-2 mt-1">

                                <span class="text-xs text-neutral-400">
                                    ${escapeHtml(product.sku)}
                                </span>

                                <span class="text-xs text-neutral-300">
                                    •
                                </span>

                                <span class="text-xs text-neutral-400">
                                    ${typeText}
                                </span>

                                ${
                                    product.product_type === 'TITIPAN' &&
                                    product.consignor_name
                                    ? `
                                        <span class="text-xs text-neutral-400">
                                            • ${escapeHtml(product.consignor_name)}
                                        </span>
                                    `
                                    : ''
                                }

                            </div>

                        </div>


                        <div class="text-right flex-shrink-0">

                            <div class="text-sm font-semibold text-neutral-900">
                                ${rupiah(product.selling_price)}
                            </div>

                            <div class="text-xs ${
                                noStock
                                    ? 'text-red-500'
                                    : 'text-neutral-400'
                            } mt-1">
                                ${stockText}
                            </div>

                        </div>

                    </div>

                </button>

            `;

        })
        .join('');

}


searchInput.addEventListener(
    'input',
    renderSearchResults
);


/*
|--------------------------------------------------------------------------
| ADD TO CART
|--------------------------------------------------------------------------
*/

function addToCart(productId) {

    const product =
        products.find(
            p => Number(p.id) === Number(productId)
        );

    if (!product) return;


    if (
        product.current_stock <= 0
    ) {
        return;
    }


    if (cart[productId]) {
        return;
    }


    cart[productId] = {
        product: product,
        quantity: 1
    };


    searchInput.value = '';

    productResults.innerHTML = '';

    renderCart();

}


/*
|--------------------------------------------------------------------------
| CHANGE QTY
|--------------------------------------------------------------------------
*/

function changeQty(
    productId,
    change
) {

    if (!cart[productId]) return;

    const product =
        cart[productId].product;

    let quantity =
        cart[productId].quantity +
        change;

    if (quantity < 1) {
        quantity = 1;
    }

    if (
        quantity >
        product.current_stock
    ) {

        quantity =
            product.current_stock;

    }

    cart[productId].quantity =
        quantity;

    renderCart();

}


/*
|--------------------------------------------------------------------------
| REMOVE
|--------------------------------------------------------------------------
*/

function removeFromCart(productId) {

    delete cart[productId];

    renderCart();

}


/*
|--------------------------------------------------------------------------
| RENDER CART
|--------------------------------------------------------------------------
*/

function renderCart() {

    const cartElement =
        document.getElementById('cart');

    const emptyElement =
        document.getElementById('cart-empty');

    const cartItems =
        Object.values(cart);


    if (cartItems.length === 0) {

        cartElement.innerHTML = `
            <div
                id="cart-empty"
                class="px-6 py-14 text-center"
            >

                <div class="w-14 h-14 mx-auto rounded-2xl bg-neutral-100 flex items-center justify-center mb-4">

                    <i
                        data-lucide="shopping-cart"
                        class="w-6 h-6 text-neutral-400"
                    ></i>

                </div>

                <h3 class="font-semibold">
                    Keranjang masih kosong
                </h3>

                <p class="text-sm text-neutral-400 mt-1">
                    Pilih barang di atas untuk memulai transaksi.
                </p>

            </div>
        `;

        updateSummary();

        if (
            typeof lucide !== 'undefined'
        ) {
            lucide.createIcons();
        }

        return;
    }


    cartElement.innerHTML =
        cartItems.map(item => {

            const product =
                item.product;

            const subtotal =
                product.selling_price *
                item.quantity;

            const maxStock =
                product.current_stock;


            return `

                <div class="cart-item">

                    <input
                        type="hidden"
                        name="product_id[]"
                        value="${product.id}"
                    >

                    <input
                        type="hidden"
                        name="quantity[]"
                        value="${item.quantity}"
                    >


                    <div class="flex items-start gap-4">


                        <div class="min-w-0 flex-1">

                            <div class="font-medium text-sm text-neutral-900">
                                ${escapeHtml(product.name)}
                            </div>

                            <div class="text-xs text-neutral-400 mt-1">

                                ${escapeHtml(product.sku)}

                                <span class="mx-1">
                                    ·
                                </span>

                                ${product.product_type === 'TITIPAN'
                                    ? 'Titipan'
                                    : 'Toko'}

                            </div>


                            ${
                                product.product_type === 'TITIPAN'
                                ? `
                                    <div class="text-xs text-violet-600 mt-1">
                                        Fee toko:
                                        ${
                                            product.fee_type === 'PERCENTAGE'
                                            ? Number(product.fee_value).toLocaleString('id-ID') + '%'
                                            : rupiah(product.fee_value)
                                        }
                                    </div>
                                `
                                : ''
                            }


                            <div class="flex items-center gap-3 mt-4">


                                <div class="qty-control">

                                    <button
                                        type="button"
                                        onclick="changeQty(${product.id}, -1)"
                                    >
                                        −
                                    </button>

                                    <span>
                                        ${item.quantity}
                                    </span>

                                    <button
                                        type="button"
                                        onclick="changeQty(${product.id}, 1)"
                                        ${
                                            item.quantity >= maxStock
                                                ? 'disabled style="opacity:.35;cursor:not-allowed;"'
                                                : ''
                                        }
                                    >
                                        +
                                    </button>

                                </div>


                                <span class="text-xs text-neutral-400">
                                    Stok ${maxStock}
                                </span>


                                <button
                                    type="button"
                                    onclick="removeFromCart(${product.id})"
                                    class="text-neutral-400 hover:text-red-500"
                                    title="Hapus"
                                >

                                    <i
                                        data-lucide="trash-2"
                                        class="w-4 h-4"
                                    ></i>

                                </button>

                            </div>

                        </div>


                        <div class="text-right flex-shrink-0">

                            <div class="text-xs text-neutral-400">
                                ${rupiah(product.selling_price)}
                            </div>

                            <div class="font-semibold text-sm mt-1">
                                ${rupiah(subtotal)}
                            </div>

                        </div>

                    </div>

                </div>

            `;

        })
        .join('');


    updateSummary();


    if (
        typeof lucide !== 'undefined'
    ) {
        lucide.createIcons();
    }

}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

function updateSummary() {

    const items =
        Object.values(cart);

    let totalQty = 0;

    let totalAmount = 0;


    items.forEach(item => {

        totalQty +=
            item.quantity;

        totalAmount +=
            item.product.selling_price *
            item.quantity;

    });


    document.getElementById(
        'summary-qty'
    ).textContent =
        totalQty.toLocaleString('id-ID');


    document.getElementById(
        'summary-items'
    ).textContent =
        items.length.toLocaleString('id-ID');


    document.getElementById(
        'grand-total'
    ).textContent =
        rupiah(totalAmount);


    document.getElementById(
        'cart-total-small'
    ).textContent =
        rupiah(totalAmount);


    document.getElementById(
        'cart-count-label'
    ).textContent =
        items.length === 0
            ? 'Belum ada barang'
            : totalQty.toLocaleString('id-ID') +
              ' barang · ' +
              items.length.toLocaleString('id-ID') +
              ' jenis';


    document.getElementById(
        'submit-sale'
    ).disabled =
        items.length === 0;

}


/*
|--------------------------------------------------------------------------
| SUBMIT PROTECTION
|--------------------------------------------------------------------------
*/

document
    .getElementById('sale-form')
    .addEventListener(
        'submit',
        function (event) {

            const items =
                Object.values(cart);

            if (items.length === 0) {

                event.preventDefault();

                alert(
                    'Pilih minimal satu barang.'
                );

                return;
            }


            const submitButton =
                document.getElementById(
                    'submit-sale'
                );

            submitButton.disabled =
                true;

            submitButton.innerHTML = `
                <span class="animate-pulse">
                    Menyimpan...
                </span>
            `;

        }
    );


/*
|--------------------------------------------------------------------------
| INIT
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        updateSummary();

        if (
            typeof lucide !== 'undefined'
        ) {
            lucide.createIcons();
        }

    }
);

</script>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>