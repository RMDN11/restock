<?php

require_once '../../includes/auth.php';
require_once '../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value)
{
    return 'Rp' . number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('Invoice tidak ditemukan.');
}


/*
|--------------------------------------------------------------------------
| DATA PENJUALAN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.*,
        u.name AS cashier_name
    FROM sales s
    LEFT JOIN users u
        ON u.id = s.created_by
    WHERE s.id = :id
      AND s.store_id = :sale_store_id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id,
    ':sale_store_id' => $authStoreId
]);

$sale = $stmt->fetch();

if (!$sale) {
    die('Data penjualan tidak ditemukan.');
}


/*
|--------------------------------------------------------------------------
| ITEM PENJUALAN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        si.*,
        p.name AS product_name,
        p.sku,
        p.product_type,

        (
            SELECT c.name
            FROM consignments cs
            INNER JOIN consignors c
                ON c.id = cs.consignor_id
            WHERE cs.product_id = si.product_id
              AND cs.store_id = si.store_id
            ORDER BY cs.id DESC
            LIMIT 1
        ) AS consignor_name

    FROM sale_items si

    INNER JOIN products p
        ON p.id = si.product_id
       AND p.store_id = si.store_id

    WHERE si.sale_id = :sale_id
      AND si.store_id = :item_store_id

    ORDER BY si.id ASC
");

$stmt->execute([
    ':sale_id' => $id,
    ':item_store_id' => $authStoreId
]);

$items = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

$totalQty = 0;
$totalProfit = 0;

foreach ($items as $item) {
    $totalQty += (int) $item['quantity'];
    $totalProfit += (float) $item['profit'];
}


/*
|--------------------------------------------------------------------------
| LABEL PAYMENT
|--------------------------------------------------------------------------
*/

$paymentLabels = [
    'CASH'     => 'Tunai',
    'QRIS'     => 'QRIS',
    'TRANSFER' => 'Transfer',
    'OTHER'    => 'Lainnya',
];

$paymentLabel = $paymentLabels[$sale['payment_method']]
    ?? $sale['payment_method'];


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$isCancelled = $sale['status'] === 'CANCELLED';


/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
*/

$logoPath = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/logo.png';

$logoExists = file_exists($logoPath);

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
        Invoice <?= e($sale['invoice_number']) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #f3f3f3;
            color: #171717;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        body {
            padding: 30px;
        }

        .toolbar {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 10px;
            border: 1px solid #e5e5e5;
            background: #fff;
            color: #171717;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-primary {
            background: #171717;
            color: #fff;
            border-color: #171717;
        }

        .invoice {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 48px;
            min-height: 900px;
            box-shadow: 0 5px 30px rgba(0,0,0,.06);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e5e5e5;
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .brand img {
            width: 150px;
            height: auto;
            object-fit: contain;
        }

        .brand-title {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -.03em;
        }

        .brand-subtitle {
            font-size: 12px;
            color: #737373;
        }

        .invoice-info {
            text-align: right;
        }

        .invoice-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #737373;
            margin-bottom: 6px;
        }

        .invoice-number {
            font-size: 20px;
            font-weight: 700;
        }

        .status {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: #f5f5f5;
            color: #525252;
        }

        .status.cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 30px 0;
        }

        .meta-label {
            font-size: 11px;
            color: #a3a3a3;
            margin-bottom: 5px;
        }

        .meta-value {
            font-size: 14px;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            border-bottom: 1px solid #171717;
        }

        th {
            padding: 12px 8px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #737373;
        }

        td {
            padding: 15px 8px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .product-name {
            font-weight: 500;
        }

        .product-detail {
            margin-top: 3px;
            font-size: 11px;
            color: #a3a3a3;
        }

        .total-section {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
        }

        .total-box {
            width: 280px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 8px 0;
            font-size: 13px;
        }

        .total-row.grand {
            margin-top: 8px;
            padding-top: 15px;
            border-top: 1px solid #171717;
            font-size: 18px;
            font-weight: 700;
        }

        .notes {
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
        }

        .notes-title {
            font-size: 11px;
            color: #a3a3a3;
            margin-bottom: 6px;
        }

        .notes-content {
            font-size: 13px;
            color: #525252;
            white-space: pre-line;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            text-align: center;
            font-size: 11px;
            color: #a3a3a3;
        }

        .watermark {
            position: absolute;
            font-size: 50px;
            font-weight: 800;
            color: rgba(185, 28, 28, .10);
            transform: rotate(-20deg);
            pointer-events: none;
        }

        @media (max-width: 600px) {

            body {
                padding: 10px;
            }

            .invoice {
                padding: 25px 18px;
            }

            .header {
                flex-direction: column;
            }

            .invoice-info {
                text-align: left;
            }

            .meta {
                grid-template-columns: 1fr;
            }

            .total-section {
                justify-content: stretch;
            }

            .total-box {
                width: 100%;
            }

            th,
            td {
                padding: 10px 5px;
            }

        }

        @media print {

            @page {
                size: A4;
                margin: 12mm;
            }

            html,
            body {
                background: #fff;
            }

            body {
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .invoice {
                max-width: none;
                width: 100%;
                min-height: auto;
                padding: 0;
                box-shadow: none;
            }

        }

    </style>

</head>

<body>


<!-- TOOLBAR -->

<div class="toolbar">

    <a
        href="/pages/penjualan/view.php?id=<?= (int) $sale['id'] ?>"
        class="btn"
    >
        Kembali
    </a>

    <button
        type="button"
        class="btn btn-primary"
        onclick="window.print()"
    >
        Cetak / Simpan PDF
    </button>

</div>


<!-- INVOICE -->

<div class="invoice">

    <?php if ($isCancelled): ?>

        <div class="watermark">
            DIBATALKAN
        </div>

    <?php endif; ?>


    <!-- HEADER -->

    <div class="header">

        <div class="brand">

            <?php if ($logoExists): ?>

                <img
                    src="/assets/images/logo.png"
                    alt="RE-STOCK"
                >

            <?php else: ?>

                <div class="brand-title">
                    RE-STOCK
                </div>

                <div class="brand-subtitle">
                    BY REQRA
                </div>

            <?php endif; ?>

        </div>


        <div class="invoice-info">

            <div class="invoice-label">
                Invoice Penjualan
            </div>

            <div class="invoice-number">
                <?= e($sale['invoice_number']) ?>
            </div>

            <?php if ($isCancelled): ?>

                <div class="status cancelled">
                    DIBATALKAN
                </div>

            <?php else: ?>

                <div class="status">
                    SELESAI
                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- META -->

    <div class="meta">

        <div>

            <div class="meta-label">
                Tanggal
            </div>

            <div class="meta-value">
                <?= date('d/m/Y H:i', strtotime($sale['sale_date'])) ?>
            </div>

        </div>


        <div>

            <div class="meta-label">
                Metode Pembayaran
            </div>

            <div class="meta-value">
                <?= e($paymentLabel) ?>
            </div>

        </div>


        <div>

            <div class="meta-label">
                Kasir
            </div>

            <div class="meta-value">
                <?= e($sale['cashier_name'] ?? '-') ?>
            </div>

        </div>


        <div>

            <div class="meta-label">
                Jumlah Item
            </div>

            <div class="meta-value">
                <?= number_format($totalQty, 0, ',', '.') ?>
                item
            </div>

        </div>

    </div>


    <!-- ITEMS -->

    <table>

        <thead>

            <tr>

                <th>
                    Produk
                </th>

                <th class="text-right">
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

            <?php foreach ($items as $item): ?>

                <tr>

                    <td>

                        <div class="product-name">
                            <?= e($item['product_name']) ?>
                        </div>

                        <div class="product-detail">

                            <?php if (!empty($item['sku'])): ?>

                                SKU <?= e($item['sku']) ?>

                            <?php endif; ?>

                            <?php if (
                                $item['product_type'] === 'TITIPAN'
                                && !empty($item['consignor_name'])
                            ): ?>

                                · Titipan:
                                <?= e($item['consignor_name']) ?>

                            <?php endif; ?>

                        </div>

                    </td>


                    <td class="text-right">
                        <?= number_format(
                            (int) $item['quantity'],
                            0,
                            ',',
                            '.'
                        ) ?>
                    </td>


                    <td class="text-right">
                        <?= rupiah($item['selling_price']) ?>
                    </td>


                    <td class="text-right">
                        <?= rupiah($item['subtotal']) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>


    <!-- TOTAL -->

    <div class="total-section">

        <div class="total-box">

            <div class="total-row">

                <span>
                    Total Item
                </span>

                <span>
                    <?= number_format(
                        $totalQty,
                        0,
                        ',',
                        '.'
                    ) ?>
                </span>

            </div>


            <div class="total-row grand">

                <span>
                    TOTAL
                </span>

                <span>
                    <?= rupiah($sale['total_amount']) ?>
                </span>

            </div>

        </div>

    </div>


    <!-- NOTES -->

    <?php if (!empty($sale['notes'])): ?>

        <div class="notes">

            <div class="notes-title">
                Catatan
            </div>

            <div class="notes-content">
                <?= e($sale['notes']) ?>
            </div>

        </div>

    <?php endif; ?>


    <!-- FOOTER -->

    <div class="footer">

        Terima kasih telah berbelanja.

        <br>

        RE-STOCK by REQRA

    </div>

</div>


</body>
</html>