<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

/*
|--------------------------------------------------------------------------
| VALIDASI ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die('Data pembelian tidak valid.');
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA PEMBELIAN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.purchase_number,
        p.purchase_date,
        p.total_amount,
        p.notes,
        p.supplier_id,
        s.name AS supplier_name,
        s.phone AS supplier_phone,
        s.address AS supplier_address
    FROM purchases p
    LEFT JOIN suppliers s
        ON s.id = p.supplier_id
       AND s.store_id = p.store_id
    WHERE p.id = :id
      AND p.store_id = :purchase_store_id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id,
    ':purchase_store_id' => $authStoreId
]);

$purchase = $stmt->fetch();

if (!$purchase) {
    die('Data pembelian tidak ditemukan.');
}

/*
|--------------------------------------------------------------------------
| AMBIL ITEM PEMBELIAN
|--------------------------------------------------------------------------
*/

$stmtItems = $pdo->prepare("
    SELECT
        pi.id,
        pi.product_id,
        pi.quantity,
        pi.buying_price,
        pi.subtotal,
        pr.name AS product_name,
        pr.sku,
        pr.unit
    FROM purchase_items pi
    INNER JOIN products pr
        ON pr.id = pi.product_id
       AND pr.store_id = pi.store_id
    WHERE pi.purchase_id = :purchase_id
      AND pi.store_id = :item_store_id
    ORDER BY pi.id ASC
");

$stmtItems->execute([
    ':purchase_id' => $id,
    ':item_store_id' => $authStoreId
]);

$items = $stmtItems->fetchAll();

/*
|--------------------------------------------------------------------------
| TOTAL QTY
|--------------------------------------------------------------------------
*/

$totalQty = 0;

foreach ($items as $item) {
    $totalQty += (int) $item['quantity'];
}

/*
|--------------------------------------------------------------------------
| FORMAT TANGGAL
|--------------------------------------------------------------------------
*/

$dateFormatted = date(
    'd F Y',
    strtotime($purchase['purchase_date'])
);

$timePrinted = date('d/m/Y H:i');

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
        Invoice <?= e($purchase['purchase_number']) ?> · RESTOCK
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f3f3;
            color: #171717;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;
            font-size: 14px;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 30px auto;
            padding: 18mm;
            background: #ffffff;
            box-shadow: 0 5px 30px rgba(0, 0, 0, .08);
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 28px;
            border-bottom: 1px solid #e5e5e5;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            width: 150px;
            max-width: 150px;
            height: auto;
            display: block;
        }

        .invoice-title {
            text-align: right;
        }

        .invoice-title h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -.8px;
        }

        .invoice-number {
            margin-top: 7px;
            color: #737373;
            font-size: 13px;
        }

        /*
        |--------------------------------------------------------------------------
        | INFO
        |--------------------------------------------------------------------------
        */

        .info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
            margin-bottom: 35px;
        }

        .info-label {
            margin-bottom: 7px;
            color: #a3a3a3;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .info-value {
            font-size: 14px;
            line-height: 1.6;
        }

        .info-value strong {
            font-weight: 600;
        }

        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            padding: 11px 10px;
            background: #f5f5f5;
            color: #525252;
            border-top: 1px solid #e5e5e5;
            border-bottom: 1px solid #e5e5e5;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        tbody td {
            padding: 14px 10px;
            border-bottom: 1px solid #eeeeee;
            vertical-align: top;
        }

        .no {
            width: 35px;
            color: #a3a3a3;
            text-align: center;
        }

        .product-name {
            font-weight: 600;
        }

        .product-sku {
            margin-top: 3px;
            color: #a3a3a3;
            font-size: 11px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /*
        |--------------------------------------------------------------------------
        | TOTAL
        |--------------------------------------------------------------------------
        */

        .summary {
            display: flex;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .summary-box {
            width: 280px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            color: #525252;
        }

        .summary-row.total {
            margin-top: 8px;
            padding-top: 15px;
            border-top: 1px solid #d4d4d4;
            color: #171717;
            font-size: 18px;
            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | NOTES
        |--------------------------------------------------------------------------
        */

        .notes {
            margin-top: 45px;
            padding: 16px;
            background: #fafafa;
            border: 1px solid #eeeeee;
            border-radius: 10px;
        }

        .notes-title {
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #737373;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .notes-text {
            color: #525252;
            line-height: 1.6;
        }

        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top: 70px;
            padding-top: 15px;
            border-top: 1px solid #eeeeee;
            display: flex;
            justify-content: space-between;
            color: #a3a3a3;
            font-size: 10px;
        }

        /*
        |--------------------------------------------------------------------------
        | BUTTON
        |--------------------------------------------------------------------------
        */

        .print-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            background: rgba(255, 255, 255, .95);
            border-top: 1px solid #e5e5e5;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, .05);
            z-index: 100;
        }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 11px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-print {
            background: #171717;
            color: #ffffff;
        }

        .btn-back {
            background: #f5f5f5;
            color: #404040;
        }

        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 768px) {

            body {
                background: #ffffff;
            }

            .page {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 25px 18px 100px;
                box-shadow: none;
            }

            .header {
                gap: 20px;
            }

            .brand img {
                width: 125px;
            }

            .invoice-title h1 {
                font-size: 22px;
            }

            .info {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .table-wrapper {
                overflow-x: auto;
            }

            table {
                min-width: 650px;
            }

            .summary {
                justify-content: stretch;
            }

            .summary-box {
                width: 100%;
            }

            .footer {
                flex-direction: column;
                gap: 5px;
            }

        }

        /*
        |--------------------------------------------------------------------------
        | PRINT
        |--------------------------------------------------------------------------
        */

        @page {
            size: A4;
            margin: 0;
        }

        @media print {

            body {
                background: #ffffff;
            }

            .page {
                width: 210mm;
                min-height: 297mm;
                margin: 0;
                padding: 18mm;
                box-shadow: none;
            }

            .print-bar {
                display: none !important;
            }

            .notes {
                break-inside: avoid;
            }

            tr {
                break-inside: avoid;
            }

        }

    </style>

</head>

<body>

<div class="page">

    <!-- HEADER -->

    <div class="header">

        <div class="brand">

            <?php
            $logoPath = __DIR__ . '/../../assets/images/logo.png';

            if (file_exists($logoPath)):
            ?>

                <img
                    src="/assets/images/logo.png"
                    alt="RE-STOCK"
                >

            <?php else: ?>

                <strong style="font-size:22px;">
                    RE-STOCK
                </strong>

            <?php endif; ?>

        </div>

        <div class="invoice-title">

            <h1>INVOICE</h1>

            <div class="invoice-number">
                <?= e($purchase['purchase_number']) ?>
            </div>

        </div>

    </div>


    <!-- INFORMATION -->

    <div class="info">

        <div>

            <div class="info-label">
                Supplier
            </div>

            <div class="info-value">

                <strong>
                    <?= e($purchase['supplier_name'] ?: '-') ?>
                </strong>

                <?php if (!empty($purchase['supplier_phone'])): ?>

                    <br>
                    <?= e($purchase['supplier_phone']) ?>

                <?php endif; ?>

                <?php if (!empty($purchase['supplier_address'])): ?>

                    <br>
                    <?= nl2br(e($purchase['supplier_address'])) ?>

                <?php endif; ?>

            </div>

        </div>


        <div>

            <div class="info-label">
                Detail Pembelian
            </div>

            <div class="info-value">

                <strong>
                    Tanggal:
                </strong>

                <?= e($dateFormatted) ?>

                <br>

                <strong>
                    Total Barang:
                </strong>

                <?= number_format($totalQty, 0, ',', '.') ?>

                item

            </div>

        </div>

    </div>


    <!-- ITEMS -->

    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th class="no">
                        #
                    </th>

                    <th>
                        Barang
                    </th>

                    <th class="text-center">
                        Qty
                    </th>

                    <th class="text-right">
                        Harga
                    </th>

                    <th class="text-right">
                        Subtotal
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php if (empty($items)): ?>

                    <tr>

                        <td
                            colspan="5"
                            class="text-center"
                            style="padding:30px;color:#999;"
                        >
                            Tidak ada item pembelian.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($items as $index => $item): ?>

                        <tr>

                            <td class="no">
                                <?= $index + 1 ?>
                            </td>

                            <td>

                                <div class="product-name">
                                    <?= e($item['product_name']) ?>
                                </div>

                                <?php if (!empty($item['sku'])): ?>

                                    <div class="product-sku">
                                        SKU <?= e($item['sku']) ?>
                                    </div>

                                <?php endif; ?>

                            </td>

                            <td class="text-center">

                                <?= number_format(
                                    (int) $item['quantity'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                                <?= e($item['unit']) ?>

                            </td>

                            <td class="text-right">

                                <?= rupiah($item['buying_price']) ?>

                            </td>

                            <td class="text-right">

                                <?= rupiah($item['subtotal']) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- SUMMARY -->

    <div class="summary">

        <div class="summary-box">

            <div class="summary-row">

                <span>
                    Total Barang
                </span>

                <strong>
                    <?= number_format(
                        $totalQty,
                        0,
                        ',',
                        '.'
                    ) ?>
                </strong>

            </div>

            <div class="summary-row total">

                <span>
                    TOTAL
                </span>

                <span>
                    <?= rupiah($purchase['total_amount']) ?>
                </span>

            </div>

        </div>

    </div>


    <!-- NOTES -->

    <?php if (!empty($purchase['notes'])): ?>

        <div class="notes">

            <div class="notes-title">
                Catatan
            </div>

            <div class="notes-text">
                <?= nl2br(e($purchase['notes'])) ?>
            </div>

        </div>

    <?php endif; ?>


    <!-- FOOTER -->

    <div class="footer">

        <div>
            RESTOCK · BY REQRA
        </div>

        <div>
            Dicetak <?= e($timePrinted) ?>
        </div>

    </div>

</div>


<!-- PRINT BAR -->

<div class="print-bar">

    <button
        type="button"
        class="btn btn-back"
        onclick="history.back()"
    >
        Kembali
    </button>

    <button
        type="button"
        class="btn btn-print"
        onclick="window.print()"
    >
        Cetak / Simpan PDF
    </button>

</div>


</body>

</html>