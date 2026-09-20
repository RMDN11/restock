<?php
declare(strict_types=1);
?>

<main class="mobile-dashboard px-4 pt-5">

    <section class="mobile-dashboard-welcome">
        <p class="text-xs font-semibold uppercase tracking-[.16em] text-neutral-400">Dashboard</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">
            Selamat datang, <?= e($_SESSION['user_name'] ?? 'Admin') ?> 👋
        </h1>
        <p class="mt-1 text-sm text-neutral-500">Ringkasan kondisi toko hari ini.</p>
    </section>

    <section class="mobile-dashboard-primary-stat mt-5">
        <div class="mobile-dashboard-stat-top">
            <div class="mobile-dashboard-stat-icon"><i data-lucide="shopping-bag"></i></div>
            <span>Hari ini</span>
        </div>
        <p class="mt-5 text-sm text-neutral-500">Penjualan Hari Ini</p>
        <div class="mt-1 flex items-end justify-between gap-4">
            <h2 class="text-3xl font-semibold tracking-tight"><?= rupiah($todaySales) ?></h2>
            <span class="mobile-dashboard-stat-meta"><?= $todayTransactions ?> transaksi</span>
        </div>
    </section>

    <section class="grid grid-cols-2 gap-3 mt-3">
        <div class="mobile-dashboard-mini-stat">
            <div class="mobile-dashboard-mini-icon bg-emerald-50 text-emerald-700"><i data-lucide="trending-up"></i></div>
            <p>Keuntungan</p>
            <strong><?= rupiah($todayProfit) ?></strong>
        </div>
        <div class="mobile-dashboard-mini-stat">
            <div class="mobile-dashboard-mini-icon bg-rose-50 text-rose-700"><i data-lucide="wallet"></i></div>
            <p>Pengeluaran</p>
            <strong><?= rupiah($todayExpense) ?></strong>
        </div>
    </section>

    <section class="mt-7">
        <div class="mb-3">
            <h2 class="text-base font-semibold">Akses Cepat</h2>
            <p class="mt-1 text-xs text-neutral-400">Fitur yang paling sering digunakan.</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <a href="/pages/barang/" class="mobile-dashboard-quick-card quick-violet">
                <span class="mobile-dashboard-quick-icon"><i data-lucide="package"></i></span>
                <strong>Produk</strong><span>Kelola barang</span>
            </a>
            <a href="/pages/belanja/" class="mobile-dashboard-quick-card quick-blue">
                <span class="mobile-dashboard-quick-icon"><i data-lucide="shopping-cart"></i></span>
                <strong>Belanja</strong><span>Stok masuk</span>
            </a>
            <a href="/pages/pengeluaran/" class="mobile-dashboard-quick-card quick-rose">
                <span class="mobile-dashboard-quick-icon"><i data-lucide="wallet"></i></span>
                <strong>Pengeluaran</strong><span>Catat biaya</span>
            </a>
            <a href="/pages/laporan/" class="mobile-dashboard-quick-card quick-cyan">
                <span class="mobile-dashboard-quick-icon"><i data-lucide="chart-no-axes-combined"></i></span>
                <strong>Laporan</strong><span>Lihat ringkasan</span>
            </a>
        </div>
    </section>

    <section class="mt-7">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-base font-semibold">Barang Hampir Habis</h2>
                <p class="mt-1 text-xs text-neutral-400">Perlu segera dibeli.</p>
            </div>
            <a href="/pages/barang/" class="text-xs font-semibold text-neutral-600">Lihat semua</a>
        </div>

        <div class="mobile-dashboard-list-card">
            <?php if (empty($lowStockProducts)): ?>
                <div class="py-8 text-center">
                    <div class="mobile-dashboard-empty-icon"><i data-lucide="package-check"></i></div>
                    <p class="mt-3 text-sm text-neutral-400">Semua stok masih aman.</p>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($lowStockProducts, 0, 4) as $product): ?>
                    <a href="/pages/barang/view.php?id=<?= (int) $product['id'] ?>" class="mobile-dashboard-list-row">
                        <span class="mobile-dashboard-list-icon"><i data-lucide="package"></i></span>
                        <span class="min-w-0 flex-1">
                            <strong><?= e($product['name']) ?></strong>
                            <small>Min. <?= (int) $product['minimum_stock'] ?> <?= e($product['unit']) ?></small>
                        </span>
                        <span class="mobile-dashboard-stock-danger"><?= (int) $product['current_stock'] ?> <?= e($product['unit']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="mt-7 pb-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-base font-semibold">Penjualan Terbaru</h2>
                <p class="mt-1 text-xs text-neutral-400">Transaksi terakhir.</p>
            </div>
            <a href="/pages/penjualan/" class="text-xs font-semibold text-neutral-600">Lihat semua</a>
        </div>

        <div class="mobile-dashboard-list-card">
            <?php if (empty($recentSales)): ?>
                <div class="py-8 text-center">
                    <div class="mobile-dashboard-empty-icon"><i data-lucide="receipt"></i></div>
                    <p class="mt-3 text-sm text-neutral-400">Belum ada transaksi.</p>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($recentSales, 0, 4) as $sale): ?>
                    <a href="/pages/penjualan/view.php?id=<?= (int) $sale['id'] ?>" class="mobile-dashboard-list-row">
                        <span class="mobile-dashboard-list-icon sales-icon"><i data-lucide="receipt"></i></span>
                        <span class="min-w-0 flex-1">
                            <strong><?= e($sale['invoice_number']) ?></strong>
                            <small><?= date('H:i', strtotime($sale['sale_date'])) ?> · <?= e($paymentLabels[$sale['payment_method']] ?? $sale['payment_method']) ?></small>
                        </span>
                        <span class="mobile-dashboard-sale-amount"><?= rupiah($sale['total_amount']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</main>
