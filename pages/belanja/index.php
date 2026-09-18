<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$pageTitle = 'Belanja';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['q'] ?? ''
);

$dateFrom = $_GET['from'] ?? '';

$dateTo = $_GET['to'] ?? '';


/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];


if ($search !== '') {

    $where[] = "
        (
            p.purchase_number LIKE :search_number
            OR s.name LIKE :search_supplier
        )
    ";

    $searchValue = '%' . $search . '%';
    $params[':search_number'] = $searchValue;
    $params[':search_supplier'] = $searchValue;

}


if ($dateFrom !== '') {

    $where[] =
        "DATE(p.purchase_date) >= :date_from";

    $params[':date_from'] =
        $dateFrom;

}


if ($dateTo !== '') {

    $where[] =
        "DATE(p.purchase_date) <= :date_to";

    $params[':date_to'] =
        $dateTo;

}


/*
|--------------------------------------------------------------------------
| MAIN QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.purchase_number,
        p.purchase_date,
        p.total_amount,
        p.notes,
        s.name AS supplier_name,
        COUNT(pi.id) AS item_count,
        COALESCE(
            SUM(pi.quantity),
            0
        ) AS total_quantity

    FROM purchases p

    LEFT JOIN suppliers s
        ON s.id = p.supplier_id
       AND s.store_id = p.store_id

    LEFT JOIN purchase_items pi
        ON pi.purchase_id = p.id
       AND pi.store_id = p.store_id
";


$where[] = "p.store_id = :purchase_store_id";
$params[':purchase_store_id'] = $authStoreId;

if (!empty($where)) {

    $sql .= "
        WHERE " .
        implode(
            " AND ",
            $where
        );

}


$sql .= "
    GROUP BY
        p.id,
        p.purchase_number,
        p.purchase_date,
        p.total_amount,
        p.notes,
        s.name

    ORDER BY
        p.purchase_date DESC,
        p.id DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$purchases =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RUPIAH
|--------------------------------------------------------------------------
*/

function rupiah($value): string
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
| FLASH
|--------------------------------------------------------------------------
*/

$success =
    $_SESSION['success'] ?? null;

$error =
    $_SESSION['error'] ?? null;

unset(
    $_SESSION['success'],
    $_SESSION['error']
);

?>


<div class="main-content">


    <!-- MOBILE HEADER -->
<main class="p-4 md:p-6 lg:p-8">


        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">

            <div>

                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                    Belanja
                </h1>

                <p class="mt-2 text-sm text-neutral-500">
                    Catat barang yang masuk dari supplier.
                </p>

            </div>


            <a
                href="/pages/belanja/create.php"
                class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800 transition"
            >

                <i
                    data-lucide="plus"
                    class="w-4 h-4"
                ></i>

                Tambah Belanja

            </a>

        </div>


        <!-- FLASH SUCCESS -->
        <?php if ($success): ?>

            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <!-- FLASH ERROR -->
        <?php if ($error): ?>

            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <!-- FILTER -->
        <section class="bento-card p-4 md:p-5 mb-5">

            <form
                method="GET"
                class="flex flex-col lg:flex-row lg:items-end gap-3"
            >


                <!-- SEARCH -->
                <div class="flex-1">

                    <label class="block text-xs font-medium text-neutral-500 mb-2">
                        Cari
                    </label>

                    <div class="relative">

                        <i
                            data-lucide="search"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400"
                        ></i>

                        <input
                            type="text"
                            name="q"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Nomor belanja atau supplier..."
                            class="w-full h-11 pl-10 pr-4 rounded-xl border border-neutral-200 bg-neutral-50 text-sm outline-none focus:bg-white focus:border-neutral-400 transition"
                        >

                    </div>

                </div>


                <!-- DARI -->
                <div class="w-full lg:w-44">

                    <label class="block text-xs font-medium text-neutral-500 mb-2">
                        Dari
                    </label>

                    <input
                        type="date"
                        name="from"
                        value="<?= htmlspecialchars($dateFrom) ?>"
                        class="w-full h-11 px-3 rounded-xl border border-neutral-200 bg-white text-sm outline-none focus:border-neutral-400"
                    >

                </div>


                <!-- SAMPAI -->
                <div class="w-full lg:w-44">

                    <label class="block text-xs font-medium text-neutral-500 mb-2">
                        Sampai
                    </label>

                    <input
                        type="date"
                        name="to"
                        value="<?= htmlspecialchars($dateTo) ?>"
                        class="w-full h-11 px-3 rounded-xl border border-neutral-200 bg-white text-sm outline-none focus:border-neutral-400"
                    >

                </div>


                <button
                    type="submit"
                    class="h-11 px-5 rounded-xl border border-neutral-200 bg-white text-sm font-medium text-neutral-700 hover:bg-neutral-50 transition"
                >
                    Filter
                </button>


                <?php if (
                    $search !== '' ||
                    $dateFrom !== '' ||
                    $dateTo !== ''
                ): ?>

                    <a
                        href="/pages/belanja/"
                        class="h-11 px-4 rounded-xl flex items-center justify-center text-sm text-neutral-500 hover:text-neutral-900"
                    >
                        Reset
                    </a>

                <?php endif; ?>

            </form>

        </section>


        <!-- LIST -->
        <section class="bento-card overflow-hidden">


            <!-- HEADER -->
            <div class="px-5 md:px-6 py-5 border-b border-neutral-100">

                <h2 class="font-semibold">
                    Riwayat Belanja
                </h2>

                <p class="text-sm text-neutral-500 mt-1">
                    <?= count($purchases) ?> transaksi
                </p>

            </div>


            <?php if (empty($purchases)): ?>


                <!-- EMPTY -->
                <div class="px-6 py-16 text-center">

                    <div class="w-14 h-14 mx-auto rounded-2xl bg-neutral-100 flex items-center justify-center mb-4">

                        <i
                            data-lucide="shopping-cart"
                            class="w-7 h-7 text-neutral-400"
                        ></i>

                    </div>

                    <h3 class="font-medium">
                        Belum ada transaksi belanja
                    </h3>

                    <p class="text-sm text-neutral-500 mt-1">
                        Transaksi belanja yang kamu tambahkan akan muncul di sini.
                    </p>

                </div>


            <?php else: ?>


                <!-- DESKTOP -->
                <div class="hidden md:block overflow-x-auto">

                    <table class="w-full">

                        <thead>

                            <tr class="border-b border-neutral-100">

                                <th class="text-left px-6 py-4 text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Nomor
                                </th>

                                <th class="text-left px-6 py-4 text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Tanggal
                                </th>

                                <th class="text-left px-6 py-4 text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Supplier
                                </th>

                                <th class="text-center px-6 py-4 text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Barang
                                </th>

                                <th class="text-right px-6 py-4 text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Total
                                </th>

                                <th class="text-right px-6 py-4 text-xs font-semibold text-neutral-400 uppercase tracking-wide">
                                    Aksi
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($purchases as $purchase): ?>

                            <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50 transition">


                                <!-- NOMOR -->
                                <td class="px-6 py-4">

                                    <div class="text-sm font-medium">
                                        <?= htmlspecialchars(
                                            $purchase['purchase_number']
                                        ) ?>
                                    </div>

                                </td>


                                <!-- TANGGAL -->
                                <td class="px-6 py-4">

                                    <div class="text-sm text-neutral-600">

                                        <?= date(
                                            'd M Y',
                                            strtotime(
                                                $purchase['purchase_date']
                                            )
                                        ) ?>

                                    </div>

                                    <div class="text-xs text-neutral-400 mt-1">

                                        <?= date(
                                            'H:i',
                                            strtotime(
                                                $purchase['purchase_date']
                                            )
                                        ) ?>

                                    </div>

                                </td>


                                <!-- SUPPLIER -->
                                <td class="px-6 py-4">

                                    <span class="text-sm text-neutral-600">

                                        <?= htmlspecialchars(
                                            $purchase['supplier_name']
                                            ?: '-'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- BARANG -->
                                <td class="px-6 py-4 text-center">

                                    <span class="text-sm text-neutral-600">
                                        <?= (int) $purchase['total_quantity'] ?>
                                    </span>

                                    <span class="text-xs text-neutral-400">
                                        item
                                    </span>

                                </td>


                                <!-- TOTAL -->
                                <td class="px-6 py-4 text-right">

                                    <span class="text-sm font-semibold">
                                        <?= rupiah(
                                            $purchase['total_amount']
                                        ) ?>
                                    </span>

                                </td>


                                <!-- AKSI -->
                                <td class="px-6 py-4">

                                    <div class="flex items-center justify-end gap-1">

                                        <a
                                            href="/pages/belanja/invoice.php?id=<?= (int) $purchase['id'] ?>"
                                            target="_blank"
                                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-neutral-100 text-neutral-600 text-xs font-medium"
                                        >
                                            <i
                                                data-lucide="file-down"
                                                class="w-3.5 h-3.5"
                                            ></i>
                                            Invoice
                                        </a>
                                        <!-- DETAIL -->
                                        <a
                                            href="/pages/belanja/view.php?id=<?= (int) $purchase['id'] ?>"
                                            title="Detail"
                                            class="w-9 h-9 rounded-lg flex items-center justify-center text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 transition"
                                        >

                                            <i
                                                data-lucide="eye"
                                                class="w-4 h-4"
                                            ></i>

                                        </a>


                                        <!-- EDIT -->
                                        <a
                                            href="/pages/belanja/edit.php?id=<?= (int) $purchase['id'] ?>"
                                            title="Edit"
                                            class="w-9 h-9 rounded-lg flex items-center justify-center text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 transition"
                                        >

                                            <i
                                                data-lucide="pencil"
                                                class="w-4 h-4"
                                            ></i>

                                        </a>


                                        <!-- DELETE -->
                                        <form
                                            method="POST"
                                            action="/pages/belanja/delete.php"
                                            onsubmit="return confirm('Hapus transaksi belanja ini? Stok barang juga akan dikurangi sesuai transaksi.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $purchase['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(
                                                    $_SESSION['csrf_token'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                            >

                                            <button
                                                type="submit"
                                                title="Hapus"
                                                class="w-9 h-9 rounded-lg flex items-center justify-center text-neutral-400 hover:bg-red-50 hover:text-red-600 transition"
                                            >

                                                <i
                                                    data-lucide="trash-2"
                                                    class="w-4 h-4"
                                                ></i>

                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- MOBILE -->
                <div class="md:hidden divide-y divide-neutral-100">

                    <?php foreach ($purchases as $purchase): ?>

                        <div class="p-5">

                            <div class="flex items-start justify-between gap-4">


                                <div class="min-w-0">

                                    <div class="text-sm font-medium">
                                        <?= htmlspecialchars(
                                            $purchase['purchase_number']
                                        ) ?>
                                    </div>

                                    <div class="text-xs text-neutral-400 mt-1">

                                        <?= date(
                                            'd M Y H:i',
                                            strtotime(
                                                $purchase['purchase_date']
                                            )
                                        ) ?>

                                    </div>


                                    <div class="text-sm text-neutral-600 mt-3">

                                        <?= htmlspecialchars(
                                            $purchase['supplier_name']
                                            ?: '-'
                                        ) ?>

                                    </div>


                                    <div class="text-xs text-neutral-400 mt-1">

                                        <?= (int) $purchase['total_quantity'] ?>
                                        barang

                                    </div>

                                </div>


                                <div class="text-right shrink-0">

                                    <div class="text-sm font-semibold">
                                        <?= rupiah(
                                            $purchase['total_amount']
                                        ) ?>
                                    </div>

                                </div>

                            </div>


                            <!-- MOBILE ACTION -->
                            <div class="flex items-center justify-end gap-2 mt-4 pt-4 border-t border-neutral-100">

                                <a
                                    href="/pages/belanja/invoice.php?id=<?= (int) $purchase['id'] ?>"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-neutral-100 text-neutral-600 text-xs font-medium"
                                >
                                    <i
                                        data-lucide="file-down"
                                        class="w-3.5 h-3.5"
                                    ></i>
                                    Invoice
                                </a>
                                <a
                                    href="/pages/belanja/view.php?id=<?= (int) $purchase['id'] ?>"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-neutral-100 text-neutral-600 text-xs font-medium"
                                >
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    Detail
                                </a>


                                <a
                                    href="/pages/belanja/edit.php?id=<?= (int) $purchase['id'] ?>"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-neutral-100 text-neutral-600 text-xs font-medium"
                                >
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                    Edit
                                </a>


                                <form
                                    method="POST"
                                    action="/pages/belanja/delete.php"
                                    onsubmit="return confirm('Hapus transaksi belanja ini? Stok barang juga akan dikurangi sesuai transaksi.');"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $purchase['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $_SESSION['csrf_token'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-red-50 text-red-600 text-xs font-medium"
                                    >
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        Hapus
                                    </button>

                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>


            <?php endif; ?>

        </section>

    </main>

</div>


<?php
require_once __DIR__ . '/../../includes/footer.php';
?>