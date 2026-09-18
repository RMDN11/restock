<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/auth.php';
require_once '../../config/database.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) { http_response_code(403); exit('Store aktif tidak ditemukan.'); }

$pageTitle = 'Laporan Penitip';


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
    return 'Rp' . number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}


/*
|--------------------------------------------------------------------------
| PARAMETER
|--------------------------------------------------------------------------
*/

$consignorId = isset($_GET['consignor_id'])
    ? (int) $_GET['consignor_id']
    : 0;

if ($consignorId <= 0) {
    header('Location: /pages/titipan/penitip.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| DEFAULT PERIODE
|--------------------------------------------------------------------------
*/

$dateFrom = $_GET['date_from']
    ?? date('Y-m-01');

$dateTo = $_GET['date_to']
    ?? date('Y-m-d');


/*
|--------------------------------------------------------------------------
| VALIDASI TANGGAL
|--------------------------------------------------------------------------
*/

$fromObj = DateTime::createFromFormat(
    'Y-m-d',
    $dateFrom
);

$toObj = DateTime::createFromFormat(
    'Y-m-d',
    $dateTo
);

if (
    !$fromObj ||
    $fromObj->format('Y-m-d') !== $dateFrom
) {
    $dateFrom = date('Y-m-01');
}

if (
    !$toObj ||
    $toObj->format('Y-m-d') !== $dateTo
) {
    $dateTo = date('Y-m-d');
}


/*
|--------------------------------------------------------------------------
| DATA PENITIP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
        address
    FROM consignors
    WHERE id = :id
    AND store_id = $storeId
    LIMIT 1
");

$stmt->execute([
    ':id' => $consignorId
]);

$consignor = $stmt->fetch();

if (!$consignor) {
    header('Location: /pages/titipan/penitip.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| DATA PENJUALAN
|--------------------------------------------------------------------------
|
| Hanya transaksi COMPLETED.
|
| Settlement dihitung per sale_item supaya tidak
| terjadi double counting.
|
*/

$stmt = $pdo->prepare("
    SELECT

        si.id AS sale_item_id,

        s.id AS sale_id,
        s.invoice_number,
        s.sale_date,

        p.name AS product_name,
        p.sku,
        p.unit,

        si.quantity,
        si.selling_price,
        si.subtotal,
        si.store_amount,
        si.consignor_amount,

        COALESCE(
            (
                SELECT SUM(csi.amount)
                FROM consignor_settlement_items csi
                INNER JOIN consignor_settlements cs
                    ON cs.id = csi.settlement_id
                    AND cs.store_id = $storeId
                WHERE csi.sale_item_id = si.id
                AND cs.consignor_id = :consignor_id_settlement
            ),
            0
        ) AS paid_amount

    FROM sale_items si

    INNER JOIN sales s
        ON s.id = si.sale_id
        AND s.store_id = $storeId

    INNER JOIN products p
        ON p.id = si.product_id
        AND p.store_id = $storeId

    INNER JOIN consignments c
        ON c.product_id = p.id
        AND c.store_id = $storeId
        AND c.consignor_id = :consignor_id_consignment

    WHERE si.store_id = $storeId
    AND s.status = 'COMPLETED'

    AND s.sale_date >= :date_from

    AND s.sale_date < DATE_ADD(
        :date_to,
        INTERVAL 1 DAY
    )

    AND p.product_type = 'TITIPAN'

    GROUP BY
        si.id,
        s.id,
        s.invoice_number,
        s.sale_date,
        p.name,
        p.sku,
        p.unit,
        si.quantity,
        si.selling_price,
        si.subtotal,
        si.store_amount,
        si.consignor_amount

    ORDER BY
        s.sale_date ASC,
        si.id ASC
");

$stmt->execute([
    ':consignor_id_settlement' =>
        $consignorId,

    ':consignor_id_consignment' =>
        $consignorId,

    ':date_from' =>
        $dateFrom,

    ':date_to' =>
        $dateTo,
]);

$items = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| REKAP
|--------------------------------------------------------------------------
*/

$totalQty = 0;
$totalSales = 0;
$totalStoreFee = 0;
$totalConsignor = 0;
$totalPaid = 0;

foreach ($items as $item) {

    $totalQty += (int) $item['quantity'];

    $totalSales +=
        (float) $item['subtotal'];

    $totalStoreFee +=
        (float) $item['store_amount'];

    $totalConsignor +=
        (float) $item['consignor_amount'];

    $totalPaid +=
        (float) $item['paid_amount'];
}


$totalUnpaid =
    max(
        0,
        $totalConsignor - $totalPaid
    );


/*
|--------------------------------------------------------------------------
| JUMLAH TRANSAKSI
|--------------------------------------------------------------------------
*/

$totalTransactions =
    count(
        array_unique(
            array_column(
                $items,
                'sale_id'
            )
        )
    );


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

if ($totalConsignor <= 0) {

    $paymentStatus = 'BELUM ADA PENJUALAN';

} elseif ($totalUnpaid <= 0) {

    $paymentStatus = 'SUDAH DIBAYAR';

} elseif ($totalPaid > 0) {

    $paymentStatus = 'SEBAGIAN DIBAYAR';

} else {

    $paymentStatus = 'BELUM DIBAYAR';
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require_once '../../includes/header.php';

?>

<style>

.report-page {
    max-width: 1180px;
    margin: 0 auto;
}

.report-paper {
    background: #fff;
}

@media print {

    @page {
        size: A4;
        margin: 12mm;
    }

    html,
    body {
        background: #fff !important;
    }

    body {
        font-size: 11px;
    }

    .no-print,
    .sidebar,
    .mobile-header {
        display: none !important;
    }

    .main-content {
        margin-left: 0 !important;
    }

    .report-page {
        max-width: none;
        margin: 0;
        padding: 0 !important;
    }

    .report-paper {
        border: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }

    .print-table {
        display: table !important;
    }

    .print-table th,
    .print-table td {
        padding: 7px 5px !important;
    }

    .print-break {
        page-break-inside: avoid;
    }

    a {
        text-decoration: none !important;
    }
}

</style>


<?php require_once '../../includes/sidebar.php'; ?>


<main class="main-content">


    <!-- =========================================================
         MOBILE HEADER
    ========================================================== -->

<div class="
        report-page
        p-5
        md:p-8
        lg:p-10
    ">


        <!-- =====================================================
             TOP ACTION
        ====================================================== -->

        <div class="
            no-print
            flex
            flex-col
            sm:flex-row
            sm:items-center
            sm:justify-between
            gap-3
            mb-6
        ">


            <a
                href="/pages/titipan/view.php?id=<?= 
                    (int) (
                        $items[0]['product_id']
                        ?? 0
                    )
                ?>"
                class="
                    inline-flex
                    items-center
                    gap-2
                    text-sm
                    text-neutral-500
                    hover:text-neutral-900
                "
                onclick="
                    if (!this.href.includes('id=0')) {
                        return true;
                    }
                    return false;
                "
            >

                <i
                    data-lucide="arrow-left"
                    class="w-4 h-4"
                ></i>

                Kembali

            </a>


            <button
                type="button"
                onclick="window.print()"
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
                    data-lucide="printer"
                    class="w-4 h-4"
                ></i>

                Cetak / Simpan PDF

            </button>

        </div>


        <!-- =====================================================
             FILTER
        ====================================================== -->

        <div class="
            no-print
            bento-card
            p-5
            mb-6
        ">

            <form
                method="GET"
                class="
                    grid
                    grid-cols-1
                    md:grid-cols-3
                    gap-4
                    items-end
                "
            >

                <input
                    type="hidden"
                    name="consignor_id"
                    value="<?= $consignorId ?>"
                >


                <div>

                    <label class="
                        block
                        text-sm
                        font-medium
                        text-neutral-700
                        mb-2
                    ">
                        Dari
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        value="<?= e($dateFrom) ?>"
                        class="
                            w-full
                            px-4
                            py-3
                            rounded-xl
                            border
                            border-neutral-200
                            text-sm
                            outline-none
                            focus:border-neutral-400
                        "
                    >

                </div>


                <div>

                    <label class="
                        block
                        text-sm
                        font-medium
                        text-neutral-700
                        mb-2
                    ">
                        Sampai
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        value="<?= e($dateTo) ?>"
                        class="
                            w-full
                            px-4
                            py-3
                            rounded-xl
                            border
                            border-neutral-200
                            text-sm
                            outline-none
                            focus:border-neutral-400
                        "
                    >

                </div>


                <button
                    type="submit"
                    class="
                        w-full
                        md:w-auto
                        px-5
                        py-3
                        rounded-xl
                        bg-neutral-900
                        text-white
                        text-sm
                        font-medium
                    "
                >
                    Tampilkan Laporan
                </button>

            </form>

        </div>


        <!-- =====================================================
             REPORT
        ====================================================== -->

        <div class="
            report-paper
            bg-white
            border
            border-neutral-200
            rounded-3xl
            shadow-sm
            overflow-hidden
        ">


            <!-- HEADER LAPORAN -->

            <div class="
                p-6
                md:p-8
                border-b
                border-neutral-200
            ">

                <div class="
                    flex
                    flex-col
                    md:flex-row
                    md:items-start
                    md:justify-between
                    gap-6
                ">


                    <div>

                        <div class="
                            text-xl
                            font-bold
                            tracking-tight
                            text-neutral-900
                        ">
                            RE-STOCK
                        </div>

                        <div class="
                            text-xs
                            text-neutral-400
                            mt-1
                        ">
                            BY REQRA
                        </div>

                    </div>


                    <div class="
                        md:text-right
                    ">

                        <h1 class="
                            text-xl
                            md:text-2xl
                            font-semibold
                            text-neutral-900
                        ">
                            Laporan Penjualan Titipan
                        </h1>

                        <p class="
                            text-sm
                            text-neutral-500
                            mt-1
                        ">
                            Rekap penjualan dan hak penitip
                        </p>

                    </div>

                </div>


                <!-- INFO PENITIP -->

                <div class="
                    mt-8
                    grid
                    grid-cols-1
                    md:grid-cols-2
                    gap-5
                ">

                    <div>

                        <div class="
                            text-xs
                            text-neutral-400
                            uppercase
                            tracking-wide
                        ">
                            Penitip
                        </div>

                        <div class="
                            text-base
                            font-semibold
                            text-neutral-900
                            mt-1
                        ">
                            <?= e($consignor['name']) ?>
                        </div>

                        <?php if (!empty($consignor['phone'])): ?>

                            <div class="
                                text-sm
                                text-neutral-500
                                mt-1
                            ">
                                <?= e($consignor['phone']) ?>
                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="md:text-right">

                        <div class="
                            text-xs
                            text-neutral-400
                            uppercase
                            tracking-wide
                        ">
                            Periode
                        </div>

                        <div class="
                            text-base
                            font-semibold
                            text-neutral-900
                            mt-1
                        ">

                            <?= date(
                                'd M Y',
                                strtotime($dateFrom)
                            ) ?>

                            -

                            <?= date(
                                'd M Y',
                                strtotime($dateTo)
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 SUMMARY
            ================================================== -->

            <div class="
                p-6
                md:p-8
                grid
                grid-cols-2
                lg:grid-cols-4
                gap-3
            ">


                <div class="
                    rounded-2xl
                    bg-neutral-50
                    p-4
                ">

                    <div class="
                        text-xs
                        text-neutral-500
                    ">
                        Transaksi
                    </div>

                    <div class="
                        text-xl
                        font-semibold
                        mt-2
                    ">
                        <?= number_format(
                            $totalTransactions,
                            0,
                            ',',
                            '.'
                        ) ?>
                    </div>

                </div>


                <div class="
                    rounded-2xl
                    bg-neutral-50
                    p-4
                ">

                    <div class="
                        text-xs
                        text-neutral-500
                    ">
                        Barang Terjual
                    </div>

                    <div class="
                        text-xl
                        font-semibold
                        mt-2
                    ">
                        <?= number_format(
                            $totalQty,
                            0,
                            ',',
                            '.'
                        ) ?>
                    </div>

                </div>


                <div class="
                    rounded-2xl
                    bg-neutral-50
                    p-4
                ">

                    <div class="
                        text-xs
                        text-neutral-500
                    ">
                        Total Penjualan
                    </div>

                    <div class="
                        text-base
                        md:text-xl
                        font-semibold
                        mt-2
                    ">
                        <?= rupiah($totalSales) ?>
                    </div>

                </div>


                <div class="
                    rounded-2xl
                    bg-neutral-900
                    text-white
                    p-4
                ">

                    <div class="
                        text-xs
                        text-neutral-300
                    ">
                        Hak Penitip
                    </div>

                    <div class="
                        text-base
                        md:text-xl
                        font-semibold
                        mt-2
                    ">
                        <?= rupiah($totalConsignor) ?>
                    </div>

                </div>

            </div>


            <!-- =================================================
                 TABLE
            ================================================== -->

            <div class="px-6 md:px-8 pb-8">

                <div class="
                    overflow-x-auto
                ">

                    <table
                        class="
                            print-table
                            w-full
                            text-sm
                        "
                    >

                        <thead>

                            <tr class="
                                border-b
                                border-neutral-200
                                text-left
                            ">

                                <th class="
                                    py-3
                                    pr-4
                                    font-medium
                                    text-neutral-500
                                ">
                                    Tanggal
                                </th>

                                <th class="
                                    py-3
                                    pr-4
                                    font-medium
                                    text-neutral-500
                                ">
                                    Barang
                                </th>

                                <th class="
                                    py-3
                                    pr-4
                                    text-right
                                    font-medium
                                    text-neutral-500
                                ">
                                    Qty
                                </th>

                                <th class="
                                    py-3
                                    pr-4
                                    text-right
                                    font-medium
                                    text-neutral-500
                                ">
                                    Harga
                                </th>

                                <th class="
                                    py-3
                                    pr-4
                                    text-right
                                    font-medium
                                    text-neutral-500
                                ">
                                    Total
                                </th>

                                <th class="
                                    py-3
                                    pr-4
                                    text-right
                                    font-medium
                                    text-neutral-500
                                ">
                                    Fee Toko
                                </th>

                                <th class="
                                    py-3
                                    text-right
                                    font-medium
                                    text-neutral-500
                                ">
                                    Hak Penitip
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php if (empty($items)): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="
                                        py-12
                                        text-center
                                        text-neutral-400
                                    "
                                >

                                    Belum ada penjualan
                                    pada periode ini.

                                </td>

                            </tr>

                        <?php else: ?>


                            <?php foreach ($items as $item): ?>

                                <tr class="
                                    border-b
                                    border-neutral-100
                                ">

                                    <td class="
                                        py-3
                                        pr-4
                                        whitespace-nowrap
                                        text-neutral-500
                                    ">

                                        <?= date(
                                            'd/m/Y',
                                            strtotime(
                                                $item['sale_date']
                                            )
                                        ) ?>

                                    </td>


                                    <td class="py-3 pr-4">

                                        <div class="
                                            font-medium
                                            text-neutral-900
                                        ">
                                            <?= e(
                                                $item['product_name']
                                            ) ?>
                                        </div>

                                        <?php if (!empty($item['sku'])): ?>

                                            <div class="
                                                text-xs
                                                text-neutral-400
                                                mt-1
                                            ">
                                                <?= e(
                                                    $item['sku']
                                                ) ?>
                                            </div>

                                        <?php endif; ?>


                                        <div class="
                                            text-xs
                                            text-neutral-400
                                            mt-1
                                        ">
                                            <?= e(
                                                $item['invoice_number']
                                            ) ?>
                                        </div>

                                    </td>


                                    <td class="
                                        py-3
                                        pr-4
                                        text-right
                                    ">
                                        <?= number_format(
                                            (int) $item['quantity'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>
                                    </td>


                                    <td class="
                                        py-3
                                        pr-4
                                        text-right
                                        whitespace-nowrap
                                    ">
                                        <?= rupiah(
                                            $item['selling_price']
                                        ) ?>
                                    </td>


                                    <td class="
                                        py-3
                                        pr-4
                                        text-right
                                        whitespace-nowrap
                                    ">
                                        <?= rupiah(
                                            $item['subtotal']
                                        ) ?>
                                    </td>


                                    <td class="
                                        py-3
                                        pr-4
                                        text-right
                                        whitespace-nowrap
                                        text-neutral-500
                                    ">
                                        <?= rupiah(
                                            $item['store_amount']
                                        ) ?>
                                    </td>


                                    <td class="
                                        py-3
                                        text-right
                                        whitespace-nowrap
                                        font-medium
                                    ">
                                        <?= rupiah(
                                            $item['consignor_amount']
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>


                        <?php endif; ?>

                        </tbody>


                        <?php if (!empty($items)): ?>

                        <tfoot>

                            <tr class="
                                border-t
                                border-neutral-300
                            ">

                                <td
                                    colspan="2"
                                    class="
                                        py-4
                                        font-semibold
                                    "
                                >
                                    TOTAL
                                </td>

                                <td class="
                                    py-4
                                    text-right
                                    font-semibold
                                ">
                                    <?= number_format(
                                        $totalQty,
                                        0,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>

                                <td></td>

                                <td class="
                                    py-4
                                    text-right
                                    font-semibold
                                ">
                                    <?= rupiah(
                                        $totalSales
                                    ) ?>
                                </td>

                                <td class="
                                    py-4
                                    text-right
                                    font-semibold
                                ">
                                    <?= rupiah(
                                        $totalStoreFee
                                    ) ?>
                                </td>

                                <td class="
                                    py-4
                                    text-right
                                    font-semibold
                                ">
                                    <?= rupiah(
                                        $totalConsignor
                                    ) ?>
                                </td>

                            </tr>

                        </tfoot>

                        <?php endif; ?>

                    </table>

                </div>

            </div>


            <!-- =================================================
                 PAYMENT SUMMARY
            ================================================== -->

            <div class="
                mx-6
                md:mx-8
                mb-8
                p-5
                md:p-6
                rounded-2xl
                border
                border-neutral-200
                print-break
            ">

                <div class="
                    flex
                    flex-col
                    md:flex-row
                    md:items-start
                    md:justify-between
                    gap-6
                ">


                    <div>

                        <div class="
                            text-sm
                            font-semibold
                            text-neutral-900
                        ">
                            Rekap Pembayaran
                        </div>

                        <div class="
                            text-xs
                            text-neutral-500
                            mt-1
                        ">
                            Berdasarkan pembayaran yang
                            tercatat di sistem.
                        </div>

                    </div>


                    <div class="
                        w-full
                        md:w-80
                        space-y-3
                    ">


                        <div class="
                            flex
                            justify-between
                            gap-4
                            text-sm
                        ">

                            <span class="text-neutral-500">
                                Hak Penitip
                            </span>

                            <strong>
                                <?= rupiah(
                                    $totalConsignor
                                ) ?>
                            </strong>

                        </div>


                        <div class="
                            flex
                            justify-between
                            gap-4
                            text-sm
                        ">

                            <span class="text-neutral-500">
                                Sudah Dibayar
                            </span>

                            <strong>
                                <?= rupiah(
                                    $totalPaid
                                ) ?>
                            </strong>

                        </div>


                        <div class="
                            border-t
                            border-neutral-200
                            pt-3
                            flex
                            justify-between
                            gap-4
                        ">

                            <span class="
                                font-medium
                            ">
                                Sisa Hak Penitip
                            </span>

                            <strong class="
                                text-lg
                            ">
                                <?= rupiah(
                                    $totalUnpaid
                                ) ?>
                            </strong>

                        </div>


                        <div class="
                            pt-1
                            text-right
                        ">

                            <span class="
                                inline-flex
                                items-center
                                px-3
                                py-1.5
                                rounded-full
                                bg-neutral-100
                                text-xs
                                font-medium
                            ">
                                <?= e($paymentStatus) ?>
                            </span>

                        </div>

                    </div>

                </div>

            </div>


            <!-- FOOTER -->

            <div class="
                px-6
                md:px-8
                pb-8
                text-center
            ">

                <p class="
                    text-xs
                    text-neutral-400
                ">
                    Laporan ini dibuat dari data transaksi
                    penjualan barang titipan di RESTOCK.
                </p>

                <p class="
                    text-xs
                    text-neutral-400
                    mt-1
                ">
                    Dicetak pada
                    <?= date(
                        'd M Y H:i'
                    ) ?>
                </p>

            </div>

        </div>

    </div>

</main>


<script>

if (
    typeof lucide !== 'undefined'
) {
    lucide.createIcons();
}

</script>


<?php require_once '../../includes/footer.php'; ?>