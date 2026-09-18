<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/auth.php';
require_once '../../config/database.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) { http_response_code(403); exit('Store aktif tidak ditemukan.'); }

$pageTitle = 'Data Penitip';

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
| FILTER
|--------------------------------------------------------------------------
*/

$statusFilter = $_GET['status'] ?? 'ALL';
$search = trim($_GET['search'] ?? '');

if (!in_array($statusFilter, ['ALL', 'ACTIVE', 'INACTIVE'], true)) {
    $statusFilter = 'ALL';
}

/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        c.id,
        c.name,
        c.phone,
        c.address,
        c.notes,
        c.status,
        c.created_at,

        (
            SELECT COUNT(DISTINCT cs.product_id)
            FROM consignments cs
            INNER JOIN products p
                ON p.id = cs.product_id
                AND p.store_id = $storeId
            WHERE cs.consignor_id = c.id
            AND cs.store_id = $storeId
        ) AS total_products,

        (
            SELECT COALESCE(SUM(p.current_stock), 0)
            FROM consignments cs
            INNER JOIN products p
                ON p.id = cs.product_id
                AND p.store_id = $storeId
            WHERE cs.consignor_id = c.id
            AND cs.store_id = $storeId
            AND p.product_type = 'TITIPAN'
        ) AS total_stock

    FROM consignors c
    WHERE c.store_id = $storeId
";

$params = [];

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

if ($statusFilter !== 'ALL') {

    $sql .= "
        AND c.status = :status
    ";

    $params[':status'] = $statusFilter;
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            c.name LIKE :search_name
            OR c.phone LIKE :search_phone
        )
    ";

    $params[':search_name'] = '%' . $search . '%';
    $params[':search_phone'] = '%' . $search . '%';
}

$sql .= "
    ORDER BY c.name ASC
";

try {

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $consignors = $stmt->fetchAll();

} catch (PDOException $e) {

    die(
        '<pre style="padding:20px;font-family:monospace;">' .
        'DATABASE ERROR - DATA PENITIP' .
        "\n\n" .
        e($e->getMessage()) .
        '</pre>'
    );
}

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require_once '../../includes/header.php';

?>

<?php require_once '../../includes/sidebar.php'; ?>


<main class="main-content">

<div class="p-5 md:p-8 lg:p-10">


        <!-- =========================================================
     HEADER
========================================================== -->

<div class="
    flex
    flex-col
    md:flex-row
    md:items-end
    md:justify-between
    gap-5
    mb-8
">


    <!-- TITLE -->

    <div>

        <h1 class="
            text-2xl
            md:text-3xl
            font-semibold
            tracking-tight
            text-neutral-900
        ">
            Data Penitip
        </h1>


        <p class="
            text-sm
            text-neutral-500
            mt-2
        ">
            Kelola data orang yang menitipkan barang di toko.
        </p>

    </div>


    <!-- ACTION -->

    <div class="
        flex
        flex-col
        sm:flex-row
        gap-2
    ">


        <!-- BARANG TITIPAN -->

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
                text-sm
                font-medium
                text-neutral-700
                hover:bg-neutral-50
                transition
            "
        >

            <i
                data-lucide="package"
                class="w-4 h-4"
            ></i>

            Barang Titipan

        </a>


        <!-- TAMBAH PENITIP -->

        <a
            href="/pages/titipan/create-penitip.php"
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

            Tambah

        </a>

    </div>

</div>


        <!-- =========================================================
             FILTER
        ========================================================== -->

        <div class="bento-card p-4 md:p-5 mb-6">

            <form
                method="GET"
                class="grid grid-cols-1 md:grid-cols-4 gap-3"
            >


                <!-- SEARCH -->

                <div class="md:col-span-3">

                    <label class="
                        block
                        text-xs
                        font-medium
                        text-neutral-500
                        mb-2
                    ">
                        Cari penitip
                    </label>


                    <div class="relative">

                        <i
                            data-lucide="search"
                            class="
                                absolute
                                left-3
                                top-1/2
                                -translate-y-1/2
                                w-4
                                h-4
                                text-neutral-400
                            "
                        ></i>


                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Nama atau nomor WhatsApp..."
                            class="
                                w-full
                                pl-10
                                pr-4
                                py-2.5
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                outline-none
                                focus:border-neutral-400
                            "
                        >

                    </div>

                </div>


                <!-- STATUS -->

                <div>

                    <label class="
                        block
                        text-xs
                        font-medium
                        text-neutral-500
                        mb-2
                    ">
                        Status
                    </label>


                    <select
                        name="status"
                        class="
                            w-full
                            px-3
                            py-2.5
                            rounded-xl
                            border
                            border-neutral-200
                            bg-white
                            text-sm
                            outline-none
                            focus:border-neutral-400
                        "
                    >

                        <option
                            value="ALL"
                            <?= $statusFilter === 'ALL' ? 'selected' : '' ?>
                        >
                            Semua
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

                </div>


                <!-- BUTTON -->

                <div class="md:col-span-4 flex gap-2">

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
                            font-medium
                            hover:bg-neutral-800
                        "
                    >

                        <i
                            data-lucide="search"
                            class="w-4 h-4"
                        ></i>

                        Cari

                    </button>


                    <a
                        href="/pages/titipan/penitip.php"
                        class="
                            inline-flex
                            items-center
                            justify-center
                            px-4
                            py-2.5
                            rounded-xl
                            border
                            border-neutral-200
                            bg-white
                            text-neutral-600
                            text-sm
                            font-medium
                            hover:bg-neutral-50
                        "
                    >
                        Reset
                    </a>

                </div>

            </form>

        </div>


        <!-- =========================================================
             TOTAL
        ========================================================== -->

        <div class="mb-4">

            <p class="text-sm text-neutral-500">
                Total penitip
            </p>

            <p class="
                text-2xl
                font-semibold
                text-neutral-900
            ">
                <?= number_format(count($consignors), 0, ',', '.') ?>
            </p>

        </div>


        <!-- =========================================================
             DESKTOP TABLE
        ========================================================== -->

        <div class="
            hidden
            lg:block
            bento-card
            overflow-hidden
        ">

            <div class="overflow-x-auto">

                <table class="w-full">

                    <thead>

                        <tr class="border-b border-neutral-100">

                            <th class="
                                px-6
                                py-4
                                text-left
                                text-xs
                                font-medium
                                text-neutral-400
                                uppercase
                                tracking-wide
                            ">
                                Penitip
                            </th>


                            <th class="
                                px-6
                                py-4
                                text-left
                                text-xs
                                font-medium
                                text-neutral-400
                                uppercase
                                tracking-wide
                            ">
                                Kontak
                            </th>


                            <th class="
                                px-6
                                py-4
                                text-center
                                text-xs
                                font-medium
                                text-neutral-400
                                uppercase
                                tracking-wide
                            ">
                                Barang
                            </th>


                            <th class="
                                px-6
                                py-4
                                text-center
                                text-xs
                                font-medium
                                text-neutral-400
                                uppercase
                                tracking-wide
                            ">
                                Stok
                            </th>


                            <th class="
                                px-6
                                py-4
                                text-center
                                text-xs
                                font-medium
                                text-neutral-400
                                uppercase
                                tracking-wide
                            ">
                                Status
                            </th>


                            <th class="
                                px-6
                                py-4
                                text-right
                                text-xs
                                font-medium
                                text-neutral-400
                                uppercase
                                tracking-wide
                            ">
                                Aksi
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-neutral-100">


                        <?php if (empty($consignors)): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="px-6 py-16 text-center"
                                >

                                    <div class="
                                        w-12
                                        h-12
                                        rounded-2xl
                                        bg-neutral-100
                                        flex
                                        items-center
                                        justify-center
                                        mx-auto
                                        mb-4
                                    ">

                                        <i
                                            data-lucide="users"
                                            class="
                                                w-6
                                                h-6
                                                text-neutral-400
                                            "
                                        ></i>

                                    </div>


                                    <p class="
                                        text-sm
                                        font-medium
                                        text-neutral-700
                                    ">
                                        Belum ada data penitip
                                    </p>


                                    <p class="
                                        text-xs
                                        text-neutral-400
                                        mt-1
                                    ">
                                        Tambahkan penitip untuk mulai mencatat barang titipan.
                                    </p>

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($consignors as $consignor): ?>

                                <?php

                                $isActive =
                                    $consignor['status'] === 'ACTIVE';

                                ?>

                                <tr class="
                                    hover:bg-neutral-50/70
                                    transition
                                ">


                                    <!-- PENITIP -->

                                    <td class="px-6 py-4">

                                        <div class="
                                            text-sm
                                            font-medium
                                            text-neutral-900
                                        ">
                                            <?= e($consignor['name']) ?>
                                        </div>

                                        <?php if (!empty($consignor['address'])): ?>

                                            <div class="
                                                text-xs
                                                text-neutral-400
                                                mt-1
                                                max-w-xs
                                                truncate
                                            ">
                                                <?= e($consignor['address']) ?>
                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- KONTAK -->

                                    <td class="px-6 py-4">

                                        <?php if (!empty($consignor['phone'])): ?>

                                            <div class="
                                                text-sm
                                                text-neutral-700
                                            ">
                                                <?= e($consignor['phone']) ?>
                                            </div>

                                        <?php else: ?>

                                            <span class="
                                                text-xs
                                                text-neutral-400
                                            ">
                                                Tidak ada nomor
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- BARANG -->

                                    <td class="
                                        px-6
                                        py-4
                                        text-center
                                    ">

                                        <span class="
                                            inline-flex
                                            items-center
                                            justify-center
                                            min-w-8
                                            h-8
                                            px-2
                                            rounded-lg
                                            bg-neutral-100
                                            text-sm
                                            font-medium
                                            text-neutral-700
                                        ">
                                            <?= number_format((int) $consignor['total_products'], 0, ',', '.') ?>
                                        </span>

                                    </td>


                                    <!-- STOK -->

                                    <td class="
                                        px-6
                                        py-4
                                        text-center
                                    ">

                                        <span class="
                                            text-sm
                                            font-medium
                                            text-neutral-800
                                        ">
                                            <?= number_format((int) $consignor['total_stock'], 0, ',', '.') ?>
                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td class="
                                        px-6
                                        py-4
                                        text-center
                                    ">

                                        <form
                                            method="POST"
                                            action="/pages/titipan/status-penitip.php"
                                            onsubmit="return confirm('Ubah status penitip ini?')"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $consignor['id'] ?>"
                                            >


                                            <?php if ($isActive): ?>

                                                <button
                                                    type="submit"
                                                    class="
                                                        inline-flex
                                                        items-center
                                                        gap-1.5
                                                        px-2.5
                                                        py-1.5
                                                        rounded-lg
                                                        bg-green-50
                                                        text-green-700
                                                        text-xs
                                                        font-medium
                                                        hover:bg-green-100
                                                    "
                                                >

                                                    <span class="
                                                        w-1.5
                                                        h-1.5
                                                        rounded-full
                                                        bg-green-500
                                                    "></span>

                                                    Aktif

                                                </button>


                                            <?php else: ?>

                                                <button
                                                    type="submit"
                                                    class="
                                                        inline-flex
                                                        items-center
                                                        gap-1.5
                                                        px-2.5
                                                        py-1.5
                                                        rounded-lg
                                                        bg-neutral-100
                                                        text-neutral-500
                                                        text-xs
                                                        font-medium
                                                        hover:bg-neutral-200
                                                    "
                                                >

                                                    <span class="
                                                        w-1.5
                                                        h-1.5
                                                        rounded-full
                                                        bg-neutral-400
                                                    "></span>

                                                    Tidak Aktif

                                                </button>

                                            <?php endif; ?>

                                        </form>

                                    </td>


                                    <!-- AKSI -->

                                    <td class="px-6 py-4">

                                        <div class="
                                            flex
                                            items-center
                                            justify-end
                                            gap-1
                                        ">


                                            <a
                                                href="/pages/titipan/edit-penitip.php?id=<?= (int) $consignor['id'] ?>"
                                                class="
                                                    w-9
                                                    h-9
                                                    rounded-lg
                                                    flex
                                                    items-center
                                                    justify-center
                                                    text-neutral-500
                                                    hover:bg-neutral-100
                                                    hover:text-neutral-900
                                                    transition
                                                "
                                                title="Edit"
                                            >

                                                <i
                                                    data-lucide="pencil"
                                                    class="w-4 h-4"
                                                ></i>

                                            </a>


                                            <a
                                                href="/pages/titipan/index.php?consignor_id=<?= (int) $consignor['id'] ?>"
                                                class="
                                                    w-9
                                                    h-9
                                                    rounded-lg
                                                    flex
                                                    items-center
                                                    justify-center
                                                    text-neutral-500
                                                    hover:bg-neutral-100
                                                    hover:text-neutral-900
                                                    transition
                                                "
                                                title="Lihat Barang"
                                            >

                                                <i
                                                    data-lucide="package"
                                                    class="w-4 h-4"
                                                ></i>

                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =========================================================
             MOBILE
        ========================================================== -->

        <div class="lg:hidden space-y-3">


            <?php if (empty($consignors)): ?>

                <div class="bento-card p-8 text-center">

                    <div class="
                        w-12
                        h-12
                        rounded-2xl
                        bg-neutral-100
                        flex
                        items-center
                        justify-center
                        mx-auto
                        mb-4
                    ">

                        <i
                            data-lucide="users"
                            class="w-6 h-6 text-neutral-400"
                        ></i>

                    </div>


                    <p class="
                        text-sm
                        font-medium
                        text-neutral-700
                    ">
                        Belum ada data penitip
                    </p>

                    <p class="
                        text-xs
                        text-neutral-400
                        mt-1
                    ">
                        Tambahkan penitip untuk mulai mencatat.
                    </p>

                </div>


            <?php else: ?>


                <?php foreach ($consignors as $consignor): ?>

                    <?php

                    $isActive =
                        $consignor['status'] === 'ACTIVE';

                    ?>


                    <div class="bento-card p-5">


                        <!-- HEADER -->

                        <div class="
                            flex
                            items-start
                            justify-between
                            gap-3
                        ">

                            <div>

                                <div class="
                                    text-base
                                    font-semibold
                                    text-neutral-900
                                ">
                                    <?= e($consignor['name']) ?>
                                </div>


                                <?php if (!empty($consignor['phone'])): ?>

                                    <div class="
                                        text-xs
                                        text-neutral-400
                                        mt-1
                                    ">
                                        <?= e($consignor['phone']) ?>
                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- STATUS -->

                            <form
                                method="POST"
                                action="/pages/titipan/status-penitip.php"
                                onsubmit="return confirm('Ubah status penitip ini?')"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($csrfToken) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $consignor['id'] ?>"
                                >


                                <?php if ($isActive): ?>

                                    <button
                                        type="submit"
                                        class="
                                            inline-flex
                                            items-center
                                            gap-1.5
                                            px-2.5
                                            py-1.5
                                            rounded-lg
                                            bg-green-50
                                            text-green-700
                                            text-xs
                                            font-medium
                                        "
                                    >

                                        <span class="
                                            w-1.5
                                            h-1.5
                                            rounded-full
                                            bg-green-500
                                        "></span>

                                        Aktif

                                    </button>


                                <?php else: ?>

                                    <button
                                        type="submit"
                                        class="
                                            inline-flex
                                            items-center
                                            gap-1.5
                                            px-2.5
                                            py-1.5
                                            rounded-lg
                                            bg-neutral-100
                                            text-neutral-500
                                            text-xs
                                            font-medium
                                        "
                                    >

                                        <span class="
                                            w-1.5
                                            h-1.5
                                            rounded-full
                                            bg-neutral-400
                                        "></span>

                                        Tidak Aktif

                                    </button>

                                <?php endif; ?>

                            </form>

                        </div>


                        <!-- INFO -->

                        <div class="
                            grid
                            grid-cols-2
                            gap-3
                            mt-5
                            pt-5
                            border-t
                            border-neutral-100
                        ">


                            <div>

                                <div class="
                                    text-xs
                                    text-neutral-400
                                    mb-1
                                ">
                                    Barang
                                </div>

                                <div class="
                                    text-sm
                                    font-semibold
                                    text-neutral-900
                                ">
                                    <?= number_format((int) $consignor['total_products'], 0, ',', '.') ?>
                                </div>

                            </div>


                            <div>

                                <div class="
                                    text-xs
                                    text-neutral-400
                                    mb-1
                                ">
                                    Stok
                                </div>

                                <div class="
                                    text-sm
                                    font-semibold
                                    text-neutral-900
                                ">
                                    <?= number_format((int) $consignor['total_stock'], 0, ',', '.') ?>
                                </div>

                            </div>


                        </div>


                        <!-- ACTION -->

                        <div class="
                            flex
                            gap-2
                            mt-5
                        ">


                            <a
                                href="/pages/titipan/index.php?consignor_id=<?= (int) $consignor['id'] ?>"
                                class="
                                    flex-1
                                    inline-flex
                                    items-center
                                    justify-center
                                    gap-2
                                    px-3
                                    py-2.5
                                    rounded-xl
                                    border
                                    border-neutral-200
                                    text-sm
                                    font-medium
                                    text-neutral-700
                                    hover:bg-neutral-50
                                "
                            >

                                <i
                                    data-lucide="package"
                                    class="w-4 h-4"
                                ></i>

                                Barang

                            </a>


                            <a
                                href="/pages/titipan/edit-penitip.php?id=<?= (int) $consignor['id'] ?>"
                                class="
                                    flex-1
                                    inline-flex
                                    items-center
                                    justify-center
                                    gap-2
                                    px-3
                                    py-2.5
                                    rounded-xl
                                    bg-neutral-900
                                    text-white
                                    text-sm
                                    font-medium
                                    hover:bg-neutral-800
                                "
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


            <?php endif; ?>

        </div>

    </div>

</main>


<?php require_once '../../includes/footer.php'; ?>