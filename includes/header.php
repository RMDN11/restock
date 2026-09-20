<?php

$pageTitle = $pageTitle ?? 'Dashboard';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#ffffff"
    >

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link
        rel="icon"
        type="image/png"
        href="/assets/images/logo.png"
    >

    <link
        rel="stylesheet"
        href="/assets/css/app.css"
    >

    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://unpkg.com/lucide@latest"></script>

    <script src="/assets/js/app.js" defer></script>

</head>

<body>

<!-- =======================================================
     MOBILE HEADER
     ======================================================= -->

<header class="restock-mobile-header">

    <button
        type="button"
        id="mobileMenuToggle"
        class="mobile-menu-button"
        aria-label="Buka menu"
        aria-controls="sidebar"
        aria-expanded="false"
    >
        <i data-lucide="menu"></i>
    </button>

    <a
        href="/index.php"
        class="mobile-header-logo"
        aria-label="RESTOCK"
    >
        <img
            src="/assets/images/logo.png"
            alt="RESTOCK"
        >
    </a>

    <div class="mobile-header-cart">

        <a
            href="/pages/penjualan/create.php"
            title="Tambah Penjualan"
            aria-label="Tambah Penjualan"
        >
            <i data-lucide="shopping-cart"></i>
        </a>

    </div>


<!-- =======================================================
     MOBILE BOTTOM APP NAVIGATION
     ======================================================= -->

<?php
$mobileNavPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$mobileNavIsHome = $mobileNavPath === '/' || $mobileNavPath === '/index.php';
$mobileNavIsProducts = strpos($mobileNavPath, '/pages/barang/') === 0;
$mobileNavIsSales = strpos($mobileNavPath, '/pages/penjualan/') === 0;
$mobileNavIsAccount = strpos($mobileNavPath, '/pages/pengaturan/') === 0
    || $mobileNavPath === '/stores.php';
?>

<nav class="mobile-app-bottom-nav" aria-label="Navigasi aplikasi">

    <a
        href="/index.php"
        class="mobile-app-nav-item <?= $mobileNavIsHome ? 'active' : '' ?>"
        <?= $mobileNavIsHome ? 'aria-current="page"' : '' ?>
    >
        <i data-lucide="house"></i>
        <span>Beranda</span>
    </a>

    <a
        href="/pages/barang/"
        class="mobile-app-nav-item <?= $mobileNavIsProducts ? 'active' : '' ?>"
        <?= $mobileNavIsProducts ? 'aria-current="page"' : '' ?>
    >
        <i data-lucide="package"></i>
        <span>Produk</span>
    </a>

    <a
        href="/pages/penjualan/create.php"
        class="mobile-app-nav-primary"
        aria-label="Tambah penjualan"
        title="Tambah penjualan"
    >
        <span class="mobile-app-nav-primary-icon">
            <i data-lucide="shopping-cart"></i>
        </span>
        <span class="mobile-app-nav-primary-label">Penjualan Baru</span>
    </a>

    <a
        href="/pages/penjualan/"
        class="mobile-app-nav-item <?= $mobileNavIsSales ? 'active' : '' ?>"
        <?= $mobileNavIsSales ? 'aria-current="page"' : '' ?>
    >
        <i data-lucide="receipt"></i>
        <span>Penjualan</span>
    </a>

    <a
        href="/pages/pengaturan/"
        class="mobile-app-nav-item <?= $mobileNavIsAccount ? 'active' : '' ?>"
        <?= $mobileNavIsAccount ? 'aria-current="page"' : '' ?>
    >
        <i data-lucide="user-round"></i>
        <span>Akun</span>
    </a>

</nav>
</header>

<!-- =======================================================
     DESKTOP GLOBAL ACTION
     ======================================================= -->

<div class="global-header-action">

    <a
        href="/pages/penjualan/create.php"
        title="Tambah Penjualan"
        aria-label="Tambah Penjualan"
    >
        <i data-lucide="shopping-cart"></i>
    </a>

</div>
