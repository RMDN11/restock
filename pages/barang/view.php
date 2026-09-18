<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$pageTitle = 'Detail Barang';


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

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


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
        pc.name AS category_name,
        creator.name AS created_by_name,
        editor.name AS updated_by_name
    FROM products p
    LEFT JOIN product_categories pc
        ON pc.id = p.category_id
        AND pc.store_id = p.store_id
    LEFT JOIN users creator
        ON creator.id = p.created_by
    LEFT JOIN users editor
        ON editor.id = p.updated_by
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
| DETAIL TITIPAN
|--------------------------------------------------------------------------
*/

$consignment = null;

if ($product['product_type'] === 'TITIPAN') {

    $stmt = $pdo->prepare("
        SELECT
            c.*,
            co.name AS consignor_name,
            co.phone AS consignor_phone
        FROM consignments c
        INNER JOIN consignors co
            ON co.id = c.consignor_id
        WHERE c.product_id = :product_id
        AND c.store_id = :store_id
        AND c.status = 'ACTIVE'
        ORDER BY c.id DESC
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
| RIWAYAT STOK
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        sm.*
    FROM stock_movements sm
    WHERE sm.product_id = :product_id
      AND sm.store_id = :store_id
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT 20
");

$stmt->execute([
    ':product_id' => $id,
    ':store_id' => $authStoreId
]);

$stockMovements = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| LABEL MOVEMENT
|--------------------------------------------------------------------------
*/

$movementLabels = [
    'BELANJA' => 'Belanja',
    'TITIPAN_MASUK' => 'Titipan Masuk',
    'PENJUALAN' => 'Penjualan',
    'RETUR' => 'Retur',
    'RUSAK' => 'Rusak',
    'KADALUARSA' => 'Kadaluarsa',
    'KOREKSI_MASUK' => 'Koreksi Masuk',
    'KOREKSI_KELUAR' => 'Koreksi Keluar'
];


$movementIcons = [
    'BELANJA' => 'shopping-cart',
    'TITIPAN_MASUK' => 'handshake',
    'PENJUALAN' => 'receipt',
    'RETUR' => 'rotate-ccw',
    'RUSAK' => 'package-x',
    'KADALUARSA' => 'calendar-x',
    'KOREKSI_MASUK' => 'arrow-down-to-line',
    'KOREKSI_KELUAR' => 'arrow-up-from-line'
];


/*
|--------------------------------------------------------------------------
| HITUNG FEE / HAK PENITIP
|--------------------------------------------------------------------------
*/

$consignorAmount = 0;
$storeFee = 0;

if ($consignment) {

    $initialPrice = (float) $consignment['initial_price'];
    $feeValue = (float) $consignment['fee_value'];
    $sellingPrice = (float) $product['selling_price'];

    if ($consignment['fee_type'] === 'PERCENTAGE') {

        $storeFee =
            $sellingPrice *
            ($feeValue / 100);

    } else {

        $storeFee = $feeValue;
    }

    $consignorAmount =
        max(
            0,
            $sellingPrice - $storeFee
        );
}


/*
|--------------------------------------------------------------------------
| TANGGAL
|--------------------------------------------------------------------------
*/

function formatDateTime($date): string
{
    if (!$date) {
        return '-';
    }

    return date(
        'd M Y, H:i',
        strtotime($date)
    );
}

function formatDate($date): string
{
    if (!$date) {
        return '-';
    }

    return date(
        'd M Y',
        strtotime($date)
    );
}


require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<div class="main-content">

    <!-- MOBILE HEADER -->

<main class="p-4 md:p-6 lg:p-8">

        <!-- PAGE HEADER -->

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">

            <div class="flex items-start gap-4">

                <a
                    href="/pages/barang/"
                    class="
                        w-10 h-10 shrink-0
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

                    <div class="flex items-center gap-2 flex-wrap">

                        <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                            <?= e($product['name']) ?>
                        </h1>

                        <?php if ($product['status'] === 'ACTIVE'): ?>

                            <span class="
                                inline-flex
                                px-2.5 py-1
                                rounded-lg
                                text-xs font-medium
                                bg-emerald-50
                                text-emerald-600
                            ">
                                Aktif
                            </span>

                        <?php else: ?>

                            <span class="
                                inline-flex
                                px-2.5 py-1
                                rounded-lg
                                text-xs font-medium
                                bg-neutral-100
                                text-neutral-500
                            ">
                                Tidak Aktif
                            </span>

                        <?php endif; ?>

                    </div>


                    <p class="mt-2 text-sm text-neutral-500">

                        <?= $product['product_type'] === 'TITIPAN'
                            ? 'Barang titipan'
                            : 'Barang toko'
                        ?>

                        <?php if (!empty($product['sku'])): ?>

                            <span class="mx-1">
                                ·
                            </span>

                            SKU <?= e($product['sku']) ?>

                        <?php endif; ?>

                    </p>

                </div>

            </div>


            <!-- EDIT -->

            <a
                href="/pages/barang/edit.php?id=<?= (int) $product['id'] ?>"
                class="
                    inline-flex
                    items-center
                    justify-center
                    gap-2
                    h-11
                    px-4
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
                    data-lucide="pencil"
                    class="w-4 h-4"
                ></i>

                Edit Barang

            </a>

        </div>


        <!-- TOP CARDS -->

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">


            <!-- HARGA JUAL -->

            <div class="bento-card p-5">

                <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center mb-4">

                    <i
                        data-lucide="tag"
                        class="w-5 h-5"
                    ></i>

                </div>

                <p class="text-sm text-neutral-500">
                    Harga Jual
                </p>

                <p class="text-xl font-semibold mt-1">
                    <?= rupiah($product['selling_price']) ?>
                </p>

            </div>


            <!-- STOK -->

            <div class="bento-card p-5">

                <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center mb-4">

                    <i
                        data-lucide="boxes"
                        class="w-5 h-5"
                    ></i>

                </div>

                <p class="text-sm text-neutral-500">
                    Stok Saat Ini
                </p>

                <p class="
                    text-xl font-semibold mt-1
                    <?= (
                        (int) $product['current_stock']
                        <=
                        (int) $product['minimum_stock']
                    )
                        ? 'text-red-600'
                        : 'text-neutral-900'
                    ?>
                ">
                    <?= (int) $product['current_stock'] ?>
                    <span class="text-sm font-normal text-neutral-400">
                        <?= e($product['unit']) ?>
                    </span>
                </p>

            </div>


            <!-- KATEGORI -->

            <div class="bento-card p-5">

                <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center mb-4">

                    <i
                        data-lucide="layers-3"
                        class="w-5 h-5"
                    ></i>

                </div>

                <p class="text-sm text-neutral-500">
                    Kategori
                </p>

                <p class="text-xl font-semibold mt-1">
                    <?= e($product['category_name'] ?: '-') ?>
                </p>

            </div>


            <!-- TIPE -->

            <div class="bento-card p-5">

                <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center mb-4">

                    <?php if ($product['product_type'] === 'TITIPAN'): ?>

                        <i
                            data-lucide="handshake"
                            class="w-5 h-5"
                        ></i>

                    <?php else: ?>

                        <i
                            data-lucide="store"
                            class="w-5 h-5"
                        ></i>

                    <?php endif; ?>

                </div>

                <p class="text-sm text-neutral-500">
                    Jenis Barang
                </p>

                <p class="text-xl font-semibold mt-1">
                    <?= $product['product_type'] === 'TITIPAN'
                        ? 'Titipan'
                        : 'Toko'
                    ?>
                </p>

            </div>

        </div>


        <!-- DETAIL -->

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">


            <!-- INFORMASI -->

            <section class="xl:col-span-2 bento-card p-5 md:p-6">

                <div class="mb-6">

                    <h2 class="font-semibold text-lg">
                        Informasi Barang
                    </h2>

                    <p class="text-sm text-neutral-500 mt-1">
                        Detail barang yang tersimpan di sistem.
                    </p>

                </div>


                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Nama Barang
                        </p>

                        <p class="text-sm font-medium">
                            <?= e($product['name']) ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            SKU
                        </p>

                        <p class="text-sm font-medium">
                            <?= e($product['sku']) ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Barcode
                        </p>

                        <p class="text-sm font-medium">
                            <?= e($product['barcode'] ?: '-') ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Satuan
                        </p>

                        <p class="text-sm font-medium">
                            <?= e($product['unit']) ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Harga Modal
                        </p>

                        <p class="text-sm font-medium">
                            <?= rupiah($product['buying_price']) ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Harga Jual
                        </p>

                        <p class="text-sm font-medium">
                            <?= rupiah($product['selling_price']) ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Minimum Stok
                        </p>

                        <p class="text-sm font-medium">
                            <?= (int) $product['minimum_stock'] ?>
                            <?= e($product['unit']) ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Dibuat
                        </p>

                        <p class="text-sm font-medium">
                            <?= formatDateTime($product['created_at']) ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Dibuat oleh
                        </p>

                        <p class="text-sm font-medium">
                            <?= e($product['created_by_name'] ?: 'Tidak diketahui') ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Terakhir diubah
                        </p>

                        <p class="text-sm font-medium">
                            <?= !empty($product['updated_at'])
                                ? formatDateTime($product['updated_at'])
                                : '-' ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Diubah oleh
                        </p>

                        <p class="text-sm font-medium">
                            <?= e($product['updated_by_name'] ?: '-') ?>
                        </p>

                    </div>



                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Dibuat oleh
                        </p>

                        <p class="text-sm font-medium">
                            <?= e($product['created_by_name'] ?: 'Tidak diketahui') ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Terakhir diubah
                        </p>

                        <p class="text-sm font-medium">
                            <?= $product['updated_at']
                                ? formatDateTime($product['updated_at'])
                                : '-' ?>
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-neutral-400 mb-1">
                            Diubah oleh
                        </p>

                        <p class="text-sm font-medium">
                            <?= e($product['updated_by_name'] ?: '-') ?>
                        </p>

                    </div>

                </div>

            </section>


            <!-- TITIPAN -->

            <?php if ($product['product_type'] === 'TITIPAN'): ?>

                <section class="bento-card p-5 md:p-6">

                    <div class="flex items-center gap-3 mb-6">

                        <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center">

                            <i
                                data-lucide="handshake"
                                class="w-5 h-5"
                            ></i>

                        </div>

                        <div>

                            <h2 class="font-semibold">
                                Detail Titipan
                            </h2>

                            <p class="text-xs text-neutral-500 mt-1">
                                Informasi penitip dan fee.
                            </p>

                        </div>

                    </div>


                    <?php if ($consignment): ?>

                        <div class="space-y-5">


                            <div>

                                <p class="text-xs text-neutral-400 mb-1">
                                    Penitip
                                </p>

                                <p class="font-medium text-sm">
                                    <?= e($consignment['consignor_name']) ?>
                                </p>

                                <?php if (!empty($consignment['consignor_phone'])): ?>

                                    <p class="text-xs text-neutral-500 mt-1">
                                        <?= e($consignment['consignor_phone']) ?>
                                    </p>

                                <?php endif; ?>

                            </div>


                            <div>

                                <p class="text-xs text-neutral-400 mb-1">
                                    Harga Awal
                                </p>

                                <p class="font-medium text-sm">
                                    <?= rupiah($consignment['initial_price']) ?>
                                </p>

                            </div>


                            <div>

                                <p class="text-xs text-neutral-400 mb-1">
                                    Fee Toko
                                </p>

                                <p class="font-medium text-sm">

                                    <?php if ($consignment['fee_type'] === 'PERCENTAGE'): ?>

                                        <?= rtrim(
                                            rtrim(
                                                number_format(
                                                    (float) $consignment['fee_value'],
                                                    2,
                                                    ',',
                                                    '.'
                                                ),
                                                '0'
                                            ),
                                            ','
                                        ) ?>%

                                    <?php else: ?>

                                        <?= rupiah($consignment['fee_value']) ?>

                                    <?php endif; ?>

                                </p>

                            </div>


                            <div class="pt-4 border-t border-neutral-100">

                                <p class="text-xs text-neutral-400 mb-1">
                                    Hak Penitip / Produk
                                </p>

                                <p class="text-lg font-semibold">
                                    <?= rupiah($consignorAmount) ?>
                                </p>

                            </div>


                            <div>

                                <p class="text-xs text-neutral-400 mb-1">
                                    Fee Toko / Produk
                                </p>

                                <p class="text-sm font-medium">
                                    <?= rupiah($storeFee) ?>
                                </p>

                            </div>


                            <div>

                                <p class="text-xs text-neutral-400 mb-1">
                                    Mulai Titipan
                                </p>

                                <p class="text-sm font-medium">
                                    <?= formatDate($consignment['start_date']) ?>
                                </p>

                            </div>


                            <?php if (!empty($consignment['end_date'])): ?>

                                <div>

                                    <p class="text-xs text-neutral-400 mb-1">
                                        Berakhir
                                    </p>

                                    <p class="text-sm font-medium">
                                        <?= formatDate($consignment['end_date']) ?>
                                    </p>

                                </div>

                            <?php endif; ?>


                            <?php if (!empty($consignment['notes'])): ?>

                                <div>

                                    <p class="text-xs text-neutral-400 mb-1">
                                        Catatan
                                    </p>

                                    <p class="text-sm text-neutral-600 leading-relaxed">
                                        <?= nl2br(e($consignment['notes'])) ?>
                                    </p>

                                </div>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <div class="text-center py-8">

                            <i
                                data-lucide="triangle-alert"
                                class="w-6 h-6 text-amber-500 mx-auto mb-2"
                            ></i>

                            <p class="text-sm text-neutral-500">
                                Detail titipan belum ditemukan.
                            </p>

                        </div>

                    <?php endif; ?>

                </section>

            <?php endif; ?>

        </div>


        <!-- RIWAYAT STOK -->

        <section class="bento-card mt-5 overflow-hidden">

            <div class="px-5 md:px-6 py-5 border-b border-neutral-100">

                <h2 class="font-semibold text-lg">
                    Riwayat Stok
                </h2>

                <p class="text-sm text-neutral-500 mt-1">
                    Maksimal 20 pergerakan stok terakhir.
                </p>

            </div>


            <?php if (empty($stockMovements)): ?>

                <div class="px-6 py-12 text-center">

                    <div class="w-12 h-12 mx-auto rounded-2xl bg-neutral-100 flex items-center justify-center mb-3">

                        <i
                            data-lucide="package-open"
                            class="w-6 h-6 text-neutral-400"
                        ></i>

                    </div>

                    <p class="text-sm text-neutral-500">
                        Belum ada riwayat stok.
                    </p>

                </div>

            <?php else: ?>

                <div class="divide-y divide-neutral-100">

                    <?php foreach ($stockMovements as $movement): ?>

                        <?php

                        $movementType =
                            $movement['movement_type'];

                        $isIncoming = in_array(
                            $movementType,
                            [
                                'BELANJA',
                                'TITIPAN_MASUK',
                                'RETUR',
                                'KOREKSI_MASUK'
                            ],
                            true
                        );

                        $quantity =
                            (int) $movement['quantity'];

                        ?>

                        <div class="px-5 md:px-6 py-4">

                            <div class="flex items-center gap-3">


                                <div class="
                                    w-10 h-10
                                    rounded-xl
                                    bg-neutral-100
                                    flex items-center justify-center
                                    shrink-0
                                ">

                                    <i
                                        data-lucide="<?= e(
                                            $movementIcons[$movementType]
                                            ?? 'package'
                                        ) ?>"
                                        class="w-4 h-4"
                                    ></i>

                                </div>


                                <div class="min-w-0 flex-1">

                                    <p class="text-sm font-medium">

                                        <?= e(
                                            $movementLabels[$movementType]
                                            ?? $movementType
                                        ) ?>

                                    </p>

                                    <p class="text-xs text-neutral-400 mt-1">

                                        <?= formatDateTime(
                                            $movement['created_at']
                                        ) ?>

                                        <?php if (!empty($movement['notes'])): ?>

                                            <span class="mx-1">
                                                ·
                                            </span>

                                            <?= e(
                                                $movement['notes']
                                            ) ?>

                                        <?php endif; ?>

                                    </p>

                                </div>


                                <div class="
                                    text-sm
                                    font-semibold
                                    <?= $isIncoming
                                        ? 'text-emerald-600'
                                        : 'text-red-600'
                                    ?>
                                ">

                                    <?= $isIncoming
                                        ? '+'
                                        : '-'
                                    ?>

                                    <?= $quantity ?>

                                    <span class="font-normal text-xs">
                                        <?= e($product['unit']) ?>
                                    </span>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

});
</script>


<?php
require_once __DIR__ . '/../../includes/footer.php';
?>