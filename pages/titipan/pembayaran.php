<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) {
    http_response_code(403);
    exit('Store aktif tidak ditemukan.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

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

$pageTitle = 'Pembayaran Penitip';

$statusFilter = $_GET['status'] ?? 'BELUM';
$consignorId = (int) ($_GET['consignor_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

if (!in_array($statusFilter, ['BELUM', 'SUDAH', 'SEMUA'], true)) {
    $statusFilter = 'BELUM';
}

/*
|--------------------------------------------------------------------------
| Penitip
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        name
    FROM consignors
    WHERE status = 'ACTIVE'
    AND store_id = $storeId
    ORDER BY name ASC
");

$consignors = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Query hak penitip
|--------------------------------------------------------------------------
|
| Hak penitip berasal dari sale_items.consignor_amount.
| Pembayaran sebelumnya diambil dari consignor_settlement_items.
|
*/

$sql = "
    SELECT
        c.id AS consignor_id,
        c.name AS consignor_name,

        COUNT(DISTINCT si.id) AS total_items,

        COALESCE(
            SUM(si.consignor_amount),
            0
        ) AS total_hak,

        COALESCE(
            SUM(
                COALESCE(paid.total_paid, 0)
            ),
            0
        ) AS total_dibayar,

        (
            COALESCE(
                SUM(si.consignor_amount),
                0
            )
            -
            COALESCE(
                SUM(
                    COALESCE(paid.total_paid, 0)
                ),
                0
            )
        ) AS total_belum

    FROM sale_items si

    INNER JOIN sales s
        ON s.id = si.sale_id
        AND s.store_id = $storeId

    INNER JOIN products p
        ON p.id = si.product_id
        AND p.store_id = $storeId

    INNER JOIN consignments cs
        ON cs.product_id = p.id
        AND cs.store_id = $storeId

    INNER JOIN consignors c
        ON c.id = cs.consignor_id
        AND c.store_id = $storeId

    LEFT JOIN (
        SELECT
            sale_item_id,
            SUM(amount) AS total_paid
        FROM consignor_settlement_items
        WHERE store_id = $storeId
        GROUP BY sale_item_id
    ) paid
        ON paid.sale_item_id = si.id

    WHERE
        si.store_id = $storeId
        AND s.status = 'COMPLETED'
        AND p.product_type = 'TITIPAN'
";

$params = [];

/*
|--------------------------------------------------------------------------
| Filter penitip
|--------------------------------------------------------------------------
*/

if ($consignorId > 0) {

    $sql .= "
        AND c.id = :consignor_id
    ";

    $params[':consignor_id'] = $consignorId;
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
|
| Setiap placeholder dibuat berbeda karena PDO
| menggunakan native prepared statement.
|
*/

if ($search !== '') {

    $sql .= "
        AND (
            c.name LIKE :search_consignor
            OR p.name LIKE :search_product
            OR s.invoice_number LIKE :search_invoice
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[':search_consignor'] = $searchValue;
    $params[':search_product'] = $searchValue;
    $params[':search_invoice'] = $searchValue;
}

/*
|--------------------------------------------------------------------------
| GROUP
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY
        c.id,
        c.name
";

/*
|--------------------------------------------------------------------------
| Filter status pembayaran
|--------------------------------------------------------------------------
*/

if ($statusFilter === 'BELUM') {

    $sql .= "
        HAVING total_belum > 0
    ";

} elseif ($statusFilter === 'SUDAH') {

    $sql .= "
        HAVING total_belum <= 0
    ";
}

/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        total_belum DESC,
        c.name ASC
";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$rows = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Total belum dibayar
|--------------------------------------------------------------------------
*/

$totalBelum = 0;

foreach ($rows as $row) {
    $totalBelum += max(
        0,
        (float) $row['total_belum']
    );
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">

    <!-- MOBILE HEADER -->
<main class="p-4 md:p-8 max-w-7xl mx-auto">

        <!-- HEADER -->
        <div class="
            flex
            flex-col
            md:flex-row
            md:items-end
            md:justify-between
            gap-5
            mb-8
        ">

            <div>
                <h1 class="
                    text-2xl
                    md:text-3xl
                    font-semibold
                    tracking-tight
                    text-neutral-900
                ">
                    Pembayaran Penitip
                </h1>

                <p class="text-sm text-neutral-500 mt-2">
                    Kelola pembayaran hasil penjualan barang titipan.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-2">

                <a
                    href="/pages/titipan/index.php"
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
                    "
                >
                    <i data-lucide="package" class="w-4 h-4"></i>
                    Barang Titipan
                </a>

                <a
                    href="/pages/titipan/penitip.php"
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
                    "
                >
                    <i data-lucide="users" class="w-4 h-4"></i>
                    Data Penitip
                </a>

            </div>

        </div>

        <!-- SUMMARY -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

            <div class="bento-card p-5">
                <div class="text-sm text-neutral-500">
                    Belum Dibayar
                </div>

                <div class="
                    text-2xl
                    font-semibold
                    tracking-tight
                    mt-2
                ">
                    <?= rupiah($totalBelum) ?>
                </div>
            </div>

            <div class="bento-card p-5">
                <div class="text-sm text-neutral-500">
                    Penitip
                </div>

                <div class="
                    text-2xl
                    font-semibold
                    tracking-tight
                    mt-2
                ">
                    <?= count($rows) ?>
                </div>
            </div>

            <div class="bento-card p-5">
                <div class="text-sm text-neutral-500">
                    Status
                </div>

                <div class="
                    text-2xl
                    font-semibold
                    tracking-tight
                    mt-2
                ">
                    <?= $statusFilter === 'BELUM'
                        ? 'Belum Dibayar'
                        : ($statusFilter === 'SUDAH'
                            ? 'Sudah Dibayar'
                            : 'Semua')
                    ?>
                </div>
            </div>

        </div>

        <!-- FILTER -->
        <div class="bento-card p-4 mb-6">

            <form
                method="GET"
                class="
                    grid
                    grid-cols-1
                    md:grid-cols-4
                    gap-3
                "
            >

                <input
                    type="text"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Cari penitip, barang, invoice..."
                    class="
                        w-full
                        px-4
                        py-2.5
                        rounded-xl
                        border
                        border-neutral-200
                        outline-none
                        focus:border-neutral-400
                    "
                >

                <select
                    name="consignor_id"
                    class="
                        w-full
                        px-4
                        py-2.5
                        rounded-xl
                        border
                        border-neutral-200
                        bg-white
                        outline-none
                    "
                >
                    <option value="0">
                        Semua Penitip
                    </option>

                    <?php foreach ($consignors as $consignor): ?>

                        <option
                            value="<?= (int) $consignor['id'] ?>"
                            <?= $consignorId === (int) $consignor['id']
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= e($consignor['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <select
                    name="status"
                    class="
                        w-full
                        px-4
                        py-2.5
                        rounded-xl
                        border
                        border-neutral-200
                        bg-white
                        outline-none
                    "
                >
                    <option value="BELUM" <?= $statusFilter === 'BELUM' ? 'selected' : '' ?>>
                        Belum Dibayar
                    </option>

                    <option value="SUDAH" <?= $statusFilter === 'SUDAH' ? 'selected' : '' ?>>
                        Sudah Dibayar
                    </option>

                    <option value="SEMUA" <?= $statusFilter === 'SEMUA' ? 'selected' : '' ?>>
                        Semua
                    </option>
                </select>

                <div class="flex gap-2">

                    <button
                        type="submit"
                        class="
                            flex-1
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
                        Filter
                    </button>

                    <a
                        href="/pages/titipan/pembayaran.php"
                        class="
                            px-4
                            py-2.5
                            rounded-xl
                            border
                            border-neutral-200
                            bg-white
                            text-sm
                            flex
                            items-center
                            justify-center
                        "
                    >
                        Reset
                    </a>

                </div>

            </form>

        </div>

        <!-- TABLE -->
        <div class="bento-card overflow-hidden">

            <div class="overflow-x-auto">

                <table class="w-full text-left">

                    <thead class="border-b border-neutral-100">

                        <tr class="text-xs text-neutral-400 uppercase">

                            <th class="px-6 py-4 font-medium">
                                Penitip
                            </th>

                            <th class="px-6 py-4 font-medium">
                                Transaksi
                            </th>

                            <th class="px-6 py-4 font-medium">
                                Hak Penitip
                            </th>

                            <th class="px-6 py-4 font-medium">
                                Sudah Dibayar
                            </th>

                            <th class="px-6 py-4 font-medium">
                                Belum Dibayar
                            </th>

                            <th class="px-6 py-4"></th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-neutral-100">

                    <?php if (!$rows): ?>

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
                                Tidak ada data pembayaran.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($rows as $row): ?>

                            <?php
                            $belum = max(
                                0,
                                (float) $row['total_belum']
                            );
                            ?>

                            <tr class="hover:bg-neutral-50">

                                <td class="px-6 py-5">

                                    <div class="font-medium text-sm">
                                        <?= e($row['consignor_name']) ?>
                                    </div>

                                </td>

                                <td class="px-6 py-5 text-sm text-neutral-500">
                                    <?= (int) $row['total_items'] ?> item
                                </td>

                                <td class="px-6 py-5 text-sm">
                                    <?= rupiah($row['total_hak']) ?>
                                </td>

                                <td class="px-6 py-5 text-sm text-neutral-500">
                                    <?= rupiah($row['total_dibayar']) ?>
                                </td>

                                <td class="px-6 py-5">

                                    <span class="
                                        text-sm
                                        font-semibold
                                        <?= $belum > 0
                                            ? 'text-red-600'
                                            : 'text-green-600'
                                        ?>
                                    ">
                                        <?= rupiah($belum) ?>
                                    </span>

                                </td>

                                <td class="px-6 py-5">

                                    <?php if ($belum > 0): ?>

                                        <a
                                            href="/pages/titipan/bayar.php?consignor_id=<?= (int) $row['consignor_id'] ?>"
                                            class="
                                                inline-flex
                                                items-center
                                                gap-2
                                                px-3.5
                                                py-2
                                                rounded-xl
                                                bg-neutral-900
                                                text-white
                                                text-xs
                                                font-medium
                                                hover:bg-neutral-800
                                            "
                                        >
                                            <i data-lucide="banknote" class="w-4 h-4"></i>
                                            Bayar
                                        </a>

                                    <?php else: ?>

                                        <span class="text-xs text-green-600">
                                            Lunas
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

<?php include '../../includes/footer.php'; ?>