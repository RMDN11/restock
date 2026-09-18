<?php

/*
|--------------------------------------------------------------------------
| LAPORAN EXCEL
|--------------------------------------------------------------------------
| Native PHP Spreadsheet-compatible XLS
| Tidak membutuhkan Composer / PhpSpreadsheet.
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'laporan-data.php';


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function excelText($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function excelRupiah($value)
{
    return 'Rp ' . number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}

function excelTanggal($date)
{
    if (!$date) {
        return '-';
    }

    return date(
        'd/m/Y H:i',
        strtotime($date)
    );
}


/*
|--------------------------------------------------------------------------
| DATA DETAIL PENJUALAN
|--------------------------------------------------------------------------
|
| Sengaja query terpisah karena laporan-data.php hanya menyimpan
| data ringkasan + daftar terbaru.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        s.id AS sale_id,
        s.invoice_number,
        s.sale_date,
        s.payment_method,
        s.total_amount,
        s.status,

        p.name AS product_name,
        p.sku,
        p.product_type,

        si.quantity,
        si.selling_price,
        si.buying_price,
        si.subtotal,
        si.profit,
        si.consignor_amount,
        si.store_amount

    FROM sale_items si

    INNER JOIN sales s
        ON s.id = si.sale_id

    INNER JOIN products p
        ON p.id = si.product_id

    WHERE

        s.store_id = :sales_detail_store_id
        AND si.store_id = :sales_detail_item_store_id
        AND p.store_id = :sales_detail_product_store_id
        AND s.status = 'COMPLETED'

        AND s.sale_date >= :sales_detail_start

        AND s.sale_date <= :sales_detail_end

    ORDER BY

        s.sale_date DESC,
        s.id DESC,
        si.id ASC
");

$stmt->execute([
    ':sales_detail_store_id' => $storeId,
    ':sales_detail_item_store_id' => $storeId,
    ':sales_detail_product_store_id' => $storeId,
    ':sales_detail_start' => $startDateTime,
    ':sales_detail_end'   => $endDateTime
]);

$salesDetail =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| DATA PEMBAYARAN PENITIP LENGKAP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        cs.id,
        cs.settlement_number,
        cs.settlement_date,
        cs.total_amount,
        cs.payment_method,
        cs.notes,

        c.name AS consignor_name,
        c.phone AS consignor_phone

    FROM consignor_settlements cs

    INNER JOIN consignors c
        ON c.id = cs.consignor_id

    WHERE

        cs.store_id = :settlement_detail_store_id
        AND c.store_id = :settlement_detail_consignor_store_id
        AND cs.settlement_date >= :settlement_detail_start

        AND cs.settlement_date <= :settlement_detail_end

    ORDER BY

        cs.settlement_date DESC,
        cs.id DESC
");

$stmt->execute([
    ':settlement_detail_store_id' => $storeId,
    ':settlement_detail_consignor_store_id' => $storeId,
    ':settlement_detail_start' => $startDateTime,
    ':settlement_detail_end'   => $endDateTime
]);

$settlementDetail =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| DATA PENGELUARAN LENGKAP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        e.id,
        e.expense_date,
        e.description,
        e.amount,
        e.payment_method,
        e.notes,

        ec.name AS category_name

    FROM expenses e

    LEFT JOIN expense_categories ec
        ON ec.id = e.category_id

    WHERE

        e.store_id = :expense_detail_store_id
        AND (ec.store_id = :expense_detail_category_store_id OR ec.id IS NULL)
        AND e.expense_date >= :expense_detail_start

        AND e.expense_date <= :expense_detail_end

    ORDER BY

        e.expense_date DESC,
        e.id DESC
");

$stmt->execute([
    ':expense_detail_store_id' => $storeId,
    ':expense_detail_category_store_id' => $storeId,
    ':expense_detail_start' => $startDateTime,
    ':expense_detail_end'   => $endDateTime
]);

$expenseDetail =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| DATA STOK LENGKAP
|--------------------------------------------------------------------------
|
| Kondisi stok saat ini.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        p.id,
        p.sku,
        p.barcode,
        p.name,
        p.product_type,
        p.unit,

        p.buying_price,
        p.selling_price,

        p.current_stock,
        p.minimum_stock,

        p.status,

        pc.name AS category_name

    FROM products p

    LEFT JOIN product_categories pc
        ON pc.id = p.category_id
        AND pc.store_id = p.store_id

    WHERE
        p.store_id = :stock_detail_store_id

    ORDER BY

        p.product_type ASC,
        p.name ASC
");

$stmt->execute([
    ':stock_detail_store_id' => $storeId
]);

$stockDetail =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| NAMA FILE
|--------------------------------------------------------------------------
*/

$safeFrom =
    str_replace(
        '-',
        '',
        $dateFrom
    );

$safeTo =
    str_replace(
        '-',
        '',
        $dateTo
    );

$fileName =
    'laporan-restock-'
    . $safeFrom
    . '-'
    . $safeTo
    . '.xls';


/*
|--------------------------------------------------------------------------
| HEADER DOWNLOAD
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: application/vnd.ms-excel; charset=UTF-8'
);

header(
    'Content-Disposition: attachment; filename="' . $fileName . '"'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


/*
|--------------------------------------------------------------------------
| EXCEL HTML
|--------------------------------------------------------------------------
*/

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <style>

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #171717;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th {
            background: #171717;
            color: #ffffff;

            font-weight: bold;

            padding: 8px;

            border: 1px solid #999999;

            white-space: nowrap;
        }

        td {
            padding: 7px;

            border: 1px solid #d4d4d4;

            vertical-align: top;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
        }

        .subtitle {
            color: #666666;
            font-size: 11px;
        }

        .section {
            font-size: 14px;
            font-weight: bold;
        }

        .number {
            text-align: right;
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }

        .total {
            background: #f5f5f5;
            font-weight: bold;
        }

        .green {
            background: #f0fdf4;
        }

        .yellow {
            background: #fefce8;
        }

        .red {
            background: #fef2f2;
        }

        .spacer {
            height: 12px;
        }

    </style>

</head>

<body>


<!-- =========================================================
     SHEET 1
     RINGKASAN
========================================================= -->

<table>

    <tr>
        <td colspan="4" class="title">
            RESTOCK
        </td>
    </tr>

    <tr>
        <td colspan="4" class="subtitle">
            Laporan Toko
        </td>
    </tr>

    <tr>
        <td colspan="4">
            Periode:
            <?= excelText($periodLabel) ?>
        </td>
    </tr>

    <tr>
        <td colspan="4"></td>
    </tr>


    <tr>
        <td colspan="4" class="section">
            RINGKASAN KEUANGAN
        </td>
    </tr>


    <tr>

        <th>
            Keterangan
        </th>

        <th>
            Nilai
        </th>

        <th>
            Keterangan
        </th>

        <th>
            Nilai
        </th>

    </tr>


    <tr>

        <td>
            Omzet
        </td>

        <td class="number">
            <?= excelRupiah($omzet) ?>
        </td>

        <td>
            Total Transaksi
        </td>

        <td class="number">
            <?= $totalTransactions ?>
        </td>

    </tr>


    <tr>

        <td>
            HPP
        </td>

        <td class="number">
            <?= excelRupiah($hpp) ?>
        </td>

        <td>
            Margin
        </td>

        <td class="number">
            <?= number_format(
                $margin,
                1,
                ',',
                '.'
            ) ?>%
        </td>

    </tr>


    <tr>

        <td>
            Laba Kotor
        </td>

        <td class="number">
            <?= excelRupiah($labaKotor) ?>
        </td>

        <td>
            Pengeluaran Operasional
        </td>

        <td class="number">
            <?= excelRupiah(
                $pengeluaranOperasional
            ) ?>
        </td>

    </tr>


    <tr>

        <td>
            Pembayaran Penitip
        </td>

        <td class="number">
            <?= excelRupiah(
                $pembayaranPenitip
            ) ?>
        </td>

        <td>
            Total Uang Keluar
        </td>

        <td class="number">
            <?= excelRupiah(
                $totalUangKeluar
            ) ?>
        </td>

    </tr>


    <tr class="total">

        <td>
            Laba Bersih
        </td>

        <td class="number">
            <?= excelRupiah($labaBersih) ?>
        </td>

        <td>
            Periode
        </td>

        <td>
            <?= excelText($periodLabel) ?>
        </td>

    </tr>

</table>


<br><br>


<!-- =========================================================
     PENJUALAN TOKO / TITIPAN
========================================================= -->

<table>

    <tr>
        <td colspan="5" class="section">
            PENJUALAN TOKO VS TITIPAN
        </td>
    </tr>

    <tr>

        <th>
            Jenis
        </th>

        <th>
            Transaksi
        </th>

        <th>
            Jumlah Barang
        </th>

        <th>
            Penjualan
        </th>

        <th>
            Laba / Fee Toko
        </th>

    </tr>


    <tr>

        <td>
            Produk Toko
        </td>

        <td class="number">
            <?= (int)
                $penjualanToko['transactions'] ?>
        </td>

        <td class="number">
            <?= (int)
                $penjualanToko['quantity'] ?>
        </td>

        <td class="number">
            <?= excelRupiah(
                $penjualanToko['sales']
            ) ?>
        </td>

        <td class="number">
            <?= excelRupiah(
                $penjualanToko['profit']
            ) ?>
        </td>

    </tr>


    <tr>

        <td>
            Barang Titipan
        </td>

        <td class="number">
            <?= (int)
                $penjualanTitipan['transactions'] ?>
        </td>

        <td class="number">
            <?= (int)
                $penjualanTitipan['quantity'] ?>
        </td>

        <td class="number">
            <?= excelRupiah(
                $penjualanTitipan['sales']
            ) ?>
        </td>

        <td class="number">
            <?= excelRupiah(
                $penjualanTitipan['profit']
            ) ?>
        </td>

    </tr>

</table>


<!-- =========================================================
     SHEET 2
     DETAIL PENJUALAN
========================================================= -->

<br><br>

<table>

    <tr>
        <td colspan="12" class="section">
            DETAIL PENJUALAN
        </td>
    </tr>

    <tr>

        <th>
            No
        </th>

        <th>
            Tanggal
        </th>

        <th>
            Invoice
        </th>

        <th>
            SKU
        </th>

        <th>
            Produk
        </th>

        <th>
            Jenis
        </th>

        <th>
            Qty
        </th>

        <th>
            Harga Jual
        </th>

        <th>
            HPP
        </th>

        <th>
            Subtotal
        </th>

        <th>
            Laba / Fee
        </th>

        <th>
            Pembayaran
        </th>

    </tr>


    <?php if (!$salesDetail): ?>

        <tr>

            <td
                colspan="12"
                class="center"
            >
                Tidak ada penjualan pada periode ini.
            </td>

        </tr>

    <?php else: ?>

        <?php foreach (
            $salesDetail
            as $index => $sale
        ): ?>

            <tr>

                <td class="center">
                    <?= $index + 1 ?>
                </td>

                <td>
                    <?= excelTanggal(
                        $sale['sale_date']
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $sale['invoice_number']
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $sale['sku']
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $sale['product_name']
                    ) ?>
                </td>

                <td>
                    <?= $sale['product_type'] === 'TITIPAN'
                        ? 'Titipan'
                        : 'Produk Toko'
                    ?>
                </td>

                <td class="number">
                    <?= (int)
                        $sale['quantity'] ?>
                </td>

                <td class="number">
                    <?= excelRupiah(
                        $sale['selling_price']
                    ) ?>
                </td>

                <td class="number">
                    <?= excelRupiah(
                        $sale['buying_price']
                    ) ?>
                </td>

                <td class="number">
                    <?= excelRupiah(
                        $sale['subtotal']
                    ) ?>
                </td>

                <td class="number">
                    <?= excelRupiah(
                        $sale['profit']
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $paymentLabels[
                            $sale['payment_method']
                        ]
                        ?? $sale['payment_method']
                    ) ?>
                </td>

            </tr>

        <?php endforeach; ?>

    <?php endif; ?>

</table>


<!-- =========================================================
     SHEET 3
     PRODUK TERLARIS
========================================================= -->

<br><br>

<table>

    <tr>
        <td colspan="6" class="section">
            PRODUK TERLARIS
        </td>
    </tr>

    <tr>

        <th>
            No
        </th>

        <th>
            Produk
        </th>

        <th>
            Jenis
        </th>

        <th>
            Terjual
        </th>

        <th>
            Penjualan
        </th>

        <th>
            Laba / Fee
        </th>

    </tr>


    <?php if (!$topProducts): ?>

        <tr>

            <td
                colspan="6"
                class="center"
            >
                Tidak ada penjualan.
            </td>

        </tr>

    <?php else: ?>

        <?php foreach (
            $topProducts
            as $index => $product
        ): ?>

            <tr>

                <td class="center">
                    <?= $index + 1 ?>
                </td>

                <td>
                    <?= excelText(
                        $product['name']
                    ) ?>
                </td>

                <td>
                    <?= $product['product_type'] === 'TITIPAN'
                        ? 'Titipan'
                        : 'Produk Toko'
                    ?>
                </td>

                <td class="number">
                    <?= (int)
                        $product['total_qty'] ?>
                </td>

                <td class="number">
                    <?= excelRupiah(
                        $product['total_sales']
                    ) ?>
                </td>

                <td class="number">
                    <?= excelRupiah(
                        $product['total_profit']
                    ) ?>
                </td>

            </tr>

        <?php endforeach; ?>

    <?php endif; ?>

</table>


<!-- =========================================================
     SHEET 4
     PEMBAYARAN PENITIP
========================================================= -->

<br><br>

<table>

    <tr>
        <td colspan="7" class="section">
            PEMBAYARAN PENITIP
        </td>
    </tr>

    <tr>

        <th>
            No
        </th>

        <th>
            Tanggal
        </th>

        <th>
            Nomor Pembayaran
        </th>

        <th>
            Penitip
        </th>

        <th>
            Telepon
        </th>

        <th>
            Metode
        </th>

        <th>
            Jumlah
        </th>

    </tr>


    <?php if (!$settlementDetail): ?>

        <tr>

            <td
                colspan="7"
                class="center"
            >
                Tidak ada pembayaran penitip.
            </td>

        </tr>

    <?php else: ?>

        <?php foreach (
            $settlementDetail
            as $index => $settlement
        ): ?>

            <tr>

                <td class="center">
                    <?= $index + 1 ?>
                </td>

                <td>
                    <?= excelTanggal(
                        $settlement['settlement_date']
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $settlement['settlement_number']
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $settlement['consignor_name']
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $settlement['consignor_phone']
                        ?? '-'
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $paymentLabels[
                            $settlement['payment_method']
                        ]
                        ?? $settlement['payment_method']
                    ) ?>
                </td>

                <td class="number">
                    <?= excelRupiah(
                        $settlement['total_amount']
                    ) ?>
                </td>

            </tr>

        <?php endforeach; ?>

    <?php endif; ?>

</table>


<!-- =========================================================
     SHEET 5
     PENGELUARAN
========================================================= -->

<br><br>

<table>

    <tr>
        <td colspan="6" class="section">
            PENGELUARAN OPERASIONAL
        </td>
    </tr>

    <tr>

        <th>
            No
        </th>

        <th>
            Tanggal
        </th>

        <th>
            Kategori
        </th>

        <th>
            Keterangan
        </th>

        <th>
            Metode
        </th>

        <th>
            Jumlah
        </th>

    </tr>


    <?php if (!$expenseDetail): ?>

        <tr>

            <td
                colspan="6"
                class="center"
            >
                Tidak ada pengeluaran.
            </td>

        </tr>

    <?php else: ?>

        <?php foreach (
            $expenseDetail
            as $index => $expense
        ): ?>

            <tr>

                <td class="center">
                    <?= $index + 1 ?>
                </td>

                <td>
                    <?= excelTanggal(
                        $expense['expense_date']
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $expense['category_name']
                        ?? '-'
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $expense['description']
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $paymentLabels[
                            $expense['payment_method']
                        ]
                        ?? $expense['payment_method']
                    ) ?>
                </td>

                <td class="number">
                    <?= excelRupiah(
                        $expense['amount']
                    ) ?>
                </td>

            </tr>

        <?php endforeach; ?>

    <?php endif; ?>

</table>


<!-- =========================================================
     SHEET 6
     STOK
========================================================= -->

<br><br>

<table>

    <tr>
        <td colspan="10" class="section">
            KONDISI STOK SAAT INI
        </td>
    </tr>

    <tr>

        <th>
            No
        </th>

        <th>
            SKU
        </th>

        <th>
            Barcode
        </th>

        <th>
            Produk
        </th>

        <th>
            Jenis
        </th>

        <th>
            Kategori
        </th>

        <th>
            Satuan
        </th>

        <th>
            Harga Modal
        </th>

        <th>
            Harga Jual
        </th>

        <th>
            Stok
        </th>

    </tr>


    <?php if (!$stockDetail): ?>

        <tr>

            <td
                colspan="10"
                class="center"
            >
                Belum ada barang.
            </td>

        </tr>

    <?php else: ?>

        <?php foreach (
            $stockDetail
            as $index => $product
        ): ?>

            <?php

            $stock =
                (int) $product['current_stock'];

            $minimum =
                (int) $product['minimum_stock'];

            $stockClass = '';

            if ($stock <= 0) {
                $stockClass = 'red';
            } elseif (
                $stock <= $minimum
            ) {
                $stockClass = 'yellow';
            } else {
                $stockClass = 'green';
            }

            ?>

            <tr class="<?= $stockClass ?>">

                <td class="center">
                    <?= $index + 1 ?>
                </td>

                <td>
                    <?= excelText(
                        $product['sku']
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $product['barcode']
                        ?? '-'
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $product['name']
                    ) ?>
                </td>

                <td>
                    <?= $product['product_type'] === 'TITIPAN'
                        ? 'Titipan'
                        : 'Produk Toko'
                    ?>
                </td>

                <td>
                    <?= excelText(
                        $product['category_name']
                        ?? '-'
                    ) ?>
                </td>

                <td>
                    <?= excelText(
                        $product['unit']
                    ) ?>
                </td>

                <td class="number">
                    <?= excelRupiah(
                        $product['buying_price']
                    ) ?>
                </td>

                <td class="number">
                    <?= excelRupiah(
                        $product['selling_price']
                    ) ?>
                </td>

                <td class="number">
                    <?= $stock ?>
                </td>

            </tr>

        <?php endforeach; ?>

    <?php endif; ?>

</table>


</body>
</html>