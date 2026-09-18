<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$pageTitle = 'Edit Barang';


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiahInput($value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    return number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}


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

$csrfToken = $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| VALIDATE ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header('Location: /pages/barang/');
    exit;
}


/*
|--------------------------------------------------------------------------
| AMBIL BARANG
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.*,
        pc.name AS category_name
    FROM products p
    LEFT JOIN product_categories pc
        ON pc.id = p.category_id
        AND pc.store_id = p.store_id
    WHERE p.id = :id
      AND p.store_id = :store_id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id,
    ':store_id' => $authStoreId
]);

$product = $stmt->fetch();

if (!$product) {
    header('Location: /pages/barang/');
    exit;
}


/*
|--------------------------------------------------------------------------
| DATA KATEGORI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name
    FROM product_categories
    WHERE status = 'ACTIVE'
      AND store_id = :category_store_id
    ORDER BY name ASC
");
$stmt->execute([
    ':category_store_id' => $authStoreId
]);

$categories = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| DATA PENITIP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone
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
| DETAIL TITIPAN
|--------------------------------------------------------------------------
*/

$consignment = null;

if ($product['product_type'] === 'TITIPAN') {

    $stmt = $pdo->prepare("
        SELECT
            *
        FROM consignments
        WHERE product_id = :product_id
          AND store_id = :store_id
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':product_id' => $id,
        ':store_id' => $authStoreId
    ]);

    $consignment = $stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| FORM DEFAULT
|--------------------------------------------------------------------------
*/

$form = [
    'name' => $product['name'],
    'sku' => $product['sku'],
    'barcode' => $product['barcode'],
    'product_type' => $product['product_type'],
    'category_id' => $product['category_id'],
    'unit' => $product['unit'],
    'buying_price' => rupiahInput(
        $product['buying_price']
    ),
    'selling_price' => rupiahInput(
        $product['selling_price']
    ),
    'current_stock' => $product['current_stock'],
    'minimum_stock' => $product['minimum_stock'],
    'consignor_id' => $consignment['consignor_id'] ?? '',
    'initial_price' => $consignment
        ? rupiahInput($consignment['initial_price'])
        : '',
    'fee_type' => $consignment['fee_type'] ?? 'FIXED',
    'fee_value' => $consignment
        ? rupiahInput($consignment['fee_value'])
        : '',
    'start_date' => $consignment['start_date']
        ?? date('Y-m-d'),
    'end_date' => $consignment['end_date'] ?? '',
    'notes' => $consignment['notes'] ?? ''
];

$errors = [];


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF CHECK
    |--------------------------------------------------------------------------
    */

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !$postedToken ||
        !hash_equals(
            $csrfToken,
            $postedToken
        )
    ) {
        $errors[] = 'Sesi formulir sudah tidak valid. Silakan coba lagi.';
    }


    /*
    |--------------------------------------------------------------------------
    | AMBIL FORM
    |--------------------------------------------------------------------------
    */

    $form['name'] = trim(
        $_POST['name'] ?? ''
    );

    $form['sku'] = trim(
        $_POST['sku'] ?? ''
    );

    $form['barcode'] = trim(
        $_POST['barcode'] ?? ''
    );

    $form['product_type'] =
        $_POST['product_type'] ?? 'TOKO';

    $form['category_id'] =
        $_POST['category_id'] !== ''
            ? (int) $_POST['category_id']
            : null;

    $form['unit'] = trim(
        $_POST['unit'] ?? 'pcs'
    );

    $form['buying_price'] =
        trim($_POST['buying_price'] ?? '');

    $form['selling_price'] =
        trim($_POST['selling_price'] ?? '');

    $form['current_stock'] =
        max(
            0,
            (int) ($_POST['current_stock'] ?? 0)
        );

    $form['minimum_stock'] =
        max(
            0,
            (int) ($_POST['minimum_stock'] ?? 0)
        );

    $form['consignor_id'] =
        $_POST['consignor_id'] !== ''
            ? (int) $_POST['consignor_id']
            : null;

    $form['initial_price'] =
        trim($_POST['initial_price'] ?? '');

    $form['fee_type'] =
        $_POST['fee_type'] ?? 'FIXED';

    $form['fee_value'] =
        trim($_POST['fee_value'] ?? '');

    $form['start_date'] =
        $_POST['start_date'] ?? '';

    $form['end_date'] =
        $_POST['end_date'] ?? '';

    $form['notes'] =
        trim($_POST['notes'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | NORMALIZE MONEY
    |--------------------------------------------------------------------------
    */

    $buyingPrice = (float) str_replace(
        ['.', ','],
        ['', '.'],
        $form['buying_price']
    );

    $sellingPrice = (float) str_replace(
        ['.', ','],
        ['', '.'],
        $form['selling_price']
    );

    $initialPrice = (float) str_replace(
        ['.', ','],
        ['', '.'],
        $form['initial_price']
    );

    $feeValue = (float) str_replace(
        ['.', ','],
        ['', '.'],
        $form['fee_value']
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDASI DASAR
    |--------------------------------------------------------------------------
    */

    if ($form['name'] === '') {
        $errors[] = 'Nama barang wajib diisi.';
    }

    if ($form['sku'] === '') {
        $errors[] = 'SKU wajib diisi.';
    }

    if ($form['unit'] === '') {
        $errors[] = 'Satuan wajib diisi.';
    }

    if ($buyingPrice < 0) {
        $errors[] = 'Harga modal tidak valid.';
    }

    if ($sellingPrice < 0) {
        $errors[] = 'Harga jual tidak valid.';
    }

    if (!in_array(
        $form['product_type'],
        ['TOKO', 'TITIPAN'],
        true
    )) {
        $errors[] = 'Jenis barang tidak valid.';
    }


    /*
    |--------------------------------------------------------------------------
    | SKU UNIQUE
    |--------------------------------------------------------------------------
    */

    if ($form['sku'] !== '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE sku = :sku
              AND store_id = :store_id
            AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            ':sku' => $form['sku'],
            ':store_id' => $authStoreId,
            ':id' => $id
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'SKU tersebut sudah digunakan barang lain.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | BARCODE UNIQUE
    |--------------------------------------------------------------------------
    */

    if ($form['barcode'] !== '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM products
            WHERE barcode = :barcode
              AND store_id = :store_id
            AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            ':barcode' => $form['barcode'],
            ':store_id' => $authStoreId,
            ':id' => $id
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Barcode tersebut sudah digunakan barang lain.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDASI TITIPAN
    |--------------------------------------------------------------------------
    */

    if ($form['product_type'] === 'TITIPAN') {

        if (!$form['consignor_id']) {
            $errors[] = 'Penitip wajib dipilih.';
        }

        if ($initialPrice < 0) {
            $errors[] = 'Harga awal titipan tidak valid.';
        }

        if (!in_array(
            $form['fee_type'],
            ['FIXED', 'PERCENTAGE'],
            true
        )) {
            $errors[] = 'Jenis fee tidak valid.';
        }

        if ($feeValue < 0) {
            $errors[] = 'Nilai fee tidak valid.';
        }

        if (
            $form['fee_type'] === 'PERCENTAGE' &&
            $feeValue > 100
        ) {
            $errors[] = 'Fee persentase tidak boleh lebih dari 100%.';
        }

        if ($form['start_date'] === '') {
            $errors[] = 'Tanggal mulai titipan wajib diisi.';
        }

        if (
            $form['end_date'] !== '' &&
            $form['start_date'] !== '' &&
            $form['end_date'] < $form['start_date']
        ) {
            $errors[] = 'Tanggal berakhir tidak boleh sebelum tanggal mulai.';
        }
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
            | UPDATE PRODUCTS
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE products
                SET
                    sku = :sku,
                    barcode = :barcode,
                    name = :name,
                    product_type = :product_type,
                    category_id = :category_id,
                    unit = :unit,
                    buying_price = :buying_price,
                    selling_price = :selling_price,
                    current_stock = :current_stock,
                    minimum_stock = :minimum_stock,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND store_id = :store_id
            ");

            $stmt->execute([
                ':sku' => $form['sku'],
                ':barcode' =>
                    $form['barcode'] !== ''
                        ? $form['barcode']
                        : null,
                ':name' => $form['name'],
                ':product_type' =>
                    $form['product_type'],
                ':category_id' =>
                    $form['category_id'],
                ':unit' => $form['unit'],
                ':buying_price' => $buyingPrice,
                ':selling_price' => $sellingPrice,
                ':current_stock' =>
                    $form['current_stock'],
                ':minimum_stock' =>
                    $form['minimum_stock'],
                ':updated_by' =>
                    $_SESSION['user_id'] ?? null,
                ':store_id' => $authStoreId,
                ':id' => $id
            ]);


            /*
            |--------------------------------------------------------------------------
            | JIKA TITIPAN
            |--------------------------------------------------------------------------
            */

            if ($form['product_type'] === 'TITIPAN') {

                /*
                | Cari data titipan aktif/terakhir
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM consignments
                    WHERE product_id = :product_id
                      AND store_id = :store_id
                    ORDER BY id DESC
                    LIMIT 1
                ");

                $stmt->execute([
                    ':product_id' => $id,
                    ':store_id' => $authStoreId
                ]);

                $existingConsignment =
                    $stmt->fetch();


                if ($existingConsignment) {

                    /*
                    | UPDATE TITIPAN
                    */

                    $stmt = $pdo->prepare("
                        UPDATE consignments
                        SET
                            consignor_id = :consignor_id,
                            initial_price = :initial_price,
                            fee_type = :fee_type,
                            fee_value = :fee_value,
                            start_date = :start_date,
                            end_date = :end_date,
                            status = 'ACTIVE',
                            notes = :notes
                        WHERE id = :id
                          AND store_id = :store_id
                    ");

                    $stmt->execute([
                        ':consignor_id' =>
                            $form['consignor_id'],
                        ':initial_price' =>
                            $initialPrice,
                        ':fee_type' =>
                            $form['fee_type'],
                        ':fee_value' =>
                            $feeValue,
                        ':start_date' =>
                            $form['start_date'],
                        ':end_date' =>
                            $form['end_date'] !== ''
                                ? $form['end_date']
                                : null,
                        ':notes' =>
                            $form['notes'] !== ''
                                ? $form['notes']
                                : null,
                        ':id' =>
                            $existingConsignment['id'],
                        ':store_id' =>
                            $authStoreId
                    ]);

                } else {

                    /*
                    | BUAT DATA TITIPAN
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
                        )
                        VALUES (
                            :store_id,
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
                        ':store_id' =>
                            $authStoreId,
                        ':product_id' =>
                            $id,
                        ':consignor_id' =>
                            $form['consignor_id'],
                        ':initial_price' =>
                            $initialPrice,
                        ':fee_type' =>
                            $form['fee_type'],
                        ':fee_value' =>
                            $feeValue,
                        ':start_date' =>
                            $form['start_date'],
                        ':end_date' =>
                            $form['end_date'] !== ''
                                ? $form['end_date']
                                : null,
                        ':notes' =>
                            $form['notes'] !== ''
                                ? $form['notes']
                                : null
                    ]);
                }

            } else {

                /*
                |--------------------------------------------------------------------------
                | JIKA DIUBAH MENJADI TOKO
                |--------------------------------------------------------------------------
                |
                | Data consignments TIDAK dihapus.
                | Hanya dinonaktifkan agar histori tetap aman.
                */

                $stmt = $pdo->prepare("
                    UPDATE consignments
                    SET
                        status = 'INACTIVE'
                    WHERE product_id = :product_id
                      AND store_id = :store_id
                ");

                $stmt->execute([
                    ':product_id' => $id,
                    ':store_id' => $authStoreId
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | AUDIT LOG
            |--------------------------------------------------------------------------
            */

            $userId =
                $_SESSION['user_id'] ?? null;

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
                    'products',
                    :record_id,
                    :description
                )
            ");

            $stmt->execute([
                ':store_id' =>
                    $authStoreId,

                ':user_id' =>
                    $userId,
                ':record_id' =>
                    $id,
                ':description' =>
                    'Mengubah data barang: ' .
                    $form['name']
            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            header(
                'Location: /pages/barang/view.php?id=' .
                $id .
                '&success=updated'
            );

            exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'Data gagal disimpan. ' .
                $e->getMessage();
        }
    }
}


require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<div class="main-content">

    <!-- MOBILE HEADER -->

<main class="p-4 md:p-6 lg:p-8 max-w-5xl mx-auto">

        <!-- HEADER -->

        <div class="flex items-center gap-4 mb-8">

            <a
                href="/pages/barang/view.php?id=<?= (int) $product['id'] ?>"
                class="
                    w-10 h-10
                    shrink-0
                    rounded-xl
                    bg-white
                    border border-neutral-200
                    flex items-center justify-center
                    hover:bg-neutral-50
                    transition
                "
                title="Kembali"
            >
                <i
                    data-lucide="arrow-left"
                    class="w-5 h-5"
                ></i>
            </a>


            <div>

                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                    Edit Barang
                </h1>

                <p class="text-sm text-neutral-500 mt-1">
                    <?= e($product['name']) ?>
                </p>

            </div>

        </div>


        <!-- ERROR -->

        <?php if (!empty($errors)): ?>

            <div class="
                mb-6
                rounded-2xl
                border border-red-200
                bg-red-50
                p-4
            ">

                <div class="flex gap-3">

                    <i
                        data-lucide="circle-alert"
                        class="w-5 h-5 text-red-600 shrink-0 mt-0.5"
                    ></i>

                    <div>

                        <p class="font-medium text-red-700 mb-2">
                            Data belum bisa disimpan
                        </p>

                        <ul class="text-sm text-red-600 space-y-1">

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
            class="space-y-5"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >


            <!-- INFORMASI DASAR -->

            <section class="bento-card p-5 md:p-6">

                <div class="mb-6">

                    <h2 class="text-lg font-semibold">
                        Informasi Barang
                    </h2>

                    <p class="text-sm text-neutral-500 mt-1">
                        Ubah informasi dasar barang.
                    </p>

                </div>


                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">


                    <!-- NAMA -->

                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium mb-2">
                            Nama Barang
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            name="name"
                            value="<?= e($form['name']) ?>"
                            required
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                    </div>


                    <!-- SKU -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            SKU
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            name="sku"
                            value="<?= e($form['sku']) ?>"
                            required
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                    </div>


                    <!-- BARCODE -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Barcode
                        </label>

                        <input
                            type="text"
                            name="barcode"
                            value="<?= e($form['barcode']) ?>"
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                    </div>


                    <!-- KATEGORI -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Kategori
                        </label>

                        <select
                            name="category_id"
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                            <option value="">
                                Tanpa kategori
                            </option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int) $category['id'] ?>"
                                    <?= (
                                        (string) $form['category_id'] ===
                                        (string) $category['id']
                                    )
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

                        <label class="block text-sm font-medium mb-2">
                            Satuan
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            name="unit"
                            value="<?= e($form['unit']) ?>"
                            required
                            placeholder="pcs"
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                    </div>

                </div>

            </section>


            <!-- JENIS BARANG -->

            <section class="bento-card p-5 md:p-6">

                <div class="mb-6">

                    <h2 class="text-lg font-semibold">
                        Jenis Barang
                    </h2>

                    <p class="text-sm text-neutral-500 mt-1">
                        Tentukan barang ini milik toko atau barang titipan.
                    </p>

                </div>


                <div class="grid grid-cols-2 gap-3 max-w-md">

                    <label class="cursor-pointer">

                        <input
                            type="radio"
                            name="product_type"
                            value="TOKO"
                            class="peer sr-only"
                            <?= $form['product_type'] === 'TOKO'
                                ? 'checked'
                                : ''
                            ?>
                        >

                        <div class="
                            rounded-2xl
                            border border-neutral-200
                            p-4
                            peer-checked:border-neutral-900
                            peer-checked:bg-neutral-50
                            transition
                        ">

                            <i
                                data-lucide="store"
                                class="w-5 h-5 mb-3"
                            ></i>

                            <p class="font-medium text-sm">
                                Produk Toko
                            </p>

                            <p class="text-xs text-neutral-500 mt-1">
                                Milik toko sendiri
                            </p>

                        </div>

                    </label>


                    <label class="cursor-pointer">

                        <input
                            type="radio"
                            name="product_type"
                            value="TITIPAN"
                            class="peer sr-only"
                            <?= $form['product_type'] === 'TITIPAN'
                                ? 'checked'
                                : ''
                            ?>
                        >

                        <div class="
                            rounded-2xl
                            border border-neutral-200
                            p-4
                            peer-checked:border-neutral-900
                            peer-checked:bg-neutral-50
                            transition
                        ">

                            <i
                                data-lucide="handshake"
                                class="w-5 h-5 mb-3"
                            ></i>

                            <p class="font-medium text-sm">
                                Produk Titipan
                            </p>

                            <p class="text-xs text-neutral-500 mt-1">
                                Milik penitip
                            </p>

                        </div>

                    </label>

                </div>

            </section>


            <!-- HARGA & STOK -->

            <section class="bento-card p-5 md:p-6">

                <div class="mb-6">

                    <h2 class="text-lg font-semibold">
                        Harga & Stok
                    </h2>

                    <p class="text-sm text-neutral-500 mt-1">
                        Informasi harga dan kondisi stok saat ini.
                    </p>

                </div>


                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">


                    <!-- HARGA MODAL -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Harga Modal
                        </label>

                        <div class="relative">

                            <span class="
                                absolute left-4 top-1/2
                                -translate-y-1/2
                                text-sm text-neutral-400
                            ">
                                Rp
                            </span>

                            <input
                                type="text"
                                name="buying_price"
                                value="<?= e($form['buying_price']) ?>"
                                inputmode="numeric"
                                class="
                                    w-full h-11
                                    pl-11 pr-4
                                    rounded-xl
                                    border border-neutral-200
                                    bg-white
                                    outline-none
                                    focus:border-neutral-400
                                    transition
                                "
                            >

                        </div>

                    </div>


                    <!-- HARGA JUAL -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Harga Jual
                        </label>

                        <div class="relative">

                            <span class="
                                absolute left-4 top-1/2
                                -translate-y-1/2
                                text-sm text-neutral-400
                            ">
                                Rp
                            </span>

                            <input
                                type="text"
                                name="selling_price"
                                value="<?= e($form['selling_price']) ?>"
                                inputmode="numeric"
                                class="
                                    w-full h-11
                                    pl-11 pr-4
                                    rounded-xl
                                    border border-neutral-200
                                    bg-white
                                    outline-none
                                    focus:border-neutral-400
                                    transition
                                "
                            >

                        </div>

                    </div>


                    <!-- STOK -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Stok Saat Ini
                        </label>

                        <input
                            type="number"
                            name="current_stock"
                            value="<?= (int) $form['current_stock'] ?>"
                            min="0"
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                        <p class="text-xs text-neutral-400 mt-2">
                            Hanya ubah jika stok fisik memang perlu disesuaikan.
                        </p>

                    </div>


                    <!-- MINIMUM -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Minimum Stok
                        </label>

                        <input
                            type="number"
                            name="minimum_stock"
                            value="<?= (int) $form['minimum_stock'] ?>"
                            min="0"
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                    </div>

                </div>

            </section>


            <!-- DETAIL TITIPAN -->

            <section
                id="consignment-section"
                class="
                    bento-card
                    p-5 md:p-6
                    <?= $form['product_type'] === 'TITIPAN'
                        ? ''
                        : 'hidden'
                    ?>
                "
            >

                <div class="mb-6">

                    <h2 class="text-lg font-semibold">
                        Detail Titipan
                    </h2>

                    <p class="text-sm text-neutral-500 mt-1">
                        Data penitip dan pembagian fee toko.
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
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                            <option value="">
                                Pilih penitip
                            </option>

                            <?php foreach ($consignors as $consignor): ?>

                                <option
                                    value="<?= (int) $consignor['id'] ?>"
                                    <?= (
                                        (string) $form['consignor_id'] ===
                                        (string) $consignor['id']
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= e($consignor['name']) ?>

                                    <?php if (!empty($consignor['phone'])): ?>

                                        · <?= e($consignor['phone']) ?>

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- HARGA AWAL -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Harga Awal
                        </label>

                        <div class="relative">

                            <span class="
                                absolute left-4 top-1/2
                                -translate-y-1/2
                                text-sm text-neutral-400
                            ">
                                Rp
                            </span>

                            <input
                                type="text"
                                name="initial_price"
                                value="<?= e($form['initial_price']) ?>"
                                inputmode="numeric"
                                class="
                                    w-full h-11
                                    pl-11 pr-4
                                    rounded-xl
                                    border border-neutral-200
                                    bg-white
                                    outline-none
                                    focus:border-neutral-400
                                    transition
                                "
                            >

                        </div>

                    </div>


                    <!-- FEE TYPE -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Jenis Fee
                        </label>

                        <select
                            name="fee_type"
                            id="fee_type"
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                            <option
                                value="FIXED"
                                <?= $form['fee_type'] === 'FIXED'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Nominal
                            </option>

                            <option
                                value="PERCENTAGE"
                                <?= $form['fee_type'] === 'PERCENTAGE'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Persentase
                            </option>

                        </select>

                    </div>


                    <!-- FEE VALUE -->

                    <div>

                        <label
                            id="fee-label"
                            class="block text-sm font-medium mb-2"
                        >
                            Fee Toko
                        </label>

                        <div class="relative">

                            <span
                                id="fee-prefix"
                                class="
                                    absolute left-4 top-1/2
                                    -translate-y-1/2
                                    text-sm text-neutral-400
                                "
                            >
                                Rp
                            </span>

                            <input
                                type="text"
                                name="fee_value"
                                id="fee_value"
                                value="<?= e($form['fee_value']) ?>"
                                inputmode="decimal"
                                class="
                                    w-full h-11
                                    pl-11 pr-4
                                    rounded-xl
                                    border border-neutral-200
                                    bg-white
                                    outline-none
                                    focus:border-neutral-400
                                    transition
                                "
                            >

                        </div>

                    </div>


                    <!-- MULAI -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Mulai Titipan
                        </label>

                        <input
                            type="date"
                            name="start_date"
                            value="<?= e($form['start_date']) ?>"
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                    </div>


                    <!-- BERAKHIR -->

                    <div>

                        <label class="block text-sm font-medium mb-2">
                            Berakhir
                        </label>

                        <input
                            type="date"
                            name="end_date"
                            value="<?= e($form['end_date']) ?>"
                            class="
                                w-full h-11
                                px-4
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                    </div>


                    <!-- CATATAN -->

                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium mb-2">
                            Catatan
                        </label>

                        <textarea
                            name="notes"
                            rows="4"
                            class="
                                w-full
                                px-4 py-3
                                rounded-xl
                                border border-neutral-200
                                bg-white
                                outline-none
                                focus:border-neutral-400
                                transition
                                resize-none
                            "
                        ><?= e($form['notes']) ?></textarea>

                    </div>

                </div>

            </section>


            <!-- ACTION -->

            <div class="
                flex flex-col-reverse
                sm:flex-row
                sm:justify-end
                gap-3
                pt-2
            ">

                <a
                    href="/pages/barang/view.php?id=<?= (int) $product['id'] ?>"
                    class="
                        h-11
                        px-5
                        rounded-xl
                        border border-neutral-200
                        bg-white
                        flex items-center
                        justify-center
                        text-sm
                        font-medium
                        hover:bg-neutral-50
                        transition
                    "
                >
                    Batal
                </a>


                <button
                    type="submit"
                    class="
                        h-11
                        px-5
                        rounded-xl
                        bg-neutral-900
                        text-white
                        flex items-center
                        justify-center
                        gap-2
                        text-sm
                        font-medium
                        hover:bg-neutral-800
                        transition
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

    </main>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | LUCIDE
    |--------------------------------------------------------------------------
    */

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT TYPE
    |--------------------------------------------------------------------------
    */

    const productTypeInputs =
        document.querySelectorAll(
            'input[name="product_type"]'
        );

    const consignmentSection =
        document.getElementById(
            'consignment-section'
        );


    function updateProductType() {

        const selected =
            document.querySelector(
                'input[name="product_type"]:checked'
            );

        if (!selected) {
            return;
        }

        if (selected.value === 'TITIPAN') {

            consignmentSection.classList.remove(
                'hidden'
            );

        } else {

            consignmentSection.classList.add(
                'hidden'
            );
        }
    }


    productTypeInputs.forEach(function (input) {

        input.addEventListener(
            'change',
            updateProductType
        );

    });


    updateProductType();


    /*
    |--------------------------------------------------------------------------
    | FEE TYPE
    |--------------------------------------------------------------------------
    */

    const feeType =
        document.getElementById('fee_type');

    const feeLabel =
        document.getElementById('fee-label');

    const feePrefix =
        document.getElementById('fee-prefix');

    const feeValue =
        document.getElementById('fee_value');


    function updateFeeType() {

        if (!feeType) {
            return;
        }

        if (feeType.value === 'PERCENTAGE') {

            feeLabel.textContent =
                'Fee Toko (%)';

            feePrefix.textContent =
                '%';

            feeValue.setAttribute(
                'inputmode',
                'decimal'
            );

        } else {

            feeLabel.textContent =
                'Fee Toko';

            feePrefix.textContent =
                'Rp';

            feeValue.setAttribute(
                'inputmode',
                'numeric'
            );
        }
    }


    if (feeType) {

        feeType.addEventListener(
            'change',
            updateFeeType
        );

        updateFeeType();
    }

});

</script>


<?php
require_once __DIR__ . '/../../includes/footer.php';
?>