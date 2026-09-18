<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/auth.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) {
    http_response_code(403);
    exit('Store aktif tidak ditemukan.');
}

$pageTitle = 'Bukti Pengeluaran';


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


/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {

    header(
        'Location: /pages/pengeluaran/?error=notfound'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DATA PENGELUARAN
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
        e.created_at,

        ec.name AS category_name,

        u.name AS created_by_name

    FROM expenses e

    LEFT JOIN expense_categories ec
        ON ec.id = e.category_id

    LEFT JOIN users u
        ON u.id = e.created_by

    WHERE e.id = :expense_id
      AND e.store_id = :expense_store_id

    LIMIT 1
");

$stmt->execute([
    ':expense_id' => $id,
    ':expense_store_id' => $storeId
]);

$expense = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$expense) {

    header(
        'Location: /pages/pengeluaran/?error=notfound'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| PAYMENT LABEL
|--------------------------------------------------------------------------
*/

$paymentLabels = [
    'CASH'     => 'Tunai',
    'QRIS'     => 'QRIS',
    'TRANSFER' => 'Transfer',
    'OTHER'    => 'Lainnya',
];

$paymentLabel =
    $paymentLabels[
        $expense['payment_method']
    ]
    ??
    $expense['payment_method']
    ??
    '-';


/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

$expenseDate = '-';
$expenseTime = '-';

if (!empty($expense['expense_date'])) {

    $timestamp = strtotime(
        $expense['expense_date']
    );

    if ($timestamp !== false) {

        $expenseDate = date(
            'd M Y',
            $timestamp
        );

        $expenseTime = date(
            'H:i',
            $timestamp
        );
    }
}


/*
|--------------------------------------------------------------------------
| CREATED AT
|--------------------------------------------------------------------------
*/

$createdAt = '-';

if (!empty($expense['created_at'])) {

    $timestamp = strtotime(
        $expense['created_at']
    );

    if ($timestamp !== false) {

        $createdAt = date(
            'd M Y, H:i',
            $timestamp
        );
    }
}


/*
|--------------------------------------------------------------------------
| DATA DISPLAY
|--------------------------------------------------------------------------
*/

$categoryName =
    !empty($expense['category_name'])
        ? $expense['category_name']
        : 'Tanpa Kategori';

$createdBy =
    !empty($expense['created_by_name'])
        ? $expense['created_by_name']
        : 'Tidak diketahui';

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
        Bukti Pengeluaran #<?= (int) $expense['id'] ?>
    </title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f5f5;
            color: #171717;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .invoice-page {
            width: 100%;
            max-width: 794px;
            min-height: 1123px;
            margin: 32px auto;
            background: #ffffff;
            padding: 56px;
            box-shadow:
                0 10px 40px rgba(0, 0, 0, 0.06);
        }

        .invoice-divider {
            border-top: 1px solid #e5e5e5;
        }

        .print-button {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 100;
        }

        @media (max-width: 640px) {

            body {
                background: #ffffff;
            }

            .invoice-page {
                min-height: auto;
                margin: 0;
                padding: 28px 20px 100px;
                box-shadow: none;
            }

            .print-button {
                right: 16px;
                bottom: 16px;
                left: 16px;
            }

            .print-button button {
                width: 100%;
            }
        }

        @media print {

            @page {
                size: A4;
                margin: 0;
            }

            body {
                background: #ffffff;
            }

            .invoice-page {
                width: 210mm;
                min-height: 297mm;
                margin: 0;
                padding: 18mm;
                box-shadow: none;
            }

            .print-button {
                display: none !important;
            }

            .no-print {
                display: none !important;
            }
        }

    </style>

</head>


<body>


<!-- =========================================================
     PRINT BUTTON
========================================================== -->

<div class="print-button">

    <button
        type="button"
        onclick="window.print()"
        class="
            inline-flex
            items-center
            justify-center
            gap-2
            px-5
            py-3
            rounded-xl
            bg-neutral-900
            text-white
            text-sm
            font-medium
            shadow-lg
            hover:bg-neutral-800
            transition
        "
    >

        <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect
                width="12"
                height="8"
                x="6"
                y="14"
            ></rect>
        </svg>

        Cetak Bukti

    </button>

</div>


<!-- =========================================================
     INVOICE
========================================================== -->

<div class="invoice-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div
        class="
            flex
            items-start
            justify-between
            gap-6
            pb-8
        "
    >

        <div>

            <div
                class="
                    text-2xl
                    font-bold
                    tracking-tight
                    text-neutral-900
                "
            >
                RESTOCK
            </div>

            <div
                class="
                    text-xs
                    text-neutral-400
                    mt-1
                "
            >
                by REQRA
            </div>

        </div>


        <div class="text-right">

            <div
                class="
                    text-xs
                    uppercase
                    tracking-[0.18em]
                    text-neutral-400
                "
            >
                Bukti Pengeluaran
            </div>

            <div
                class="
                    text-lg
                    font-semibold
                    text-neutral-900
                    mt-1
                "
            >
                #<?= (int) $expense['id'] ?>
            </div>

        </div>

    </div>


    <div class="invoice-divider"></div>


    <!-- =====================================================
         DATE / META
    ====================================================== -->

    <div
        class="
            grid
            grid-cols-2
            md:grid-cols-4
            gap-6
            py-7
        "
    >

        <!-- TANGGAL -->

        <div>

            <div
                class="
                    text-[10px]
                    uppercase
                    tracking-[0.15em]
                    text-neutral-400
                "
            >
                Tanggal
            </div>

            <div
                class="
                    text-sm
                    font-medium
                    text-neutral-900
                    mt-2
                "
            >
                <?= e($expenseDate) ?>
            </div>

        </div>


        <!-- WAKTU -->

        <div>

            <div
                class="
                    text-[10px]
                    uppercase
                    tracking-[0.15em]
                    text-neutral-400
                "
            >
                Waktu
            </div>

            <div
                class="
                    text-sm
                    font-medium
                    text-neutral-900
                    mt-2
                "
            >
                <?= e($expenseTime) ?>
            </div>

        </div>


        <!-- KATEGORI -->

        <div>

            <div
                class="
                    text-[10px]
                    uppercase
                    tracking-[0.15em]
                    text-neutral-400
                "
            >
                Kategori
            </div>

            <div
                class="
                    text-sm
                    font-medium
                    text-neutral-900
                    mt-2
                "
            >
                <?= e($categoryName) ?>
            </div>

        </div>


        <!-- PEMBAYARAN -->

        <div>

            <div
                class="
                    text-[10px]
                    uppercase
                    tracking-[0.15em]
                    text-neutral-400
                "
            >
                Pembayaran
            </div>

            <div
                class="
                    text-sm
                    font-medium
                    text-neutral-900
                    mt-2
                "
            >
                <?= e($paymentLabel) ?>
            </div>

        </div>

    </div>


    <div class="invoice-divider"></div>


    <!-- =====================================================
         DESCRIPTION
    ====================================================== -->

    <div class="py-8">

        <div
            class="
                text-[10px]
                uppercase
                tracking-[0.15em]
                text-neutral-400
                mb-3
            "
        >
            Keterangan Pengeluaran
        </div>

        <div
            class="
                text-lg
                md:text-xl
                font-medium
                leading-relaxed
                text-neutral-900
            "
        >
            <?= e($expense['description']) ?>
        </div>

    </div>


    <!-- =====================================================
         AMOUNT
    ====================================================== -->

    <div
        class="
            rounded-2xl
            bg-neutral-50
            border
            border-neutral-100
            p-6
            md:p-7
        "
    >

        <div
            class="
                text-xs
                text-neutral-500
            "
        >
            Total Pengeluaran
        </div>

        <div
            class="
                text-3xl
                md:text-4xl
                font-semibold
                tracking-tight
                text-neutral-900
                mt-2
            "
        >
            <?= rupiah($expense['amount']) ?>
        </div>

    </div>


    <!-- =====================================================
         NOTES
    ====================================================== -->

    <?php if (!empty($expense['notes'])): ?>

        <div class="pt-8">

            <div
                class="
                    text-[10px]
                    uppercase
                    tracking-[0.15em]
                    text-neutral-400
                    mb-3
                "
            >
                Catatan
            </div>

            <div
                class="
                    text-sm
                    leading-6
                    text-neutral-600
                    whitespace-pre-line
                "
            >
                <?= e($expense['notes']) ?>
            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         CREATED INFO
    ====================================================== -->

    <div
        class="
            mt-10
            pt-6
            border-t
            border-neutral-100
            grid
            grid-cols-1
            sm:grid-cols-2
            gap-6
        "
    >

        <div>

            <div
                class="
                    text-[10px]
                    uppercase
                    tracking-[0.15em]
                    text-neutral-400
                "
            >
                Dicatat Oleh
            </div>

            <div
                class="
                    text-sm
                    font-medium
                    text-neutral-900
                    mt-2
                "
            >
                <?= e($createdBy) ?>
            </div>

        </div>


        <div class="sm:text-right">

            <div
                class="
                    text-[10px]
                    uppercase
                    tracking-[0.15em]
                    text-neutral-400
                "
            >
                Dicatat Pada
            </div>

            <div
                class="
                    text-sm
                    font-medium
                    text-neutral-900
                    mt-2
                "
            >
                <?= e($createdAt) ?>
            </div>

        </div>

    </div>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <div
        class="
            mt-14
            pt-6
            border-t
            border-neutral-100
            text-center
        "
    >

        <div
            class="
                text-xs
                text-neutral-400
            "
        >
            Dokumen ini merupakan bukti pencatatan
            pengeluaran pada sistem RESTOCK.
        </div>

        <div
            class="
                text-[10px]
                text-neutral-300
                mt-2
            "
        >
            RESTOCK by REQRA
        </div>

    </div>

</div>


</body>

</html>