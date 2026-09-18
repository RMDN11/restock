<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = 'Tambah Barang';

/*
|--------------------------------------------------------------------------
| SESSION / CSRF
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

function parseMoney($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return 0;
    }

    // Hilangkan Rp, spasi, titik ribuan
    $value = preg_replace('/[^\d,.-]/', '', $value);

    // Format Indonesia: 10.000,50
    if (strpos($value, ',') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } else {
        $value = str_replace('.', '', $value);
    }

    return (float) $value;
}

/*
|--------------------------------------------------------------------------
| DATA DROPDOWN
|--------------------------------------------------------------------------
*/

// Kategori aktif
$stmt = $pdo->prepare("
    SELECT id, name
    FROM product_categories
    WHERE status = 'ACTIVE'
      AND store_id = :category_store_id
    ORDER BY name ASC
");
$stmt->execute([
    ':category_store_id' => $authStoreId
]);

$categories = $stmt->fetchAll();

// Penitip aktif
$stmt = $pdo->prepare("
    SELECT id, name, phone
    FROM consignors
    WHERE status = 'ACTIVE'
      AND store_id = :consignor_store_id
    ORDER BY name ASC
");
$stmt->execute([
    ':consignor_store_id' => $authStoreId
]);

$consignors = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| DEFAULT
|--------------------------------------------------------------------------
*/

$old = [
    'sku'            => '',
    'barcode'        => '',
    'name'           => '',
    'product_type'   => 'TOKO',
    'category_id'    => '',
    'unit'           => 'pcs',
    'buying_price'   => '',
    'selling_price'  => '',
    'current_stock'  => '0',
    'minimum_stock'  => '0',

    'consignor_id'   => '',
    'initial_price'  => '',
    'fee_type'       => 'FIXED',
    'fee_value'      => '0',
    'start_date'     => date('Y-m-d'),
    'notes'          => '',
];

$errors = [];

/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($old as $key => $value) {
        if (isset($_POST[$key])) {
            $old[$key] = trim((string) $_POST[$key]);
        }
    }

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
        $errors[] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALISASI
    |--------------------------------------------------------------------------
    */

    $name = trim($old['name']);
    $productType = strtoupper($old['product_type']);

    $categoryId = (int) $old['category_id'];
    $unit = trim($old['unit']) ?: 'pcs';

    $buyingPrice = parseMoney($old['buying_price']);
    $sellingPrice = parseMoney($old['selling_price']);

    $currentStock = (int) $old['current_stock'];
    $minimumStock = (int) $old['minimum_stock'];

    /*
    |--------------------------------------------------------------------------
    | VALIDASI UMUM
    |--------------------------------------------------------------------------
    */

    if ($name === '') {
        $errors[] = 'Nama barang wajib diisi.';
    }

    if (!in_array($productType, ['TOKO', 'TITIPAN'], true)) {
        $errors[] = 'Jenis barang tidak valid.';
    }

    if ($categoryId > 0) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM product_categories
            WHERE id = :id
              AND store_id = :store_id
              AND status = 'ACTIVE'
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $categoryId,
            ':store_id' => $authStoreId
        ]);

        if (!$stmt->fetch()) {
            $errors[] = 'Kategori yang dipilih tidak tersedia.';
        }
    } else {
        $categoryId = null;
    }

    if ($currentStock < 0) {
        $errors[] = 'Stok awal tidak boleh kurang dari 0.';
    }

    if ($minimumStock < 0) {
        $errors[] = 'Minimum stok tidak boleh kurang dari 0.';
    }

    if ($buyingPrice < 0) {
        $errors[] = 'Harga modal tidak boleh kurang dari 0.';
    }

    if ($sellingPrice < 0) {
        $errors[] = 'Harga jual tidak boleh kurang dari 0.';
    }

    /*
    |--------------------------------------------------------------------------
    | SKU
    |--------------------------------------------------------------------------
    |
    | SKU dibuat otomatis jika dikosongkan.
    |
    */

    $sku = strtoupper(trim($old['sku']));

    if ($sku === '') {

        do {

            $sku = 'BRG-' . date('ymd') . '-' . strtoupper(
                substr(bin2hex(random_bytes(3)), 0, 6)
            );

            $stmt = $pdo->prepare("
                SELECT id
                FROM products
                WHERE sku = :sku
                  AND store_id = :store_id
                LIMIT 1
            ");

            $stmt->execute([
                ':sku'      => $sku,
                ':store_id' => $authStoreId
            ]);

        } while ($stmt->fetch());

    } else {

        $stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE sku = :sku
              AND store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute([
            ':sku' => $sku,
            ':store_id' => $authStoreId
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'SKU tersebut sudah digunakan.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BARCODE
    |--------------------------------------------------------------------------
    */

    $barcode = trim($old['barcode']);

    if ($barcode !== '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE barcode = :barcode
              AND store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute([
            ':barcode' => $barcode,
            ':store_id' => $authStoreId
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Barcode tersebut sudah digunakan.';
        }
    } else {
        $barcode = null;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI TITIPAN
    |--------------------------------------------------------------------------
    */

    $consignorId = null;
    $initialPrice = 0;
    $feeType = 'FIXED';
    $feeValue = 0;
    $startDate = date('Y-m-d');
    $consignmentNotes = '';

    if ($productType === 'TITIPAN') {

        $consignorId = (int) $old['consignor_id'];

        $initialPrice = parseMoney($old['initial_price']);

        $feeType = strtoupper($old['fee_type']);

        $feeValue = parseMoney($old['fee_value']);

        $startDate = $old['start_date'] ?: date('Y-m-d');

        $consignmentNotes = trim($old['notes']);

        if ($consignorId <= 0) {

            $errors[] = 'Penitip wajib dipilih.';

        } else {

            $stmt = $pdo->prepare("
                SELECT id
                FROM consignors
                WHERE id = :id
                AND status = 'ACTIVE'
                AND store_id = :store_id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $consignorId,
                ':store_id' => $authStoreId
            ]);

            if (!$stmt->fetch()) {
                $errors[] = 'Penitip yang dipilih tidak tersedia.';
            }
        }

        if ($initialPrice < 0) {
            $errors[] = 'Harga awal titipan tidak boleh kurang dari 0.';
        }

        if (!in_array($feeType, ['FIXED', 'PERCENTAGE'], true)) {
            $errors[] = 'Jenis fee tidak valid.';
        }

        if ($feeValue < 0) {
            $errors[] = 'Fee tidak boleh kurang dari 0.';
        }

        if (
            $feeType === 'PERCENTAGE' &&
            $feeValue > 100
        ) {
            $errors[] = 'Fee persentase tidak boleh lebih dari 100%.';
        }

        $dateCheck = DateTime::createFromFormat(
            'Y-m-d',
            $startDate
        );

        if (
            !$dateCheck ||
            $dateCheck->format('Y-m-d') !== $startDate
        ) {
            $errors[] = 'Tanggal mulai titipan tidak valid.';
        }

    } else {

        /*
        |--------------------------------------------------------------------------
        | PRODUK TOKO
        |--------------------------------------------------------------------------
        */

        $initialPrice = 0;
        $feeType = 'FIXED';
        $feeValue = 0;
        $consignorId = null;
        $startDate = date('Y-m-d');
        $consignmentNotes = '';
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | INSERT PRODUCTS
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO products (
                    store_id,
                    sku,
                    barcode,
                    name,
                    product_type,
                    category_id,
                    unit,
                    buying_price,
                    selling_price,
                    current_stock,
                    minimum_stock,
                    status,
                    created_by
                ) VALUES (
                    :store_id,
                    :sku,
                    :barcode,
                    :name,
                    :product_type,
                    :category_id,
                    :unit,
                    :buying_price,
                    :selling_price,
                    :current_stock,
                    :minimum_stock,
                    'ACTIVE',
                    :created_by
                )
            ");

            $stmt->execute([
                ':store_id'      => $authStoreId,
                ':sku'           => $sku,
                ':barcode'       => $barcode,
                ':name'          => $name,
                ':product_type'  => $productType,
                ':category_id'   => $categoryId,
                ':unit'          => $unit,
                ':buying_price'  => $buyingPrice,
                ':selling_price' => $sellingPrice,
                ':current_stock' => $currentStock,
                ':minimum_stock' => $minimumStock,
                ':created_by'    => $_SESSION['user_id'] ?? null,
            ]);

            $productId = (int) $pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | TITIPAN
            |--------------------------------------------------------------------------
            */

            if ($productType === 'TITIPAN') {

                $stmt = $pdo->prepare("
                    INSERT INTO consignments (
                        store_id,
                        product_id,
                        consignor_id,
                        initial_price,
                        fee_type,
                        fee_value,
                        start_date,
                        status,
                        notes
                    ) VALUES (
                        :store_id,
                        :product_id,
                        :consignor_id,
                        :initial_price,
                        :fee_type,
                        :fee_value,
                        :start_date,
                        'ACTIVE',
                        :notes
                    )
                ");

                $stmt->execute([
                    ':store_id'      => $authStoreId,
                    ':product_id'    => $productId,
                    ':consignor_id'  => $consignorId,
                    ':initial_price' => $initialPrice,
                    ':fee_type'      => $feeType,
                    ':fee_value'     => $feeValue,
                    ':start_date'    => $startDate,
                    ':notes'         => $consignmentNotes ?: null,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | STOK AWAL
            |--------------------------------------------------------------------------
            */

            if ($currentStock > 0) {

                $movementType =
                    $productType === 'TITIPAN'
                        ? 'TITIPAN_MASUK'
                        : 'KOREKSI_MASUK';

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
                    ) VALUES (
                        :store_id,
                        :product_id,
                        :movement_type,
                        :quantity,
                        'PRODUCT',
                        :reference_id,
                        :notes,
                        :created_by
                    )
                ");

                $stmt->execute([
                    ':store_id'      => $authStoreId,
                    ':product_id'    => $productId,
                    ':movement_type' => $movementType,
                    ':quantity'      => $currentStock,
                    ':reference_id'  => $productId,
                    ':notes'         => 'Stok awal saat barang dibuat.',
                    ':created_by'    => $_SESSION['user_id'] ?? null,
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
                ) VALUES (
                    :store_id,
                    :user_id,
                    'CREATE',
                    'products',
                    :record_id,
                    :description
                )
            ");

            $stmt->execute([
                ':store_id'   => $authStoreId,
                ':user_id'    => $_SESSION['user_id'] ?? null,
                ':record_id'  => $productId,
                ':description' =>
                    'Menambahkan barang: ' .
                    $name .
                    ' (' .
                    $productType .
                    ')',
            ]);

            $pdo->commit();

            header(
                'Location: /pages/barang/?success=created'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'Barang gagal disimpan: ' .
                $e->getMessage();
        }
    }
}

?>

<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>


<main class="main-content">
<div class="p-4 sm:p-6 lg:p-8">

        <!-- HEADER -->

        <div class="mb-6">

            <a
                href="/pages/barang/"
                class="inline-flex items-center gap-2 text-sm text-neutral-500 hover:text-neutral-900 mb-4"
            >
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Kembali ke Barang
            </a>

            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Tambah Barang
                </h1>

                <p class="mt-1 text-sm text-neutral-500">
                    Tambahkan barang toko atau barang titipan.
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
                            Barang belum disimpan
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
            class="space-y-6"
            autocomplete="off"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >


            <!-- DATA BARANG -->

            <div class="bento-card p-5 sm:p-6">

                <div class="mb-6">

                    <h2 class="font-semibold text-lg">
                        Data Barang
                    </h2>

                    <p class="text-sm text-neutral-500 mt-1">
                        Informasi dasar barang.
                    </p>

                </div>


                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">


                    <!-- JENIS -->

                    <!-- JENIS BARANG -->

<div class="md:col-span-2">

    <div class="mb-5">

        <h2 class="text-xl font-semibold tracking-tight text-neutral-900">
            Jenis Barang
        </h2>

        <p class="text-sm text-neutral-500 mt-2">
            Tentukan barang ini milik toko atau barang titipan.
        </p>

    </div>


    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">


        <!-- PRODUK TOKO -->

        <label class="product-type-card">

            <input
                type="radio"
                name="product_type"
                value="TOKO"
                class="sr-only"
                <?= $old['product_type'] === 'TOKO' ? 'checked' : '' ?>
                onchange="updateType()"
            >

            <div class="product-type-content">

                <div class="product-type-icon">

                    <i
                        data-lucide="store"
                        class="w-8 h-8"
                    ></i>

                </div>

                <div class="mt-6">

                    <div class="text-xl font-medium text-neutral-900">
                        Produk Toko
                    </div>

                    <div class="text-base text-neutral-500 mt-2">
                        Milik toko sendiri
                    </div>

                </div>

            </div>

        </label>


        <!-- PRODUK TITIPAN -->

        <label class="product-type-card">

            <input
                type="radio"
                name="product_type"
                value="TITIPAN"
                class="sr-only"
                <?= $old['product_type'] === 'TITIPAN' ? 'checked' : '' ?>
                onchange="updateType()"
            >

            <div class="product-type-content">

                <div class="product-type-icon">

                    <i
                        data-lucide="handshake"
                        class="w-8 h-8"
                    ></i>

                </div>

                <div class="mt-6">

                    <div class="text-xl font-medium text-neutral-900">
                        Produk Titipan
                    </div>

                    <div class="text-base text-neutral-500 mt-2">
                        Milik penitip
                    </div>

                </div>

            </div>

        </label>

    </div>

</div>

                    <!-- NAMA -->

                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium mb-2">
                            Nama Barang
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            name="name"
                            value="<?= e($old['name']) ?>"
                            required
                            maxlength="150"
                            placeholder="Contoh: Indomie Goreng"
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                    </div>


                    <!-- SKU -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            SKU
                        </label>

                        <input
                            type="text"
                            name="sku"
                            value="<?= e($old['sku']) ?>"
                            maxlength="50"
                            placeholder="Kosongkan untuk otomatis"
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm uppercase outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                        <p class="text-xs text-neutral-400 mt-2">
                            Akan dibuat otomatis jika dikosongkan.
                        </p>

                    </div>


                    <!-- BARCODE -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Barcode
                        </label>

                        <input
                            type="text"
                            name="barcode"
                            value="<?= e($old['barcode']) ?>"
                            maxlength="100"
                            placeholder="Opsional"
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                    </div>


                    <!-- KATEGORI -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Kategori
                        </label>

                        <select
                            name="category_id"
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm bg-white outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                            <option value="">
                                Tanpa Kategori
                            </option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int) $category['id'] ?>"
                                    <?= (int) $old['category_id'] === (int) $category['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($category['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <a
                            href="/pages/barang/kategori/create.php"
                            class="inline-flex items-center gap-1 mt-2 text-xs text-neutral-500 hover:text-neutral-900"
                        >
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            Tambah kategori
                        </a>

                    </div>


                    <!-- SATUAN -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Satuan
                        </label>

                        <input
                            type="text"
                            name="unit"
                            value="<?= e($old['unit']) ?>"
                            maxlength="30"
                            placeholder="pcs"
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                    </div>

                </div>

            </div>


            <!-- HARGA & STOK -->

            <div class="bento-card p-5 sm:p-6">

                <div class="mb-6">

                    <h2 class="font-semibold text-lg">
                        Harga & Stok
                    </h2>

                    <p class="text-sm text-neutral-500 mt-1">
                        Atur harga dan jumlah stok awal.
                    </p>

                </div>


                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">


                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Harga Modal
                        </label>

                        <input
                            type="text"
                            name="buying_price"
                            value="<?= e($old['buying_price']) ?>"
                            inputmode="numeric"
                            placeholder="0"
                            class="money-input w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                    </div>


                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Harga Jual
                        </label>

                        <input
                            type="text"
                            name="selling_price"
                            value="<?= e($old['selling_price']) ?>"
                            inputmode="numeric"
                            placeholder="0"
                            class="money-input w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                    </div>


                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Stok Awal
                        </label>

                        <input
                            type="number"
                            name="current_stock"
                            value="<?= e($old['current_stock']) ?>"
                            min="0"
                            step="1"
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                    </div>


                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Minimum Stok
                        </label>

                        <input
                            type="number"
                            name="minimum_stock"
                            value="<?= e($old['minimum_stock']) ?>"
                            min="0"
                            step="1"
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                    </div>

                </div>

            </div>


            <!-- TITIPAN -->

            <div
                id="titipan-section"
                class="bento-card p-5 sm:p-6"
                style="<?= $old['product_type'] === 'TITIPAN' ? '' : 'display:none;' ?>"
            >

                <div class="mb-6">

                    <h2 class="font-semibold text-lg">
                        Data Titipan
                    </h2>

                    <p class="text-sm text-neutral-500 mt-1">
                        Data penitip dan pembagian hasil barang.
                    </p>

                </div>


                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">


                    <!-- PENITIP -->

                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium mb-2">
                            Penitip
                            <span class="text-red-500">*</span>
                        </label>

                        <select
                            name="consignor_id"
                            id="consignor_id"
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm bg-white outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                            <option value="">
                                Pilih Penitip
                            </option>

                            <?php foreach ($consignors as $consignor): ?>

                                <option
                                    value="<?= (int) $consignor['id'] ?>"
                                    <?= (int) $old['consignor_id'] === (int) $consignor['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($consignor['name']) ?>
                                    <?php if (!empty($consignor['phone'])): ?>
                                        - <?= e($consignor['phone']) ?>
                                    <?php endif; ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <a
                            href="/pages/titipan/penitip/create.php"
                            class="inline-flex items-center gap-1 mt-2 text-xs text-neutral-500 hover:text-neutral-900"
                        >
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            Tambah penitip
                        </a>

                    </div>


                    <!-- HARGA AWAL -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Harga Awal
                        </label>

                        <input
                            type="text"
                            name="initial_price"
                            value="<?= e($old['initial_price']) ?>"
                            inputmode="numeric"
                            placeholder="0"
                            class="money-input w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                    </div>


                    <!-- TANGGAL -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Mulai Titipan
                        </label>

                        <input
                            type="date"
                            name="start_date"
                            value="<?= e($old['start_date']) ?>"
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                    </div>


                    <!-- FEE TYPE -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Jenis Fee
                        </label>

                        <select
                            name="fee_type"
                            id="fee_type"
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm bg-white outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >

                            <option
                                value="FIXED"
                                <?= $old['fee_type'] === 'FIXED' ? 'selected' : '' ?>
                            >
                                Nominal Tetap
                            </option>

                            <option
                                value="PERCENTAGE"
                                <?= $old['fee_type'] === 'PERCENTAGE' ? 'selected' : '' ?>
                            >
                                Persentase
                            </option>

                        </select>

                    </div>


                    <!-- FEE -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Fee Toko
                        </label>

                        <div class="relative">

                            <input
                                type="text"
                                name="fee_value"
                                value="<?= e($old['fee_value']) ?>"
                                inputmode="numeric"
                                placeholder="0"
                                class="money-input w-full rounded-xl border border-neutral-200 px-4 py-3 pr-14 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                            >

                            <span
                                id="fee-suffix"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-neutral-400"
                            >
                                Rp
                            </span>

                        </div>

                    </div>


                    <!-- NOTES -->

                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium mb-2">
                            Catatan
                        </label>

                        <textarea
                            name="notes"
                            rows="3"
                            placeholder="Catatan tambahan..."
                            class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none resize-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        ><?= e($old['notes']) ?></textarea>

                    </div>

                </div>

            </div>


            <!-- ACTION -->

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">

                <a
                    href="/pages/barang/"
                    class="inline-flex items-center justify-center px-5 py-3 rounded-xl border border-neutral-200 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-neutral-900 text-white text-sm font-semibold hover:bg-neutral-800"
                >
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Simpan Barang
                </button>

            </div>

        </form>

    </div>

</main>


<style>

.product-type-card {
    display: block;
    cursor: pointer;
}

.product-type-content {
    min-height: 150px;
    padding: 22px;
    border: 1.5px solid #e5e5e5;
    border-radius: 22px;
    background: #ffffff;
    transition:
        border-color .18s ease,
        background-color .18s ease,
        box-shadow .18s ease;
}

.product-type-card:hover .product-type-content {
    border-color: #d4d4d4;
    background: #fafafa;
}

.product-type-card input:checked + .product-type-content {
    border-color: #171717;
    background: #fafafa;
    box-shadow: 0 3px 12px rgba(0, 0, 0, .03);
}

.product-type-icon {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    color: #171717;
}

.product-type-icon svg {
    width: 24px;
    height: 24px;
    stroke-width: 1.8;
}

.product-type-content .mt-6 {
    margin-top: 18px !important;
}
</style>


<script>

function updateType() {

    const type = document.querySelector(
        'input[name="product_type"]:checked'
    )?.value;

    const section = document.getElementById(
        'titipan-section'
    );

    const consignor = document.getElementById(
        'consignor_id'
    );

    if (!section) return;

    if (type === 'TITIPAN') {

        section.style.display = '';

        if (consignor) {
            consignor.required = true;
        }

    } else {

        section.style.display = 'none';

        if (consignor) {
            consignor.required = false;
        }

    }

}


function updateFeeSuffix() {

    const feeType = document.getElementById(
        'fee_type'
    );

    const suffix = document.getElementById(
        'fee-suffix'
    );

    if (!feeType || !suffix) return;

    suffix.textContent =
        feeType.value === 'PERCENTAGE'
            ? '%'
            : 'Rp';

}


document.addEventListener(
    'DOMContentLoaded',
    function () {

        updateType();
        updateFeeSuffix();

        const feeType =
            document.getElementById('fee_type');

        if (feeType) {
            feeType.addEventListener(
                'change',
                updateFeeSuffix
            );
        }

        if (
            typeof lucide !== 'undefined'
        ) {
            lucide.createIcons();
        }

    }
);

</script>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>