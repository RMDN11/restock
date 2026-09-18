<?php

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$isDashboard   = $currentPath === '/' || $currentPath === '/index.php';
$isBarang      = strpos($currentPath, '/pages/barang/') === 0;
$isBelanja     = strpos($currentPath, '/pages/belanja/') === 0;
$isPenjualan   = strpos($currentPath, '/pages/penjualan/') === 0;
$isTitipan     = strpos($currentPath, '/pages/titipan/') === 0;
$isPengeluaran = strpos($currentPath, '/pages/pengeluaran/') === 0;
$isLaporan     = strpos($currentPath, '/pages/laporan/') === 0;
$isPengaturan  = strpos($currentPath, '/pages/pengaturan/') === 0;

?>

<aside id="sidebar" class="sidebar" aria-label="Navigasi utama">

    <div class="sidebar-inner">

        <!-- BRAND -->
        <div class="sidebar-brand">

            <a href="/index.php"
               class="sidebar-brand-inner"
               aria-label="RESTOCK">

                <img
                    src="/assets/images/logo.png"
                    alt="RESTOCK"
                    class="sidebar-logo"
                >

                <div class="sidebar-brand-copy">
                    <div class="sidebar-brand-title">
                        RE-STOCK
                    </div>

                    <div class="sidebar-brand-subtitle">
                        Kelola toko lebih mudah
                    </div>
                </div>

            </a>



        </div>

        <!-- MAIN MENU -->
        <nav class="sidebar-nav">

            <a
                href="/index.php"
                data-menu="dashboard"
                class="sidebar-link <?= $isDashboard ? 'active' : '' ?>"
                <?= $isDashboard ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="layout-dashboard"></i>
                <span>Dashboard</span>
            </a>

            <a
                href="/pages/barang/"
                data-menu="produk"
                class="sidebar-link <?= $isBarang ? 'active' : '' ?>"
                <?= $isBarang ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="package"></i>
                <span>Produk</span>
            </a>

            <a
                href="/pages/belanja/"
                data-menu="belanja"
                class="sidebar-link <?= $isBelanja ? 'active' : '' ?>"
                <?= $isBelanja ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="shopping-cart"></i>
                <span>Belanja</span>
            </a>

            <a
                href="/pages/penjualan/"
                data-menu="penjualan"
                class="sidebar-link <?= $isPenjualan ? 'active' : '' ?>"
                <?= $isPenjualan ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="receipt"></i>
                <span>Penjualan</span>
            </a>

            <a
                href="/pages/titipan/"
                data-menu="titipan"
                class="sidebar-link <?= $isTitipan ? 'active' : '' ?>"
                <?= $isTitipan ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="handshake"></i>
                <span>Barang Titipan</span>
            </a>

            <a
                href="/pages/pengeluaran/"
                data-menu="pengeluaran"
                class="sidebar-link <?= $isPengeluaran ? 'active' : '' ?>"
                <?= $isPengeluaran ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="wallet"></i>
                <span>Pengeluaran</span>
            </a>

            <a
                href="/pages/laporan/"
                data-menu="laporan"
                class="sidebar-link <?= $isLaporan ? 'active' : '' ?>"
                <?= $isLaporan ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="chart-no-axes-combined"></i>
                <span>Laporan</span>
            </a>

        </nav>

        <!-- BOTTOM MENU -->
        <div class="sidebar-bottom">

            <a
                href="/pages/pengaturan/"
                data-menu="pengaturan"
                class="sidebar-link <?= $isPengaturan ? 'active' : '' ?>"
                <?= $isPengaturan ? 'aria-current="page"' : '' ?>
            >
                <i data-lucide="settings"></i>
                <span>Pengaturan</span>
            </a>

            <a
                href="/logout.php"
                class="sidebar-link logout-link"
            >
                <i data-lucide="log-out"></i>
                <span>Keluar</span>
            </a>

        </div>

    </div>

</aside>

<div
    id="sidebarOverlay"
    class="sidebar-overlay"
    aria-hidden="true"
></div>
