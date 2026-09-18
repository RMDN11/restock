<?php

/*
|--------------------------------------------------------------------------
| LAPORAN
|--------------------------------------------------------------------------
| Semua data laporan diambil dari laporan-data.php
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'laporan-data.php';

$pageTitle = 'Laporan';

include '../../includes/header.php';
include '../../includes/sidebar.php';

?>

<div class="main-content">

    <!-- =====================================================
         MOBILE HEADER
    ====================================================== -->

<!-- =====================================================
         CONTENT
    ====================================================== -->

    <main class="p-4 md:p-8">

        <!-- =================================================
             HEADER
        ================================================== -->

        <div
            class="
                flex
                flex-col
                md:flex-row
                md:items-end
                md:justify-between
                gap-5
                mb-8
            "
        >

            <div>

                <h1
                    class="
                        text-2xl
                        md:text-3xl
                        font-semibold
                        tracking-tight
                        text-neutral-900
                    "
                >
                    Laporan
                </h1>

                <p
                    class="
                        text-sm
                        text-neutral-500
                        mt-2
                    "
                >
                    Ringkasan kondisi toko berdasarkan periode.
                </p>

            </div>


            <!-- ACTION -->

            <div
                class="
                    flex
                    flex-col
                    sm:flex-row
                    gap-2
                    md:mt-8
                "
            >

                <!-- EXCEL -->

                <a
                    href="/pages/laporan/excel.php?period=<?= urlencode($period) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
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
                        transition
                    "
                >
                    <i
                        data-lucide="file-spreadsheet"
                        class="w-4 h-4"
                    ></i>

                    Excel
                </a>


                <!-- PDF -->

                <a
                    href="/pages/laporan/pdf.php?period=<?= urlencode($period) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
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

                    PDF
                </a>

            </div>

        </div>


        <!-- =================================================
             PERIODE
        ================================================== -->

        <div class="bento-card p-4 mb-6">

            <form
                method="GET"
                class="
                    flex
                    flex-col
                    lg:flex-row
                    lg:items-end
                    gap-3
                "
            >

                <div class="flex-1">

                    <div
                        class="
                            text-xs
                            text-neutral-400
                            mb-2
                        "
                    >
                        PERIODE
                    </div>


                    <div
                        class="
                            grid
                            grid-cols-2
                            sm:grid-cols-4
                            gap-2
                        "
                    >

                        <!-- TODAY -->

                        <button
                            type="submit"
                            name="period"
                            value="today"
                            class="
                                px-4
                                py-2.5
                                rounded-xl
                                text-sm
                                font-medium
                                border
                                <?= $period === 'today'
                                    ? 'bg-neutral-900 text-white border-neutral-900'
                                    : 'bg-white text-neutral-600 border-neutral-200 hover:bg-neutral-50'
                                ?>
                            "
                        >
                            Hari Ini
                        </button>


                        <!-- WEEK -->

                        <button
                            type="submit"
                            name="period"
                            value="week"
                            class="
                                px-4
                                py-2.5
                                rounded-xl
                                text-sm
                                font-medium
                                border
                                <?= $period === 'week'
                                    ? 'bg-neutral-900 text-white border-neutral-900'
                                    : 'bg-white text-neutral-600 border-neutral-200 hover:bg-neutral-50'
                                ?>
                            "
                        >
                            Minggu Ini
                        </button>


                        <!-- MONTH -->

                        <button
                            type="submit"
                            name="period"
                            value="month"
                            class="
                                px-4
                                py-2.5
                                rounded-xl
                                text-sm
                                font-medium
                                border
                                <?= $period === 'month'
                                    ? 'bg-neutral-900 text-white border-neutral-900'
                                    : 'bg-white text-neutral-600 border-neutral-200 hover:bg-neutral-50'
                                ?>
                            "
                        >
                            Bulan Ini
                        </button>


                        <!-- CUSTOM -->

                        <button
                            type="button"
                            onclick="document.getElementById('customPeriod').classList.toggle('hidden')"
                            class="
                                px-4
                                py-2.5
                                rounded-xl
                                text-sm
                                font-medium
                                border
                                <?= $period === 'custom'
                                    ? 'bg-neutral-900 text-white border-neutral-900'
                                    : 'bg-white text-neutral-600 border-neutral-200 hover:bg-neutral-50'
                                ?>
                            "
                        >
                            Custom
                        </button>

                    </div>

                </div>


                <!-- =================================================
                     CUSTOM DATE
                ================================================== -->

                <div
                    id="customPeriod"
                    class="
                        <?= $period === 'custom'
                            ? ''
                            : 'hidden'
                        ?>
                        grid
                        grid-cols-1
                        sm:grid-cols-3
                        gap-2
                        lg:w-auto
                    "
                >

                    <div>

                        <label
                            class="
                                block
                                text-xs
                                text-neutral-400
                                mb-2
                            "
                        >
                            Dari
                        </label>

                        <input
                            type="date"
                            name="date_from"
                            value="<?= e($dateFrom) ?>"
                            class="
                                w-full
                                px-4
                                py-2.5
                                rounded-xl
                                border
                                border-neutral-200
                                text-sm
                            "
                        >

                    </div>


                    <div>

                        <label
                            class="
                                block
                                text-xs
                                text-neutral-400
                                mb-2
                            "
                        >
                            Sampai
                        </label>

                        <input
                            type="date"
                            name="date_to"
                            value="<?= e($dateTo) ?>"
                            class="
                                w-full
                                px-4
                                py-2.5
                                rounded-xl
                                border
                                border-neutral-200
                                text-sm
                            "
                        >

                    </div>


                    <div class="flex items-end">

                        <button
                            type="submit"
                            name="period"
                            value="custom"
                            class="
                                w-full
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
                            Terapkan
                        </button>

                    </div>

                </div>

            </form>


            <!-- PERIOD LABEL -->

            <div
                class="
                    text-xs
                    text-neutral-400
                    mt-3
                "
            >
                Periode:

                <span class="text-neutral-600 font-medium">
                    <?= e($periodLabel) ?>
                </span>
            </div>

        </div>


        <!-- =================================================
             FINANCIAL SUMMARY
        ================================================== -->

        <div
            class="
                grid
                grid-cols-1
                sm:grid-cols-2
                lg:grid-cols-4
                gap-4
                mb-6
            "
        >

            <!-- OMZET -->

            <div class="bento-card p-5">

                <div class="text-sm text-neutral-500">
                    Omzet
                </div>

                <div
                    class="
                        text-2xl
                        font-semibold
                        tracking-tight
                        mt-2
                    "
                >
                    <?= rupiah($omzet) ?>
                </div>

                <div
                    class="
                        text-xs
                        text-neutral-400
                        mt-1
                    "
                >
                    <?= $totalTransactions ?>
                    transaksi
                </div>

            </div>


            <!-- LABA KOTOR -->

            <div class="bento-card p-5">

                <div class="text-sm text-neutral-500">
                    Laba Kotor
                </div>

                <div
                    class="
                        text-2xl
                        font-semibold
                        tracking-tight
                        mt-2
                    "
                >
                    <?= rupiah($labaKotor) ?>
                </div>

                <div
                    class="
                        text-xs
                        text-neutral-400
                        mt-1
                    "
                >
                    Margin
                    <?= number_format(
                        $margin,
                        1,
                        ',',
                        '.'
                    ) ?>%
                </div>

            </div>


            <!-- OPERASIONAL -->

            <div class="bento-card p-5">

                <div class="text-sm text-neutral-500">
                    Pengeluaran Operasional
                </div>

                <div
                    class="
                        text-2xl
                        font-semibold
                        tracking-tight
                        mt-2
                    "
                >
                    <?= rupiah(
                        $pengeluaranOperasional
                    ) ?>
                </div>

                <div
                    class="
                        text-xs
                        text-neutral-400
                        mt-1
                    "
                >
                    Tidak termasuk pembayaran penitip
                </div>

            </div>


            <!-- PEMBAYARAN PENITIP -->

            <div class="bento-card p-5">

                <div class="text-sm text-neutral-500">
                    Pembayaran Penitip
                </div>

                <div
                    class="
                        text-2xl
                        font-semibold
                        tracking-tight
                        mt-2
                    "
                >
                    <?= rupiah(
                        $pembayaranPenitip
                    ) ?>
                </div>

                <div
                    class="
                        text-xs
                        text-neutral-400
                        mt-1
                    "
                >
                    <?= $settlementTransactions ?>
                    pembayaran
                </div>

            </div>

        </div>


        <!-- =================================================
             SECONDARY SUMMARY
        ================================================== -->

        <div
            class="
                grid
                grid-cols-1
                sm:grid-cols-3
                gap-4
                mb-6
            "
        >

            <!-- HPP -->

            <div class="bento-card p-5">

                <div class="text-sm text-neutral-500">
                    HPP
                </div>

                <div
                    class="
                        text-xl
                        font-semibold
                        mt-2
                    "
                >
                    <?= rupiah($hpp) ?>
                </div>

            </div>


            <!-- LABA BERSIH -->

            <div class="bento-card p-5">

                <div class="text-sm text-neutral-500">
                    Laba Bersih
                </div>

                <div
                    class="
                        text-xl
                        font-semibold
                        mt-2
                    "
                >
                    <?= rupiah($labaBersih) ?>
                </div>

                <div
                    class="
                        text-xs
                        text-neutral-400
                        mt-1
                    "
                >
                    Laba kotor - pengeluaran operasional
                </div>

            </div>


            <!-- TOTAL UANG KELUAR -->

            <div class="bento-card p-5">

                <div class="text-sm text-neutral-500">
                    Total Uang Keluar
                </div>

                <div
                    class="
                        text-xl
                        font-semibold
                        mt-2
                    "
                >
                    <?= rupiah($totalUangKeluar) ?>
                </div>

                <div
                    class="
                        text-xs
                        text-neutral-400
                        mt-1
                    "
                >
                    Operasional + pembayaran penitip
                </div>

            </div>

        </div>


        <!-- =================================================
             PENJUALAN TOKO / TITIPAN
        ================================================== -->

        <div
            class="
                grid
                grid-cols-1
                lg:grid-cols-2
                gap-4
                mb-6
            "
        >

            <!-- TOKO -->

            <div class="bento-card p-6">

                <div
                    class="
                        flex
                        items-center
                        justify-between
                        gap-3
                        mb-5
                    "
                >

                    <div>

                        <h2 class="font-semibold">
                            Produk Toko
                        </h2>

                        <p
                            class="
                                text-xs
                                text-neutral-400
                                mt-1
                            "
                        >
                            Penjualan barang milik toko.
                        </p>

                    </div>

                    <div
                        class="
                            w-10
                            h-10
                            rounded-xl
                            bg-neutral-100
                            flex
                            items-center
                            justify-center
                        "
                    >
                        <i
                            data-lucide="store"
                            class="w-5 h-5"
                        ></i>
                    </div>

                </div>


                <div
                    class="
                        grid
                        grid-cols-2
                        gap-4
                    "
                >

                    <div>

                        <div
                            class="
                                text-xs
                                text-neutral-400
                            "
                        >
                            Penjualan
                        </div>

                        <div
                            class="
                                text-lg
                                font-semibold
                                mt-1
                            "
                        >
                            <?= rupiah(
                                $penjualanToko['sales']
                            ) ?>
                        </div>

                    </div>


                    <div>

                        <div
                            class="
                                text-xs
                                text-neutral-400
                            "
                        >
                            Laba
                        </div>

                        <div
                            class="
                                text-lg
                                font-semibold
                                mt-1
                            "
                        >
                            <?= rupiah(
                                $penjualanToko['profit']
                            ) ?>
                        </div>

                    </div>

                </div>

            </div>


            <!-- TITIPAN -->

            <div class="bento-card p-6">

                <div
                    class="
                        flex
                        items-center
                        justify-between
                        gap-3
                        mb-5
                    "
                >

                    <div>

                        <h2 class="font-semibold">
                            Barang Titipan
                        </h2>

                        <p
                            class="
                                text-xs
                                text-neutral-400
                                mt-1
                            "
                        >
                            Penjualan barang milik penitip.
                        </p>

                    </div>

                    <div
                        class="
                            w-10
                            h-10
                            rounded-xl
                            bg-neutral-100
                            flex
                            items-center
                            justify-center
                        "
                    >
                        <i
                            data-lucide="handshake"
                            class="w-5 h-5"
                        ></i>
                    </div>

                </div>


                <div
                    class="
                        grid
                        grid-cols-2
                        gap-4
                    "
                >

                    <div>

                        <div
                            class="
                                text-xs
                                text-neutral-400
                            "
                        >
                            Penjualan
                        </div>

                        <div
                            class="
                                text-lg
                                font-semibold
                                mt-1
                            "
                        >
                            <?= rupiah(
                                $penjualanTitipan['sales']
                            ) ?>
                        </div>

                    </div>


                    <div>

                        <div
                            class="
                                text-xs
                                text-neutral-400
                            "
                        >
                            Fee Toko
                        </div>

                        <div
                            class="
                                text-lg
                                font-semibold
                                mt-1
                            "
                        >
                            <?= rupiah(
                                $penjualanTitipan['profit']
                            ) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             TOP PRODUCTS + STOCK
        ================================================== -->

        <div
            class="
                grid
                grid-cols-1
                lg:grid-cols-2
                gap-4
                mb-6
            "
        >

            <!-- PRODUK TERLARIS -->

            <div class="bento-card overflow-hidden">

                <div
                    class="
                        p-5
                        border-b
                        border-neutral-100
                    "
                >

                    <h2 class="font-semibold">
                        Produk Terlaris
                    </h2>

                    <p
                        class="
                            text-xs
                            text-neutral-400
                            mt-1
                        "
                    >
                        Berdasarkan jumlah barang terjual.
                    </p>

                </div>


                <?php if (!$topProducts): ?>

                    <div
                        class="
                            p-10
                            text-center
                            text-sm
                            text-neutral-400
                        "
                    >
                        Belum ada penjualan pada periode ini.
                    </div>

                <?php else: ?>

                    <div
                        class="
                            divide-y
                            divide-neutral-100
                        "
                    >

                        <?php foreach (
                            $topProducts
                            as $index => $product
                        ): ?>

                            <div
                                class="
                                    p-5
                                    flex
                                    items-center
                                    gap-4
                                "
                            >

                                <div
                                    class="
                                        w-8
                                        h-8
                                        rounded-lg
                                        bg-neutral-100
                                        flex
                                        items-center
                                        justify-center
                                        text-xs
                                        font-semibold
                                        shrink-0
                                    "
                                >
                                    <?= $index + 1 ?>
                                </div>


                                <div
                                    class="
                                        flex-1
                                        min-w-0
                                    "
                                >

                                    <div
                                        class="
                                            text-sm
                                            font-medium
                                            truncate
                                        "
                                    >
                                        <?= e(
                                            $product['name']
                                        ) ?>
                                    </div>

                                    <div
                                        class="
                                            text-xs
                                            text-neutral-400
                                            mt-1
                                        "
                                    >
                                        <?= $product['product_type'] === 'TITIPAN'
                                            ? 'Titipan'
                                            : 'Produk Toko'
                                        ?>

                                        ·

                                        <?= (int) $product['total_qty'] ?>

                                        terjual
                                    </div>

                                </div>


                                <div
                                    class="
                                        text-right
                                        shrink-0
                                    "
                                >

                                    <div
                                        class="
                                            text-sm
                                            font-semibold
                                        "
                                    >
                                        <?= rupiah(
                                            $product['total_sales']
                                        ) ?>
                                    </div>

                                    <div
                                        class="
                                            text-xs
                                            text-neutral-400
                                            mt-1
                                        "
                                    >
                                        Laba
                                        <?= rupiah(
                                            $product['total_profit']
                                        ) ?>
                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>


            <!-- STOK -->

            <div class="bento-card p-6">

                <div
                    class="
                        flex
                        items-center
                        justify-between
                        gap-3
                        mb-6
                    "
                >

                    <div>

                        <h2 class="font-semibold">
                            Kondisi Stok
                        </h2>

                        <p
                            class="
                                text-xs
                                text-neutral-400
                                mt-1
                            "
                        >
                            Kondisi stok barang aktif saat ini.
                        </p>

                    </div>


                    <div
                        class="
                            w-10
                            h-10
                            rounded-xl
                            bg-neutral-100
                            flex
                            items-center
                            justify-center
                        "
                    >
                        <i
                            data-lucide="package"
                            class="w-5 h-5"
                        ></i>
                    </div>

                </div>


                <div
                    class="
                        grid
                        grid-cols-2
                        gap-4
                    "
                >

                    <!-- TOTAL BARANG -->

                    <div
                        class="
                            rounded-2xl
                            bg-neutral-50
                            p-4
                        "
                    >

                        <div
                            class="
                                text-xs
                                text-neutral-400
                            "
                        >
                            Total Barang
                        </div>

                        <div
                            class="
                                text-2xl
                                font-semibold
                                mt-2
                            "
                        >
                            <?= $totalProducts ?>
                        </div>

                    </div>


                    <!-- TOTAL STOK -->

                    <div
                        class="
                            rounded-2xl
                            bg-neutral-50
                            p-4
                        "
                    >

                        <div
                            class="
                                text-xs
                                text-neutral-400
                            "
                        >
                            Total Stok
                        </div>

                        <div
                            class="
                                text-2xl
                                font-semibold
                                mt-2
                            "
                        >
                            <?= $totalStock ?>
                        </div>

                    </div>


                    <!-- STOK MENIPIS -->

                    <div
                        class="
                            rounded-2xl
                            border
                            border-neutral-200
                            p-4
                        "
                    >

                        <div
                            class="
                                text-xs
                                text-neutral-400
                            "
                        >
                            Stok Menipis
                        </div>

                        <div
                            class="
                                text-2xl
                                font-semibold
                                mt-2
                            "
                        >
                            <?= $lowProducts ?>
                        </div>

                        <div
                            class="
                                text-xs
                                text-neutral-400
                                mt-1
                            "
                        >
                            ≤ batas minimum
                        </div>

                    </div>


                    <!-- HABIS -->

                    <div
                        class="
                            rounded-2xl
                            border
                            border-neutral-200
                            p-4
                        "
                    >

                        <div
                            class="
                                text-xs
                                text-neutral-400
                            "
                        >
                            Stok Habis
                        </div>

                        <div
                            class="
                                text-2xl
                                font-semibold
                                mt-2
                            "
                        >
                            <?= $emptyProducts ?>
                        </div>

                    </div>

                </div>


                <div
                    class="
                        mt-5
                        pt-5
                        border-t
                        border-neutral-100
                    "
                >

                    <div
                        class="
                            flex
                            items-center
                            justify-between
                            text-xs
                        "
                    >

                        <span class="text-neutral-400">
                            Barang dengan stok tersedia
                        </span>

                        <span class="font-medium">
                            <?= $availableProducts ?>
                        </span>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             PEMBAYARAN PENITIP + PENGELUARAN
        ================================================== -->

        <div
            class="
                grid
                grid-cols-1
                lg:grid-cols-2
                gap-4
            "
        >

            <!-- =================================================
                 PEMBAYARAN PENITIP
            ================================================== -->

            <div class="bento-card overflow-hidden">

                <div
                    class="
                        p-5
                        border-b
                        border-neutral-100
                        flex
                        items-center
                        justify-between
                        gap-3
                    "
                >

                    <div>

                        <h2 class="font-semibold">
                            Pembayaran Penitip
                        </h2>

                        <p
                            class="
                                text-xs
                                text-neutral-400
                                mt-1
                            "
                        >
                            Pembayaran pada periode ini.
                        </p>

                    </div>


                    <a
                        href="/pages/titipan/pembayaran.php"
                        class="
                            text-xs
                            font-medium
                            text-neutral-500
                            hover:text-neutral-900
                        "
                    >
                        Lihat semua
                    </a>

                </div>


                <?php if (!$settlements): ?>

                    <div
                        class="
                            p-10
                            text-center
                            text-sm
                            text-neutral-400
                        "
                    >
                        Belum ada pembayaran penitip.
                    </div>

                <?php else: ?>

                    <div
                        class="
                            divide-y
                            divide-neutral-100
                        "
                    >

                        <?php foreach (
                            $settlements
                            as $settlement
                        ): ?>

                            <div class="p-5">

                                <div
                                    class="
                                        flex
                                        items-center
                                        justify-between
                                        gap-4
                                    "
                                >

                                    <div
                                        class="
                                            min-w-0
                                        "
                                    >

                                        <div
                                            class="
                                                text-sm
                                                font-medium
                                                truncate
                                            "
                                        >
                                            <?= e(
                                                $settlement['consignor_name']
                                            ) ?>
                                        </div>

                                        <div
                                            class="
                                                text-xs
                                                text-neutral-400
                                                mt-1
                                            "
                                        >
                                            <?= e(
                                                $settlement['settlement_number']
                                            ) ?>

                                            ·

                                            <?= date(
                                                'd M Y H:i',
                                                strtotime(
                                                    $settlement['settlement_date']
                                                )
                                            ) ?>
                                        </div>

                                    </div>


                                    <div
                                        class="
                                            text-sm
                                            font-semibold
                                            whitespace-nowrap
                                        "
                                    >
                                        <?= rupiah(
                                            $settlement['total_amount']
                                        ) ?>
                                    </div>

                                </div>


                                <div
                                    class="
                                        flex
                                        items-center
                                        justify-between
                                        mt-3
                                    "
                                >

                                    <span
                                        class="
                                            text-xs
                                            text-neutral-400
                                        "
                                    >
                                        <?= e(
                                            $paymentLabels[
                                                $settlement['payment_method']
                                            ]
                                            ?? $settlement['payment_method']
                                        ) ?>
                                    </span>


                                    <div class="flex gap-2">

                                        <a
                                            href="/pages/titipan/pembayaran-view.php?id=<?= (int) $settlement['id'] ?>"
                                            class="
                                                text-xs
                                                text-neutral-500
                                                hover:text-neutral-900
                                            "
                                        >
                                            Detail
                                        </a>


                                        <a
                                            href="/pages/titipan/invoice.php?id=<?= (int) $settlement['id'] ?>"
                                            target="_blank"
                                            class="
                                                text-xs
                                                text-neutral-500
                                                hover:text-neutral-900
                                            "
                                        >
                                            Invoice
                                        </a>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 PENGELUARAN
            ================================================== -->

            <div class="bento-card overflow-hidden">

                <div
                    class="
                        p-5
                        border-b
                        border-neutral-100
                        flex
                        items-center
                        justify-between
                        gap-3
                    "
                >

                    <div>

                        <h2 class="font-semibold">
                            Pengeluaran
                        </h2>

                        <p
                            class="
                                text-xs
                                text-neutral-400
                                mt-1
                            "
                        >
                            Pengeluaran operasional terbaru.
                        </p>

                    </div>


                    <a
                        href="/pages/pengeluaran/"
                        class="
                            text-xs
                            font-medium
                            text-neutral-500
                            hover:text-neutral-900
                        "
                    >
                        Lihat semua
                    </a>

                </div>


                <?php if (!$recentExpenses): ?>

                    <div
                        class="
                            p-10
                            text-center
                            text-sm
                            text-neutral-400
                        "
                    >
                        Belum ada pengeluaran.
                    </div>

                <?php else: ?>

                    <div
                        class="
                            divide-y
                            divide-neutral-100
                        "
                    >

                        <?php foreach (
                            $recentExpenses
                            as $expense
                        ): ?>

                            <div
                                class="
                                    p-5
                                    flex
                                    items-center
                                    justify-between
                                    gap-4
                                "
                            >

                                <div
                                    class="
                                        min-w-0
                                    "
                                >

                                    <div
                                        class="
                                            text-sm
                                            font-medium
                                            truncate
                                        "
                                    >
                                        <?= e(
                                            $expense['description']
                                        ) ?>
                                    </div>


                                    <div
                                        class="
                                            text-xs
                                            text-neutral-400
                                            mt-1
                                        "
                                    >

                                        <?= date(
                                            'd M Y H:i',
                                            strtotime(
                                                $expense['expense_date']
                                            )
                                        ) ?>


                                        <?php if (
                                            !empty(
                                                $expense['category_name']
                                            )
                                        ): ?>

                                            ·

                                            <?= e(
                                                $expense['category_name']
                                            ) ?>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <div
                                    class="
                                        text-sm
                                        font-semibold
                                        whitespace-nowrap
                                    "
                                >
                                    <?= rupiah(
                                        $expense['amount']
                                    ) ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php include '../../includes/footer.php'; ?>