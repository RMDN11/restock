<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) {
    http_response_code(403);
    exit('Store aktif tidak ditemukan.');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Helper
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
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = 'Pengeluaran';

/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$dateFrom = trim(
    $_GET['date_from'] ?? date('Y-m-01')
);

$dateTo = trim(
    $_GET['date_to'] ?? date('Y-m-d')
);

/*
|--------------------------------------------------------------------------
| Category
|--------------------------------------------------------------------------
|
| 0 / ALL        = Semua
| CONSIGNOR      = Pembayaran Penitip
| angka          = expense_categories.id
|
*/

$categoryFilter = $_GET['category'] ?? '0';

/*
|--------------------------------------------------------------------------
| Validasi tanggal
|--------------------------------------------------------------------------
*/

if (
    $dateFrom !== ''
    &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $dateFrom
    )
) {
    $dateFrom = date('Y-m-01');
}

if (
    $dateTo !== ''
    &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $dateTo
    )
) {
    $dateTo = date('Y-m-d');
}

/*
|--------------------------------------------------------------------------
| Kategori
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name FROM expense_categories
    WHERE store_id = :categories_store_id
      AND status = 'ACTIVE'
    ORDER BY name ASC
");
$stmt->execute([':categories_store_id' => $storeId]);
$categories = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| QUERY DATA
|--------------------------------------------------------------------------
|
| Sumber:
|
| 1. expenses
| 2. consignor_settlements
|
| Keduanya digabung dengan UNION ALL.
|
*/

$sql = "
    SELECT
        e.id,
        e.expense_date,
        e.description,
        e.amount,
        e.payment_method,
        e.notes,

        ec.name AS category_name,

        'EXPENSE' AS record_type,

        NULL AS settlement_id,
        NULL AS settlement_number

    FROM expenses e

    LEFT JOIN expense_categories ec
        ON ec.id = e.category_id
       AND ec.store_id = e.store_id

    WHERE e.store_id = :expenses_store_id
";

$params = [
    ':expenses_store_id' => $storeId,
    ':settlements_store_id' => $storeId
];

/*
|--------------------------------------------------------------------------
| Filter tanggal - expenses
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $sql .= "
        AND e.expense_date >= :expense_date_from
    ";

    $params[':expense_date_from'] =
        $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {

    $sql .= "
        AND e.expense_date <= :expense_date_to
    ";

    $params[':expense_date_to'] =
        $dateTo . ' 23:59:59';
}

/*
|--------------------------------------------------------------------------
| Filter kategori - expenses
|--------------------------------------------------------------------------
|
| Jika CONSIGNOR dipilih, pengeluaran biasa tidak ditampilkan.
|
*/

if ($categoryFilter === 'CONSIGNOR') {

    $sql .= "
        AND 1 = 0
    ";

} elseif (
    $categoryFilter !== '0'
    &&
    ctype_digit((string) $categoryFilter)
) {

    $sql .= "
        AND e.category_id = :expense_category_id
    ";

    $params[':expense_category_id'] =
        (int) $categoryFilter;
}

/*
|--------------------------------------------------------------------------
| Search - expenses
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            e.description LIKE :expense_search_description
            OR ec.name LIKE :expense_search_category
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $params[':expense_search_description'] =
        $searchValue;

    $params[':expense_search_category'] =
        $searchValue;
}

/*
|--------------------------------------------------------------------------
| UNION PEMBAYARAN PENITIP
|--------------------------------------------------------------------------
*/

$sql .= "

    UNION ALL

    SELECT
        cs.id,
        cs.settlement_date AS expense_date,

        CONCAT(
            'Pembayaran Penitip - ',
            c.name
        ) AS description,

        cs.total_amount AS amount,

        cs.payment_method,

        cs.notes,

        'Pembayaran Penitip' AS category_name,

        'SETTLEMENT' AS record_type,

        cs.id AS settlement_id,
        cs.settlement_number

    FROM consignor_settlements cs

    INNER JOIN consignors c
        ON c.id = cs.consignor_id
       AND c.store_id = cs.store_id

    WHERE cs.store_id = :settlements_store_id
";

/*
|--------------------------------------------------------------------------
| Filter tanggal - settlement
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $sql .= "
        AND cs.settlement_date >= :settlement_date_from
    ";

    $params[':settlement_date_from'] =
        $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {

    $sql .= "
        AND cs.settlement_date <= :settlement_date_to
    ";

    $params[':settlement_date_to'] =
        $dateTo . ' 23:59:59';
}

/*
|--------------------------------------------------------------------------
| Filter kategori - settlement
|--------------------------------------------------------------------------
|
| Settlement hanya muncul:
|
| - Semua
| - Pembayaran Penitip
|
| Tidak muncul ketika user memilih kategori
| pengeluaran biasa.
|
*/

if (
    $categoryFilter !== '0'
    &&
    $categoryFilter !== 'CONSIGNOR'
) {

    $sql .= "
        AND 1 = 0
    ";
}

/*
|--------------------------------------------------------------------------
| Search - settlement
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            c.name LIKE :settlement_search_name
            OR cs.settlement_number LIKE :settlement_search_number
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $params[':settlement_search_name'] =
        $searchValue;

    $params[':settlement_search_number'] =
        $searchValue;
}

/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$sql .= "

    ORDER BY
        expense_date DESC,
        id DESC
";

/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$expenses = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Total hasil filter
|--------------------------------------------------------------------------
*/

$filteredTotal = 0;

foreach ($expenses as $expense) {

    $filteredTotal +=
        (float) $expense['amount'];
}

/*
|--------------------------------------------------------------------------
| TOTAL HARI INI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS total
    FROM (
        SELECT amount AS total_amount FROM expenses
        WHERE store_id = :today_expenses_store_id
          AND expense_date >= CURDATE()
          AND expense_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        UNION ALL
        SELECT total_amount FROM consignor_settlements
        WHERE store_id = :today_settlements_store_id
          AND settlement_date >= CURDATE()
          AND settlement_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ) x
");
$stmt->execute([
    ':today_expenses_store_id' => $storeId,
    ':today_settlements_store_id' => $storeId
]);
$totalToday = (float) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| TOTAL BULAN INI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS total
    FROM (
        SELECT amount AS total_amount FROM expenses
        WHERE store_id = :month_expenses_store_id
          AND expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
          AND expense_date < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)
        UNION ALL
        SELECT total_amount FROM consignor_settlements
        WHERE store_id = :month_settlements_store_id
          AND settlement_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
          AND settlement_date < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)
    ) x
");
$stmt->execute([
    ':month_expenses_store_id' => $storeId,
    ':month_settlements_store_id' => $storeId
]);
$totalMonth = (float) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Payment labels
|--------------------------------------------------------------------------
*/

$paymentLabels = [
    'CASH' => 'Cash',
    'QRIS' => 'QRIS',
    'TRANSFER' => 'Transfer',
    'OTHER' => 'Lainnya'
];

/*
|--------------------------------------------------------------------------
| Flash
|--------------------------------------------------------------------------
*/

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

include '../../includes/header.php';
include '../../includes/sidebar.php';

?>

<div class="main-content">

    <!-- =========================================================
         MOBILE HEADER
    ========================================================== -->

<!-- =========================================================
         CONTENT
    ========================================================== -->

    <main
    class="
        p-4
        md:p-8
    "
>

        <!-- =====================================================
             HEADER
        ====================================================== -->

        <div
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
            Pengeluaran
        </h1>

        <p
            class="
                text-sm
                text-neutral-500
                mt-2
            "
        >
            Catat dan pantau semua uang yang keluar dari toko.
        </p>

    </div>

    <div
        class="
            flex
            flex-col
            sm:flex-row
            gap-2
            md:mt-8
        "
    >

        <a
            href="/pages/pengeluaran/kategori.php"
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
                data-lucide="layers-3"
                class="w-4 h-4"
            ></i>

            Kategori
        </a>

        <a
            href="/pages/pengeluaran/create.php"
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
                data-lucide="plus"
                class="w-4 h-4"
            ></i>

            Tambah Pengeluaran
        </a>

    </div>

</div>

        <!-- =====================================================
             FLASH MESSAGE
        ====================================================== -->

        <?php if ($success === 'created'): ?>

            <div
                class="
                    mb-6
                    rounded-xl
                    border
                    border-green-200
                    bg-green-50
                    px-4
                    py-3
                    text-sm
                    text-green-700
                "
            >
                Pengeluaran berhasil ditambahkan.
            </div>

        <?php elseif ($success === 'updated'): ?>

            <div
                class="
                    mb-6
                    rounded-xl
                    border
                    border-green-200
                    bg-green-50
                    px-4
                    py-3
                    text-sm
                    text-green-700
                "
            >
                Pengeluaran berhasil diperbarui.
            </div>

        <?php elseif ($success === 'deleted'): ?>

            <div
                class="
                    mb-6
                    rounded-xl
                    border
                    border-green-200
                    bg-green-50
                    px-4
                    py-3
                    text-sm
                    text-green-700
                "
            >
                Pengeluaran berhasil dihapus.
            </div>

        <?php elseif ($error === 'csrf'): ?>

            <div
                class="
                    mb-6
                    rounded-xl
                    border
                    border-red-200
                    bg-red-50
                    px-4
                    py-3
                    text-sm
                    text-red-700
                "
            >
                Permintaan tidak valid. Silakan coba lagi.
            </div>

        <?php elseif ($error === 'delete'): ?>

            <div
                class="
                    mb-6
                    rounded-xl
                    border
                    border-red-200
                    bg-red-50
                    px-4
                    py-3
                    text-sm
                    text-red-700
                "
            >
                Pengeluaran gagal dihapus.
            </div>

        <?php elseif ($error): ?>

            <div
                class="
                    mb-6
                    rounded-xl
                    border
                    border-red-200
                    bg-red-50
                    px-4
                    py-3
                    text-sm
                    text-red-700
                "
            >
                Terjadi kesalahan. Silakan coba lagi.
            </div>

        <?php endif; ?>


        <!-- =====================================================
             SUMMARY
        ====================================================== -->

        <div
            class="
                grid
                grid-cols-1
                sm:grid-cols-2
                lg:grid-cols-3
                gap-4
                mb-6
            "
        >

            <!-- HARI INI -->

            <div class="bento-card p-5">

                <div
                    class="
                        text-sm
                        text-neutral-500
                    "
                >
                    Pengeluaran Hari Ini
                </div>

                <div
                    class="
                        text-2xl
                        font-semibold
                        tracking-tight
                        mt-2
                    "
                >
                    <?= rupiah($totalToday) ?>
                </div>

            </div>


            <!-- BULAN INI -->

            <div class="bento-card p-5">

                <div
                    class="
                        text-sm
                        text-neutral-500
                    "
                >
                    Pengeluaran Bulan Ini
                </div>

                <div
                    class="
                        text-2xl
                        font-semibold
                        tracking-tight
                        mt-2
                    "
                >
                    <?= rupiah($totalMonth) ?>
                </div>

            </div>


            <!-- HASIL FILTER -->

            <div class="bento-card p-5">

                <div
                    class="
                        text-sm
                        text-neutral-500
                    "
                >
                    Hasil Filter
                </div>

                <div
                    class="
                        text-2xl
                        font-semibold
                        tracking-tight
                        mt-2
                    "
                >
                    <?= rupiah($filteredTotal) ?>
                </div>

                <div
                    class="
                        text-xs
                        text-neutral-400
                        mt-1
                    "
                >
                    <?= count($expenses) ?> transaksi
                </div>

            </div>

        </div>


        <!-- =====================================================
             FILTER
        ====================================================== -->

        <div class="bento-card p-4 mb-6">

            <form
                method="GET"
                class="
                    grid
                    grid-cols-1
                    md:grid-cols-2
                    lg:grid-cols-5
                    gap-3
                "
            >

                <!-- SEARCH -->

                <div class="lg:col-span-2">

                    <input
                        type="text"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Cari pengeluaran, penitip, invoice..."
                        class="
                            w-full
                            px-4
                            py-2.5
                            rounded-xl
                            border
                            border-neutral-200
                            outline-none
                            focus:border-neutral-400
                            text-sm
                        "
                    >

                </div>


                <!-- DARI -->

                <div>

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
                            outline-none
                            focus:border-neutral-400
                            text-sm
                        "
                    >

                </div>


                <!-- SAMPAI -->

                <div>

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
                            outline-none
                            focus:border-neutral-400
                            text-sm
                        "
                    >

                </div>


                <!-- KATEGORI -->

                <div>

                    <select
                        name="category"
                        class="
                            w-full
                            px-4
                            py-2.5
                            rounded-xl
                            border
                            border-neutral-200
                            bg-white
                            outline-none
                            text-sm
                        "
                    >

                        <option value="0">
                            Semua Kategori
                        </option>

                        <option
                            value="CONSIGNOR"
                            <?= $categoryFilter === 'CONSIGNOR'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Pembayaran Penitip
                        </option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= $categoryFilter === (string) $category['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e($category['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- BUTTON -->

                <div
                    class="
                        flex
                        gap-2
                        md:col-span-2
                        lg:col-span-5
                    "
                >

                    <button
                        type="submit"
                        class="
                            flex-1
                            md:flex-none
                            px-5
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
                        Filter
                    </button>

                    <a
                        href="/pages/pengeluaran/"
                        class="
                            px-5
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
                            inline-flex
                            items-center
                            justify-center
                        "
                    >
                        Reset
                    </a>

                </div>

            </form>

        </div>


        <!-- =====================================================
             DESKTOP TABLE
        ====================================================== -->

        <div
            class="
                hidden
                md:block
                bento-card
                overflow-hidden
            "
        >

            <div class="overflow-x-auto">

                <table class="w-full text-left">

                    <thead
                        class="
                            border-b
                            border-neutral-100
                        "
                    >

                        <tr
                            class="
                                text-xs
                                text-neutral-400
                                uppercase
                            "
                        >

                            <th class="px-6 py-4 font-medium">
                                Tanggal
                            </th>

                            <th class="px-6 py-4 font-medium">
                                Keterangan
                            </th>

                            <th class="px-6 py-4 font-medium">
                                Kategori
                            </th>

                            <th class="px-6 py-4 font-medium">
                                Metode
                            </th>

                            <th class="px-6 py-4 font-medium text-right">
                                Jumlah
                            </th>

                            <th class="px-6 py-4"></th>

                        </tr>

                    </thead>


                    <tbody
                        class="
                            divide-y
                            divide-neutral-100
                        "
                    >

                    <?php if (!$expenses): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="
                                    px-6
                                    py-16
                                    text-center
                                    text-sm
                                    text-neutral-400
                                "
                            >
                                Belum ada data pengeluaran.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($expenses as $expense): ?>

                            <?php
                            $isSettlement =
                                ($expense['record_type'] ?? '') === 'SETTLEMENT';

                            $paymentLabel =
                                $paymentLabels[
                                    $expense['payment_method']
                                ]
                                ?? $expense['payment_method'];
                            ?>

                            <tr
                                class="
                                    hover:bg-neutral-50
                                    transition
                                "
                            >

                                <!-- TANGGAL -->

                                <td class="px-6 py-5">

                                    <div
                                        class="
                                            text-sm
                                            font-medium
                                        "
                                    >
                                        <?= date(
                                            'd M Y',
                                            strtotime(
                                                $expense['expense_date']
                                            )
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
                                            'H:i',
                                            strtotime(
                                                $expense['expense_date']
                                            )
                                        ) ?>
                                    </div>

                                </td>


                                <!-- KETERANGAN -->

                                <td class="px-6 py-5">

                                    <div
                                        class="
                                            text-sm
                                            font-medium
                                            text-neutral-900
                                        "
                                    >
                                        <?= e(
                                            $expense['description']
                                        ) ?>
                                    </div>

                                    <?php if (
                                        $isSettlement
                                        &&
                                        !empty(
                                            $expense['settlement_number']
                                        )
                                    ): ?>

                                        <div
                                            class="
                                                text-xs
                                                text-neutral-400
                                                mt-1
                                            "
                                        >
                                            <?= e(
                                                $expense['settlement_number']
                                            ) ?>
                                        </div>

                                    <?php endif; ?>

                                </td>


                                <!-- KATEGORI -->

                                <td class="px-6 py-5">

                                    <?php if ($isSettlement): ?>

                                        <span
                                            class="
                                                inline-flex
                                                items-center
                                                px-2.5
                                                py-1
                                                rounded-lg
                                                bg-neutral-100
                                                text-neutral-600
                                                text-xs
                                                font-medium
                                            "
                                        >
                                            Pembayaran Penitip
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="
                                                text-sm
                                                text-neutral-600
                                            "
                                        >
                                            <?= e(
                                                $expense['category_name']
                                                    ?: 'Tanpa Kategori'
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- METODE -->

                                <td class="px-6 py-5">

                                    <span
                                        class="
                                            text-sm
                                            text-neutral-600
                                        "
                                    >
                                        <?= e($paymentLabel) ?>
                                    </span>

                                </td>


                                <!-- JUMLAH -->

                                <td
                                    class="
                                        px-6
                                        py-5
                                        text-right
                                    "
                                >

                                    <span
                                        class="
                                            text-sm
                                            font-semibold
                                            <?= $isSettlement
                                                ? 'text-neutral-900'
                                                : 'text-neutral-900'
                                            ?>
                                        "
                                    >
                                        <?= rupiah(
                                            $expense['amount']
                                        ) ?>
                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td class="px-6 py-5">

                                    <div
                                        class="
                                            flex
                                            items-center
                                            justify-end
                                            gap-1
                                        "
                                    >

                                        <?php if ($isSettlement): ?>

                                            <!-- DETAIL -->

                                            <a
                                                href="/pages/titipan/pembayaran-view.php?id=<?= (int) $expense['settlement_id'] ?>"
                                                title="Detail Pembayaran"
                                                class="
                                                    w-9
                                                    h-9
                                                    rounded-lg
                                                    flex
                                                    items-center
                                                    justify-center
                                                    text-neutral-400
                                                    hover:bg-neutral-100
                                                    hover:text-neutral-900
                                                    transition
                                                "
                                            >
                                                <i
                                                    data-lucide="eye"
                                                    class="w-4 h-4"
                                                ></i>
                                            </a>


                                            <!-- INVOICE -->

                                            <a
                                                href="/pages/titipan/invoice.php?id=<?= (int) $expense['settlement_id'] ?>"
                                                target="_blank"
                                                title="Invoice"
                                                class="
                                                    w-9
                                                    h-9
                                                    rounded-lg
                                                    flex
                                                    items-center
                                                    justify-center
                                                    text-neutral-400
                                                    hover:bg-neutral-100
                                                    hover:text-neutral-900
                                                    transition
                                                "
                                            >
                                                <i
                                                    data-lucide="file-text"
                                                    class="w-4 h-4"
                                                ></i>
                                            </a>

                                        <?php else: ?>

                                            <!-- DETAIL -->

                                            <a
                                                href="/pages/pengeluaran/view.php?id=<?= (int) $expense['id'] ?>"
                                                title="Detail"
                                                class="
                                                    w-9
                                                    h-9
                                                    rounded-lg
                                                    flex
                                                    items-center
                                                    justify-center
                                                    text-neutral-400
                                                    hover:bg-neutral-100
                                                    hover:text-neutral-900
                                                    transition
                                                "
                                            >
                                                <i
                                                    data-lucide="eye"
                                                    class="w-4 h-4"
                                                ></i>
                                            </a>


                                            <!-- EDIT -->

                                            <a
                                                href="/pages/pengeluaran/edit.php?id=<?= (int) $expense['id'] ?>"
                                                title="Edit"
                                                class="
                                                    w-9
                                                    h-9
                                                    rounded-lg
                                                    flex
                                                    items-center
                                                    justify-center
                                                    text-neutral-400
                                                    hover:bg-neutral-100
                                                    hover:text-neutral-900
                                                    transition
                                                "
                                            >
                                                <i
                                                    data-lucide="pencil"
                                                    class="w-4 h-4"
                                                ></i>
                                            </a>


                                            <!-- DELETE -->

                                            <form
                                                method="POST"
                                                action="/pages/pengeluaran/delete.php"
                                                onsubmit="
                                                    return confirm(
                                                        'Hapus pengeluaran ini? Data yang sudah dihapus tidak dapat dikembalikan.'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($csrfToken) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $expense['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    title="Hapus"
                                                    class="
                                                        w-9
                                                        h-9
                                                        rounded-lg
                                                        flex
                                                        items-center
                                                        justify-center
                                                        text-neutral-400
                                                        hover:bg-red-50
                                                        hover:text-red-600
                                                        transition
                                                    "
                                                >
                                                    <i
                                                        data-lucide="trash-2"
                                                        class="w-4 h-4"
                                                    ></i>
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =====================================================
             MOBILE LIST
        ====================================================== -->

        <div
            class="
                md:hidden
                space-y-3
            "
        >

            <?php if (!$expenses): ?>

                <div
                    class="
                        bento-card
                        p-10
                        text-center
                        text-sm
                        text-neutral-400
                    "
                >
                    Belum ada data pengeluaran.
                </div>

            <?php else: ?>

                <?php foreach ($expenses as $expense): ?>

                    <?php
                    $isSettlement =
                        ($expense['record_type'] ?? '') === 'SETTLEMENT';

                    $paymentLabel =
                        $paymentLabels[
                            $expense['payment_method']
                        ]
                        ?? $expense['payment_method'];
                    ?>

                    <div class="bento-card p-4">

                        <!-- TOP -->

                        <div
                            class="
                                flex
                                items-start
                                justify-between
                                gap-3
                            "
                        >

                            <div class="min-w-0">

                                <div
                                    class="
                                        text-xs
                                        text-neutral-400
                                    "
                                >
                                    <?= date(
                                        'd M Y · H:i',
                                        strtotime(
                                            $expense['expense_date']
                                        )
                                    ) ?>
                                </div>

                                <div
                                    class="
                                        text-sm
                                        font-semibold
                                        text-neutral-900
                                        mt-1
                                    "
                                >
                                    <?= e(
                                        $expense['description']
                                    ) ?>
                                </div>

                                <?php if (
                                    $isSettlement
                                    &&
                                    !empty(
                                        $expense['settlement_number']
                                    )
                                ): ?>

                                    <div
                                        class="
                                            text-xs
                                            text-neutral-400
                                            mt-1
                                        "
                                    >
                                        <?= e(
                                            $expense['settlement_number']
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- AMOUNT -->

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


                        <!-- META -->

                        <div
                            class="
                                flex
                                items-center
                                justify-between
                                gap-3
                                mt-4
                            "
                        >

                            <div class="flex flex-wrap gap-2">

                                <span
                                    class="
                                        inline-flex
                                        items-center
                                        px-2.5
                                        py-1
                                        rounded-lg
                                        bg-neutral-100
                                        text-neutral-600
                                        text-xs
                                    "
                                >
                                    <?= e(
                                        $expense['category_name']
                                            ?: 'Tanpa Kategori'
                                    ) ?>
                                </span>

                                <span
                                    class="
                                        inline-flex
                                        items-center
                                        px-2.5
                                        py-1
                                        rounded-lg
                                        border
                                        border-neutral-200
                                        text-neutral-500
                                        text-xs
                                    "
                                >
                                    <?= e($paymentLabel) ?>
                                </span>

                            </div>

                        </div>


                        <!-- ACTION -->

                        <div
                            class="
                                flex
                                justify-end
                                gap-2
                                mt-4
                                pt-4
                                border-t
                                border-neutral-100
                            "
                        >

                            <?php if ($isSettlement): ?>

                                <a
                                    href="/pages/titipan/pembayaran-view.php?id=<?= (int) $expense['settlement_id'] ?>"
                                    class="
                                        h-10
                                        px-3
                                        rounded-xl
                                        border
                                        border-neutral-200
                                        flex
                                        items-center
                                        justify-center
                                        gap-2
                                        text-sm
                                        text-neutral-700
                                        hover:bg-neutral-50
                                    "
                                >
                                    <i
                                        data-lucide="eye"
                                        class="w-4 h-4"
                                    ></i>

                                    Detail
                                </a>

                                <a
                                    href="/pages/titipan/invoice.php?id=<?= (int) $expense['settlement_id'] ?>"
                                    target="_blank"
                                    class="
                                        h-10
                                        px-3
                                        rounded-xl
                                        bg-neutral-900
                                        text-white
                                        flex
                                        items-center
                                        justify-center
                                        gap-2
                                        text-sm
                                        font-medium
                                        hover:bg-neutral-800
                                    "
                                >
                                    <i
                                        data-lucide="file-text"
                                        class="w-4 h-4"
                                    ></i>

                                    Invoice
                                </a>

                            <?php else: ?>

                                <a
                                    href="/pages/pengeluaran/view.php?id=<?= (int) $expense['id'] ?>"
                                    class="
                                        w-10
                                        h-10
                                        rounded-xl
                                        border
                                        border-neutral-200
                                        flex
                                        items-center
                                        justify-center
                                        text-neutral-500
                                        hover:bg-neutral-50
                                    "
                                    title="Detail"
                                >
                                    <i
                                        data-lucide="eye"
                                        class="w-4 h-4"
                                    ></i>
                                </a>


                                <a
                                    href="/pages/pengeluaran/edit.php?id=<?= (int) $expense['id'] ?>"
                                    class="
                                        w-10
                                        h-10
                                        rounded-xl
                                        border
                                        border-neutral-200
                                        flex
                                        items-center
                                        justify-center
                                        text-neutral-500
                                        hover:bg-neutral-50
                                    "
                                    title="Edit"
                                >
                                    <i
                                        data-lucide="pencil"
                                        class="w-4 h-4"
                                    ></i>
                                </a>


                                <form
                                    method="POST"
                                    action="/pages/pengeluaran/delete.php"
                                    onsubmit="
                                        return confirm(
                                            'Hapus pengeluaran ini? Data yang sudah dihapus tidak dapat dikembalikan.'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $expense['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        title="Hapus"
                                        class="
                                            w-10
                                            h-10
                                            rounded-xl
                                            border
                                            border-neutral-200
                                            flex
                                            items-center
                                            justify-center
                                            text-neutral-400
                                            hover:bg-red-50
                                            hover:text-red-600
                                        "
                                    >
                                        <i
                                            data-lucide="trash-2"
                                            class="w-4 h-4"
                                        ></i>
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php include '../../includes/footer.php'; ?>