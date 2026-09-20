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
        href="/assets/css/app.css?v=20260920"
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

    <a
        href="/index.php"
        class="mobile-header-brand"
        aria-label="RESTOCK"
    >
        <img
            src="/assets/images/logo.png"
            alt="RESTOCK"
        >
        <span>RE-STOCK</span>
    </a>

    <div class="mobile-header-title">
        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
    </div>

    <a
        href="/stores.php"
        class="mobile-header-store"
        aria-label="Toko Saya"
        title="Toko Saya"
    >
        <i data-lucide="store"></i>
    </a>

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
