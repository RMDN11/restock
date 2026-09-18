<?php

/*
|--------------------------------------------------------------------------
| LAPORAN PDF
|--------------------------------------------------------------------------
| Menggunakan browser print.
| Tidak membutuhkan Composer / Dompdf.
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'laporan-data.php';

$pageTitle = 'Laporan PDF';

function formatRupiahPdf($value)
{
    return 'Rp ' . number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}

function formatTanggalPdf($date)
{
    if (!$date) {
        return '-';
    }

    return date(
        'd/m/Y H:i',
        strtotime($date)
    );
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Laporan RESTOCK - <?= htmlspecialchars($periodLabel) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            background: #e5e5e5;
            color: #171717;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 12px;

            padding: 14px 20px;

            background: #ffffff;
            border-bottom: 1px solid #e5e5e5;
        }

        .toolbar-title {
            font-size: 14px;
            font-weight: 700;
        }

        .toolbar-subtitle {
            margin-top: 2px;
            color: #737373;
            font-size: 11px;
        }

        .toolbar-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 9px 14px;

            border-radius: 8px;

            border: 1px solid #d4d4d4;

            background: #ffffff;

            color: #171717;

            font-size: 12px;
            font-weight: 600;

            text-decoration: none;

            cursor: pointer;
        }

        .btn-primary {
            background: #171717;
            color: #ffffff;
            border-color: #171717;
        }

        .page {
            width: 210mm;
            min-height: 297mm;

            margin: 24px auto;

            padding: 16mm;

            background: #ffffff;

            box-shadow:
                0 8px 30px rgba(0, 0, 0, .08);
        }

        .report-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;

            gap: 20px;

            padding-bottom: 18px;

            border-bottom: 2px solid #171717;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            display: block;
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .brand {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -.5px;
        }

        .brand-subtitle {
            margin-top: 3px;

            color: #737373;

            font-size: 10px;
        }

        .report-meta {
            text-align: right;
        }

        .report-title {
            font-size: 17px;
            font-weight: 700;
        }

        .report-period {
            margin-top: 4px;

            color: #525252;

            font-size: 11px;
        }

        .section {
            margin-top: 22px;
        }

        .section-title {
            margin-bottom: 10px;

            font-size: 13px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .summary-grid {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 8px;
        }

        .summary-card {
            padding: 12px;

            border: 1px solid #e5e5e5;

            border-radius: 8px;
        }

        .summary-label {
            color: #737373;

            font-size: 10px;
        }

        .summary-value {
            margin-top: 5px;

            font-size: 14px;
            font-weight: 700;
        }

        .summary-note {
            margin-top: 3px;

            color: #a3a3a3;

            font-size: 9px;
        }

        .two-column {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 12px;
        }

        .info-box {
            padding: 13px;

            border: 1px solid #e5e5e5;

            border-radius: 8px;
        }

        .info-box-title {
            margin-bottom: 10px;

            font-size: 12px;
            font-weight: 700;
        }

        .info-row {
            display: flex;

            justify-content: space-between;

            gap: 15px;

            padding: 6px 0;

            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: 0;
        }

        .info-label {
            color: #737373;
        }

        .info-value {
            font-weight: 600;
            text-align: right;
        }

        table {
            width: 100%;

            border-collapse: collapse;
        }

        th {
            padding: 8px;

            background: #f5f5f5;

            border-bottom: 1px solid #d4d4d4;

            color: #525252;

            font-size: 9px;

            text-align: left;

            text-transform: uppercase;
        }

        td {
            padding: 8px;

            border-bottom: 1px solid #eeeeee;

            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .muted {
            color: #737373;
        }

        .small {
            font-size: 10px;
        }

        .footer {
            margin-top: 30px;

            padding-top: 12px;

            border-top: 1px solid #e5e5e5;

            color: #a3a3a3;

            font-size: 9px;

            text-align: center;
        }

        .page-break {
            page-break-before: always;
        }

        @media print {

            body {
                background: #ffffff;
            }

            .toolbar {
                display: none;
            }

            .page {
                width: auto;
                min-height: auto;

                margin: 0;

                padding: 0;

                box-shadow: none;
            }

            .section {
                break-inside: avoid;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            @page {
                size: A4;
                margin: 12mm;
            }

        }

        @media screen and (max-width: 800px) {

            .toolbar {
                position: static;

                flex-direction: column;
                align-items: stretch;
            }

            .toolbar-actions {
                width: 100%;
            }

            .toolbar-actions .btn {
                flex: 1;
            }

            .page {
                width: calc(100% - 24px);

                min-height: auto;

                margin: 12px;

                padding: 20px;
            }

            .summary-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .two-column {
                grid-template-columns: 1fr;
            }

            .report-header {
                flex-direction: column;
            }

            .report-meta {
                text-align: left;
            }

        }

    </style>

</head>

<body>


<!-- =========================================================
     TOOLBAR
========================================================= -->

<div class="toolbar">

    <div>

        <div class="toolbar-title">
            RESTOCK
        </div>

        <div class="toolbar-subtitle">
            Laporan <?= htmlspecialchars($periodLabel) ?>
        </div>

    </div>


    <div class="toolbar-actions">

        <a
            href="/pages/laporan/"
            class="btn"
        >
            Kembali
        </a>

        <button
            type="button"
            onclick="window.print()"
            class="btn btn-primary"
        >
            Print / Simpan PDF
        </button>

    </div>

</div>


<!-- =========================================================
     A4 PAGE
========================================================= -->

<div class="page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="report-header">

        <div>

            <div class="brand">
                RESTOCK
            </div>

            <div class="brand-subtitle">
                Sistem pencatatan dan laporan toko
            </div>

        </div>


        <div class="report-meta">

            <div class="report-title">
                LAPORAN TOKO
            </div>

            <div class="report-period">
                <?= htmlspecialchars($periodLabel) ?>
            </div>

        </div>

    </div>


    <!-- =====================================================
         RINGKASAN KEUANGAN
    ====================================================== -->

    <div class="section">

        <div class="section-title">
            Ringkasan Keuangan
        </div>


        <div class="summary-grid">


            <!-- OMZET -->

            <div class="summary-card">

                <div class="summary-label">
                    Omzet
                </div>

                <div class="summary-value">
                    <?= formatRupiahPdf($omzet) ?>
                </div>

                <div class="summary-note">
                    <?= $totalTransactions ?>
                    transaksi
                </div>

            </div>


            <!-- HPP -->

            <div class="summary-card">

                <div class="summary-label">
                    HPP
                </div>

                <div class="summary-value">
                    <?= formatRupiahPdf($hpp) ?>
                </div>

            </div>


            <!-- LABA KOTOR -->

            <div class="summary-card">

                <div class="summary-label">
                    Laba Kotor
                </div>

                <div class="summary-value">
                    <?= formatRupiahPdf($labaKotor) ?>
                </div>

                <div class="summary-note">
                    Margin
                    <?= number_format(
                        $margin,
                        1,
                        ',',
                        '.'
                    ) ?>%
                </div>

            </div>


            <!-- LABA BERSIH -->

            <div class="summary-card">

                <div class="summary-label">
                    Laba Bersih
                </div>

                <div class="summary-value">
                    <?= formatRupiahPdf($labaBersih) ?>
                </div>

                <div class="summary-note">
                    Setelah pengeluaran operasional
                </div>

            </div>


        </div>

    </div>


    <!-- =====================================================
         ARUS UANG
    ====================================================== -->

    <div class="section">

        <div class="section-title">
            Arus Uang Keluar
        </div>


        <div class="two-column">


            <!-- OPERASIONAL -->

            <div class="info-box">

                <div class="info-box-title">
                    Pengeluaran Operasional
                </div>

                <div class="info-row">

                    <span class="info-label">
                        Total
                    </span>

                    <span class="info-value">
                        <?= formatRupiahPdf(
                            $pengeluaranOperasional
                        ) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Status
                    </span>

                    <span class="info-value">
                        Biaya operasional
                    </span>

                </div>

            </div>


            <!-- PENITIP -->

            <div class="info-box">

                <div class="info-box-title">
                    Pembayaran Penitip
                </div>

                <div class="info-row">

                    <span class="info-label">
                        Total
                    </span>

                    <span class="info-value">
                        <?= formatRupiahPdf(
                            $pembayaranPenitip
                        ) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Transaksi
                    </span>

                    <span class="info-value">
                        <?= $settlementTransactions ?>
                        pembayaran
                    </span>

                </div>

            </div>


        </div>


        <div
            class="info-box"
            style="margin-top: 12px;"
        >

            <div class="info-row">

                <span class="info-label">
                    Total Uang Keluar
                </span>

                <span class="info-value">
                    <?= formatRupiahPdf(
                        $totalUangKeluar
                    ) ?>
                </span>

            </div>

        </div>

    </div>


    <!-- =====================================================
         PENJUALAN TOKO VS TITIPAN
    ====================================================== -->

    <div class="section">

        <div class="section-title">
            Penjualan
        </div>


        <div class="two-column">


            <!-- TOKO -->

            <div class="info-box">

                <div class="info-box-title">
                    Produk Toko
                </div>

                <div class="info-row">

                    <span class="info-label">
                        Penjualan
                    </span>

                    <span class="info-value">
                        <?= formatRupiahPdf(
                            $penjualanToko['sales']
                        ) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Laba
                    </span>

                    <span class="info-value">
                        <?= formatRupiahPdf(
                            $penjualanToko['profit']
                        ) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Transaksi
                    </span>

                    <span class="info-value">
                        <?= (int)
                            $penjualanToko['transactions'] ?>
                    </span>

                </div>

            </div>


            <!-- TITIPAN -->

            <div class="info-box">

                <div class="info-box-title">
                    Barang Titipan
                </div>

                <div class="info-row">

                    <span class="info-label">
                        Penjualan
                    </span>

                    <span class="info-value">
                        <?= formatRupiahPdf(
                            $penjualanTitipan['sales']
                        ) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Fee Toko
                    </span>

                    <span class="info-value">
                        <?= formatRupiahPdf(
                            $penjualanTitipan['profit']
                        ) ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Transaksi
                    </span>

                    <span class="info-value">
                        <?= (int)
                            $penjualanTitipan['transactions'] ?>
                    </span>

                </div>

            </div>


        </div>

    </div>


    <!-- =====================================================
         PRODUK TERLARIS
    ====================================================== -->

    <div class="section">

        <div class="section-title">
            Produk Terlaris
        </div>


        <?php if (!$topProducts): ?>

            <div class="info-box muted">
                Belum ada penjualan pada periode ini.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th width="35">
                            #
                        </th>

                        <th>
                            Produk
                        </th>

                        <th>
                            Jenis
                        </th>

                        <th class="text-right">
                            Terjual
                        </th>

                        <th class="text-right">
                            Penjualan
                        </th>

                        <th class="text-right">
                            Laba
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach (
                        $topProducts
                        as $index => $product
                    ): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $product['name']
                                ) ?>
                            </td>

                            <td>
                                <?= $product['product_type'] === 'TITIPAN'
                                    ? 'Titipan'
                                    : 'Produk Toko'
                                ?>
                            </td>

                            <td class="text-right">
                                <?= (int)
                                    $product['total_qty'] ?>
                            </td>

                            <td class="text-right">
                                <?= formatRupiahPdf(
                                    $product['total_sales']
                                ) ?>
                            </td>

                            <td class="text-right">
                                <?= formatRupiahPdf(
                                    $product['total_profit']
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         STOK
    ====================================================== -->

    <div class="section">

        <div class="section-title">
            Kondisi Stok Saat Ini
        </div>


        <div class="two-column">


            <div class="info-box">

                <div class="info-row">

                    <span class="info-label">
                        Total Barang Aktif
                    </span>

                    <span class="info-value">
                        <?= $totalProducts ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Total Stok
                    </span>

                    <span class="info-value">
                        <?= $totalStock ?>
                    </span>

                </div>

            </div>


            <div class="info-box">

                <div class="info-row">

                    <span class="info-label">
                        Stok Menipis
                    </span>

                    <span class="info-value">
                        <?= $lowProducts ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Stok Habis
                    </span>

                    <span class="info-value">
                        <?= $emptyProducts ?>
                    </span>

                </div>

            </div>


        </div>

    </div>


    <!-- =====================================================
         PEMBAYARAN PENITIP
    ====================================================== -->

    <div class="section">

        <div class="section-title">
            Pembayaran Penitip
        </div>


        <?php if (!$settlements): ?>

            <div class="info-box muted">
                Tidak ada pembayaran penitip pada periode ini.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            Tanggal
                        </th>

                        <th>
                            Nomor
                        </th>

                        <th>
                            Penitip
                        </th>

                        <th>
                            Metode
                        </th>

                        <th class="text-right">
                            Jumlah
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach (
                        $settlements
                        as $settlement
                    ): ?>

                        <tr>

                            <td>
                                <?= formatTanggalPdf(
                                    $settlement['settlement_date']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $settlement['settlement_number']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $settlement['consignor_name']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $paymentLabels[
                                        $settlement['payment_method']
                                    ]
                                    ?? $settlement['payment_method']
                                ) ?>
                            </td>

                            <td class="text-right">
                                <?= formatRupiahPdf(
                                    $settlement['total_amount']
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         PENGELUARAN
    ====================================================== -->

    <div class="section">

        <div class="section-title">
            Pengeluaran Operasional
        </div>


        <?php if (!$recentExpenses): ?>

            <div class="info-box muted">
                Tidak ada pengeluaran pada periode ini.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            Tanggal
                        </th>

                        <th>
                            Keterangan
                        </th>

                        <th>
                            Kategori
                        </th>

                        <th class="text-right">
                            Jumlah
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach (
                        $recentExpenses
                        as $expense
                    ): ?>

                        <tr>

                            <td>
                                <?= formatTanggalPdf(
                                    $expense['expense_date']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $expense['description']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $expense['category_name']
                                    ?? '-'
                                ) ?>
                            </td>

                            <td class="text-right">
                                <?= formatRupiahPdf(
                                    $expense['amount']
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <div class="footer">

        Laporan dibuat oleh RESTOCK ·
        <?= date('d/m/Y H:i') ?>

    </div>


</div>


<script>

    /*
    |--------------------------------------------------------------------------
    | Auto Print
    |--------------------------------------------------------------------------
    | Sengaja tidak otomatis print.
    | User bisa review laporan terlebih dahulu.
    |--------------------------------------------------------------------------
    */

</script>

</body>
</html>