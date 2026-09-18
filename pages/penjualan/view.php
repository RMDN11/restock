<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$pageTitle = 'Detail Penjualan';

/*
|--------------------------------------------------------------------------
| Helper
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
| ID TRANSAKSI
|--------------------------------------------------------------------------
*/

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: /pages/penjualan/');
    exit;
}


/*
|--------------------------------------------------------------------------
| Flash message
|--------------------------------------------------------------------------
*/

$success = $_GET['success'] ?? null;
$error   = $_GET['error'] ?? null;


/*
|--------------------------------------------------------------------------
| AMBIL TRANSAKSI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.*,
        u.name AS created_by_name
    FROM sales s
    LEFT JOIN users u
        ON u.id = s.created_by
    WHERE s.id = :sale_id
      AND s.store_id = :sale_store_id
    LIMIT 1
");

$stmt->execute([
    ':sale_id' => $id,
    ':sale_store_id' => $authStoreId
]);

$sale = $stmt->fetch();

if (!$sale) {
    header(
        'Location: /pages/penjualan/?error=' .
        urlencode('Transaksi tidak ditemukan.')
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| AMBIL ITEM TRANSAKSI
|--------------------------------------------------------------------------
|
| Untuk setiap sale_item, kita coba ambil data penitip
| dari consignment yang terkait dengan produk tersebut.
|
*/

$stmt = $pdo->prepare("
    SELECT
        si.id,
        si.sale_id,
        si.product_id,
        si.quantity,
        si.selling_price,
        si.buying_price,
        si.subtotal,
        si.profit,
        si.consignor_amount,
        si.store_amount,

        p.name AS product_name,
        p.sku,
        p.product_type,

        (
            SELECT c.name
            FROM consignments cs
            INNER JOIN consignors c
                ON c.id = cs.consignor_id
            WHERE cs.product_id = p.id
              AND cs.store_id = p.store_id
            ORDER BY
                CASE
                    WHEN cs.status = 'ACTIVE' THEN 0
                    ELSE 1
                END,
                cs.id DESC
            LIMIT 1
        ) AS consignor_name

    FROM sale_items si

    INNER JOIN products p
        ON p.id = si.product_id

    WHERE si.sale_id = :sale_id
      AND si.store_id = :item_store_id
      AND p.store_id = si.store_id

    ORDER BY si.id ASC
");

$stmt->execute([
    ':sale_id' => $id,
    ':item_store_id' => $authStoreId
]);

$items = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RINGKASAN
|--------------------------------------------------------------------------
*/

$totalQty = 0;
$totalProfit = 0;
$totalConsignorAmount = 0;
$totalStoreAmount = 0;

foreach ($items as $item) {

    $totalQty += (int) $item['quantity'];

    $totalProfit += (float) $item['profit'];

    $totalConsignorAmount +=
        (float) $item['consignor_amount'];

    $totalStoreAmount +=
        (float) $item['store_amount'];
}


/*
|--------------------------------------------------------------------------
| PAYMENT LABEL
|--------------------------------------------------------------------------
*/

$paymentLabels = [
    'CASH'     => 'Cash',
    'QRIS'     => 'QRIS',
    'TRANSFER' => 'Transfer',
    'OTHER'    => 'Lainnya',
];

$paymentLabel =
    $paymentLabels[$sale['payment_method']]
    ?? $sale['payment_method'];


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$isCancelled =
    $sale['status'] === 'CANCELLED';


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<div class="main-content">

    <!-- HEADER -->
    <main class="p-4 md:p-6 lg:p-8">

        <div class="mb-8">

            <a
                href="/pages/penjualan/"
                class="inline-flex items-center gap-2 text-sm text-neutral-500 hover:text-neutral-900 mb-5"
            >
                <i
                    data-lucide="arrow-left"
                    class="w-4 h-4"
                ></i>

                Kembali ke Penjualan
            </a>


            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">

                <div>

                    <div class="flex items-center gap-3 flex-wrap">

                        <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-neutral-900">
                            Detail Penjualan
                        </h1>


                        <?php if ($isCancelled): ?>

                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-neutral-100 text-neutral-500 text-xs font-medium">

                                <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>

                                Dibatalkan

                            </span>

                        <?php else: ?>

                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-50 text-green-700 text-xs font-medium">

                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>

                                Selesai

                            </span>

                        <?php endif; ?>

                    </div>


                    <p class="text-sm text-neutral-500 mt-2">
                        <?= e($sale['invoice_number']) ?>
                    </p>

                </div>


                <!-- ACTION -->
                <div class="flex flex-wrap gap-2">

                    <!-- INVOICE -->
                    <a
                        href="/pages/penjualan/invoice.php?id=<?= (int) $sale['id'] ?>"
                        target="_blank"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-neutral-200 bg-white text-neutral-700 text-sm font-medium hover:bg-neutral-50 transition"
                    >
                        <i
                            data-lucide="file-text"
                            class="w-4 h-4"
                        ></i>

                        Invoice
                    </a>


                    <?php if (!$isCancelled): ?>

                        <!-- EDIT -->
                        <button
                            type="button"
                            onclick="openEditSaleModal()"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-neutral-200 bg-white text-neutral-700 text-sm font-medium hover:bg-neutral-50 transition"
                        >
                            <i
                                data-lucide="pencil"
                                class="w-4 h-4"
                            ></i>

                            Edit
                        </button>


                        <!-- BATALKAN -->
                        <form
                            action="/pages/penjualan/cancel.php"
                            method="POST"
                            onsubmit="return confirmCancelSale();"
                        >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $sale['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_token'] ?? '') ?>"
                            >

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-red-200 bg-white text-red-600 text-sm font-medium hover:bg-red-50 transition"
                            >
                                <i
                                    data-lucide="ban"
                                    class="w-4 h-4"
                                ></i>

                                Batalkan
                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- FLASH SUCCESS -->
        <?php if ($success): ?>

            <div class="mb-5 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <?= e($success) ?>
            </div>

        <?php endif; ?>


        <!-- FLASH ERROR -->
        <?php if ($error): ?>

            <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <!-- INFORMASI TRANSAKSI -->
        <section class="bento-card p-5 sm:p-6 mb-5">

            <div class="flex items-center justify-between mb-6">

                <div>

                    <h2 class="text-lg font-semibold text-neutral-900">
                        Informasi Transaksi
                    </h2>

                    <p class="text-sm text-neutral-500 mt-1">
                        Detail dasar transaksi penjualan.
                    </p>

                </div>

            </div>


            <div class="grid grid-cols-2 md:grid-cols-4 gap-5">

                <div>

                    <div class="text-xs text-neutral-400 mb-1">
                        Invoice
                    </div>

                    <div class="text-sm font-medium text-neutral-900">
                        <?= e($sale['invoice_number']) ?>
                    </div>

                </div>


                <div>

                    <div class="text-xs text-neutral-400 mb-1">
                        Tanggal
                    </div>

                    <div class="text-sm font-medium text-neutral-900">
                        <?= date('d M Y', strtotime($sale['sale_date'])) ?>
                    </div>

                    <div class="text-xs text-neutral-400 mt-1">
                        <?= date('H:i', strtotime($sale['sale_date'])) ?>
                    </div>

                </div>


                <div>

                    <div class="text-xs text-neutral-400 mb-1">
                        Pembayaran
                    </div>

                    <div class="text-sm font-medium text-neutral-900">
                        <?= e($paymentLabel) ?>
                    </div>

                </div>


                <div>

                    <div class="text-xs text-neutral-400 mb-1">
                        Dibuat oleh
                    </div>

                    <div class="text-sm font-medium text-neutral-900">
                        <?= e($sale['created_by_name'] ?: '-') ?>
                    </div>

                </div>

            </div>

        </section>


        <!-- BARANG -->
        <section class="bento-card overflow-hidden mb-5">

            <div class="p-5 sm:p-6 border-b border-neutral-100">

                <h2 class="text-lg font-semibold text-neutral-900">
                    Barang Terjual
                </h2>

                <p class="text-sm text-neutral-500 mt-1">
                    <?= $totalQty ?> barang dalam transaksi ini.
                </p>

            </div>


            <!-- DESKTOP -->
            <div class="hidden md:block overflow-x-auto">

                <table class="w-full">

                    <thead>

                        <tr class="border-b border-neutral-100">

                            <th class="px-6 py-4 text-left text-xs font-medium text-neutral-400">
                                Barang
                            </th>

                            <th class="px-6 py-4 text-left text-xs font-medium text-neutral-400">
                                Jenis
                            </th>

                            <th class="px-6 py-4 text-right text-xs font-medium text-neutral-400">
                                Qty
                            </th>

                            <th class="px-6 py-4 text-right text-xs font-medium text-neutral-400">
                                Harga
                            </th>

                            <th class="px-6 py-4 text-right text-xs font-medium text-neutral-400">
                                Subtotal
                            </th>

                            <th class="px-6 py-4 text-right text-xs font-medium text-neutral-400">
                                Keterangan
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-neutral-100">

                        <?php foreach ($items as $item): ?>

                            <tr>

                                <td class="px-6 py-4">

                                    <div class="text-sm font-medium text-neutral-900">
                                        <?= e($item['product_name']) ?>
                                    </div>

                                    <?php if (!empty($item['sku'])): ?>

                                        <div class="text-xs text-neutral-400 mt-1">
                                            SKU <?= e($item['sku']) ?>
                                        </div>

                                    <?php endif; ?>

                                </td>


                                <td class="px-6 py-4">

                                    <?php if ($item['product_type'] === 'TITIPAN'): ?>

                                        <span class="inline-flex px-2.5 py-1 rounded-full bg-neutral-100 text-neutral-600 text-xs">
                                            Titipan
                                        </span>

                                    <?php else: ?>

                                        <span class="inline-flex px-2.5 py-1 rounded-full bg-neutral-100 text-neutral-600 text-xs">
                                            Toko
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td class="px-6 py-4 text-sm text-neutral-700 text-right">
                                    <?= (int) $item['quantity'] ?>
                                </td>


                                <td class="px-6 py-4 text-sm text-neutral-700 text-right">
                                    <?= rupiah($item['selling_price']) ?>
                                </td>


                                <td class="px-6 py-4 text-sm font-medium text-neutral-900 text-right">
                                    <?= rupiah($item['subtotal']) ?>
                                </td>


                                <td class="px-6 py-4 text-right">

                                    <?php if ($item['product_type'] === 'TITIPAN'): ?>

                                        <div class="text-xs text-neutral-500">
                                            Penitip:
                                            <?= e($item['consignor_name'] ?: '-') ?>
                                        </div>

                                        <div class="text-xs text-neutral-500 mt-1">
                                            Bagian toko:
                                            <?= rupiah($item['store_amount']) ?>
                                        </div>

                                        <div class="text-xs text-neutral-500 mt-1">
                                            Bagian penitip:
                                            <?= rupiah($item['consignor_amount']) ?>
                                        </div>

                                    <?php else: ?>

                                        <div class="text-xs text-neutral-500">
                                            Untung:
                                            <?= rupiah($item['profit']) ?>
                                        </div>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <!-- MOBILE -->
            <div class="md:hidden divide-y divide-neutral-100">

                <?php foreach ($items as $item): ?>

                    <div class="p-5">

                        <div class="flex items-start justify-between gap-4">

                            <div class="min-w-0">

                                <div class="text-sm font-medium text-neutral-900">
                                    <?= e($item['product_name']) ?>
                                </div>

                                <?php if (!empty($item['sku'])): ?>

                                    <div class="text-xs text-neutral-400 mt-1">
                                        SKU <?= e($item['sku']) ?>
                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="text-sm font-semibold text-neutral-900 whitespace-nowrap">
                                <?= rupiah($item['subtotal']) ?>
                            </div>

                        </div>


                        <div class="grid grid-cols-3 gap-3 mt-4">

                            <div>

                                <div class="text-xs text-neutral-400">
                                    Qty
                                </div>

                                <div class="text-sm text-neutral-700 mt-1">
                                    <?= (int) $item['quantity'] ?>
                                </div>

                            </div>


                            <div>

                                <div class="text-xs text-neutral-400">
                                    Harga
                                </div>

                                <div class="text-sm text-neutral-700 mt-1">
                                    <?= rupiah($item['selling_price']) ?>
                                </div>

                            </div>


                            <div>

                                <div class="text-xs text-neutral-400">
                                    Jenis
                                </div>

                                <div class="text-sm text-neutral-700 mt-1">
                                    <?= $item['product_type'] === 'TITIPAN'
                                        ? 'Titipan'
                                        : 'Toko' ?>
                                </div>

                            </div>

                        </div>


                        <?php if ($item['product_type'] === 'TITIPAN'): ?>

                            <div class="mt-4 p-3 rounded-xl bg-neutral-50 text-xs text-neutral-600 space-y-1">

                                <div>
                                    Penitip:
                                    <strong>
                                        <?= e($item['consignor_name'] ?: '-') ?>
                                    </strong>
                                </div>

                                <div>
                                    Bagian toko:
                                    <strong>
                                        <?= rupiah($item['store_amount']) ?>
                                    </strong>
                                </div>

                                <div>
                                    Bagian penitip:
                                    <strong>
                                        <?= rupiah($item['consignor_amount']) ?>
                                    </strong>
                                </div>

                            </div>

                        <?php else: ?>

                            <div class="mt-4 text-xs text-neutral-500">

                                Keuntungan:

                                <strong class="text-neutral-700">
                                    <?= rupiah($item['profit']) ?>
                                </strong>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- RINGKASAN -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">


            <!-- CATATAN -->
            <section class="bento-card p-5 sm:p-6 lg:col-span-2">

                <h2 class="text-lg font-semibold text-neutral-900">
                    Catatan
                </h2>

                <div class="mt-4 text-sm text-neutral-600 whitespace-pre-line">

                    <?php if (!empty($sale['notes'])): ?>

                        <?= e($sale['notes']) ?>

                    <?php else: ?>

                        <span class="text-neutral-400">
                            Tidak ada catatan.
                        </span>

                    <?php endif; ?>

                </div>

            </section>


            <!-- RINGKASAN -->
            <section class="bento-card p-5 sm:p-6">

                <h2 class="text-lg font-semibold text-neutral-900 mb-5">
                    Ringkasan
                </h2>


                <div class="space-y-4">

                    <div class="flex items-center justify-between text-sm">

                        <span class="text-neutral-500">
                            Total barang
                        </span>

                        <span class="font-medium text-neutral-900">
                            <?= $totalQty ?>
                        </span>

                    </div>


                    <div class="flex items-center justify-between text-sm">

                        <span class="text-neutral-500">
                            Total transaksi
                        </span>

                        <span class="font-medium text-neutral-900">
                            <?= rupiah($sale['total_amount']) ?>
                        </span>

                    </div>


                    <div class="pt-4 border-t border-neutral-100">

                        <div class="flex items-center justify-between gap-4">

                            <span class="text-sm text-neutral-500">
                                Keuntungan toko
                            </span>

                            <span class="text-xl font-semibold text-neutral-900">
                                <?= rupiah($totalProfit) ?>
                            </span>

                        </div>

                    </div>


                    <?php if ($totalConsignorAmount > 0): ?>

                        <div class="pt-4 border-t border-neutral-100">

                            <div class="flex items-center justify-between text-sm">

                                <span class="text-neutral-500">
                                    Bagian penitip
                                </span>

                                <span class="font-medium text-neutral-900">
                                    <?= rupiah($totalConsignorAmount) ?>
                                </span>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

        </div>


        <!-- CANCELLED INFO -->
        <?php if ($isCancelled): ?>

            <div class="mt-5 rounded-2xl border border-neutral-200 bg-neutral-50 p-5">

                <div class="flex gap-3">

                    <i
                        data-lucide="info"
                        class="w-5 h-5 text-neutral-500 flex-shrink-0"
                    ></i>

                    <div>

                        <div class="text-sm font-medium text-neutral-900">
                            Transaksi ini sudah dibatalkan.
                        </div>

                        <div class="text-sm text-neutral-500 mt-1">
                            Stok sudah dikembalikan dan transaksi tidak dapat diedit.
                        </div>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>


<!-- =========================================================
     MODAL EDIT PENJUALAN
========================================================= -->

<?php if (!$isCancelled): ?>

<div
    id="editSaleModal"
    class="fixed inset-0 z-[100] hidden"
>

    <!-- Overlay -->
    <div
        class="absolute inset-0 bg-black/30 backdrop-blur-sm"
        onclick="closeEditSaleModal()"
    ></div>


    <!-- Modal Wrapper -->
    <div class="relative min-h-full flex items-center justify-center p-4">

        <div
            class="w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white rounded-3xl shadow-2xl"
        >

            <!-- Modal Header -->
            <div class="sticky top-0 z-10 bg-white border-b border-neutral-100 px-5 sm:px-6 py-5">

                <div class="flex items-center justify-between gap-4">

                    <div>

                        <h2 class="text-lg font-semibold text-neutral-900">
                            Edit Penjualan
                        </h2>

                        <p class="text-sm text-neutral-500 mt-1">
                            <?= e($sale['invoice_number']) ?>
                        </p>

                    </div>


                    <button
                        type="button"
                        onclick="closeEditSaleModal()"
                        class="w-9 h-9 rounded-xl border border-neutral-200 flex items-center justify-center text-neutral-500 hover:bg-neutral-50"
                    >
                        <i
                            data-lucide="x"
                            class="w-4 h-4"
                        ></i>
                    </button>

                </div>

            </div>


            <!-- FORM -->
            <form
                action="/pages/penjualan/edit.php"
                method="POST"
                class="p-5 sm:p-6 space-y-6"
                onsubmit="return confirmEditSale();"
            >

                <input
                    type="hidden"
                    name="sale_id"
                    value="<?= (int) $sale['id'] ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token'] ?? '') ?>"
                >


                <!-- TANGGAL -->
                <div>

                    <label class="block text-sm font-medium text-neutral-700 mb-2">
                        Tanggal & Waktu
                    </label>

                    <input
                        type="datetime-local"
                        name="sale_date"
                        value="<?= date(
                            'Y-m-d\TH:i',
                            strtotime($sale['sale_date'])
                        ) ?>"
                        required
                        class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-neutral-200"
                    >

                </div>


                <!-- PEMBAYARAN -->
                <div>

                    <label class="block text-sm font-medium text-neutral-700 mb-2">
                        Pembayaran
                    </label>

                    <select
                        name="payment_method"
                        class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-neutral-200"
                    >

                        <?php foreach ($paymentLabels as $value => $label): ?>

                            <option
                                value="<?= e($value) ?>"
                                <?= $sale['payment_method'] === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($label) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- BARANG -->
                <div>

                    <div class="mb-3">

                        <label class="block text-sm font-medium text-neutral-700">
                            Barang
                        </label>

                        <p class="text-xs text-neutral-400 mt-1">
                            Jumlah dapat diubah. Harga jual mengikuti Master Data Barang.
                            Untuk mengubah harga, edit dari menu Barang.
                        </p>

                    </div>


                    <div class="space-y-3">

                        <?php foreach ($items as $index => $item): ?>

                            <div class="p-4 rounded-2xl bg-neutral-50 border border-neutral-100">

                                <input
                                    type="hidden"
                                    name="items[<?= $index ?>][product_id]"
                                    value="<?= (int) $item['product_id'] ?>"
                                >


                                <div class="flex items-start justify-between gap-4">

                                    <div class="min-w-0">

                                        <div class="text-sm font-medium text-neutral-900">
                                            <?= e($item['product_name']) ?>
                                        </div>

                                        <?php if (!empty($item['sku'])): ?>

                                            <div class="text-xs text-neutral-400 mt-1">
                                                SKU <?= e($item['sku']) ?>
                                            </div>

                                        <?php endif; ?>

                                    </div>


                                    <span class="text-xs px-2 py-1 rounded-full bg-white border border-neutral-200 text-neutral-500 whitespace-nowrap">

                                        <?= $item['product_type'] === 'TITIPAN'
                                            ? 'Titipan'
                                            : 'Toko' ?>

                                    </span>

                                </div>


                                <div class="grid grid-cols-2 gap-3 mt-4">


                                    <!-- QTY -->
                                    <div>

                                        <label class="block text-xs text-neutral-500 mb-1.5">
                                            Jumlah
                                        </label>

                                        <input
                                            type="number"
                                            name="items[<?= $index ?>][quantity]"
                                            value="<?= (int) $item['quantity'] ?>"
                                            min="1"
                                            required
                                            class="w-full rounded-xl border border-neutral-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-neutral-200"
                                        >

                                    </div>


                                    <!-- HARGA -->
                                    <div>

                                        <label class="block text-xs text-neutral-500 mb-1.5">
                                            Harga Jual
                                        </label>

                                        <input
                                            type="text"
                                            value="<?= e(rupiah($item['selling_price'])) ?>"
                                            readonly
                                            tabindex="-1"
                                            class="w-full rounded-xl border border-neutral-200 bg-neutral-100 px-3 py-2.5 text-sm text-neutral-500 cursor-not-allowed"
                                        >

                                        <p class="text-[11px] text-neutral-400 mt-1.5">
                                            Harga diambil dari Master Data Barang.
                                        </p>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>


                <!-- CATATAN -->
                <div>

                    <label class="block text-sm font-medium text-neutral-700 mb-2">
                        Catatan
                    </label>

                    <textarea
                        name="notes"
                        rows="3"
                        placeholder="Tambahkan catatan jika perlu..."
                        class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-neutral-200"
                    ><?= e($sale['notes'] ?? '') ?></textarea>

                </div>


                <!-- WARNING -->
                <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4">

                    <div class="flex gap-3">

                        <i
                            data-lucide="triangle-alert"
                            class="w-5 h-5 text-amber-600 flex-shrink-0"
                        ></i>

                        <div>

                            <div class="text-sm font-medium text-amber-900">
                                Data transaksi akan dihitung ulang.
                            </div>

                            <p class="text-xs text-amber-700 mt-1 leading-relaxed">
                                Stok, total transaksi, dan keuntungan akan disesuaikan dengan data yang baru.
                            </p>

                        </div>

                    </div>

                </div>


                <!-- FOOTER -->
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">

                    <button
                        type="button"
                        onclick="closeEditSaleModal()"
                        class="px-4 py-2.5 rounded-xl border border-neutral-200 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
                    >
                        Batal
                    </button>


                    <button
                        type="submit"
                        class="px-4 py-2.5 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
                    >
                        Simpan Perubahan
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

function openEditSaleModal()
{
    const modal =
        document.getElementById('editSaleModal');

    if (!modal) {
        return;
    }

    modal.classList.remove('hidden');

    document.body.classList.add('overflow-hidden');

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}


function closeEditSaleModal()
{
    const modal =
        document.getElementById('editSaleModal');

    if (!modal) {
        return;
    }

    modal.classList.add('hidden');

    document.body.classList.remove('overflow-hidden');
}


function confirmEditSale()
{
    return confirm(
        'Simpan perubahan transaksi ini?\n\n' +
        'Stok, total transaksi, dan keuntungan akan dihitung ulang.'
    );
}


function confirmCancelSale()
{
    return confirm(
        'Batalkan transaksi ini?\n\n' +
        'Stok barang akan dikembalikan.'
    );
}


document.addEventListener('keydown', function(event)
{
    if (event.key === 'Escape') {
        closeEditSaleModal();
    }
});

</script>

<?php endif; ?>


<?php
require_once __DIR__ . '/../../includes/footer.php';
?>