<?php

/*
|--------------------------------------------------------------------------
| LAPORAN DATA
|--------------------------------------------------------------------------
| File ini hanya menyediakan data laporan.
|
| Digunakan oleh:
| - index.php
| - pdf.php
| - excel.php
|
| Tidak menghasilkan HTML.
|--------------------------------------------------------------------------
*/

require_once '../../config/database.php';
require_once '../../includes/auth.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) {
    http_response_code(403);
    exit('Store aktif tidak ditemukan.');
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($value)
{
    return 'Rp ' . number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}

function tanggalIndonesia($date)
{
    $bulan = [
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '-';
    }

    return date('j', $timestamp)
        . ' '
        . $bulan[(int) date('n', $timestamp)]
        . ' '
        . date('Y', $timestamp);
}

/*
|--------------------------------------------------------------------------
| FILTER PERIODE
|--------------------------------------------------------------------------
*/

$period = $_GET['period'] ?? 'month';

$allowedPeriods = [
    'today',
    'week',
    'month',
    'custom'
];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 'month';
}

/*
|--------------------------------------------------------------------------
| DEFAULT
|--------------------------------------------------------------------------
*/

$dateFrom = date('Y-m-01');
$dateTo   = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| HARI INI
|--------------------------------------------------------------------------
*/

if ($period === 'today') {

    $dateFrom = date('Y-m-d');
    $dateTo   = date('Y-m-d');
}

/*
|--------------------------------------------------------------------------
| MINGGU INI
|--------------------------------------------------------------------------
*/

elseif ($period === 'week') {

    $dateFrom = date(
        'Y-m-d',
        strtotime('monday this week')
    );

    $dateTo = date('Y-m-d');
}

/*
|--------------------------------------------------------------------------
| BULAN INI
|--------------------------------------------------------------------------
*/

elseif ($period === 'month') {

    $dateFrom = date('Y-m-01');
    $dateTo   = date('Y-m-d');
}

/*
|--------------------------------------------------------------------------
| CUSTOM
|--------------------------------------------------------------------------
*/

elseif ($period === 'custom') {

    $dateFrom = trim(
        $_GET['date_from'] ?? ''
    );

    $dateTo = trim(
        $_GET['date_to'] ?? ''
    );

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $dateFrom
        )
    ) {
        $dateFrom = date('Y-m-01');
    }

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $dateTo
        )
    ) {
        $dateTo = date('Y-m-d');
    }

    /*
    |--------------------------------------------------------------------------
    | Jika tanggal terbalik
    |--------------------------------------------------------------------------
    */

    if ($dateFrom > $dateTo) {

        $temp = $dateFrom;

        $dateFrom = $dateTo;

        $dateTo = $temp;
    }
}

/*
|--------------------------------------------------------------------------
| LABEL PERIODE
|--------------------------------------------------------------------------
*/

if ($period === 'today') {

    $periodLabel = 'Hari Ini';

} elseif ($period === 'week') {

    $periodLabel = 'Minggu Ini';

} elseif ($period === 'month') {

    $periodLabel = 'Bulan Ini';

} else {

    $periodLabel =
        tanggalIndonesia($dateFrom)
        . ' - '
        . tanggalIndonesia($dateTo);
}

/*
|--------------------------------------------------------------------------
| RANGE DATETIME
|--------------------------------------------------------------------------
*/

$startDateTime =
    $dateFrom . ' 00:00:00';

$endDateTime =
    $dateTo . ' 23:59:59';


/*
|--------------------------------------------------------------------------
| 1. RINGKASAN PENJUALAN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        COUNT(*) AS total_transactions,

        COALESCE(
            SUM(total_amount),
            0
        ) AS omzet

    FROM sales

    WHERE
        store_id = :sales_store_id
        AND status = 'COMPLETED'

        AND sale_date >= :sales_start

        AND sale_date <= :sales_end
");

$stmt->execute([
    ':sales_store_id' => $storeId,
    ':sales_start' => $startDateTime,
    ':sales_end'   => $endDateTime
]);

$salesSummary = $stmt->fetch();

$totalTransactions =
    (int) ($salesSummary['total_transactions'] ?? 0);

$omzet =
    (float) ($salesSummary['omzet'] ?? 0);


/*
|--------------------------------------------------------------------------
| 2. HPP + LABA KOTOR
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        COALESCE(
            SUM(
                si.buying_price * si.quantity
            ),
            0
        ) AS hpp,

        COALESCE(
            SUM(si.profit),
            0
        ) AS profit

    FROM sale_items si

    INNER JOIN sales s
        ON s.id = si.sale_id

    WHERE
        s.store_id = :profit_store_id
        AND si.store_id = :profit_item_store_id
        AND s.status = 'COMPLETED'

        AND s.sale_date >= :profit_start

        AND s.sale_date <= :profit_end
");

$stmt->execute([
    ':profit_store_id' => $storeId,
    ':profit_item_store_id' => $storeId,
    ':profit_start' => $startDateTime,
    ':profit_end'   => $endDateTime
]);

$profitSummary = $stmt->fetch();

$hpp =
    (float) ($profitSummary['hpp'] ?? 0);

$labaKotor =
    (float) ($profitSummary['profit'] ?? 0);


/*
|--------------------------------------------------------------------------
| 3. PENGELUARAN OPERASIONAL
|--------------------------------------------------------------------------
|
| Hanya mengambil expenses.
|
| Pembayaran penitip tidak masuk ke sini.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        COALESCE(
            SUM(amount),
            0
        ) AS total

    FROM expenses

    WHERE
        store_id = :expense_store_id
        AND expense_date >= :expense_start

        AND expense_date <= :expense_end
");

$stmt->execute([
    ':expense_store_id' => $storeId,
    ':expense_start' => $startDateTime,
    ':expense_end'   => $endDateTime
]);

$pengeluaranOperasional =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| 4. PEMBAYARAN PENITIP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        COALESCE(
            SUM(total_amount),
            0
        ) AS total,

        COUNT(*) AS transactions

    FROM consignor_settlements

    WHERE
        store_id = :settlement_store_id
        AND settlement_date >= :settlement_start

        AND settlement_date <= :settlement_end
");

$stmt->execute([
    ':settlement_store_id' => $storeId,
    ':settlement_start' => $startDateTime,
    ':settlement_end'   => $endDateTime
]);

$settlementSummary =
    $stmt->fetch();

$pembayaranPenitip =
    (float) ($settlementSummary['total'] ?? 0);

$settlementTransactions =
    (int) ($settlementSummary['transactions'] ?? 0);


/*
|--------------------------------------------------------------------------
| 5. LABA BERSIH
|--------------------------------------------------------------------------
|
| Pembayaran penitip TIDAK dikurangi dari laba.
|--------------------------------------------------------------------------
*/

$labaBersih =
    $labaKotor
    -
    $pengeluaranOperasional;


/*
|--------------------------------------------------------------------------
| 6. TOTAL UANG KELUAR
|--------------------------------------------------------------------------
*/

$totalUangKeluar =
    $pengeluaranOperasional
    +
    $pembayaranPenitip;


/*
|--------------------------------------------------------------------------
| 7. PENJUALAN TOKO VS TITIPAN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        p.product_type,

        COUNT(DISTINCT s.id)
            AS total_transactions,

        COALESCE(
            SUM(si.subtotal),
            0
        ) AS total_sales,

        COALESCE(
            SUM(si.profit),
            0
        ) AS total_profit,

        COALESCE(
            SUM(si.quantity),
            0
        ) AS total_quantity

    FROM sale_items si

    INNER JOIN sales s
        ON s.id = si.sale_id

    INNER JOIN products p
        ON p.id = si.product_id

    WHERE
        s.store_id = :type_store_id
        AND si.store_id = :type_item_store_id
        AND p.store_id = :type_product_store_id
        AND s.status = 'COMPLETED'

        AND s.sale_date >= :type_start

        AND s.sale_date <= :type_end

    GROUP BY
        p.product_type
");

$stmt->execute([
    ':type_store_id' => $storeId,
    ':type_item_store_id' => $storeId,
    ':type_product_store_id' => $storeId,
    ':type_start' => $startDateTime,
    ':type_end'   => $endDateTime
]);

$typeRows = $stmt->fetchAll();

$penjualanToko = [
    'transactions' => 0,
    'sales'        => 0,
    'profit'       => 0,
    'quantity'     => 0
];

$penjualanTitipan = [
    'transactions' => 0,
    'sales'        => 0,
    'profit'       => 0,
    'quantity'     => 0
];

foreach ($typeRows as $row) {

    if ($row['product_type'] === 'TOKO') {

        $penjualanToko = [
            'transactions' =>
                (int) $row['total_transactions'],

            'sales' =>
                (float) $row['total_sales'],

            'profit' =>
                (float) $row['total_profit'],

            'quantity' =>
                (int) $row['total_quantity']
        ];
    }

    elseif (
        $row['product_type'] === 'TITIPAN'
    ) {

        $penjualanTitipan = [
            'transactions' =>
                (int) $row['total_transactions'],

            'sales' =>
                (float) $row['total_sales'],

            'profit' =>
                (float) $row['total_profit'],

            'quantity' =>
                (int) $row['total_quantity']
        ];
    }
}


/*
|--------------------------------------------------------------------------
| 8. PRODUK TERLARIS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        p.id,
        p.name,
        p.product_type,

        COALESCE(
            SUM(si.quantity),
            0
        ) AS total_qty,

        COALESCE(
            SUM(si.subtotal),
            0
        ) AS total_sales,

        COALESCE(
            SUM(si.profit),
            0
        ) AS total_profit

    FROM sale_items si

    INNER JOIN sales s
        ON s.id = si.sale_id

    INNER JOIN products p
        ON p.id = si.product_id

    WHERE
        s.store_id = :top_store_id
        AND si.store_id = :top_item_store_id
        AND p.store_id = :top_product_store_id
        AND s.status = 'COMPLETED'

        AND s.sale_date >= :top_start

        AND s.sale_date <= :top_end

    GROUP BY
        p.id,
        p.name,
        p.product_type

    ORDER BY
        total_qty DESC,
        total_sales DESC

    LIMIT 10
");

$stmt->execute([
    ':top_store_id' => $storeId,
    ':top_item_store_id' => $storeId,
    ':top_product_store_id' => $storeId,
    ':top_start' => $startDateTime,
    ':top_end'   => $endDateTime
]);

$topProducts =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| 9. RINGKASAN STOK
|--------------------------------------------------------------------------
|
| Stok adalah kondisi SAAT INI,
| bukan berdasarkan filter periode.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        COUNT(*) AS total_products,

        COALESCE(
            SUM(current_stock),
            0
        ) AS total_stock,

        SUM(
            CASE
                WHEN current_stock <= 0
                THEN 1
                ELSE 0
            END
        ) AS empty_products,

        SUM(
            CASE
                WHEN current_stock > 0
                AND current_stock <= minimum_stock
                THEN 1
                ELSE 0
            END
        ) AS low_products,

        SUM(
            CASE
                WHEN current_stock > minimum_stock
                THEN 1
                ELSE 0
            END
        ) AS available_products

    FROM products

    WHERE
        store_id = :stock_store_id
        AND status = 'ACTIVE'
");

$stmt->execute([
    ':stock_store_id' => $storeId
]);

$stockSummary =
    $stmt->fetch();

$totalProducts =
    (int) ($stockSummary['total_products'] ?? 0);

$totalStock =
    (int) ($stockSummary['total_stock'] ?? 0);

$emptyProducts =
    (int) ($stockSummary['empty_products'] ?? 0);

$lowProducts =
    (int) ($stockSummary['low_products'] ?? 0);

$availableProducts =
    (int) ($stockSummary['available_products'] ?? 0);


/*
|--------------------------------------------------------------------------
| 10. PEMBAYARAN PENITIP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        cs.id,
        cs.settlement_number,
        cs.settlement_date,
        cs.total_amount,
        cs.payment_method,

        c.name AS consignor_name

    FROM consignor_settlements cs

    INNER JOIN consignors c
        ON c.id = cs.consignor_id

    WHERE
        cs.store_id = :settlement_list_store_id
        AND c.store_id = :settlement_list_consignor_store_id
        AND cs.settlement_date >= :settlement_list_start

        AND cs.settlement_date <= :settlement_list_end

    ORDER BY
        cs.settlement_date DESC,
        cs.id DESC

    LIMIT 10
");

$stmt->execute([
    ':settlement_list_store_id' => $storeId,
    ':settlement_list_consignor_store_id' => $storeId,
    ':settlement_list_start' => $startDateTime,
    ':settlement_list_end'   => $endDateTime
]);

$settlements =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| 11. PENGELUARAN OPERASIONAL TERBARU
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        e.id,
        e.expense_date,
        e.description,
        e.amount,

        ec.name AS category_name

    FROM expenses e

    LEFT JOIN expense_categories ec
        ON ec.id = e.category_id

    WHERE
        e.store_id = :recent_expense_store_id
        AND (ec.store_id = :recent_expense_category_store_id OR ec.id IS NULL)
        AND e.expense_date >= :recent_expense_start

        AND e.expense_date <= :recent_expense_end

    ORDER BY
        e.expense_date DESC,
        e.id DESC

    LIMIT 10
");

$stmt->execute([
    ':recent_expense_store_id' => $storeId,
    ':recent_expense_category_store_id' => $storeId,
    ':recent_expense_start' => $startDateTime,
    ':recent_expense_end'   => $endDateTime
]);

$recentExpenses =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| 12. MARGIN
|--------------------------------------------------------------------------
*/

$margin =
    $omzet > 0
        ? ($labaKotor / $omzet) * 100
        : 0;


/*
|--------------------------------------------------------------------------
| 13. PAYMENT LABEL
|--------------------------------------------------------------------------
*/

$paymentLabels = [
    'CASH'     => 'Cash',
    'QRIS'     => 'QRIS',
    'TRANSFER' => 'Transfer',
    'OTHER'    => 'Lainnya'
];


/*
|--------------------------------------------------------------------------
| 14. DATA EXPORT
|--------------------------------------------------------------------------
|
| Data ringkasan sederhana yang bisa dipakai PDF / Excel.
|--------------------------------------------------------------------------
*/

$reportSummary = [

    [
        'label' => 'Omzet',
        'value' => $omzet
    ],

    [
        'label' => 'HPP',
        'value' => $hpp
    ],

    [
        'label' => 'Laba Kotor',
        'value' => $labaKotor
    ],

    [
        'label' => 'Pengeluaran Operasional',
        'value' => $pengeluaranOperasional
    ],

    [
        'label' => 'Pembayaran Penitip',
        'value' => $pembayaranPenitip
    ],

    [
        'label' => 'Laba Bersih',
        'value' => $labaBersih
    ],

    [
        'label' => 'Total Uang Keluar',
        'value' => $totalUangKeluar
    ]
];

/*
|--------------------------------------------------------------------------
| SELESAI
|--------------------------------------------------------------------------
*/