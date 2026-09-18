<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Jakarta');

$pageTitle = 'Penjualan';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
| HAPUS RIWAYAT BERDASARKAN RENTANG TANGGAL
|--------------------------------------------------------------------------
*/

$deleteError = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete_range'
) {
    $deleteFrom = trim($_POST['delete_date_from'] ?? '');
    $deleteTo   = trim($_POST['delete_date_to'] ?? '');

    try {
        if (
            empty($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception('Sesi keamanan tidak valid. Silakan coba lagi.');
        }

        $fromCheck = DateTime::createFromFormat('Y-m-d', $deleteFrom);
        $toCheck   = DateTime::createFromFormat('Y-m-d', $deleteTo);

        if (
            !$fromCheck ||
            $fromCheck->format('Y-m-d') !== $deleteFrom ||
            !$toCheck ||
            $toCheck->format('Y-m-d') !== $deleteTo
        ) {
            throw new Exception('Rentang tanggal tidak valid.');
        }

        if ($deleteFrom > $deleteTo) {
            throw new Exception('Tanggal mulai tidak boleh setelah tanggal akhir.');
        }

        $deleteToExclusive = (new DateTime($deleteTo))
            ->modify('+1 day')
            ->format('Y-m-d');

        $pdo->beginTransaction();

        /*
        | Pembayaran penitip tidak lagi menjadi penghalang penghapusan.
        | Settlement yang terkait akan dibalik otomatis sebelum sale_items
        | dan sales dihapus.
        */
        /*
        | Ambil ID penjualan yang akan dihapus.
        */
        $stmt = $pdo->prepare("
            SELECT id, status, invoice_number
            FROM sales
            WHERE sale_date >= :delete_from
              AND sale_date < :delete_to_exclusive
              AND store_id = :delete_store_id
            ORDER BY id ASC
            FOR UPDATE
        ");

        $stmt->execute([
            ':delete_from'        => $deleteFrom . ' 00:00:00',
            ':delete_to_exclusive' => $deleteToExclusive . ' 00:00:00',
            ':delete_store_id' => $authStoreId,
        ]);

        $salesToDelete = $stmt->fetchAll();

        if (empty($salesToDelete)) {
            throw new Exception(
                'Tidak ada riwayat penjualan pada rentang tanggal tersebut.'
            );
        }

        $saleIds = array_map(
            static fn($row) => (int) $row['id'],
            $salesToDelete
        );

        /*
        | Transaksi COMPLETED pernah mengurangi stok.
        | Sebelum data dihapus, stok dikembalikan agar kondisi barang
        | kembali seperti sebelum transaksi tersebut ada.
        */
        $placeholders = [];
        $saleParams = [];

        foreach ($saleIds as $i => $saleId) {
            $key = ':sale_' . $i;
            $placeholders[] = $key;
            $saleParams[$key] = $saleId;
        }

        $inSales = implode(',', $placeholders);

        $stmt = $pdo->prepare("
            SELECT
                si.product_id,
                SUM(si.quantity) AS total_quantity
            FROM sale_items si
            INNER JOIN sales s
                ON s.id = si.sale_id
            WHERE si.sale_id IN ($inSales)
              AND si.store_id = :restore_item_store_id
              AND s.store_id = :restore_sale_store_id
              AND s.status = 'COMPLETED'
            GROUP BY si.product_id
        ");

        $restoreParams = $saleParams;
        $restoreParams[':restore_item_store_id'] = $authStoreId;
        $restoreParams[':restore_sale_store_id'] = $authStoreId;
        $stmt->execute($restoreParams);

        $stockToRestore = $stmt->fetchAll();

        foreach ($stockToRestore as $stockRow) {
            $stmt = $pdo->prepare("
                UPDATE products
                SET current_stock = current_stock + :restore_qty
                WHERE id = :restore_product_id
                  AND store_id = :restore_product_store_id
            ");

            $stmt->execute([
                ':restore_qty'        => (int) $stockRow['total_quantity'],
                ':restore_product_id' => (int) $stockRow['product_id'],
                ':restore_product_store_id' => $authStoreId,
            ]);
        }

        /*
        | Balik pembayaran penitip yang terkait dengan penjualan.
        |
        | Untuk setiap settlement yang memiliki sale item dari transaksi
        | yang akan dihapus:
        | - item pembayaran terkait dihapus
        | - total settlement dihitung ulang
        | - settlement kosong ikut dihapus
        */
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                cs.id
            FROM consignor_settlements cs
            INNER JOIN consignor_settlement_items csi
                ON csi.settlement_id = cs.id
            INNER JOIN sale_items si
                ON si.id = csi.sale_item_id
            INNER JOIN sales s
                ON s.id = si.sale_id
            WHERE si.sale_id IN ($inSales)
              AND si.store_id = :settlement_items_store_id
              AND cs.store_id = :settlement_header_store_id
              AND s.store_id = :settlement_sales_store_id
            FOR UPDATE
        ");

        $settlementParams = $saleParams;
        $settlementParams[':settlement_items_store_id'] = $authStoreId;
        $settlementParams[':settlement_header_store_id'] = $authStoreId;
        $settlementParams[':settlement_sales_store_id'] = $authStoreId;
        $stmt->execute($settlementParams);

        $affectedSettlements = $stmt->fetchAll();

        $stmtDeleteSettlementItems = $pdo->prepare("
            DELETE csi
            FROM consignor_settlement_items csi
            INNER JOIN sale_items si
                ON si.id = csi.sale_item_id
            WHERE si.sale_id IN ($inSales)
              AND si.store_id = :delete_settlement_items_store_id
              AND csi.settlement_id = :delete_settlement_id
        ");

        $stmtRecalcSettlement = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM consignor_settlement_items
            WHERE settlement_id = :recalc_settlement_id
              AND store_id = :recalc_settlement_store_id
        ");

        $stmtDeleteSettlement = $pdo->prepare("
            DELETE FROM consignor_settlements
            WHERE id = :delete_settlement_header_id
              AND store_id = :delete_settlement_header_store_id
        ");

        $stmtUpdateSettlement = $pdo->prepare("
            UPDATE consignor_settlements
            SET total_amount = :new_settlement_total
            WHERE id = :update_settlement_id
              AND store_id = :update_settlement_store_id
        ");

        foreach ($affectedSettlements as $settlement) {
            $settlementId = (int) $settlement['id'];

            $stmtDeleteSettlementItems->execute([
                ...$saleParams,
                ':delete_settlement_items_store_id' => $authStoreId,
                ':delete_settlement_id' => $settlementId
            ]);

            $stmtRecalcSettlement->execute([
                ':recalc_settlement_id' => $settlementId,
                ':recalc_settlement_store_id' => $authStoreId
            ]);

            $remainingAmount = (float) $stmtRecalcSettlement->fetchColumn();

            if ($remainingAmount <= 0) {
                $stmtDeleteSettlement->execute([
                    ':delete_settlement_header_id' => $settlementId,
                    ':delete_settlement_header_store_id' => $authStoreId
                ]);
            } else {
                $stmtUpdateSettlement->execute([
                    ':new_settlement_total' => $remainingAmount,
                    ':update_settlement_id' => $settlementId,
                    ':update_settlement_store_id' => $authStoreId
                ]);
            }
        }

        /*
        | Hapus movement yang dibuat oleh transaksi penjualan.
        | Ini mencakup movement PENJUALAN dan RETUR dari transaksi
        | yang sebelumnya dibatalkan.
        */
        $stmt = $pdo->prepare("
            DELETE FROM stock_movements
            WHERE reference_type = 'SALE'
              AND reference_id IN ($inSales)
              AND store_id = :delete_movement_store_id
        ");
        $movementParams = $saleParams;
        $movementParams[':delete_movement_store_id'] = $authStoreId;
        $stmt->execute($movementParams);

        /*
        | Hapus detail transaksi lalu header transaksi.
        */
        $stmt = $pdo->prepare("
            DELETE FROM sale_items
            WHERE sale_id IN ($inSales)
              AND store_id = :delete_items_store_id
        ");
        $itemDeleteParams = $saleParams;
        $itemDeleteParams[':delete_items_store_id'] = $authStoreId;
        $stmt->execute($itemDeleteParams);

        $stmt = $pdo->prepare("
            DELETE FROM sales
            WHERE id IN ($inSales)
              AND store_id = :delete_sales_store_id
        ");
        $salesDeleteParams = $saleParams;
        $salesDeleteParams[':delete_sales_store_id'] = $authStoreId;
        $stmt->execute($salesDeleteParams);

        $deletedCount = count($salesToDelete);

        $pdo->commit();

        $_SESSION['sale_flash'] = [
            'type' => 'deleted',
            'message' => $deletedCount . ' transaksi berhasil dihapus dari riwayat.',
        ];

        header('Location: /pages/penjualan/');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $deleteError = $e->getMessage();
    }
}

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
    return 'Rp ' . number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$dateFrom = trim($_GET['date_from'] ?? '');

$dateTo = trim($_GET['date_to'] ?? '');

$statusFilter = strtoupper(
    trim($_GET['status'] ?? 'ALL')
);

$paymentFilter = strtoupper(
    trim($_GET['payment'] ?? 'ALL')
);


/*
|--------------------------------------------------------------------------
| VALIDASI FILTER
|--------------------------------------------------------------------------
*/

if (!in_array(
    $statusFilter,
    ['ALL', 'COMPLETED', 'CANCELLED'],
    true
)) {
    $statusFilter = 'ALL';
}

if (!in_array(
    $paymentFilter,
    ['ALL', 'CASH', 'QRIS', 'TRANSFER', 'OTHER'],
    true
)) {
    $paymentFilter = 'ALL';
}


/*
|--------------------------------------------------------------------------
| QUERY UTAMA
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.id,
        s.invoice_number,
        s.sale_date,
        s.total_amount,
        s.payment_method,
        s.status,
        s.notes,

        COALESCE(
            SUM(si.quantity),
            0
        ) AS total_qty,

        COUNT(DISTINCT si.product_id) AS item_count,

        COALESCE(
            SUM(si.profit),
            0
        ) AS total_profit,

        COALESCE(
            GROUP_CONCAT(
                CONCAT(
                    p.name,
                    ' × ',
                    si.quantity
                )
                ORDER BY p.name ASC
                SEPARATOR ' · '
            ),
            ''
        ) AS item_names

    FROM sales s

    LEFT JOIN sale_items si
        ON si.sale_id = s.id
       AND si.store_id = s.store_id

    LEFT JOIN products p
        ON p.id = si.product_id
       AND p.store_id = s.store_id

    WHERE s.store_id = :list_store_id
";

$params = [
    ':list_store_id' => $authStoreId
];


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            s.invoice_number LIKE :search_invoice
            OR s.notes LIKE :search_notes
            OR EXISTS (
                SELECT 1
                FROM sale_items si_search
                INNER JOIN products p_search
                    ON p_search.id = si_search.product_id
                   AND p_search.store_id = s.store_id
                WHERE si_search.sale_id = s.id
                  AND si_search.store_id = s.store_id
                  AND p_search.name LIKE :search_product
            )
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[':search_invoice'] = $searchValue;
    $params[':search_notes']   = $searchValue;
    $params[':search_product'] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| DATE FROM
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $sql .= "
        AND s.sale_date >= :date_from_start
    ";

    $params[':date_from_start'] = $dateFrom . ' 00:00:00';
}


/*
|--------------------------------------------------------------------------
| DATE TO
|--------------------------------------------------------------------------
*/

if ($dateTo !== '') {

    $dateToExclusive = (new DateTime($dateTo))
        ->modify('+1 day')
        ->format('Y-m-d');

    $sql .= "
        AND s.sale_date < :date_to_exclusive
    ";

    $params[':date_to_exclusive'] =
        $dateToExclusive . ' 00:00:00';
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

if ($statusFilter !== 'ALL') {

    $sql .= "
        AND s.status = :status
    ";

    $params[':status'] = $statusFilter;
}


/*
|--------------------------------------------------------------------------
| PAYMENT
|--------------------------------------------------------------------------
*/

if ($paymentFilter !== 'ALL') {

    $sql .= "
        AND s.payment_method = :payment
    ";

    $params[':payment'] = $paymentFilter;
}


/*
|--------------------------------------------------------------------------
| GROUP
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY
        s.id,
        s.invoice_number,
        s.sale_date,
        s.total_amount,
        s.payment_method,
        s.status,
        s.notes

    ORDER BY
        s.sale_date DESC,
        s.id DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$sales = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| STATISTIK HARI INI
|--------------------------------------------------------------------------
*/

$todayStart = date('Y-m-d 00:00:00');
$tomorrowStart = date(
    'Y-m-d 00:00:00',
    strtotime('+1 day')
);

$stmtToday = $pdo->prepare("
    SELECT

        COUNT(
            CASE
                WHEN status = 'COMPLETED'
                THEN 1
            END
        ) AS total_transaction,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'COMPLETED'
                    THEN total_amount
                    ELSE 0
                END
            ),
            0
        ) AS total_sales,

        COALESCE(
            (
                SELECT SUM(si.profit)
                FROM sale_items si
                INNER JOIN sales s2
                    ON s2.id = si.sale_id
                WHERE s2.sale_date >= :today_profit_start
                  AND s2.sale_date < :today_profit_end
                  AND s2.store_id = :today_profit_store_id
                  AND s2.status = 'COMPLETED'
            ),
            0
        ) AS total_profit

    FROM sales

    WHERE sale_date >= :today_sales_start
      AND sale_date < :today_sales_end
      AND store_id = :today_sales_store_id
");

$stmtToday->execute([
    ':today_profit_start' => $todayStart,
    ':today_profit_end'   => $tomorrowStart,
    ':today_sales_start'  => $todayStart,
    ':today_sales_end'    => $tomorrowStart,
    ':today_profit_store_id' => $authStoreId,
    ':today_sales_store_id' => $authStoreId,
]);

$today = $stmtToday->fetch();


/*
|--------------------------------------------------------------------------
| JUMLAH DATA HASIL FILTER
|--------------------------------------------------------------------------
*/

$totalDisplayed = count($sales);


/*
|--------------------------------------------------------------------------
| FORMAT PAYMENT
|--------------------------------------------------------------------------
*/

$paymentLabels = [
    'CASH'     => 'Cash',
    'QRIS'     => 'QRIS',
    'TRANSFER' => 'Transfer',
    'OTHER'    => 'Lainnya',
];


/*
|--------------------------------------------------------------------------
| FLASH
|--------------------------------------------------------------------------
*/

$success = $_GET['success'] ?? '';

$saleFlash = $_SESSION['sale_flash'] ?? null;
unset($_SESSION['sale_flash']);

?>

<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>


<main class="main-content">
<div class="p-4 sm:p-6 lg:p-8">


        <!-- HEADER -->

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">

            <div>

                <div class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-2">
                    Transaksi
                </div>

                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">
                    Penjualan
                </h1>

                <p class="mt-1 text-sm text-neutral-500">
                    Catat dan lihat transaksi penjualan toko.
                </p>

            </div>


            <a
                href="/pages/penjualan/create.php"
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
                    font-semibold
                    hover:bg-neutral-800
                    transition
                "
            >

                <i
                    data-lucide="plus"
                    class="w-4 h-4"
                ></i>

                Penjualan Baru

            </a>

        </div>


        <!-- SUCCESS / ERROR -->

        <?php if (is_array($saleFlash) && ($saleFlash['type'] ?? '') === 'deleted'): ?>

            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">

                <div class="flex items-center gap-3 text-sm text-emerald-700">
                    <i data-lucide="circle-check" class="w-5 h-5"></i>
                    <span><?= e($saleFlash['message'] ?? 'Riwayat berhasil dihapus.') ?></span>
                </div>

            </div>

        <?php endif; ?>

        <?php if ($success === 'cancelled'): ?>

            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">

                <div class="flex items-center gap-3 text-sm text-emerald-700">

                    <i
                        data-lucide="circle-check"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Transaksi berhasil dibatalkan.
                    </span>

                </div>

            </div>

        <?php endif; ?>

        <?php if ($deleteError !== ''): ?>

            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3">

                <div class="flex items-start gap-3 text-sm text-red-700">

                    <i
                        data-lucide="circle-alert"
                        class="w-5 h-5 flex-shrink-0 mt-0.5"
                    ></i>

                    <span>
                        <?= e($deleteError) ?>
                    </span>

                </div>

            </div>

        <?php endif; ?>


        <!-- STATISTIK -->

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-6">


            <!-- TRANSAKSI -->

            <div class="bento-card p-4 sm:p-5">

                <div class="flex items-center justify-between gap-3">

                    <div>

                        <div class="text-xs text-neutral-400">
                            Transaksi Hari Ini
                        </div>

                        <div class="text-2xl font-bold mt-1">
                            <?= number_format(
                                (int) ($today['total_transaction'] ?? 0)
                            ) ?>
                        </div>

                    </div>

                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center">

                        <i
                            data-lucide="receipt"
                            class="w-5 h-5 text-neutral-600"
                        ></i>

                    </div>

                </div>

            </div>


            <!-- OMZET -->

            <div class="bento-card p-4 sm:p-5">

                <div class="flex items-center justify-between gap-3">

                    <div>

                        <div class="text-xs text-neutral-400">
                            Penjualan Hari Ini
                        </div>

                        <div class="text-xl sm:text-2xl font-bold mt-1">
                            <?= rupiah(
                                $today['total_sales'] ?? 0
                            ) ?>
                        </div>

                    </div>

                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center">

                        <i
                            data-lucide="banknote"
                            class="w-5 h-5 text-neutral-600"
                        ></i>

                    </div>

                </div>

            </div>


            <!-- PROFIT -->

            <div class="bento-card p-4 sm:p-5">

                <div class="flex items-center justify-between gap-3">

                    <div>

                        <div class="text-xs text-neutral-400">
                            Keuntungan Hari Ini
                        </div>

                        <div class="text-xl sm:text-2xl font-bold mt-1">
                            <?= rupiah(
                                $today['total_profit'] ?? 0
                            ) ?>
                        </div>

                    </div>

                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">

                        <i
                            data-lucide="trending-up"
                            class="w-5 h-5 text-emerald-600"
                        ></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- FILTER -->

        <div class="bento-card p-4 sm:p-5 mb-6">

            <form
                method="GET"
                class="space-y-3"
            >

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">


                    <!-- SEARCH -->

                    <div class="lg:col-span-2 relative">

                        <i
                            data-lucide="search"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400"
                        ></i>

                        <input
                            type="search"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Cari invoice, nama barang..."
                            class="
                                w-full
                                rounded-xl
                                border
                                border-neutral-200
                                pl-10
                                pr-4
                                py-2.5
                                text-sm
                                outline-none
                                focus:border-neutral-400
                                focus:ring-2
                                focus:ring-neutral-100
                            "
                        >

                    </div>


                    <!-- DATE FROM -->

                    <input
                        type="date"
                        name="date_from"
                        value="<?= e($dateFrom) ?>"
                        class="
                            rounded-xl
                            border
                            border-neutral-200
                            px-3
                            py-2.5
                            text-sm
                            bg-white
                            outline-none
                            focus:border-neutral-400
                        "
                    >


                    <!-- DATE TO -->

                    <input
                        type="date"
                        name="date_to"
                        value="<?= e($dateTo) ?>"
                        class="
                            rounded-xl
                            border
                            border-neutral-200
                            px-3
                            py-2.5
                            text-sm
                            bg-white
                            outline-none
                            focus:border-neutral-400
                        "
                    >


                    <!-- STATUS -->

                    <select
                        name="status"
                        class="
                            rounded-xl
                            border
                            border-neutral-200
                            px-3
                            py-2.5
                            text-sm
                            bg-white
                            outline-none
                            focus:border-neutral-400
                        "
                    >

                        <option value="ALL">
                            Semua Status
                        </option>

                        <option
                            value="COMPLETED"
                            <?= $statusFilter === 'COMPLETED' ? 'selected' : '' ?>
                        >
                            Selesai
                        </option>

                        <option
                            value="CANCELLED"
                            <?= $statusFilter === 'CANCELLED' ? 'selected' : '' ?>
                        >
                            Dibatalkan
                        </option>

                    </select>

                </div>


                <div class="flex flex-col sm:flex-row gap-2">


                    <!-- PAYMENT -->

                    <select
                        name="payment"
                        class="
                            rounded-xl
                            border
                            border-neutral-200
                            px-3
                            py-2.5
                            text-sm
                            bg-white
                            outline-none
                            focus:border-neutral-400
                        "
                    >

                        <option value="ALL">
                            Semua Pembayaran
                        </option>

                        <option
                            value="CASH"
                            <?= $paymentFilter === 'CASH' ? 'selected' : '' ?>
                        >
                            Cash
                        </option>

                        <option
                            value="QRIS"
                            <?= $paymentFilter === 'QRIS' ? 'selected' : '' ?>
                        >
                            QRIS
                        </option>

                        <option
                            value="TRANSFER"
                            <?= $paymentFilter === 'TRANSFER' ? 'selected' : '' ?>
                        >
                            Transfer
                        </option>

                        <option
                            value="OTHER"
                            <?= $paymentFilter === 'OTHER' ? 'selected' : '' ?>
                        >
                            Lainnya
                        </option>

                    </select>


                    <button
                        type="submit"
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
                            font-semibold
                            hover:bg-neutral-800
                        "
                    >

                        <i
                            data-lucide="filter"
                            class="w-4 h-4"
                        ></i>

                        Filter

                    </button>


                    <a
                        href="/pages/penjualan/"
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
                            text-sm
                            font-medium
                            text-neutral-600
                            hover:bg-neutral-50
                        "
                    >

                        <i
                            data-lucide="rotate-ccw"
                            class="w-4 h-4"
                        ></i>

                        Reset

                    </a>

                </div>

            </form>

        </div>


        <!-- HAPUS RIWAYAT -->

        <details class="bento-card mb-6 group">

            <summary class="list-none cursor-pointer px-5 sm:px-6 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">

                    <div class="w-9 h-9 rounded-xl bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-neutral-800">
                            Hapus Riwayat Penjualan
                        </div>
                        <div class="text-xs text-neutral-400 mt-0.5">
                            Hapus transaksi berdasarkan rentang tanggal
                        </div>
                    </div>

                </div>

                <i
                    data-lucide="chevron-down"
                    class="w-5 h-5 text-neutral-400 flex-shrink-0 transition-transform group-open:rotate-180"
                ></i>

            </summary>

            <div class="px-5 sm:px-6 pb-5 pt-1 border-t border-neutral-100">

                <div class="rounded-xl bg-red-50 border border-red-100 p-4 mt-4 text-sm text-red-700">
                    <div class="font-semibold mb-1">
                        Perhatian
                    </div>
                    <p>
                        Transaksi yang dihapus tidak bisa dikembalikan.
                        Stok dari transaksi <strong>Selesai</strong> akan dikembalikan.
                        Transaksi yang sudah dibayarkan ke penitip tidak dapat dihapus.
                    </p>
                </div>

                <form
                    method="POST"
                    class="mt-4"
                    id="delete-range-form"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="delete_range"
                    >

                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-3 items-end">

                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-2">
                                Dari tanggal
                            </label>

                            <input
                                type="date"
                                name="delete_date_from"
                                id="delete-date-from"
                                required
                                class="w-full rounded-xl border border-neutral-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-neutral-400"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-2">
                                Sampai tanggal
                            </label>

                            <input
                                type="date"
                                name="delete_date_to"
                                id="delete-date-to"
                                required
                                class="w-full rounded-xl border border-neutral-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-neutral-400"
                            >
                        </div>

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition"
                        >
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                            Hapus Riwayat
                        </button>

                    </div>

                </form>

            </div>

        </details>


        <!-- RIWAYAT -->

        <div class="bento-card overflow-hidden">


            <div class="px-5 sm:px-6 py-5 border-b border-neutral-100">

                <div class="flex items-center justify-between">

                    <div>

                        <h2 class="font-semibold">
                            Riwayat Penjualan
                        </h2>

                        <p class="text-xs text-neutral-400 mt-1">
                            <?= number_format($totalDisplayed) ?>
                            transaksi ditampilkan
                        </p>

                    </div>

                </div>

            </div>


            <?php if (empty($sales)): ?>

                <div class="px-6 py-16 text-center">

                    <div class="w-14 h-14 mx-auto rounded-2xl bg-neutral-100 flex items-center justify-center mb-4">

                        <i
                            data-lucide="receipt-text"
                            class="w-6 h-6 text-neutral-400"
                        ></i>

                    </div>

                    <h3 class="font-semibold">
                        Belum ada transaksi
                    </h3>

                    <p class="text-sm text-neutral-400 mt-1">
                        Belum ada penjualan yang sesuai dengan filter.
                    </p>

                    <a
                        href="/pages/penjualan/create.php"
                        class="
                            inline-flex
                            items-center
                            gap-2
                            mt-5
                            px-4
                            py-2.5
                            rounded-xl
                            bg-neutral-900
                            text-white
                            text-sm
                            font-semibold
                        "
                    >

                        <i
                            data-lucide="plus"
                            class="w-4 h-4"
                        ></i>

                        Penjualan Baru

                    </a>

                </div>

            <?php else: ?>


                <!-- DESKTOP -->

                <div class="hidden md:block overflow-x-auto">

                    <table class="w-full">

                        <thead>

                            <tr class="border-b border-neutral-100 bg-neutral-50/70">

                                <th class="px-6 py-3 text-left text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Transaksi
                                </th>

                                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Tanggal
                                </th>

                                <th class="px-4 py-3 text-center text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Barang
                                </th>

                                <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Total
                                </th>

                                <th class="px-4 py-3 text-center text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Pembayaran
                                </th>

                                <th class="px-4 py-3 text-center text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Status
                                </th>

                                <th class="px-6 py-3 text-right text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Aksi
                                </th>

                            </tr>

                        </thead>


                        <tbody class="divide-y divide-neutral-100">

                            <?php foreach ($sales as $sale): ?>

                                <?php
                                $completed =
                                    $sale['status'] === 'COMPLETED';
                                ?>

                                <tr class="
                                    hover:bg-neutral-50/70
                                    transition
                                    <?= !$completed ? 'bg-neutral-50/50' : '' ?>
                                ">


                                    <!-- INVOICE -->

                                    <td class="px-6 py-4">

                                        <a
                                            href="/pages/penjualan/view.php?id=<?= (int) $sale['id'] ?>"
                                            class="
                                                font-medium
                                                text-sm
                                                text-neutral-900
                                                hover:underline
                                                underline-offset-4
                                            "
                                        >
                                            <?= e($sale['invoice_number']) ?>
                                        </a>

                                    </td>


                                    <!-- TANGGAL -->

                                    <td class="px-4 py-4">

                                        <div class="text-sm text-neutral-700">

                                            <?= date(
                                                'd/m/Y',
                                                strtotime($sale['sale_date'])
                                            ) ?>

                                        </div>

                                        <div class="text-xs text-neutral-400 mt-1">

                                            <?= date(
                                                'H:i',
                                                strtotime($sale['sale_date'])
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- BARANG -->

                                    <td class="px-4 py-4">

                                        <div class="text-sm font-medium text-neutral-800 max-w-[360px]">
                                            <?= e($sale['item_names'] ?: 'Tidak ada detail barang') ?>
                                        </div>

                                        <div class="text-xs text-neutral-400 mt-1">
                                            <?= number_format((int) $sale['total_qty']) ?>
                                            item ·
                                            <?= number_format((int) $sale['item_count']) ?>
                                            jenis
                                        </div>

                                    </td>


                                    <!-- TOTAL -->

                                    <td class="px-4 py-4 text-right">

                                        <div class="text-sm font-semibold">
                                            <?= rupiah(
                                                $sale['total_amount']
                                            ) ?>
                                        </div>

                                        <?php if ($completed && (float) $sale['total_profit'] != 0): ?>

                                            <div class="text-xs text-emerald-600 mt-1">

                                                Untung
                                                <?= rupiah(
                                                    $sale['total_profit']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- PAYMENT -->

                                    <td class="px-4 py-4 text-center">

                                        <span class="
                                            inline-flex
                                            rounded-lg
                                            bg-neutral-100
                                            text-neutral-600
                                            px-2.5
                                            py-1
                                            text-xs
                                            font-medium
                                        ">

                                            <?= e(
                                                $paymentLabels[
                                                    $sale['payment_method']
                                                ] ??
                                                $sale['payment_method']
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td class="px-4 py-4 text-center">

                                        <?php if ($completed): ?>

                                            <span class="
                                                inline-flex
                                                items-center
                                                gap-1.5
                                                rounded-lg
                                                bg-emerald-50
                                                text-emerald-700
                                                px-2.5
                                                py-1.5
                                                text-xs
                                                font-medium
                                            ">

                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>

                                                Selesai

                                            </span>

                                        <?php else: ?>

                                            <span class="
                                                inline-flex
                                                items-center
                                                gap-1.5
                                                rounded-lg
                                                bg-neutral-100
                                                text-neutral-500
                                                px-2.5
                                                py-1.5
                                                text-xs
                                                font-medium
                                            ">

                                                <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>

                                                Dibatalkan

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- AKSI -->

                                    <td class="px-6 py-4">

                                        <div class="flex items-center justify-end gap-1">


                                            <a
                                                href="/pages/penjualan/view.php?id=<?= (int) $sale['id'] ?>"
                                                title="Lihat transaksi"
                                                class="
                                                    inline-flex
                                                    items-center
                                                    justify-center
                                                    w-9
                                                    h-9
                                                    rounded-lg
                                                    text-neutral-500
                                                    hover:bg-neutral-100
                                                    hover:text-neutral-900
                                                "
                                            >

                                                <i
                                                    data-lucide="eye"
                                                    class="w-4 h-4"
                                                ></i>

                                            </a>


                                            <?php if ($completed): ?>

                                                <a
                                                    href="/pages/penjualan/invoice.php?id=<?= (int) $sale['id'] ?>"
                                                    target="_blank"
                                                    title="Invoice"
                                                    class="
                                                        inline-flex
                                                        items-center
                                                        justify-center
                                                        w-9
                                                        h-9
                                                        rounded-lg
                                                        text-neutral-500
                                                        hover:bg-neutral-100
                                                        hover:text-neutral-900
                                                    "
                                                >

                                                    <i
                                                        data-lucide="file-text"
                                                        class="w-4 h-4"
                                                    ></i>

                                                </a>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- MOBILE -->

                <div class="md:hidden divide-y divide-neutral-100">

                    <?php foreach ($sales as $sale): ?>

                        <?php
                        $completed =
                            $sale['status'] === 'COMPLETED';
                        ?>

                        <div class="
                            p-4
                            <?= !$completed ? 'bg-neutral-50/60' : '' ?>
                        ">


                            <div class="flex items-start justify-between gap-3">

                                <div class="min-w-0">

                                    <a
                                        href="/pages/penjualan/view.php?id=<?= (int) $sale['id'] ?>"
                                        class="
                                            font-semibold
                                            text-sm
                                            text-neutral-900
                                            hover:underline
                                        "
                                    >
                                        <?= e($sale['invoice_number']) ?>
                                    </a>

                                    <div class="text-xs text-neutral-400 mt-1">

                                        <?= date(
                                            'd/m/Y H:i',
                                            strtotime($sale['sale_date'])
                                        ) ?>

                                    </div>

                                </div>


                                <?php if ($completed): ?>

                                    <span class="
                                        inline-flex
                                        items-center
                                        gap-1.5
                                        rounded-lg
                                        bg-emerald-50
                                        text-emerald-700
                                        px-2
                                        py-1
                                        text-xs
                                        font-medium
                                        flex-shrink-0
                                    ">

                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>

                                        Selesai

                                    </span>

                                <?php else: ?>

                                    <span class="
                                        inline-flex
                                        items-center
                                        gap-1.5
                                        rounded-lg
                                        bg-neutral-100
                                        text-neutral-500
                                        px-2
                                        py-1
                                        text-xs
                                        font-medium
                                        flex-shrink-0
                                    ">

                                        <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>

                                        Dibatalkan

                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="mt-4">

                                <div class="rounded-xl bg-neutral-50 p-3.5">

                                    <div class="text-xs text-neutral-400">
                                        Barang
                                    </div>

                                    <div class="text-sm font-semibold text-neutral-800 mt-1 leading-6">
                                        <?= e($sale['item_names'] ?: 'Tidak ada detail barang') ?>
                                    </div>

                                    <div class="text-xs text-neutral-400 mt-1">
                                        <?= number_format((int) $sale['total_qty']) ?>
                                        item ·
                                        <?= number_format((int) $sale['item_count']) ?>
                                        jenis
                                    </div>

                                </div>

                            </div>

                            <div class="grid grid-cols-2 gap-3 mt-3">

                                <div class="rounded-xl bg-neutral-50 p-3">

                                    <div class="text-xs text-neutral-400">
                                        Total
                                    </div>

                                    <div class="font-semibold text-sm mt-1">
                                        <?= rupiah(
                                            $sale['total_amount']
                                        ) ?>
                                    </div>

                                </div>

                                <div class="rounded-xl bg-neutral-50 p-3">

                                    <div class="text-xs text-neutral-400">
                                        Pembayaran
                                    </div>

                                    <div class="font-semibold text-sm mt-1">
                                        <?= e(
                                            $paymentLabels[
                                                $sale['payment_method']
                                            ] ??
                                            $sale['payment_method']
                                        ) ?>
                                    </div>

                                </div>

                            </div>


                            <div class="flex items-center justify-between mt-4">

                                <span class="
                                    inline-flex
                                    rounded-lg
                                    bg-neutral-100
                                    text-neutral-600
                                    px-2.5
                                    py-1
                                    text-xs
                                    font-medium
                                ">

                                    <?= e(
                                        $paymentLabels[
                                            $sale['payment_method']
                                        ] ??
                                        $sale['payment_method']
                                    ) ?>

                                </span>


                                <div class="flex items-center gap-1">


                                    <a
                                        href="/pages/penjualan/view.php?id=<?= (int) $sale['id'] ?>"
                                        class="
                                            inline-flex
                                            items-center
                                            gap-1.5
                                            px-3
                                            py-2
                                            rounded-lg
                                            text-sm
                                            font-medium
                                            text-neutral-600
                                            hover:bg-neutral-100
                                        "
                                    >

                                        <i
                                            data-lucide="eye"
                                            class="w-4 h-4"
                                        ></i>

                                        Detail

                                    </a>


                                    <?php if ($completed): ?>

                                        <a
                                            href="/pages/penjualan/invoice.php?id=<?= (int) $sale['id'] ?>"
                                            target="_blank"
                                            class="
                                                inline-flex
                                                items-center
                                                justify-center
                                                w-9
                                                h-9
                                                rounded-lg
                                                text-neutral-500
                                                hover:bg-neutral-100
                                            "
                                            title="Invoice"
                                        >

                                            <i
                                                data-lucide="file-text"
                                                class="w-4 h-4"
                                            ></i>

                                        </a>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</main>


<!-- TOAST SUKSES -->
<?php if (is_array($saleFlash) && ($saleFlash['type'] ?? '') === 'created'): ?>

    <?php
    $flashNames = array_values(
        array_filter(
            array_unique(
                array_map(
                    'strval',
                    $saleFlash['names'] ?? []
                )
            )
        )
    );

    if (count($flashNames) === 1) {
        $flashMessage = 'Yay! ' . $flashNames[0] . ' berhasil terjual';
    } elseif (count($flashNames) > 1) {
        $flashMessage = 'Yay! ' . count($flashNames) . ' barang berhasil terjual';
    } else {
        $flashMessage = 'Yay! Penjualan berhasil disimpan';
    }
    ?>

    <div
        id="sale-success-toast"
        class="fixed top-5 left-1/2 -translate-x-1/2 z-[100] w-[calc(100%-32px)] max-w-md"
        role="status"
        aria-live="polite"
    >
        <div class="rounded-2xl border border-emerald-200 bg-white px-4 py-3 shadow-xl shadow-neutral-900/10 flex items-center gap-3">

            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <i data-lucide="circle-check" class="w-5 h-5"></i>
            </div>

            <div class="min-w-0">
                <div class="text-sm font-semibold text-neutral-900">
                    <?= e($flashMessage) ?>
                </div>

                <?php if (!empty($saleFlash['total'])): ?>
                    <div class="text-xs text-neutral-400 mt-0.5">
                        Total <?= rupiah($saleFlash['total']) ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <style>
        #sale-success-toast {
            animation: saleToastIn .3s ease-out both;
        }

        #sale-success-toast.is-hiding {
            animation: saleToastOut .3s ease-in both;
        }

        @keyframes saleToastIn {
            from {
                opacity: 0;
                transform: translate(-50%, -12px);
            }
            to {
                opacity: 1;
                transform: translate(-50%, 0);
            }
        }

        @keyframes saleToastOut {
            from {
                opacity: 1;
                transform: translate(-50%, 0);
            }
            to {
                opacity: 0;
                transform: translate(-50%, -12px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            #sale-success-toast,
            #sale-success-toast.is-hiding {
                animation: none;
            }

            #sale-success-toast.is-hiding {
                opacity: 0;
            }
        }
    </style>

    <script>
        setTimeout(function () {
            const toast = document.getElementById('sale-success-toast');

            if (!toast) return;

            toast.classList.add('is-hiding');

            setTimeout(function () {
                toast.remove();
            }, 300);
        }, 3000);
    </script>

<?php endif; ?>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('delete-range-form');

    if (!form) return;

    form.addEventListener('submit', function (event) {
        const from = document.getElementById('delete-date-from')?.value || '';
        const to = document.getElementById('delete-date-to')?.value || '';

        if (!from || !to) {
            return;
        }

        const confirmed = confirm(
            'Hapus semua riwayat penjualan dari ' +
            from +
            ' sampai ' +
            to +
            '?\n\n' +
            'Transaksi yang dihapus tidak bisa dikembalikan. ' +
            'Stok transaksi yang selesai akan dikembalikan.'
        );

        if (!confirmed) {
            event.preventDefault();
        }
    });
});
</script>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>