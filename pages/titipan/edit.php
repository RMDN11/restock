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

$pageTitle = 'Edit Barang Titipan';


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

    $value = str_replace(
        ['Rp', 'rp', ' '],
        '',
        $value
    );

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
| ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {
    header('Location: /pages/titipan/index.php');
    exit;
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
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.sku,
        p.barcode,
        p.name,
        p.category_id,
        p.unit,
        p.selling_price,
        p.current_stock,
        p.minimum_stock,
        p.status AS product_status,

        cs.id AS consignment_id,
        cs.consignor_id,
        cs.initial_price,
        cs.fee_type,
        cs.fee_value,
        cs.start_date,
        cs.end_date,
        cs.status AS consignment_status,
        cs.notes

    FROM products p

    LEFT JOIN consignments cs
        ON cs.id = (
            SELECT c2.id
            FROM consignments c2
            WHERE c2.product_id = p.id
            AND c2.store_id = $storeId
            ORDER BY
                CASE
                    WHEN c2.status = 'ACTIVE' THEN 0
                    ELSE 1
                END,
                c2.id DESC
            LIMIT 1
        )

    WHERE p.id = :id
    AND p.store_id = $storeId
    AND p.product_type = 'TITIPAN'

    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$product = $stmt->fetch();

if (!$product) {

    header(
        'Location: /pages/titipan/index.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| OLD DATA
|--------------------------------------------------------------------------
*/

$old = [
    'sku'            => $product['sku'],
    'barcode'        => $product['barcode'],
    'name'           => $product['name'],
    'category_id'    => $product['category_id'],
    'unit'           => $product['unit'],
    'initial_price'  => $product['initial_price'],
    'selling_price'  => $product['selling_price'],
    'stock'          => $product['current_stock'],
    'minimum_stock'  => $product['minimum_stock'],
    'consignor_id'   => $product['consignor_id'],
    'fee_type'       => $product['fee_type'] ?? 'FIXED',
    'fee_value'      => $product['fee_value'] ?? 0,
    'start_date'     => $product['start_date'] ?? date('Y-m-d'),
    'end_date'       => $product['end_date'] ?? '',
    'notes'          => $product['notes'] ?? '',
];

$error = '';


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

        $error =
            'Sesi formulir sudah tidak berlaku. Silakan coba lagi.';
    }


    /*
    |--------------------------------------------------------------------------
    | INPUT
    |--------------------------------------------------------------------------
    */

    $old['sku'] =
        trim($_POST['sku'] ?? '');

    $old['barcode'] =
        trim($_POST['barcode'] ?? '');

    $old['name'] =
        trim($_POST['name'] ?? '');

    $old['category_id'] =
        trim($_POST['category_id'] ?? '');

    $old['unit'] =
        trim($_POST['unit'] ?? 'pcs');

    $old['initial_price'] =
        trim($_POST['initial_price'] ?? '');

    $old['selling_price'] =
        trim($_POST['selling_price'] ?? '');

    $old['stock'] =
        trim($_POST['stock'] ?? '0');

    $old['minimum_stock'] =
        trim($_POST['minimum_stock'] ?? '0');

    $old['consignor_id'] =
        trim($_POST['consignor_id'] ?? '');

    $old['fee_type'] =
        $_POST['fee_type'] ?? 'FIXED';

    $old['fee_value'] =
        trim($_POST['fee_value'] ?? '');

    $old['start_date'] =
        trim($_POST['start_date'] ?? '');

    $old['end_date'] =
        trim($_POST['end_date'] ?? '');

    $old['notes'] =
        trim($_POST['notes'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | NORMALIZE
    |--------------------------------------------------------------------------
    */

    $initialPrice =
        parseMoney($old['initial_price']);

    $sellingPrice =
        parseMoney($old['selling_price']);

    $newStock =
        (int) $old['stock'];

    $minimumStock =
        (int) $old['minimum_stock'];

    $consignorId =
        (int) $old['consignor_id'];

    $feeValue =
        parseMoney($old['fee_value']);


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $old['name'] === ''
    ) {
        $error = 'Nama barang wajib diisi.';
    }

    if (
        $error === '' &&
        $old['unit'] === ''
    ) {
        $error = 'Satuan barang wajib diisi.';
    }

    if (
        $error === '' &&
        $consignorId <= 0
    ) {
        $error = 'Penitip wajib dipilih.';
    }

    if (
        $error === '' &&
        $sellingPrice <= 0
    ) {
        $error = 'Harga jual harus lebih dari 0.';
    }

    if (
        $error === '' &&
        $newStock < 0
    ) {
        $error = 'Stok tidak boleh kurang dari 0.';
    }

    if (
        $error === '' &&
        $minimumStock < 0
    ) {
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

    if (
        $error === '' &&
        $feeValue < 0
    ) {
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
        $error =
            'Fee toko tidak boleh lebih besar dari harga jual.';
    }


    /*
    |--------------------------------------------------------------------------
    | DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $startDateObj =
            DateTime::createFromFormat(
                'Y-m-d',
                $old['start_date']
            );

        if (
            !$startDateObj ||
            $startDateObj->format('Y-m-d')
                !== $old['start_date']
        ) {

            $error =
                'Tanggal mulai tidak valid.';
        }
    }


    if (
        $error === '' &&
        $old['end_date'] !== ''
    ) {

        $endDateObj =
            DateTime::createFromFormat(
                'Y-m-d',
                $old['end_date']
            );

        if (
            !$endDateObj ||
            $endDateObj->format('Y-m-d')
                !== $old['end_date']
        ) {

            $error =
                'Tanggal selesai tidak valid.';
        }

        if (
            $error === '' &&
            $old['end_date']
                < $old['start_date']
        ) {

            $error =
                'Tanggal selesai tidak boleh sebelum tanggal mulai.';
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
            | CEK SKU
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM products
                WHERE sku = :sku
                AND store_id = $storeId
                AND id != :product_id
                LIMIT 1
            ");

            $stmt->execute([
                ':sku' =>
                    $old['sku'],
                ':product_id' =>
                    $id,
            ]);

            if ($stmt->fetch()) {

                $error =
                    'SKU sudah digunakan barang lain.';
            }


            /*
            |--------------------------------------------------------------------------
            | CEK BARCODE
            |--------------------------------------------------------------------------
            */

            if (
                $error === '' &&
                $old['barcode'] !== ''
            ) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM products
                    WHERE barcode = :barcode
                    AND store_id = $storeId
                    AND id != :product_id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':barcode' =>
                        $old['barcode'],

                    ':product_id' =>
                        $id,
                ]);

                if ($stmt->fetch()) {

                    $error =
                        'Barcode sudah digunakan barang lain.';
                }
            }


            /*
            |--------------------------------------------------------------------------
            | CEK PENITIP
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

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
                    ':consignor_id' =>
                        $consignorId
                ]);

                $consignor =
                    $stmt->fetch();

                if (!$consignor) {

                    $error =
                        'Penitip tidak ditemukan atau tidak aktif.';
                }
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | UPDATE PRODUCT
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE products
                    SET
                        sku = :sku,
                        barcode = :barcode,
                        name = :name,
                        category_id = :category_id,
                        unit = :unit,
                        selling_price = :selling_price,
                        current_stock = :current_stock,
                        minimum_stock = :minimum_stock
                    WHERE id = :id
                    AND store_id = $storeId
                ");

                $stmt->execute([
                    ':sku' =>
                        $old['sku'],

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
                        $newStock,

                    ':minimum_stock' =>
                        $minimumStock,

                    ':id' =>
                        $id,
                ]);


                /*
                |--------------------------------------------------------------------------
                | UPDATE CONSIGNMENT
                |--------------------------------------------------------------------------
                */

                if (!empty($product['consignment_id'])) {

                    $stmt = $pdo->prepare("
                        UPDATE consignments
                        SET
                            consignor_id = :consignor_id,
                            initial_price = :initial_price,
                            fee_type = :fee_type,
                            fee_value = :fee_value,
                            start_date = :start_date,
                            end_date = :end_date,
                            notes = :notes
                        WHERE id = :id
                        AND store_id = $storeId
                    ");

                    $stmt->execute([
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

                        ':id' =>
                            $product['consignment_id'],
                    ]);

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | JIKA BELUM ADA CONSIGNMENT
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
                            $id,

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
                }


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
                        $storeId,
                        :user_id,
                        'UPDATE',
                        'products',
                        :record_id,
                        :description
                    )
                ");

                $stmt->execute([
                    ':user_id' =>
                        $_SESSION['user_id'] ?? null,

                    ':record_id' =>
                        $id,

                    ':description' =>
                        'Mengubah barang titipan: ' .
                        $old['name'],
                ]);


                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | REDIRECT
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: /pages/titipan/view.php?id=' .
                    $id .
                    '&success=updated'
                );

                exit;
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error =
                'Barang titipan gagal diperbarui: ' .
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
                href="/pages/titipan/view.php?id=<?= $id ?>"
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

                Kembali ke Detail

            </a>


            <h1 class="
                text-2xl
                md:text-3xl
                font-semibold
                tracking-tight
                text-neutral-900
            ">
                Edit Barang Titipan
            </h1>


            <p class="
                text-sm
                text-neutral-500
                mt-2
            ">
                Perbarui informasi barang dan data penitip.
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
                        class="w-5 h-5 shrink-0"
                    ></i>

                    <div>
                        <?= e($error) ?>
                    </div>

                </div>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            id="titipanEditForm"
            class="space-y-6"
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
                        Informasi dasar barang titipan.
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
                                uppercase
                                outline-none
                                focus:border-neutral-400
                            "
                        >

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
                                    <?= (string) $old['category_id']
                                        === (string) $category['id']
                                        ? 'selected'
                                        : ''
                                    ?>
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
                        Pemilik barang titipan.
                    </p>

                </div>


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
                            <?= (string) $old['consignor_id']
                                === (string) $consignor['id']
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e($consignor['name']) ?>

                            <?php if (!empty($consignor['phone'])): ?>

                                - <?= e($consignor['phone']) ?>

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

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
                                    <?= $old['fee_type'] === 'FIXED'
                                        ? 'checked'
                                        : ''
                                    ?>
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
                                    <?= $old['fee_type'] === 'PERCENTAGE'
                                        ? 'checked'
                                        : ''
                                    ?>
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
                                    transition
                                ">
                                    Persentase
                                </div>

                            </label>

                        </div>

                    </div>


                    <!-- FEE -->

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


                        <input
                            type="text"
                            name="fee_value"
                            id="fee_value"
                            value="<?= e($old['fee_value']) ?>"
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
                            Bagian penitip
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
                            Stok
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


                    <!-- MINIMUM -->

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
                    href="/pages/titipan/view.php?id=<?= $id ?>"
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

                    Simpan Perubahan

                </button>

            </div>

        </form>

    </div>

</main>


<script>

/*
|--------------------------------------------------------------------------
| FORMAT RUPIAH
|--------------------------------------------------------------------------
*/

function formatRupiah(value)
{
    value = String(value)
        .replace(/\D/g, '');

    if (!value) {
        return '';
    }

    return 'Rp' +
        Number(value).toLocaleString('id-ID');
}


/*
|--------------------------------------------------------------------------
| RAW NUMBER
|--------------------------------------------------------------------------
*/

function rawNumber(value)
{
    return String(value)
        .replace(/\D/g, '');
}


/*
|--------------------------------------------------------------------------
| MONEY INPUT
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.money-input')
    .forEach(function(input) {

        input.addEventListener(
            'input',
            function() {

                const raw =
                    rawNumber(this.value);

                this.value =
                    raw
                        ? formatRupiah(raw)
                        : '';

                updateConsignorPreview();
            }
        );

    });


/*
|--------------------------------------------------------------------------
| FEE TYPE
|--------------------------------------------------------------------------
*/

function updateFeeLabel()
{
    const selected =
        document.querySelector(
            'input[name="fee_type"]:checked'
        );

    if (!selected) {
        return;
    }

    const label =
        document.getElementById('feeLabel');

    const input =
        document.getElementById('fee_value');


    if (selected.value === 'PERCENTAGE') {

        label.textContent =
            'Fee Toko (%)';

        input.placeholder =
            'Contoh 20';

    } else {

        label.textContent =
            'Fee Toko';

        input.placeholder =
            'Rp0';
    }


    updateFeeStyle();
    updateConsignorPreview();
}


/*
|--------------------------------------------------------------------------
| FEE STYLE
|--------------------------------------------------------------------------
*/

function updateFeeStyle()
{
    document
        .querySelectorAll(
            'input[name="fee_type"]'
        )
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
| PREVIEW
|--------------------------------------------------------------------------
*/

function updateConsignorPreview()
{
    const sellingInput =
        document.getElementById(
            'selling_price'
        );

    const feeInput =
        document.getElementById(
            'fee_value'
        );

    const preview =
        document.getElementById(
            'consignorPreview'
        );

    const feeType =
        document.querySelector(
            'input[name="fee_type"]:checked'
        );

    if (
        !sellingInput ||
        !feeInput ||
        !preview ||
        !feeType
    ) {
        return;
    }


    const selling =
        Number(
            rawNumber(
                sellingInput.value
            )
        ) || 0;


    let fee =
        Number(
            rawNumber(
                feeInput.value
            )
        ) || 0;


    if (
        feeType.value === 'PERCENTAGE'
    ) {

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
        consignorAmount
            .toLocaleString('id-ID');
}


/*
|--------------------------------------------------------------------------
| CHANGE FEE TYPE
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        'input[name="fee_type"]'
    )
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
| SUBMIT
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        'titipanEditForm'
    )
    .addEventListener(
        'submit',
        function() {

            this
                .querySelectorAll(
                    '.money-input'
                )
                .forEach(function(input) {

                    input.value =
                        rawNumber(
                            input.value
                        );
                });

        }
    );

</script>


<?php require_once '../../includes/footer.php'; ?>