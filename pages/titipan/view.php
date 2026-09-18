<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/auth.php';
require_once '../../config/database.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) { http_response_code(403); exit('Store aktif tidak ditemukan.'); }

$pageTitle = 'Detail Barang Titipan';

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
    return 'Rp' . number_format((float) $value, 0, ',', '.');
}

/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

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
| DATA BARANG + PENITIP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.sku,
        p.barcode,
        p.name,
        p.unit,
        p.buying_price,
        p.selling_price,
        p.current_stock,
        p.minimum_stock,
        p.status AS product_status,
        p.created_at,
        p.updated_at,

        pc.name AS category_name,

        cs.id AS consignment_id,
        cs.initial_price,
        cs.fee_type,
        cs.fee_value,
        cs.start_date,
        cs.end_date,
        cs.status AS consignment_status,
        cs.notes AS consignment_notes,

        c.id AS consignor_id,
        c.name AS consignor_name,
        c.phone AS consignor_phone,
        c.address AS consignor_address,
        c.status AS consignor_status

    FROM products p

    LEFT JOIN product_categories pc
        ON pc.id = p.category_id
        AND pc.store_id = p.store_id

    LEFT JOIN consignments cs
        ON cs.id = (
            SELECT c2.id
            FROM consignments c2
            WHERE c2.product_id = p.id
            AND c2.store_id = p.store_id
            ORDER BY
                CASE
                    WHEN c2.status = 'ACTIVE' THEN 0
                    ELSE 1
                END,
                c2.id DESC
            LIMIT 1
        )

    LEFT JOIN consignors c
        ON c.id = cs.consignor_id
        AND c.store_id = p.store_id

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
    header('Location: /pages/titipan/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| HITUNG FEE
|--------------------------------------------------------------------------
*/

$sellingPrice = (float) $product['selling_price'];
$initialPrice = (float) $product['initial_price'];
$feeValue = (float) $product['fee_value'];

if ($product['fee_type'] === 'PERCENTAGE') {

    $storeFee = $sellingPrice * ($feeValue / 100);

} else {

    $storeFee = $feeValue;
}

$storeFee = max(
    0,
    min($sellingPrice, $storeFee)
);

$consignorAmount = max(
    0,
    $sellingPrice - $storeFee
);

/*
|--------------------------------------------------------------------------
| STOCK STATUS
|--------------------------------------------------------------------------
*/

$currentStock = (int) $product['current_stock'];
$minimumStock = (int) $product['minimum_stock'];

if ($currentStock <= 0) {

    $stockLabel = 'Habis';
    $stockClass = 'bg-red-50 text-red-700';

} elseif (
    $minimumStock > 0 &&
    $currentStock <= $minimumStock
) {

    $stockLabel = 'Stok Menipis';
    $stockClass = 'bg-amber-50 text-amber-700';

} else {

    $stockLabel = 'Aman';
    $stockClass = 'bg-green-50 text-green-700';
}

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$isActive =
    $product['product_status'] === 'ACTIVE'
    && $product['consignment_status'] === 'ACTIVE';

$statusLabel =
    $isActive
        ? 'Aktif'
        : 'Tidak Aktif';

/*
|--------------------------------------------------------------------------
| STOCK MOVEMENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        movement_type,
        quantity,
        reference_type,
        reference_id,
        notes,
        created_at
    FROM stock_movements
    WHERE product_id = :product_id
    AND store_id = $storeId
    ORDER BY created_at DESC, id DESC
    LIMIT 30
");

$stmt->execute([
    ':product_id' => $id
]);

$movements = $stmt->fetchAll();

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
        max-w-6xl
    ">


        <!-- =========================================================
             HEADER
        ========================================================== -->

        <div class="
            flex
            flex-col
            lg:flex-row
            lg:items-start
            lg:justify-between
            gap-5
            mb-8
        ">

            <div>

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

                    Barang Titipan

                </a>


                <div class="
                    flex
                    flex-wrap
                    items-center
                    gap-3
                    mb-2
                ">

                    <h1 class="
                        text-2xl
                        md:text-3xl
                        font-semibold
                        tracking-tight
                        text-neutral-900
                    ">
                        <?= e($product['name']) ?>
                    </h1>


                    <?php if ($isActive): ?>

                        <span class="
                            inline-flex
                            items-center
                            gap-1.5
                            px-2.5
                            py-1.5
                            rounded-lg
                            bg-green-50
                            text-green-700
                            text-xs
                            font-medium
                        ">

                            <span class="
                                w-1.5
                                h-1.5
                                rounded-full
                                bg-green-500
                            "></span>

                            Aktif

                        </span>

                    <?php else: ?>

                        <span class="
                            inline-flex
                            items-center
                            gap-1.5
                            px-2.5
                            py-1.5
                            rounded-lg
                            bg-neutral-100
                            text-neutral-500
                            text-xs
                            font-medium
                        ">

                            <span class="
                                w-1.5
                                h-1.5
                                rounded-full
                                bg-neutral-400
                            "></span>

                            Tidak Aktif

                        </span>

                    <?php endif; ?>

                </div>


                <p class="text-sm text-neutral-500">

                    <?php if (!empty($product['sku'])): ?>

                        SKU <?= e($product['sku']) ?>

                    <?php else: ?>

                        Barang Titipan

                    <?php endif; ?>

                </p>

            </div>


            <!-- ACTION -->

            <div class="
                flex
                flex-wrap
                gap-2
            ">

            <a
        href="/pages/titipan/laporan.php?consignor_id=<?= (int) $product['consignor_id'] ?>"
        target="_blank"
        class="
            inline-flex
            items-center
            justify-center
            gap-2
            px-4
            py-2.5
            rounded-xl
            bg-neutral-900
            text-white
            text-sm
            font-medium
            hover:bg-neutral-800
            transition
        "
    >
        <i
            data-lucide="file-text"
            class="w-4 h-4"
        ></i>

        Laporan Penitip
    </a>
                <a
                    href="/pages/titipan/edit.php?id=<?= $id ?>"
                    class="
                        inline-flex
                        items-center
                        justify-center
                        gap-2
                        px-4
                        py-2.5
                        rounded-xl
                        bg-neutral-900
                        text-white
                        text-sm
                        font-medium
                        hover:bg-neutral-800
                    "
                >

                    <i
                        data-lucide="pencil"
                        class="w-4 h-4"
                    ></i>

                    Edit

                </a>


                <form
                    method="POST"
                    action="/pages/titipan/status.php"
                    onsubmit="return confirm('Ubah status barang ini?')"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <input
                        type="hidden"
                        name="id"
                        value="<?= $id ?>"
                    >

                    <button
                        type="submit"
                        class="
                            inline-flex
                            items-center
                            justify-center
                            gap-2
                            px-4
                            py-2.5
                            rounded-xl
                            border
                            border-neutral-200
                            bg-white
                            text-neutral-700
                            text-sm
                            font-medium
                            hover:bg-neutral-50
                        "
                    >

                        <i
                            data-lucide="power"
                            class="w-4 h-4"
                        ></i>

                        <?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>

                    </button>

                </form>

            </div>

        </div>


        <!-- =========================================================
             SUMMARY CARDS
        ========================================================== -->

        <div class="
            grid
            grid-cols-2
            lg:grid-cols-4
            gap-3
            md:gap-4
            mb-6
        ">


            <!-- STOK -->

            <div class="bento-card p-5">

                <div class="
                    flex
                    items-center
                    justify-between
                    gap-3
                ">

                    <span class="
                        text-xs
                        text-neutral-400
                    ">
                        Stok
                    </span>


                    <span class="
                        inline-flex
                        px-2
                        py-1
                        rounded-lg
                        text-[10px]
                        font-medium
                        <?= $stockClass ?>
                    ">
                        <?= e($stockLabel) ?>
                    </span>

                </div>


                <div class="
                    text-2xl
                    font-semibold
                    text-neutral-900
                    mt-3
                ">
                    <?= number_format($currentStock, 0, ',', '.') ?>
                </div>


                <div class="
                    text-xs
                    text-neutral-400
                    mt-1
                ">
                    <?= e($product['unit']) ?>
                </div>

            </div>


            <!-- HARGA JUAL -->

            <div class="bento-card p-5">

                <div class="
                    text-xs
                    text-neutral-400
                ">
                    Harga Jual
                </div>


                <div class="
                    text-xl
                    md:text-2xl
                    font-semibold
                    text-neutral-900
                    mt-3
                ">
                    <?= rupiah($sellingPrice) ?>
                </div>


                <div class="
                    text-xs
                    text-neutral-400
                    mt-1
                ">
                    per <?= e($product['unit']) ?>
                </div>

            </div>


            <!-- FEE TOKO -->

            <div class="bento-card p-5">

                <div class="
                    text-xs
                    text-neutral-400
                ">
                    Fee Toko
                </div>


                <div class="
                    text-xl
                    md:text-2xl
                    font-semibold
                    text-neutral-900
                    mt-3
                ">
                    <?= rupiah($storeFee) ?>
                </div>


                <div class="
                    text-xs
                    text-neutral-400
                    mt-1
                ">

                    <?php if ($product['fee_type'] === 'PERCENTAGE'): ?>

                        <?= rtrim(rtrim(number_format($feeValue, 2, ',', '.'), '0'), ',') ?>%

                    <?php else: ?>

                        Nominal tetap

                    <?php endif; ?>

                </div>

            </div>


            <!-- HAK PENITIP -->

            <div class="bento-card p-5">

                <div class="
                    text-xs
                    text-neutral-400
                ">
                    Bagian Penitip
                </div>


                <div class="
                    text-xl
                    md:text-2xl
                    font-semibold
                    text-neutral-900
                    mt-3
                ">
                    <?= rupiah($consignorAmount) ?>
                </div>


                <div class="
                    text-xs
                    text-neutral-400
                    mt-1
                ">
                    per barang terjual
                </div>

            </div>

        </div>


        <!-- =========================================================
             MAIN GRID
        ========================================================== -->

        <div class="
            grid
            grid-cols-1
            xl:grid-cols-3
            gap-6
        ">


            <!-- =====================================================
                 LEFT
            ====================================================== -->

            <div class="
                xl:col-span-2
                space-y-6
            ">


                <!-- DATA BARANG -->

                <div class="bento-card p-5 md:p-7">

                    <div class="mb-6">

                        <h2 class="
                            text-lg
                            font-semibold
                            text-neutral-900
                        ">
                            Informasi Barang
                        </h2>

                    </div>


                    <div class="
                        grid
                        grid-cols-1
                        sm:grid-cols-2
                        gap-5
                    ">


                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                Nama Barang
                            </div>

                            <div class="
                                text-sm
                                font-medium
                                text-neutral-900
                            ">
                                <?= e($product['name']) ?>
                            </div>

                        </div>


                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                SKU
                            </div>

                            <div class="
                                text-sm
                                font-medium
                                text-neutral-900
                            ">
                                <?= !empty($product['sku']) ? e($product['sku']) : '-' ?>
                            </div>

                        </div>


                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                Barcode
                            </div>

                            <div class="
                                text-sm
                                font-medium
                                text-neutral-900
                            ">
                                <?= !empty($product['barcode']) ? e($product['barcode']) : '-' ?>
                            </div>

                        </div>


                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                Kategori
                            </div>

                            <div class="
                                text-sm
                                font-medium
                                text-neutral-900
                            ">
                                <?= !empty($product['category_name']) ? e($product['category_name']) : '-' ?>
                            </div>

                        </div>


                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                Satuan
                            </div>

                            <div class="
                                text-sm
                                font-medium
                                text-neutral-900
                            ">
                                <?= e($product['unit']) ?>
                            </div>

                        </div>


                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                Minimum Stok
                            </div>

                            <div class="
                                text-sm
                                font-medium
                                text-neutral-900
                            ">
                                <?= number_format($minimumStock, 0, ',', '.') ?>
                                <?= e($product['unit']) ?>
                            </div>

                        </div>

                    </div>

                </div>


                <!-- HARGA -->

                <div class="bento-card p-5 md:p-7">

                    <div class="mb-6">

                        <h2 class="
                            text-lg
                            font-semibold
                            text-neutral-900
                        ">
                            Harga & Pembagian
                        </h2>

                    </div>


                    <div class="
                        grid
                        grid-cols-1
                        sm:grid-cols-3
                        gap-4
                    ">


                        <div class="
                            rounded-2xl
                            bg-neutral-50
                            p-4
                        ">

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-2
                            ">
                                Harga Awal
                            </div>

                            <div class="
                                text-base
                                font-semibold
                                text-neutral-900
                            ">
                                <?= rupiah($initialPrice) ?>
                            </div>

                        </div>


                        <div class="
                            rounded-2xl
                            bg-neutral-50
                            p-4
                        ">

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-2
                            ">
                                Harga Jual
                            </div>

                            <div class="
                                text-base
                                font-semibold
                                text-neutral-900
                            ">
                                <?= rupiah($sellingPrice) ?>
                            </div>

                        </div>


                        <div class="
                            rounded-2xl
                            bg-neutral-50
                            p-4
                        ">

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-2
                            ">
                                Fee Toko
                            </div>

                            <div class="
                                text-base
                                font-semibold
                                text-neutral-900
                            ">
                                <?= rupiah($storeFee) ?>
                            </div>

                        </div>

                    </div>


                    <div class="
                        mt-4
                        p-4
                        rounded-2xl
                        border
                        border-neutral-100
                        bg-white
                    ">

                        <div class="
                            flex
                            items-center
                            justify-between
                            gap-4
                        ">

                            <div>

                                <div class="
                                    text-sm
                                    font-medium
                                    text-neutral-800
                                ">
                                    Bagian Penitip
                                </div>

                                <div class="
                                    text-xs
                                    text-neutral-400
                                    mt-1
                                ">
                                    Jumlah yang menjadi hak penitip saat barang terjual.
                                </div>

                            </div>


                            <div class="
                                text-base
                                font-semibold
                                text-neutral-900
                            ">
                                <?= rupiah($consignorAmount) ?>
                            </div>

                        </div>

                    </div>

                </div>


                <!-- RIWAYAT STOK -->

                <div class="bento-card overflow-hidden">

                    <div class="
                        p-5
                        md:p-7
                        border-b
                        border-neutral-100
                    ">

                        <h2 class="
                            text-lg
                            font-semibold
                            text-neutral-900
                        ">
                            Riwayat Stok
                        </h2>

                        <p class="
                            text-sm
                            text-neutral-500
                            mt-1
                        ">
                            Pergerakan stok barang ini.
                        </p>

                    </div>


                    <?php if (empty($movements)): ?>

                        <div class="
                            p-8
                            text-center
                        ">

                            <p class="
                                text-sm
                                text-neutral-500
                            ">
                                Belum ada riwayat stok.
                            </p>

                        </div>

                    <?php else: ?>

                        <div class="divide-y divide-neutral-100">

                            <?php foreach ($movements as $movement): ?>

                                <?php

                                $movementType =
                                    $movement['movement_type'];

                                $isIncoming = in_array(
                                    $movementType,
                                    [
                                        'BELANJA',
                                        'TITIPAN_MASUK',
                                        'KOREKSI_MASUK'
                                    ],
                                    true
                                );

                                $quantityPrefix =
                                    $isIncoming
                                        ? '+'
                                        : '-';

                                $movementLabel = match (
                                    $movementType
                                ) {

                                    'TITIPAN_MASUK'
                                        => 'Titipan Masuk',

                                    'PENJUALAN'
                                        => 'Penjualan',

                                    'RETUR'
                                        => 'Retur',

                                    'RUSAK'
                                        => 'Rusak',

                                    'KADALUARSA'
                                        => 'Kedaluwarsa',

                                    'KOREKSI_MASUK'
                                        => 'Koreksi Masuk',

                                    'KOREKSI_KELUAR'
                                        => 'Koreksi Keluar',

                                    default
                                        => $movementType,
                                };

                                ?>

                                <div class="
                                    px-5
                                    md:px-7
                                    py-4
                                    flex
                                    items-center
                                    justify-between
                                    gap-4
                                ">


                                    <div class="
                                        flex
                                        items-center
                                        gap-3
                                        min-w-0
                                    ">

                                        <div class="
                                            w-9
                                            h-9
                                            rounded-xl
                                            bg-neutral-100
                                            flex
                                            items-center
                                            justify-center
                                            shrink-0
                                        ">

                                            <i
                                                data-lucide="<?= $isIncoming ? 'arrow-down-left' : 'arrow-up-right' ?>"
                                                class="w-4 h-4 text-neutral-600"
                                            ></i>

                                        </div>


                                        <div class="min-w-0">

                                            <div class="
                                                text-sm
                                                font-medium
                                                text-neutral-800
                                            ">
                                                <?= e($movementLabel) ?>
                                            </div>

                                            <div class="
                                                text-xs
                                                text-neutral-400
                                                mt-1
                                            ">
                                                <?= date('d M Y H:i', strtotime($movement['created_at'])) ?>
                                            </div>

                                            <?php if (!empty($movement['notes'])): ?>

                                                <div class="
                                                    text-xs
                                                    text-neutral-400
                                                    mt-1
                                                    truncate
                                                ">
                                                    <?= e($movement['notes']) ?>
                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div>


                                    <div class="
                                        text-sm
                                        font-semibold
                                        shrink-0
                                        <?= $isIncoming
                                            ? 'text-green-700'
                                            : 'text-neutral-900'
                                        ?>
                                    ">
                                        <?= $quantityPrefix ?>
                                        <?= number_format(
                                            (int) $movement['quantity'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =====================================================
                 RIGHT
            ====================================================== -->

            <div class="space-y-6">


                <!-- PENITIP -->

                <div class="bento-card p-5 md:p-6">

                    <div class="
                        flex
                        items-center
                        gap-3
                        mb-5
                    ">

                        <div class="
                            w-10
                            h-10
                            rounded-xl
                            bg-neutral-100
                            flex
                            items-center
                            justify-center
                        ">

                            <i
                                data-lucide="user-round"
                                class="w-5 h-5 text-neutral-700"
                            ></i>

                        </div>


                        <div>

                            <h2 class="
                                text-base
                                font-semibold
                                text-neutral-900
                            ">
                                Penitip
                            </h2>

                            <p class="
                                text-xs
                                text-neutral-400
                            ">
                                Pemilik barang
                            </p>

                        </div>

                    </div>


                    <?php if (!empty($product['consignor_id'])): ?>

                        <div class="space-y-4">

                            <div>

                                <div class="
                                    text-xs
                                    text-neutral-400
                                    mb-1
                                ">
                                    Nama
                                </div>

                                <div class="
                                    text-sm
                                    font-semibold
                                    text-neutral-900
                                ">
                                    <?= e($product['consignor_name']) ?>
                                </div>

                            </div>


                            <div>

                                <div class="
                                    text-xs
                                    text-neutral-400
                                    mb-1
                                ">
                                    WhatsApp
                                </div>

                                <div class="
                                    text-sm
                                    text-neutral-700
                                ">
                                    <?= !empty($product['consignor_phone'])
                                        ? e($product['consignor_phone'])
                                        : '-'
                                    ?>
                                </div>

                            </div>


                            <div>

                                <div class="
                                    text-xs
                                    text-neutral-400
                                    mb-1
                                ">
                                    Alamat
                                </div>

                                <div class="
                                    text-sm
                                    text-neutral-700
                                ">
                                    <?= !empty($product['consignor_address'])
                                        ? e($product['consignor_address'])
                                        : '-'
                                    ?>
                                </div>

                            </div>


                            <a
                                href="/pages/titipan/penitip.php"
                                class="
                                    w-full
                                    inline-flex
                                    items-center
                                    justify-center
                                    gap-2
                                    px-4
                                    py-2.5
                                    rounded-xl
                                    border
                                    border-neutral-200
                                    text-sm
                                    font-medium
                                    text-neutral-700
                                    hover:bg-neutral-50
                                "
                            >

                                <i
                                    data-lucide="users"
                                    class="w-4 h-4"
                                ></i>

                                Data Penitip

                            </a>

                        </div>

                    <?php else: ?>

                        <p class="
                            text-sm
                            text-neutral-500
                        ">
                            Data penitip tidak ditemukan.
                        </p>

                    <?php endif; ?>

                </div>


                <!-- PERIODE -->

                <div class="bento-card p-5 md:p-6">

                    <h2 class="
                        text-base
                        font-semibold
                        text-neutral-900
                        mb-5
                    ">
                        Periode Titipan
                    </h2>


                    <div class="space-y-4">


                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                Mulai Titip
                            </div>

                            <div class="
                                text-sm
                                font-medium
                                text-neutral-800
                            ">

                                <?= !empty($product['start_date'])
                                    ? date(
                                        'd M Y',
                                        strtotime($product['start_date'])
                                    )
                                    : '-'
                                ?>

                            </div>

                        </div>


                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                Selesai Titip
                            </div>

                            <div class="
                                text-sm
                                font-medium
                                text-neutral-800
                            ">

                                <?= !empty($product['end_date'])
                                    ? date(
                                        'd M Y',
                                        strtotime($product['end_date'])
                                    )
                                    : 'Tidak ditentukan'
                                ?>

                            </div>

                        </div>


                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                Status Penitip
                            </div>


                            <?php if ($product['consignor_status'] === 'ACTIVE'): ?>

                                <span class="
                                    inline-flex
                                    items-center
                                    gap-1.5
                                    px-2.5
                                    py-1.5
                                    rounded-lg
                                    bg-green-50
                                    text-green-700
                                    text-xs
                                    font-medium
                                ">
                                    Aktif
                                </span>

                            <?php else: ?>

                                <span class="
                                    inline-flex
                                    items-center
                                    gap-1.5
                                    px-2.5
                                    py-1.5
                                    rounded-lg
                                    bg-neutral-100
                                    text-neutral-500
                                    text-xs
                                    font-medium
                                ">
                                    Tidak Aktif
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <!-- CATATAN -->

                <?php if (
                    !empty($product['consignment_notes'])
                ): ?>

                    <div class="bento-card p-5 md:p-6">

                        <div class="
                            flex
                            items-center
                            gap-2
                            mb-4
                        ">

                            <i
                                data-lucide="notebook-pen"
                                class="
                                    w-4
                                    h-4
                                    text-neutral-500
                                "
                            ></i>

                            <h2 class="
                                text-base
                                font-semibold
                                text-neutral-900
                            ">
                                Catatan
                            </h2>

                        </div>


                        <p class="
                            text-sm
                            leading-6
                            text-neutral-600
                            whitespace-pre-line
                        ">
                            <?= e($product['consignment_notes']) ?>
                        </p>

                    </div>

                <?php endif; ?>


                <!-- FEE DETAIL -->

                <div class="bento-card p-5 md:p-6">

                    <h2 class="
                        text-base
                        font-semibold
                        text-neutral-900
                        mb-5
                    ">
                        Pembagian Saat Terjual
                    </h2>


                    <div class="space-y-3">


                        <div class="
                            flex
                            items-center
                            justify-between
                            gap-3
                        ">

                            <span class="
                                text-sm
                                text-neutral-500
                            ">
                                Harga jual
                            </span>

                            <span class="
                                text-sm
                                font-medium
                                text-neutral-900
                            ">
                                <?= rupiah($sellingPrice) ?>
                            </span>

                        </div>


                        <div class="
                            flex
                            items-center
                            justify-between
                            gap-3
                        ">

                            <span class="
                                text-sm
                                text-neutral-500
                            ">
                                Fee toko
                            </span>

                            <span class="
                                text-sm
                                font-medium
                                text-neutral-900
                            ">
                                <?= rupiah($storeFee) ?>
                            </span>

                        </div>


                        <div class="
                            pt-3
                            border-t
                            border-neutral-100
                            flex
                            items-center
                            justify-between
                            gap-3
                        ">

                            <span class="
                                text-sm
                                font-medium
                                text-neutral-700
                            ">
                                Hak penitip
                            </span>

                            <span class="
                                text-sm
                                font-semibold
                                text-neutral-900
                            ">
                                <?= rupiah($consignorAmount) ?>
                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</main>


<?php require_once '../../includes/footer.php'; ?>