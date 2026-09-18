<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = 'Barang';

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

$typeFilter = strtoupper(
    trim($_GET['type'] ?? 'ALL')
);

$statusFilter = strtoupper(
    trim($_GET['status'] ?? 'ALL')
);

$categoryFilter = (int) (
    $_GET['category_id'] ?? 0
);

if (!in_array(
    $typeFilter,
    ['ALL', 'TOKO', 'TITIPAN'],
    true
)) {
    $typeFilter = 'ALL';
}

if (!in_array(
    $statusFilter,
    ['ALL', 'ACTIVE', 'INACTIVE'],
    true
)) {
    $statusFilter = 'ALL';
}

/*
|--------------------------------------------------------------------------
| KATEGORI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name
    FROM product_categories
    WHERE store_id = :category_store_id
    ORDER BY name ASC
");

$stmt->execute([
    ':category_store_id' => $authStoreId
]);

$categories = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| QUERY BARANG
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.sku,
        p.barcode,
        p.name,
        p.product_type,
        p.category_id,
        p.unit,
        p.buying_price,
        p.selling_price,
        p.current_stock,
        p.minimum_stock,
        p.status,
        p.created_at,
        p.created_by,
        p.updated_by,

        pc.name AS category_name,

        creator.name AS created_by_name,
        editor.name AS updated_by_name,

        c.name AS consignor_name,
        c.phone AS consignor_phone,

        cs.initial_price,
        cs.fee_type,
        cs.fee_value

    FROM products p

    LEFT JOIN product_categories pc
        ON pc.id = p.category_id
       AND pc.store_id = p.store_id

    LEFT JOIN users creator
        ON creator.id = p.created_by

    LEFT JOIN users editor
        ON editor.id = p.updated_by

    LEFT JOIN consignments cs
        ON cs.id = (
            SELECT c1.id
            FROM consignments c1
            WHERE c1.product_id = p.id
              AND c1.store_id = p.store_id
            ORDER BY
                CASE
                    WHEN c1.status = 'ACTIVE'
                    THEN 0
                    ELSE 1
                END,
                c1.id DESC
            LIMIT 1
        )

    LEFT JOIN consignors c
        ON c.id = cs.consignor_id
       AND c.store_id = p.store_id

    WHERE p.store_id = :product_store_id
";

$params = [];

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            p.name LIKE :search_name
            OR p.sku LIKE :search_sku
            OR p.barcode LIKE :search_barcode
            OR c.name LIKE :search_consignor
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[':search_name'] =
        $searchValue;

    $params[':search_sku'] =
        $searchValue;

    $params[':search_barcode'] =
        $searchValue;

    $params[':search_consignor'] =
        $searchValue;
}

/*
|--------------------------------------------------------------------------
| TYPE
|--------------------------------------------------------------------------
*/

if ($typeFilter !== 'ALL') {

    $sql .= "
        AND p.product_type = :product_type
    ";

    $params[':product_type'] =
        $typeFilter;
}

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

if ($statusFilter !== 'ALL') {

    $sql .= "
        AND p.status = :status
    ";

    $params[':status'] =
        $statusFilter;
}

/*
|--------------------------------------------------------------------------
| CATEGORY
|--------------------------------------------------------------------------
*/

if ($categoryFilter > 0) {

    $sql .= "
        AND p.category_id = :category_id
    ";

    $params[':category_id'] =
        $categoryFilter;
}

/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        p.status ASC,
        p.name ASC
";

$stmt = $pdo->prepare($sql);

$params[':product_store_id'] = $authStoreId;

$stmt->execute($params);

$products = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| JUMLAH DATA
|--------------------------------------------------------------------------
*/

$totalProducts = count($products);

$activeCount = 0;
$inactiveCount = 0;
$tokoCount = 0;
$titipanCount = 0;

foreach ($products as $product) {

    if ($product['status'] === 'ACTIVE') {
        $activeCount++;
    } else {
        $inactiveCount++;
    }

    if ($product['product_type'] === 'TOKO') {
        $tokoCount++;
    } else {
        $titipanCount++;
    }
}

/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$success = $_GET['success'] ?? '';

?>

<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>


<main class="main-content">


<div class="p-4 sm:p-6 lg:p-8">


        <!-- HEADER -->

        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5 mb-6">

            <div>

                <div class="flex items-center gap-2 mb-2">

                    <span class="text-xs font-semibold uppercase tracking-wider text-neutral-400">
                        Master Data
                    </span>

                </div>

                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">
                    Barang
                </h1>

                <p class="mt-1 text-sm text-neutral-500">
                    Kelola barang toko dan barang titipan.
                </p>

            </div>


            <div class="flex flex-col sm:flex-row gap-2">

                <a
                    href="/pages/barang/kategori/"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-neutral-200 bg-white text-sm font-medium text-neutral-700 hover:bg-neutral-50"
                >
                    <i
                        data-lucide="layers-3"
                        class="w-4 h-4"
                    ></i>

                    Kategori

                </a>


                <a
                    href="/pages/barang/create.php"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-neutral-900 text-white text-sm font-semibold hover:bg-neutral-800"
                >
                    <i
                        data-lucide="plus"
                        class="w-4 h-4"
                    ></i>

                    Tambah Barang

                </a>

            </div>

        </div>


        <!-- SUCCESS -->

        <?php if ($success === 'created'): ?>

            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">

                <div class="flex items-center gap-3 text-sm text-emerald-700">

                    <i
                        data-lucide="circle-check"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Barang berhasil ditambahkan.
                    </span>

                </div>

            </div>

        <?php elseif ($success === 'updated'): ?>

            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">

                <div class="flex items-center gap-3 text-sm text-emerald-700">

                    <i
                        data-lucide="circle-check"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Barang berhasil diperbarui.
                    </span>

                </div>

            </div>

        <?php elseif ($success === 'status'): ?>

            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">

                <div class="flex items-center gap-3 text-sm text-emerald-700">

                    <i
                        data-lucide="circle-check"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Status barang berhasil diperbarui.
                    </span>

                </div>

            </div>

        <?php endif; ?>


        <!-- SUMMARY -->

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">


            <div class="bento-card p-4 sm:p-5">

                <div class="flex items-center justify-between">

                    <div>

                        <div class="text-xs text-neutral-400">
                            Total Barang
                        </div>

                        <div class="text-2xl font-bold mt-1">
                            <?= number_format($totalProducts) ?>
                        </div>

                    </div>

                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center">

                        <i
                            data-lucide="package"
                            class="w-5 h-5 text-neutral-600"
                        ></i>

                    </div>

                </div>

            </div>


            <div class="bento-card p-4 sm:p-5">

                <div class="flex items-center justify-between">

                    <div>

                        <div class="text-xs text-neutral-400">
                            Produk Toko
                        </div>

                        <div class="text-2xl font-bold mt-1">
                            <?= number_format($tokoCount) ?>
                        </div>

                    </div>

                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center">

                        <i
                            data-lucide="store"
                            class="w-5 h-5 text-neutral-600"
                        ></i>

                    </div>

                </div>

            </div>


            <div class="bento-card p-4 sm:p-5">

                <div class="flex items-center justify-between">

                    <div>

                        <div class="text-xs text-neutral-400">
                            Produk Titipan
                        </div>

                        <div class="text-2xl font-bold mt-1">
                            <?= number_format($titipanCount) ?>
                        </div>

                    </div>

                    <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center">

                        <i
                            data-lucide="handshake"
                            class="w-5 h-5 text-neutral-600"
                        ></i>

                    </div>

                </div>

            </div>


            <div class="bento-card p-4 sm:p-5">

                <div class="flex items-center justify-between">

                    <div>

                        <div class="text-xs text-neutral-400">
                            Aktif
                        </div>

                        <div class="text-2xl font-bold mt-1">
                            <?= number_format($activeCount) ?>
                        </div>

                    </div>

                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">

                        <i
                            data-lucide="circle-check"
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
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3"
            >


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
                        placeholder="Cari nama, SKU, barcode..."
                        class="w-full rounded-xl border border-neutral-200 pl-10 pr-4 py-2.5 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                    >

                </div>


                <!-- TYPE -->

                <select
                    name="type"
                    class="rounded-xl border border-neutral-200 px-3 py-2.5 text-sm bg-white outline-none focus:border-neutral-400"
                >

                    <option value="ALL">
                        Semua Jenis
                    </option>

                    <option
                        value="TOKO"
                        <?= $typeFilter === 'TOKO' ? 'selected' : '' ?>
                    >
                        Produk Toko
                    </option>

                    <option
                        value="TITIPAN"
                        <?= $typeFilter === 'TITIPAN' ? 'selected' : '' ?>
                    >
                        Produk Titipan
                    </option>

                </select>


                <!-- STATUS -->

                <select
                    name="status"
                    class="rounded-xl border border-neutral-200 px-3 py-2.5 text-sm bg-white outline-none focus:border-neutral-400"
                >

                    <option value="ALL">
                        Semua Status
                    </option>

                    <option
                        value="ACTIVE"
                        <?= $statusFilter === 'ACTIVE' ? 'selected' : '' ?>
                    >
                        Aktif
                    </option>

                    <option
                        value="INACTIVE"
                        <?= $statusFilter === 'INACTIVE' ? 'selected' : '' ?>
                    >
                        Tidak Aktif
                    </option>

                </select>


                <!-- CATEGORY -->

                <select
                    name="category_id"
                    class="rounded-xl border border-neutral-200 px-3 py-2.5 text-sm bg-white outline-none focus:border-neutral-400"
                >

                    <option value="0">
                        Semua Kategori
                    </option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= (int) $category['id'] ?>"
                            <?= $categoryFilter === (int) $category['id'] ? 'selected' : '' ?>
                        >
                            <?= e($category['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>


                <div class="sm:col-span-2 lg:col-span-5 flex flex-col sm:flex-row gap-2">

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-neutral-900 text-white text-sm font-semibold hover:bg-neutral-800"
                    >

                        <i
                            data-lucide="filter"
                            class="w-4 h-4"
                        ></i>

                        Terapkan Filter

                    </button>


                    <a
                        href="/pages/barang/"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-neutral-200 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
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


        <!-- DATA -->

        <div class="bento-card overflow-hidden">


            <div class="px-5 sm:px-6 py-5 border-b border-neutral-100">

                <div class="flex items-center justify-between gap-3">

                    <div>

                        <h2 class="font-semibold">
                            Daftar Barang
                        </h2>

                        <p class="text-xs text-neutral-400 mt-1">
                            <?= number_format($totalProducts) ?>
                            barang ditampilkan
                        </p>

                    </div>

                    <?php if ($inactiveCount > 0): ?>

                        <span class="hidden sm:inline-flex text-xs text-neutral-400">
                            <?= number_format($inactiveCount) ?> tidak aktif
                        </span>

                    <?php endif; ?>

                </div>

            </div>


            <?php if (empty($products)): ?>

                <div class="px-6 py-16 text-center">

                    <div class="w-14 h-14 mx-auto rounded-2xl bg-neutral-100 flex items-center justify-center mb-4">

                        <i
                            data-lucide="package-open"
                            class="w-6 h-6 text-neutral-400"
                        ></i>

                    </div>

                    <h3 class="font-semibold">
                        Belum ada barang
                    </h3>

                    <p class="text-sm text-neutral-400 mt-1">
                        Belum ada barang yang sesuai dengan filter.
                    </p>

                    <a
                        href="/pages/barang/create.php"
                        class="inline-flex items-center gap-2 mt-5 px-4 py-2.5 rounded-xl bg-neutral-900 text-white text-sm font-semibold"
                    >
                        <i
                            data-lucide="plus"
                            class="w-4 h-4"
                        ></i>
                        Tambah Barang
                    </a>

                </div>

            <?php else: ?>


                <!-- DESKTOP -->

                <div class="hidden md:block overflow-x-auto">

                    <table class="w-full">

                        <thead>

                            <tr class="border-b border-neutral-100 bg-neutral-50/70">

                                <th class="px-6 py-3 text-left text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Barang
                                </th>

                                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Jenis
                                </th>

                                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Kategori
                                </th>

                                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Dibuat oleh
                                </th>

                                <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Harga Jual
                                </th>

                                <th class="px-4 py-3 text-center text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Stok
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

                            <?php foreach ($products as $product): ?>

                                <?php

                                $isTitipan =
                                    $product['product_type'] === 'TITIPAN';

                                $isActive =
                                    $product['status'] === 'ACTIVE';

                                $stock =
                                    (int) $product['current_stock'];

                                $minimum =
                                    (int) $product['minimum_stock'];

                                $lowStock =
                                    $stock <= $minimum;

                                ?>

                                <tr class="<?= !$isActive ? 'bg-neutral-50/60' : '' ?> hover:bg-neutral-50/70 transition">


                                    <!-- BARANG -->

                                    <td class="px-6 py-4">

                                        <a
                                            href="/pages/barang/view.php?id=<?= (int) $product['id'] ?>"
                                            class="block font-medium text-sm text-neutral-900 hover:text-neutral-600 hover:underline underline-offset-4 transition"
                                        >
                                            <?= e($product['name']) ?>
                                        </a>

                                        <div class="text-xs text-neutral-400 mt-1">

                                            SKU <?= e($product['sku']) ?>

                                            <?php if (!empty($product['barcode'])): ?>

                                                <span class="mx-1">
                                                    ·
                                                </span>

                                                <?= e($product['barcode']) ?>

                                            <?php endif; ?>

                                        </div>

                                        <?php if ($isTitipan && !empty($product['consignor_name'])): ?>

                                            <div class="text-xs text-neutral-500 mt-1 flex items-center gap-1">

                                                <i
                                                    data-lucide="user-round"
                                                    class="w-3 h-3"
                                                ></i>

                                                <?= e($product['consignor_name']) ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- JENIS -->

                                    <td class="px-4 py-4">

                                        <?php if ($isTitipan): ?>

                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-violet-50 text-violet-700 px-2.5 py-1 text-xs font-medium">

                                                <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>

                                                Titipan

                                            </span>

                                        <?php else: ?>

                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 text-blue-700 px-2.5 py-1 text-xs font-medium">

                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>

                                                Toko

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- KATEGORI -->

                                    <td class="px-4 py-4">

                                        <span class="text-sm text-neutral-600">

                                            <?= e($product['category_name'] ?: '-') ?>

                                        </span>

                                    </td>


                                    <!-- DIBUAT OLEH -->

                                    <td class="px-4 py-4">
                                        <div class="text-sm font-medium text-neutral-800">
                                            <?= e($product['created_by_name'] ?: '-') ?>
                                        </div>
                                        <?php if (!empty($product['created_at'])): ?>
                                            <div class="text-xs text-neutral-400 mt-1">
                                                <?= date('d M Y H:i', strtotime($product['created_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>


                                    <!-- HARGA -->

                                    <td class="px-4 py-4 text-right">

                                        <div class="text-sm font-medium text-neutral-900">
                                            <?= rupiah($product['selling_price']) ?>
                                        </div>

                                        <?php if ($isTitipan): ?>

                                            <div class="text-xs text-neutral-400 mt-1">

                                                Fee:

                                                <?php if ($product['fee_type'] === 'PERCENTAGE'): ?>

                                                    <?= number_format((float) $product['fee_value'], 0, ',', '.') ?>%

                                                <?php else: ?>

                                                    <?= rupiah($product['fee_value']) ?>

                                                <?php endif; ?>

                                            </div>

                                        <?php else: ?>

                                            <div class="text-xs text-neutral-400 mt-1">
                                                Modal <?= rupiah($product['buying_price']) ?>
                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- STOK -->

                                    <td class="px-4 py-4 text-center">

                                        <span class="
                                            inline-flex items-center gap-1.5
                                            text-sm font-semibold
                                            <?= $lowStock && $isActive ? 'text-red-600' : 'text-neutral-700' ?>
                                        ">

                                            <?php if ($lowStock && $isActive): ?>

                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>

                                            <?php endif; ?>

                                            <?= number_format($stock) ?>

                                            <span class="text-xs font-normal text-neutral-400">
                                                <?= e($product['unit']) ?>
                                            </span>

                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td class="px-4 py-4">

                                        <form
                                            method="POST"
                                            action="/pages/barang/status.php"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $product['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($csrfToken) ?>"
                                            >

                                            <?php if ($isActive): ?>

                                                <button
                                                    type="submit"
                                                    title="Klik untuk menonaktifkan"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 text-emerald-700 px-2.5 py-1.5 text-xs font-medium hover:bg-emerald-100 transition"
                                                >

                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>

                                                    Aktif

                                                </button>

                                            <?php else: ?>

                                                <button
                                                    type="submit"
                                                    title="Klik untuk mengaktifkan"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-100 text-neutral-500 px-2.5 py-1.5 text-xs font-medium hover:bg-neutral-200 transition"
                                                >

                                                    <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>

                                                    Tidak Aktif

                                                </button>

                                            <?php endif; ?>

                                        </form>

                                    </td>


                                    <!-- AKSI -->

                                    <td class="px-6 py-4">

                                        <div class="flex items-center justify-end gap-1">


                                            <a
                                                href="/pages/barang/edit.php?id=<?= (int) $product['id'] ?>"
                                                title="Edit barang"
                                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900"
                                            >

                                                <i
                                                    data-lucide="pencil"
                                                    class="w-4 h-4"
                                                ></i>

                                            </a>


                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- MOBILE -->

                <div class="md:hidden divide-y divide-neutral-100">

                    <?php foreach ($products as $product): ?>

                        <?php

                        $isTitipan =
                            $product['product_type'] === 'TITIPAN';

                        $isActive =
                            $product['status'] === 'ACTIVE';

                        $stock =
                            (int) $product['current_stock'];

                        $minimum =
                            (int) $product['minimum_stock'];

                        $lowStock =
                            $stock <= $minimum;

                        ?>

                        <div class="
                            p-4
                            <?= !$isActive ? 'bg-neutral-50/60' : '' ?>
                        ">


                            <div class="flex items-start justify-between gap-3">


                                <div class="min-w-0">

                                    <a
                                        href="/pages/barang/view.php?id=<?= (int) $product['id'] ?>"
                                        class="font-semibold text-sm text-neutral-900 hover:underline underline-offset-4"
                                    >
                                        <?= e($product['name']) ?>
                                    </a>

                                    <div class="text-xs text-neutral-400 mt-1">
                                        <?= e($product['sku']) ?>
                                    </div>

                                    <?php if ($isTitipan && !empty($product['consignor_name'])): ?>

                                        <div class="text-xs text-neutral-500 mt-1">

                                            Penitip:
                                            <?= e($product['consignor_name']) ?>

                                        </div>

                                    <?php endif; ?>


                                    <div class="text-xs text-neutral-400 mt-2">
                                        Dibuat oleh:
                                        <span class="text-neutral-600">
                                            <?= e($product['created_by_name'] ?: '-') ?>
                                        </span>
                                    </div>


                                </div>


                                <form
                                    method="POST"
                                    action="/pages/barang/status.php"
                                    class="flex-shrink-0"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $product['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>"
                                    >

                                    <?php if ($isActive): ?>

                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 text-emerald-700 px-2.5 py-1.5 text-xs font-medium"
                                        >

                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>

                                            Aktif

                                        </button>

                                    <?php else: ?>

                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-100 text-neutral-500 px-2.5 py-1.5 text-xs font-medium"
                                        >

                                            <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>

                                            Tidak Aktif

                                        </button>

                                    <?php endif; ?>

                                </form>

                            </div>


                            <div class="grid grid-cols-2 gap-3 mt-4">


                                <div class="rounded-xl bg-neutral-50 p-3">

                                    <div class="text-xs text-neutral-400">
                                        Harga Jual
                                    </div>

                                    <div class="font-semibold text-sm mt-1">
                                        <?= rupiah($product['selling_price']) ?>
                                    </div>

                                </div>


                                <div class="rounded-xl bg-neutral-50 p-3">

                                    <div class="text-xs text-neutral-400">
                                        Stok
                                    </div>

                                    <div class="
                                        font-semibold
                                        text-sm
                                        mt-1
                                        <?= $lowStock && $isActive ? 'text-red-600' : '' ?>
                                    ">

                                        <?= number_format($stock) ?>

                                        <span class="font-normal text-neutral-400">
                                            <?= e($product['unit']) ?>
                                        </span>

                                    </div>

                                </div>

                            </div>


                            <div class="flex items-center justify-between mt-4">

                                <div>

                                    <?php if ($isTitipan): ?>

                                        <span class="inline-flex items-center rounded-lg bg-violet-50 text-violet-700 px-2 py-1 text-xs font-medium">
                                            Titipan
                                        </span>

                                    <?php else: ?>

                                        <span class="inline-flex items-center rounded-lg bg-blue-50 text-blue-700 px-2 py-1 text-xs font-medium">
                                            Toko
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <a
                                    href="/pages/barang/edit.php?id=<?= (int) $product['id'] ?>"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-600 hover:text-neutral-900"
                                >

                                    <i
                                        data-lucide="pencil"
                                        class="w-4 h-4"
                                    ></i>

                                    Edit

                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</main>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>