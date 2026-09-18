<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/auth.php';
require_once '../../config/database.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) { http_response_code(403); exit('Store aktif tidak ditemukan.'); }

$pageTitle = 'Tambah Barang Titipan';

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

    $value = str_replace(['Rp', 'rp', ' '], '', $value);

    /*
     * Format Indonesia:
     * 25.000
     * 25.000,50
     */

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
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| DEFAULT
|--------------------------------------------------------------------------
*/

$old = [
    'sku'            => '',
    'barcode'        => '',
    'name'           => '',
    'category_id'    => '',
    'unit'           => 'pcs',
    'initial_price'  => '',
    'selling_price'  => '',
    'stock'          => '0',
    'minimum_stock'  => '0',
    'consignor_id'   => '',
    'fee_type'       => 'FIXED',
    'fee_value'      => '',
    'start_date'     => date('Y-m-d'),
    'end_date'       => '',
    'notes'          => '',
];

$error = '';

/*
|--------------------------------------------------------------------------
| LOAD CATEGORY
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            id,
            name
        FROM product_categories
        WHERE status = 'ACTIVE'
        AND store_id = $storeId
        ORDER BY name ASC
    ");

    $categories = $stmt->fetchAll();

} catch (PDOException $e) {

    die(
        '<pre style="padding:20px;font-family:monospace;">' .
        'DATABASE ERROR - CATEGORY' .
        "\n\n" .
        e($e->getMessage()) .
        '</pre>'
    );
}

/*
|--------------------------------------------------------------------------
| LOAD PENITIP
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            phone
        FROM consignors
        WHERE status = 'ACTIVE'
        AND store_id = $storeId
        ORDER BY name ASC
    ");

    $consignors = $stmt->fetchAll();

} catch (PDOException $e) {

    die(
        '<pre style="padding:20px;font-family:monospace;">' .
        'DATABASE ERROR - PENITIP' .
        "\n\n" .
        e($e->getMessage()) .
        '</pre>'
    );
}

/*
|--------------------------------------------------------------------------
| SUBMIT
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
            $csrfToken,
            $_POST['csrf_token']
        )
    ) {
        $error = 'Sesi formulir sudah tidak berlaku. Silakan coba lagi.';
    }

    /*
    |--------------------------------------------------------------------------
    | INPUT
    |--------------------------------------------------------------------------
    */

    $old['sku'] = trim($_POST['sku'] ?? '');
    $old['barcode'] = trim($_POST['barcode'] ?? '');
    $old['name'] = trim($_POST['name'] ?? '');
    $old['category_id'] = trim($_POST['category_id'] ?? '');
    $old['unit'] = trim($_POST['unit'] ?? 'pcs');

    $old['initial_price'] = trim(
        $_POST['initial_price'] ?? ''
    );

    $old['selling_price'] = trim(
        $_POST['selling_price'] ?? ''
    );

    $old['stock'] = trim(
        $_POST['stock'] ?? '0'
    );

    $old['minimum_stock'] = trim(
        $_POST['minimum_stock'] ?? '0'
    );

    $old['consignor_id'] = trim(
        $_POST['consignor_id'] ?? ''
    );

    $old['fee_type'] = $_POST['fee_type'] ?? 'FIXED';

    $old['fee_value'] = trim(
        $_POST['fee_value'] ?? ''
    );

    $old['start_date'] = trim(
        $_POST['start_date'] ?? date('Y-m-d')
    );

    $old['end_date'] = trim(
        $_POST['end_date'] ?? ''
    );

    $old['notes'] = trim(
        $_POST['notes'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE
    |--------------------------------------------------------------------------
    */

    $initialPrice = parseMoney(
        $old['initial_price']
    );

    $sellingPrice = parseMoney(
        $old['selling_price']
    );

    $stock = (int) $old['stock'];

    $minimumStock = (int) $old['minimum_stock'];

    $consignorId = (int) $old['consignor_id'];

    $feeValue = parseMoney(
        $old['fee_value']
    );

    /*
    |--------------------------------------------------------------------------
    | VALIDASI DASAR
    |--------------------------------------------------------------------------
    */

    if ($error === '' && $old['name'] === '') {
        $error = 'Nama barang wajib diisi.';
    }

    if ($error === '' && $old['unit'] === '') {
        $error = 'Satuan barang wajib diisi.';
    }

    if ($error === '' && $consignorId <= 0) {
        $error = 'Penitip wajib dipilih.';
    }

    if ($error === '' && $initialPrice < 0) {
        $error = 'Harga awal tidak boleh kurang dari 0.';
    }

    if ($error === '' && $sellingPrice <= 0) {
        $error = 'Harga jual harus lebih dari 0.';
    }

    if ($error === '' && $stock < 0) {
        $error = 'Stok tidak boleh kurang dari 0.';
    }

    if ($error === '' && $minimumStock < 0) {
        $error = 'Minimum stok tidak boleh kurang dari 0.';
    }

    if (
        $error === '' &&
        !in_array(
            $old['fee_type'],
            ['FIXED', 'PERCENTAGE'],
            true
        )
    ) {
        $error = 'Jenis fee tidak valid.';
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI FEE
    |--------------------------------------------------------------------------
    */

    if ($error === '' && $feeValue < 0) {
        $error = 'Fee tidak boleh kurang dari 0.';
    }

    if (
        $error === '' &&
        $old['fee_type'] === 'PERCENTAGE' &&
        $feeValue > 100
    ) {
        $error = 'Fee persentase tidak boleh lebih dari 100%.';
    }

    if (
        $error === '' &&
        $old['fee_type'] === 'FIXED' &&
        $feeValue > $sellingPrice
    ) {
        $error = 'Fee toko tidak boleh lebih besar dari harga jual.';
    }

    /*
    |--------------------------------------------------------------------------
    | TANGGAL
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $startDateObj = DateTime::createFromFormat(
            'Y-m-d',
            $old['start_date']
        );

        if (
            !$startDateObj ||
            $startDateObj->format('Y-m-d') !== $old['start_date']
        ) {
            $error = 'Tanggal mulai tidak valid.';
        }
    }

    if (
        $error === '' &&
        $old['end_date'] !== ''
    ) {

        $endDateObj = DateTime::createFromFormat(
            'Y-m-d',
            $old['end_date']
        );

        if (
            !$endDateObj ||
            $endDateObj->format('Y-m-d') !== $old['end_date']
        ) {
            $error = 'Tanggal selesai tidak valid.';
        }

        if (
            $error === '' &&
            $old['end_date'] < $old['start_date']
        ) {
            $error = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            /*
            |--------------------------------------------------------------------------
            | CEK PENITIP
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    name
                FROM consignors
                WHERE id = :consignor_id
                AND store_id = $storeId
                AND status = 'ACTIVE'
                LIMIT 1
            ");

            $stmt->execute([
                ':consignor_id' => $consignorId
            ]);

            $consignor = $stmt->fetch();

            if (!$consignor) {

                $error = 'Penitip tidak ditemukan atau sudah tidak aktif.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | AUTO SKU
                |--------------------------------------------------------------------------
                */

                $sku = $old['sku'];

                if ($sku === '') {

                    $sku = 'TIT-' .
                        date('Ymd') .
                        '-' .
                        strtoupper(
                            substr(
                                bin2hex(random_bytes(3)),
                                0,
                                6
                            )
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | CEK SKU
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM products
                    WHERE sku = :sku
                    AND store_id = $storeId
                    LIMIT 1
                ");

                $stmt->execute([
                    ':sku' => $sku
                ]);

                if ($stmt->fetch()) {

                    $error = 'SKU sudah digunakan. Silakan gunakan SKU lain.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CEK BARCODE
                    |--------------------------------------------------------------------------
                    */

                    if ($old['barcode'] !== '') {

                        $stmt = $pdo->prepare("
                            SELECT id
                            FROM products
                            WHERE barcode = :barcode
                            AND store_id = $storeId
                            LIMIT 1
                        ");

                        $stmt->execute([
                            ':barcode' => $old['barcode']
                        ]);

                        if ($stmt->fetch()) {

                            $error = 'Barcode sudah digunakan.';
                        }
                    }


                    if ($error === '') {

                        $pdo->beginTransaction();

                        /*
                        |--------------------------------------------------------------------------
                        | INSERT PRODUCT
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
                                status
                            ) VALUES (
                                $storeId,
                                :sku,
                                :barcode,
                                :name,
                                'TITIPAN',
                                :category_id,
                                :unit,
                                0,
                                :selling_price,
                                :current_stock,
                                :minimum_stock,
                                'ACTIVE'
                            )
                        ");

                        $stmt->execute([
                            ':sku' => $sku,

                            ':barcode' =>
                                $old['barcode'] !== ''
                                    ? $old['barcode']
                                    : null,

                            ':name' =>
                                $old['name'],

                            ':category_id' =>
                                $old['category_id'] !== ''
                                    ? (int) $old['category_id']
                                    : null,

                            ':unit' =>
                                $old['unit'],

                            ':selling_price' =>
                                $sellingPrice,

                            ':current_stock' =>
                                $stock,

                            ':minimum_stock' =>
                                $minimumStock,
                        ]);

                        $productId = (int) $pdo->lastInsertId();


                        /*
                        |--------------------------------------------------------------------------
                        | INSERT CONSIGNMENT
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $pdo->prepare("
                            INSERT INTO consignments (
                                store_id,
                                product_id,
                                consignor_id,
                                initial_price,
                                fee_type,
                                fee_value,
                                start_date,
                                end_date,
                                status,
                                notes
                            ) VALUES (
                                $storeId,
                                :product_id,
                                :consignor_id,
                                :initial_price,
                                :fee_type,
                                :fee_value,
                                :start_date,
                                :end_date,
                                'ACTIVE',
                                :notes
                            )
                        ");

                        $stmt->execute([
                            ':product_id' =>
                                $productId,

                            ':consignor_id' =>
                                $consignorId,

                            ':initial_price' =>
                                $initialPrice,

                            ':fee_type' =>
                                $old['fee_type'],

                            ':fee_value' =>
                                $feeValue,

                            ':start_date' =>
                                $old['start_date'],

                            ':end_date' =>
                                $old['end_date'] !== ''
                                    ? $old['end_date']
                                    : null,

                            ':notes' =>
                                $old['notes'] !== ''
                                    ? $old['notes']
                                    : null,
                        ]);


                        /*
                        |--------------------------------------------------------------------------
                        | STOCK MOVEMENT
                        |--------------------------------------------------------------------------
                        */

                        if ($stock > 0) {

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
                                    $storeId,
                                    :product_id,
                                    'TITIPAN_MASUK',
                                    :quantity,
                                    'CONSIGNMENT',
                                    :reference_id,
                                    :notes,
                                    :created_by
                                )
                            ");

                            $consignmentId =
                                (int) $pdo->lastInsertId();

                            $stmt->execute([
                                ':product_id' =>
                                    $productId,

                                ':quantity' =>
                                    $stock,

                                ':reference_id' =>
                                    $consignmentId,

                                ':notes' =>
                                    'Stok awal barang titipan',

                                ':created_by' =>
                                    $_SESSION['user_id'] ?? null,
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
                            $storeId,
                                :user_id,
                                'CREATE',
                                'products',
                                :record_id,
                                :description
                            )
                        ");

                        $stmt->execute([
                            ':user_id' =>
                                $_SESSION['user_id'] ?? null,

                            ':record_id' =>
                                $productId,

                            ':description' =>
                                'Menambahkan barang titipan: ' .
                                $old['name'] .
                                ' - Penitip: ' .
                                $consignor['name'],
                        ]);


                        $pdo->commit();


                        /*
                        |--------------------------------------------------------------------------
                        | REDIRECT
                        |--------------------------------------------------------------------------
                        */

                        header(
                            'Location: /pages/titipan/index.php?success=created'
                        );

                        exit;
                    }
                }
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error =
                'Barang titipan gagal disimpan: ' .
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require_once '../../includes/header.php';

?>

<?php require_once '../../includes/sidebar.php'; ?>


<main class="main-content">


    <!-- =============================================================
         MOBILE HEADER
    ============================================================== -->

<!-- =============================================================
         CONTENT
    ============================================================== -->

    <div class="
        p-5
        md:p-8
        lg:p-10
        max-w-5xl
    ">


        <!-- HEADER -->

        <div class="mb-8">

            <a
                href="/pages/titipan/index.php"
                class="
                    inline-flex
                    items-center
                    gap-2
                    text-sm
                    text-neutral-500
                    hover:text-neutral-900
                    mb-5
                "
            >

                <i
                    data-lucide="arrow-left"
                    class="w-4 h-4"
                ></i>

                Kembali ke Barang Titipan

            </a>


            <h1 class="
                text-2xl
                md:text-3xl
                font-semibold
                tracking-tight
                text-neutral-900
            ">
                Tambah Barang Titipan
            </h1>


            <p class="
                text-sm
                text-neutral-500
                mt-2
            ">
                Tambahkan barang milik penitip ke toko.
            </p>

        </div>


        <!-- ERROR -->

        <?php if ($error !== ''): ?>

            <div class="
                mb-6
                rounded-2xl
                border
                border-red-200
                bg-red-50
                p-4
                text-sm
                text-red-700
            ">

                <div class="flex items-start gap-3">

                    <i
                        data-lucide="circle-alert"
                        class="
                            w-5
                            h-5
                            shrink-0
                        "
                    ></i>

                    <div>
                        <?= e($error) ?>
                    </div>

                </div>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            class="space-y-6"
            id="titipanForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >


            <!-- =====================================================
                 DATA BARANG
            ====================================================== -->

            <div class="bento-card p-5 md:p-7">

                <div class="mb-6">

                    <h2 class="
                        text-lg
                        font-semibold
                        text-neutral-900
                    ">
                        Data Barang
                    </h2>

                    <p class="
                        text-sm
                        text-neutral-500
                        mt-1
                    ">
                        Informasi barang yang dititipkan.
                    </p>

                </div>


                <div class="
                    grid
                    grid-cols-1
                    md:grid-cols-2
                    gap-5
                ">


                    <!-- NAMA -->

                    <div class="md:col-span-2">

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">

                            Nama Barang

                            <span class="text-red-500">*</span>

                        </label>


                        <input
                            type="text"
                            name="name"
                            value="<?= e($old['name']) ?>"
                            placeholder="Contoh: Keripik Pisang"
                            required
                            autofocus
                            class="
                                w-full
                                px-4
                                py-3
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


                    <!-- SKU -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            SKU
                        </label>


                        <input
                            type="text"
                            name="sku"
                            value="<?= e($old['sku']) ?>"
                            placeholder="Kosongkan untuk otomatis"
                            class="
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                uppercase
                                outline-none
                                focus:border-neutral-400
                            "
                        >


                        <p class="
                            text-xs
                            text-neutral-400
                            mt-2
                        ">
                            Jika dikosongkan, SKU dibuat otomatis.
                        </p>

                    </div>


                    <!-- BARCODE -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Barcode
                        </label>


                        <input
                            type="text"
                            name="barcode"
                            value="<?= e($old['barcode']) ?>"
                            placeholder="Opsional"
                            class="
                                w-full
                                px-4
                                py-3
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


                    <!-- KATEGORI -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Kategori
                        </label>


                        <select
                            name="category_id"
                            class="
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                outline-none
                                focus:border-neutral-400
                            "
                        >

                            <option value="">
                                Tanpa Kategori
                            </option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int) $category['id'] ?>"
                                    <?= (string) $old['category_id'] === (string) $category['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($category['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SATUAN -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Satuan
                            <span class="text-red-500">*</span>
                        </label>


                        <input
                            type="text"
                            name="unit"
                            value="<?= e($old['unit']) ?>"
                            placeholder="pcs"
                            required
                            class="
                                w-full
                                px-4
                                py-3
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

                </div>

            </div>


            <!-- =====================================================
                 PENITIP
            ====================================================== -->

            <div class="bento-card p-5 md:p-7">

                <div class="mb-6">

                    <h2 class="
                        text-lg
                        font-semibold
                        text-neutral-900
                    ">
                        Data Penitip
                    </h2>

                    <p class="
                        text-sm
                        text-neutral-500
                        mt-1
                    ">
                        Tentukan siapa pemilik barang ini.
                    </p>

                </div>


                <div>

                    <label class="
                        block
                        text-sm
                        font-medium
                        text-neutral-800
                        mb-2
                    ">

                        Penitip

                        <span class="text-red-500">*</span>

                    </label>


                    <?php if (empty($consignors)): ?>

                        <div class="
                            rounded-xl
                            border
                            border-amber-200
                            bg-amber-50
                            p-4
                            text-sm
                            text-amber-700
                        ">

                            Belum ada penitip aktif.
                            Tambahkan penitip terlebih dahulu.

                            <a
                                href="/pages/titipan/create-penitip.php"
                                class="
                                    font-semibold
                                    underline
                                    underline-offset-2
                                    ml-1
                                "
                            >
                                Tambah Penitip
                            </a>

                        </div>

                    <?php else: ?>

                        <select
                            name="consignor_id"
                            required
                            class="
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                outline-none
                                focus:border-neutral-400
                            "
                        >

                            <option value="">
                                Pilih penitip
                            </option>

                            <?php foreach ($consignors as $consignor): ?>

                                <option
                                    value="<?= (int) $consignor['id'] ?>"
                                    <?= (string) $old['consignor_id'] === (string) $consignor['id'] ? 'selected' : '' ?>
                                >

                                    <?= e($consignor['name']) ?>

                                    <?php if (!empty($consignor['phone'])): ?>
                                        - <?= e($consignor['phone']) ?>
                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =====================================================
                 HARGA
            ====================================================== -->

            <div class="bento-card p-5 md:p-7">

                <div class="mb-6">

                    <h2 class="
                        text-lg
                        font-semibold
                        text-neutral-900
                    ">
                        Harga & Fee
                    </h2>

                    <p class="
                        text-sm
                        text-neutral-500
                        mt-1
                    ">
                        Tentukan harga barang dan bagian toko.
                    </p>

                </div>


                <div class="
                    grid
                    grid-cols-1
                    md:grid-cols-2
                    gap-5
                ">


                    <!-- HARGA AWAL -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Harga Awal
                        </label>


                        <input
                            type="text"
                            name="initial_price"
                            value="<?= e($old['initial_price']) ?>"
                            placeholder="Rp0"
                            inputmode="numeric"
                            class="money-input
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                outline-none
                                focus:border-neutral-400
                            "
                        >

                        <p class="
                            text-xs
                            text-neutral-400
                            mt-2
                        ">
                            Harga awal yang disepakati dengan penitip.
                        </p>

                    </div>


                    <!-- HARGA JUAL -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Harga Jual

                            <span class="text-red-500">*</span>
                        </label>


                        <input
                            type="text"
                            name="selling_price"
                            id="selling_price"
                            value="<?= e($old['selling_price']) ?>"
                            placeholder="Rp0"
                            inputmode="numeric"
                            required
                            class="money-input
                                w-full
                                px-4
                                py-3
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


                    <!-- FEE TYPE -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Jenis Fee Toko
                        </label>


                        <div class="
                            grid
                            grid-cols-2
                            gap-2
                        ">


                            <label class="cursor-pointer">

                                <input
                                    type="radio"
                                    name="fee_type"
                                    value="FIXED"
                                    class="sr-only"
                                    <?= $old['fee_type'] === 'FIXED' ? 'checked' : '' ?>
                                    onchange="updateFeeLabel()"
                                >

                                <div class="
                                    fee-type-card
                                    border
                                    border-neutral-200
                                    rounded-xl
                                    px-4
                                    py-3
                                    text-center
                                    text-sm
                                    text-neutral-600
                                    transition
                                ">
                                    Nominal
                                </div>

                            </label>


                            <label class="cursor-pointer">

                                <input
                                    type="radio"
                                    name="fee_type"
                                    value="PERCENTAGE"
                                    class="sr-only"
                                    <?= $old['fee_type'] === 'PERCENTAGE' ? 'checked' : '' ?>
                                    onchange="updateFeeLabel()"
                                >

                                <div class="
                                    fee-type-card
                                    border
                                    border-neutral-200
                                    rounded-xl
                                    px-4
                                    py-3
                                    text-center
                                    text-sm
                                    text-neutral-600
                                    transition
                                ">
                                    Persentase
                                </div>

                            </label>

                        </div>

                    </div>


                    <!-- FEE VALUE -->

                    <div>

                        <label
                            id="feeLabel"
                            class="
                                block
                                text-sm
                                font-medium
                                text-neutral-800
                                mb-2
                            "
                        >
                            Fee Toko
                        </label>


                        <div class="relative">

                            <input
                                type="text"
                                name="fee_value"
                                id="fee_value"
                                value="<?= e($old['fee_value']) ?>"
                                placeholder="Rp0"
                                inputmode="numeric"
                                class="money-input
                                    w-full
                                    px-4
                                    py-3
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


                        <p
                            id="feeHelp"
                            class="
                                text-xs
                                text-neutral-400
                                mt-2
                            "
                        >
                            Fee tetap yang diterima toko setiap barang terjual.

                        </p>

                    </div>

                </div>


                <!-- PREVIEW -->

                <div class="
                    mt-6
                    p-4
                    rounded-2xl
                    bg-neutral-50
                    border
                    border-neutral-100
                ">

                    <div class="
                        flex
                        items-center
                        justify-between
                        gap-4
                    ">

                        <span class="
                            text-sm
                            text-neutral-500
                        ">
                            Perkiraan bagian penitip
                        </span>


                        <strong
                            id="consignorPreview"
                            class="
                                text-sm
                                font-semibold
                                text-neutral-900
                            "
                        >
                            Rp0
                        </strong>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 STOK & PERIODE
            ====================================================== -->

            <div class="bento-card p-5 md:p-7">

                <div class="mb-6">

                    <h2 class="
                        text-lg
                        font-semibold
                        text-neutral-900
                    ">
                        Stok & Periode
                    </h2>

                    <p class="
                        text-sm
                        text-neutral-500
                        mt-1
                    ">
                        Catat stok awal dan masa penitipan.
                    </p>

                </div>


                <div class="
                    grid
                    grid-cols-1
                    md:grid-cols-2
                    gap-5
                ">


                    <!-- STOK -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Stok Awal
                        </label>


                        <input
                            type="number"
                            name="stock"
                            value="<?= e($old['stock']) ?>"
                            min="0"
                            step="1"
                            class="
                                w-full
                                px-4
                                py-3
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


                    <!-- MINIMUM STOK -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Minimum Stok
                        </label>


                        <input
                            type="number"
                            name="minimum_stock"
                            value="<?= e($old['minimum_stock']) ?>"
                            min="0"
                            step="1"
                            class="
                                w-full
                                px-4
                                py-3
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


                    <!-- MULAI -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Mulai Titip
                            <span class="text-red-500">*</span>
                        </label>


                        <input
                            type="date"
                            name="start_date"
                            value="<?= e($old['start_date']) ?>"
                            required
                            class="
                                w-full
                                px-4
                                py-3
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


                    <!-- SELESAI -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Selesai Titip
                        </label>


                        <input
                            type="date"
                            name="end_date"
                            value="<?= e($old['end_date']) ?>"
                            class="
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                outline-none
                                focus:border-neutral-400
                            "
                        >


                        <p class="
                            text-xs
                            text-neutral-400
                            mt-2
                        ">
                            Kosongkan jika tidak ada batas waktu.
                        </p>

                    </div>


                    <!-- CATATAN -->

                    <div class="md:col-span-2">

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Catatan
                        </label>


                        <textarea
                            name="notes"
                            rows="3"
                            placeholder="Catatan barang titipan..."
                            class="
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                outline-none
                                resize-none
                                focus:border-neutral-400
                            "
                        ><?= e($old['notes']) ?></textarea>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 BUTTON
            ====================================================== -->

            <div class="
                flex
                flex-col-reverse
                sm:flex-row
                sm:justify-end
                gap-2
            ">

                <a
                    href="/pages/titipan/index.php"
                    class="
                        inline-flex
                        items-center
                        justify-center
                        px-5
                        py-3
                        rounded-xl
                        border
                        border-neutral-200
                        bg-white
                        text-sm
                        font-medium
                        text-neutral-700
                        hover:bg-neutral-50
                    "
                >
                    Batal
                </a>


                <button
                    type="submit"
                    class="
                        inline-flex
                        items-center
                        justify-center
                        gap-2
                        px-5
                        py-3
                        rounded-xl
                        bg-neutral-900
                        text-white
                        text-sm
                        font-medium
                        hover:bg-neutral-800
                    "
                >

                    <i
                        data-lucide="save"
                        class="w-4 h-4"
                    ></i>

                    Simpan Barang

                </button>

            </div>

        </form>

    </div>

</main>


<script>

function formatRupiah(value) {

    value = String(value)
        .replace(/\D/g, '');

    if (!value) {
        return '';
    }

    return 'Rp' + Number(value)
        .toLocaleString('id-ID');

}


function rawNumber(value) {

    return String(value)
        .replace(/\D/g, '');

}


/*
|--------------------------------------------------------------------------
| MONEY INPUT
|--------------------------------------------------------------------------
*/

document.querySelectorAll('.money-input').forEach(function(input) {

    input.addEventListener('input', function() {

        const cursorPosition =
            this.selectionStart;

        const raw =
            rawNumber(this.value);

        this.value =
            raw ? formatRupiah(raw) : '';

        updateConsignorPreview();

    });

});


/*
|--------------------------------------------------------------------------
| FEE TYPE
|--------------------------------------------------------------------------
*/

function updateFeeLabel() {

    const selected =
        document.querySelector(
            'input[name="fee_type"]:checked'
        );

    if (!selected) {
        return;
    }

    const feeLabel =
        document.getElementById('feeLabel');

    const feeHelp =
        document.getElementById('feeHelp');

    const feeInput =
        document.getElementById('fee_value');


    if (selected.value === 'PERCENTAGE') {

        feeLabel.textContent =
            'Fee Toko (%)';

        feeInput.placeholder =
            'Contoh 20';

        feeHelp.textContent =
            'Persentase bagian yang diterima toko dari harga jual.';

    } else {

        feeLabel.textContent =
            'Fee Toko';

        feeInput.placeholder =
            'Rp0';

        feeHelp.textContent =
            'Nominal tetap yang diterima toko setiap barang terjual.';

    }

    updateFeeTypeStyle();
    updateConsignorPreview();
}


/*
|--------------------------------------------------------------------------
| FEE CARD STYLE
|--------------------------------------------------------------------------
*/

function updateFeeTypeStyle() {

    document
        .querySelectorAll('input[name="fee_type"]')
        .forEach(function(input) {

            const card =
                input.parentElement.querySelector(
                    '.fee-type-card'
                );

            if (input.checked) {

                card.classList.add(
                    'border-neutral-900',
                    'bg-neutral-50',
                    'text-neutral-900'
                );

                card.classList.remove(
                    'border-neutral-200',
                    'text-neutral-600'
                );

            } else {

                card.classList.remove(
                    'border-neutral-900',
                    'bg-neutral-50',
                    'text-neutral-900'
                );

                card.classList.add(
                    'border-neutral-200',
                    'text-neutral-600'
                );
            }

        });

}


/*
|--------------------------------------------------------------------------
| PREVIEW BAGIAN PENITIP
|--------------------------------------------------------------------------
*/

function updateConsignorPreview() {

    const sellingInput =
        document.getElementById('selling_price');

    const feeInput =
        document.getElementById('fee_value');

    const preview =
        document.getElementById('consignorPreview');

    const feeType =
        document.querySelector(
            'input[name="fee_type"]:checked'
        );

    if (!sellingInput || !feeInput || !preview || !feeType) {
        return;
    }

    const selling =
        Number(rawNumber(sellingInput.value)) || 0;

    let fee =
        Number(rawNumber(feeInput.value)) || 0;


    if (feeType.value === 'PERCENTAGE') {

        fee =
            selling * (fee / 100);
    }


    const consignorAmount =
        Math.max(
            0,
            selling - fee
        );


    preview.textContent =
        'Rp' +
        consignorAmount.toLocaleString('id-ID');

}


/*
|--------------------------------------------------------------------------
| FEE PERCENTAGE
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('input[name="fee_type"]')
    .forEach(function(input) {

        input.addEventListener(
            'change',
            updateFeeLabel
        );

    });


/*
|--------------------------------------------------------------------------
| INITIAL
|--------------------------------------------------------------------------
*/

updateFeeLabel();

updateConsignorPreview();


/*
|--------------------------------------------------------------------------
| BEFORE SUBMIT
|--------------------------------------------------------------------------
|
| Karena input harga ditampilkan sebagai Rp25.000,
| server akan membersihkannya kembali.
|
*/

document
    .getElementById('titipanForm')
    .addEventListener('submit', function() {

        const moneyInputs =
            this.querySelectorAll('.money-input');

        moneyInputs.forEach(function(input) {

            input.value =
                rawNumber(input.value);

        });

    });

</script>


<?php require_once '../../includes/footer.php'; ?>
