<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Dashboard';

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
| Tanggal hari ini
|--------------------------------------------------------------------------
*/

$today = date('Y-m-d');
$storeId = $authStoreId;


/*
|--------------------------------------------------------------------------
| PENJUALAN HARI INI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(total_amount), 0) AS total_sales
    FROM sales
    WHERE sale_date >= :start_date
      AND sale_date < :end_date
      AND status = 'COMPLETED'
      AND store_id = :store_id_sales
");

$stmt->execute([
    ':start_date' => $today . ' 00:00:00',
    ':end_date'   => date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00',
    ':store_id_sales' => $storeId,
]);

$todaySales = (float) ($stmt->fetch()['total_sales'] ?? 0);


/*
|--------------------------------------------------------------------------
| JUMLAH TRANSAKSI HARI INI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total_transactions
    FROM sales
    WHERE sale_date >= :start_date
      AND sale_date < :end_date
      AND status = 'COMPLETED'
      AND store_id = :store_id_transactions
");

$stmt->execute([
    ':start_date' => $today . ' 00:00:00',
    ':end_date' => date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00',
    ':store_id_transactions' => $storeId,
]);

$todayTransactions = (int) ($stmt->fetch()['total_transactions'] ?? 0);


/*
|--------------------------------------------------------------------------
| KEUNTUNGAN HARI INI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(si.profit), 0) AS total_profit
    FROM sale_items si
    INNER JOIN sales s
        ON s.id = si.sale_id
    WHERE s.sale_date >= :start_date
      AND s.sale_date < :end_date
      AND s.status = 'COMPLETED'
      AND s.store_id = :store_id_profit
");

$stmt->execute([
    ':start_date' => $today . ' 00:00:00',
    ':end_date'   => date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00',
    ':store_id_profit' => $storeId,
]);

$todayProfit = (float) ($stmt->fetch()['total_profit'] ?? 0);


/*
|--------------------------------------------------------------------------
| PENGELUARAN HARI INI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total_expense
    FROM expenses
    WHERE expense_date >= :start_date
      AND store_id = :store_id_expense
      AND expense_date < :end_date
");

$stmt->execute([
    ':start_date' => $today . ' 00:00:00',
    ':end_date'   => date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00',
    ':store_id_expense' => $storeId,
]);

$todayExpense = (float) ($stmt->fetch()['total_expense'] ?? 0);


/*
|--------------------------------------------------------------------------
| BELUM DIBAYAR KE PENITIP
|
| Total bagian penitip dari penjualan TITIPAN
| dikurangi pembayaran/settlement yang sudah dilakukan.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(si.consignor_amount), 0) AS total_consignor
    FROM sale_items si
    INNER JOIN sales s
        ON s.id = si.sale_id
    INNER JOIN products p
        ON p.id = si.product_id
    WHERE p.product_type = 'TITIPAN'
      AND s.status = 'COMPLETED'
      AND s.store_id = :store_id_consignor_sales
      AND p.store_id = :store_id_consignor_products
");

$stmt->execute([
    ':store_id_consignor_sales' => $storeId,
    ':store_id_consignor_products' => $storeId,
]);

$totalConsignor = (float) ($stmt->fetch()['total_consignor'] ?? 0);


$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(csi.amount), 0) AS total_settled
    FROM consignor_settlement_items csi
    INNER JOIN consignor_settlements cs
        ON cs.id = csi.settlement_id
    WHERE cs.store_id = :store_id_settlement
");

$stmt->execute([
    ':store_id_settlement' => $storeId,
]);

$totalSettled = (float) ($stmt->fetch()['total_settled'] ?? 0);

$unpaidConsignor = max(0, $totalConsignor - $totalSettled);


/*
|--------------------------------------------------------------------------
| BARANG HAMPIR HABIS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.sku,
        p.current_stock,
        p.minimum_stock,
        p.unit
    FROM products p
    WHERE p.status = 'ACTIVE'
      AND p.current_stock <= p.minimum_stock
      AND p.store_id = :store_id_low_stock
    ORDER BY p.current_stock ASC, p.name ASC
    LIMIT 8
");

$stmt->execute([
    ':store_id_low_stock' => $storeId,
]);

$lowStockProducts = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| PENJUALAN TERBARU
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.invoice_number,
        s.sale_date,
        s.total_amount,
        s.payment_method,
        s.status,
        COUNT(si.id) AS total_item
    FROM sales s
    LEFT JOIN sale_items si
        ON si.sale_id = s.id
    WHERE s.store_id = :store_id_recent_sales
    GROUP BY
        s.id,
        s.invoice_number,
        s.sale_date,
        s.total_amount,
        s.payment_method,
        s.status
    ORDER BY s.sale_date DESC, s.id DESC
    LIMIT 5
");

$stmt->execute([
    ':store_id_recent_sales' => $storeId,
]);

$recentSales = $stmt->fetchAll();


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


require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

?>

<div class="main-content">

    <!-- Mobile Header -->
<!-- Main -->
    <?php require_once __DIR__ . '/includes/mobile_dashboard.php'; ?>

<main class="desktop-dashboard p-4 md:p-6 lg:p-8">

        <!-- Header -->
        <div class="mb-8">

            <div class="flex items-center gap-2">

                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                    Selamat datang,
                    <?= e($_SESSION['user_name'] ?? 'Admin') ?>
                </h1>

                <i
                    data-lucide="sparkles"
                    class="w-6 h-6 text-neutral-400"
                ></i>

            </div>

            <p class="mt-2 text-sm text-neutral-500">
                Ringkasan kondisi toko hari ini.
            </p>

        </div>


        <!-- Statistik -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">


            <!-- PENJUALAN -->
            <div class="bento-card p-5">

                <div class="flex items-center justify-between mb-5">

                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center">
                        <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                    </div>

                    <span class="text-xs text-neutral-400">
                        Hari ini
                    </span>

                </div>

                <p class="text-sm text-neutral-500">
                    Penjualan Hari Ini
                </p>

                <h2 class="text-2xl font-semibold mt-1">
                    <?= rupiah($todaySales) ?>
                </h2>

            </div>


            <!-- KEUNTUNGAN -->
            <div class="bento-card p-5">

                <div class="flex items-center justify-between mb-5">

                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center">
                        <i data-lucide="trending-up" class="w-5 h-5"></i>
                    </div>

                    <span class="text-xs text-neutral-400">
                        Hari ini
                    </span>

                </div>

                <p class="text-sm text-neutral-500">
                    Keuntungan Hari Ini
                </p>

                <h2 class="text-2xl font-semibold mt-1">
                    <?= rupiah($todayProfit) ?>
                </h2>

            </div>


            <!-- PENGELUARAN -->
            <div class="bento-card p-5">

                <div class="flex items-center justify-between mb-5">

                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center">
                        <i data-lucide="wallet" class="w-5 h-5"></i>
                    </div>

                    <span class="text-xs text-neutral-400">
                        Hari ini
                    </span>

                </div>

                <p class="text-sm text-neutral-500">
                    Pengeluaran Hari Ini
                </p>

                <h2 class="text-2xl font-semibold mt-1">
                    <?= rupiah($todayExpense) ?>
                </h2>

            </div>


            <!-- PENITIP -->
            <div class="bento-card p-5">

                <div class="flex items-center justify-between mb-5">

                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center">
                        <i data-lucide="hand-coins" class="w-5 h-5"></i>
                    </div>

                    <span class="text-xs text-neutral-400">
                        Titipan
                    </span>

                </div>

                <p class="text-sm text-neutral-500">
                    Belum Dibayar ke Penitip
                </p>

                <h2 class="text-2xl font-semibold mt-1">
                    <?= rupiah($unpaidConsignor) ?>
                </h2>

            </div>

        </div>


        <!-- Bento Content -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">


            <!-- STOK -->
            <section class="bento-card p-6">

                <div class="flex items-center justify-between mb-5">

                    <div>

                        <h2 class="font-semibold text-lg">
                            Barang Hampir Habis
                        </h2>

                        <p class="text-sm text-neutral-500 mt-1">
                            Barang yang perlu segera dibeli.
                        </p>

                    </div>

                    <a
                        href="/pages/barang/"
                        class="inline-flex items-center gap-1 text-sm font-medium text-neutral-700 hover:text-neutral-950"
                    >
                        <span>Lihat semua</span>

                        <i
                            data-lucide="arrow-up-right"
                            class="w-4 h-4"
                        ></i>

                    </a>

                </div>


                <?php if (empty($lowStockProducts)): ?>

                    <div class="py-10 text-center">

                        <div class="w-10 h-10 mx-auto rounded-xl bg-neutral-100 flex items-center justify-center mb-3">
                            <i
                                data-lucide="package-check"
                                class="w-5 h-5 text-neutral-400"
                            ></i>
                        </div>

                        <p class="text-sm text-neutral-400">
                            Belum ada barang yang hampir habis.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="divide-y divide-neutral-100">

                        <?php foreach ($lowStockProducts as $product): ?>

                            <a
                                href="/pages/barang/view.php?id=<?= (int) $product['id'] ?>"
                                class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0 hover:bg-neutral-50 rounded-xl px-2 -mx-2 transition"
                            >

                                <div class="min-w-0">

                                    <div class="text-sm font-medium text-neutral-900 truncate">
                                        <?= e($product['name']) ?>
                                    </div>

                                    <?php if (!empty($product['sku'])): ?>

                                        <div class="text-xs text-neutral-400 mt-1">
                                            SKU <?= e($product['sku']) ?>
                                        </div>

                                    <?php endif; ?>

                                </div>


                                <div class="text-right flex-shrink-0">

                                    <div class="text-sm font-semibold text-neutral-900">
                                        <?= (int) $product['current_stock'] ?>
                                        <?= e($product['unit']) ?>
                                    </div>

                                    <div class="text-xs text-neutral-400 mt-1">
                                        Min. <?= (int) $product['minimum_stock'] ?>
                                    </div>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>


            <!-- PENJUALAN TERBARU -->
            <section class="bento-card p-6">

                <div class="flex items-center justify-between mb-5">

                    <div>

                        <h2 class="font-semibold text-lg">
                            Penjualan Terbaru
                        </h2>

                        <p class="text-sm text-neutral-500 mt-1">
                            Transaksi penjualan terakhir.
                        </p>

                    </div>

                    <a
                        href="/pages/penjualan/"
                        class="inline-flex items-center gap-1 text-sm font-medium text-neutral-700 hover:text-neutral-950"
                    >
                        <span>Lihat semua</span>

                        <i
                            data-lucide="arrow-up-right"
                            class="w-4 h-4"
                        ></i>

                    </a>

                </div>


                <?php if (empty($recentSales)): ?>

                    <div class="py-10 text-center">

                        <div class="w-10 h-10 mx-auto rounded-xl bg-neutral-100 flex items-center justify-center mb-3">
                            <i
                                data-lucide="receipt"
                                class="w-5 h-5 text-neutral-400"
                            ></i>
                        </div>

                        <p class="text-sm text-neutral-400">
                            Belum ada transaksi penjualan.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="divide-y divide-neutral-100">

                        <?php foreach ($recentSales as $sale): ?>

                            <a
                                href="/pages/penjualan/view.php?id=<?= (int) $sale['id'] ?>"
                                class="block py-3 first:pt-0 last:pb-0 hover:bg-neutral-50 rounded-xl px-2 -mx-2 transition"
                            >

                                <div class="flex items-center justify-between gap-4">

                                    <div class="min-w-0">

                                        <div class="text-sm font-medium text-neutral-900">
                                            <?= e($sale['invoice_number']) ?>
                                        </div>

                                        <div class="text-xs text-neutral-400 mt-1">

                                            <?= date('d/m/Y H:i', strtotime($sale['sale_date'])) ?>

                                            ·

                                            <?= (int) $sale['total_item'] ?>
                                            <?= ((int) $sale['total_item'] === 1) ? 'jenis' : 'jenis' ?>

                                            ·

                                            <?= e(
                                                $paymentLabels[$sale['payment_method']]
                                                ?? $sale['payment_method']
                                            ) ?>

                                        </div>

                                    </div>


                                    <div class="text-right flex-shrink-0">

                                        <div class="text-sm font-semibold text-neutral-900">
                                            <?= rupiah($sale['total_amount']) ?>
                                        </div>


                                        <?php if ($sale['status'] === 'COMPLETED'): ?>

                                            <div class="text-xs text-green-600 mt-1">
                                                Selesai
                                            </div>

                                        <?php else: ?>

                                            <div class="text-xs text-neutral-400 mt-1">
                                                Dibatalkan
                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>

</div>


<?php
require_once __DIR__ . '/includes/footer.php';
?>