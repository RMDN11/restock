<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';

$pageTitle = 'Kategori Barang';


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$flashSuccess =
    $_SESSION['flash_success'] ?? null;

$flashError =
    $_SESSION['flash_error'] ?? null;

unset(
    $_SESSION['flash_success'],
    $_SESSION['flash_error']
);


/*
|--------------------------------------------------------------------------
| FILTER STATUS
|--------------------------------------------------------------------------
*/

$status = $_GET['status'] ?? 'ALL';

if (!in_array(
    $status,
    ['ALL', 'ACTIVE', 'INACTIVE'],
    true
)) {
    $status = 'ALL';
}


/*
|--------------------------------------------------------------------------
| QUERY KATEGORI
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        pc.id,
        pc.name,
        pc.status,
        pc.created_at,
        pc.updated_at,
        COUNT(p.id) AS product_count
    FROM product_categories pc
    LEFT JOIN products p
        ON p.category_id = pc.id
        AND p.store_id = pc.store_id
    WHERE pc.store_id = :category_store_id
";

$params = [
    ':category_store_id' => $authStoreId
];

if ($status !== 'ALL') {

    $sql .= "
        AND pc.status = :status
    ";

    $params[':status'] = $status;
}

$sql .= "
    GROUP BY
        pc.id,
        pc.name,
        pc.status,
        pc.created_at,
        pc.updated_at
    ORDER BY
        pc.name ASC
";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$categories = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

$totalCategories = count($categories);

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

?>

<div class="main-content">

    <!-- MOBILE HEADER -->

<main class="p-4 md:p-6 lg:p-8">


        <!-- HEADER -->

        <div class="
            flex flex-col
            sm:flex-row
            sm:items-center
            sm:justify-between
            gap-4
            mb-6
        ">

            <div>

                <div class="flex items-center gap-3">

                    <a
                        href="/pages/barang/"
                        class="
                            w-10 h-10
                            rounded-xl
                            bg-white
                            border border-neutral-200
                            flex items-center justify-center
                            hover:bg-neutral-50
                            transition
                        "
                        title="Kembali ke Barang"
                    >
                        <i
                            data-lucide="arrow-left"
                            class="w-5 h-5"
                        ></i>
                    </a>

                    <div>

                        <h1 class="
                            text-2xl md:text-3xl
                            font-semibold
                            tracking-tight
                        ">
                            Kategori Barang
                        </h1>

                        <p class="
                            text-sm
                            text-neutral-500
                            mt-1
                        ">
                            Kelola kategori yang digunakan pada barang.
                        </p>

                    </div>

                </div>

            </div>


            <!-- TAMBAH -->

            <a
                href="/pages/barang/kategori/create.php"
                class="
                    inline-flex
                    items-center
                    justify-center
                    gap-2
                    h-11
                    px-4
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

                Tambah Kategori

            </a>

        </div>


        <!-- FLASH SUCCESS -->

        <?php if ($flashSuccess): ?>

            <div class="
                mb-5
                rounded-2xl
                border border-emerald-200
                bg-emerald-50
                px-4 py-3
                flex items-center gap-3
                text-sm
                text-emerald-700
            ">

                <i
                    data-lucide="circle-check"
                    class="w-5 h-5 shrink-0"
                ></i>

                <span>
                    <?= e($flashSuccess) ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- FLASH ERROR -->

        <?php if ($flashError): ?>

            <div class="
                mb-5
                rounded-2xl
                border border-red-200
                bg-red-50
                px-4 py-3
                flex items-center gap-3
                text-sm
                text-red-700
            ">

                <i
                    data-lucide="circle-alert"
                    class="w-5 h-5 shrink-0"
                ></i>

                <span>
                    <?= e($flashError) ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- FILTER -->

        <div class="
            bento-card
            p-3
            mb-5
            inline-flex
            flex-wrap
            gap-2
        ">

            <a
                href="/pages/barang/kategori/"
                class="
                    px-4 py-2
                    rounded-xl
                    text-sm
                    font-medium
                    transition
                    <?= $status === 'ALL'
                        ? 'bg-neutral-900 text-white'
                        : 'text-neutral-500 hover:bg-neutral-100'
                    ?>
                "
            >
                Semua
            </a>


            <a
                href="/pages/barang/kategori/?status=ACTIVE"
                class="
                    px-4 py-2
                    rounded-xl
                    text-sm
                    font-medium
                    transition
                    <?= $status === 'ACTIVE'
                        ? 'bg-neutral-900 text-white'
                        : 'text-neutral-500 hover:bg-neutral-100'
                    ?>
                "
            >
                Aktif
            </a>


            <a
                href="/pages/barang/kategori/?status=INACTIVE"
                class="
                    px-4 py-2
                    rounded-xl
                    text-sm
                    font-medium
                    transition
                    <?= $status === 'INACTIVE'
                        ? 'bg-neutral-900 text-white'
                        : 'text-neutral-500 hover:bg-neutral-100'
                    ?>
                "
            >
                Tidak Aktif
            </a>

        </div>


        <!-- TABLE -->

        <section class="bento-card overflow-hidden">

            <div class="
                px-5 md:px-6
                py-5
                border-b border-neutral-100
                flex items-center justify-between
            ">

                <div>

                    <h2 class="font-semibold text-lg">
                        Daftar Kategori
                    </h2>

                    <p class="
                        text-sm
                        text-neutral-500
                        mt-1
                    ">
                        <?= $totalCategories ?>
                        kategori
                    </p>

                </div>

            </div>


            <?php if (empty($categories)): ?>

                <div class="px-6 py-14 text-center">

                    <div class="
                        w-12 h-12
                        mx-auto
                        rounded-2xl
                        bg-neutral-100
                        flex items-center justify-center
                        mb-3
                    ">

                        <i
                            data-lucide="layers-3"
                            class="
                                w-6 h-6
                                text-neutral-400
                            "
                        ></i>

                    </div>

                    <p class="
                        text-sm
                        font-medium
                        text-neutral-700
                    ">
                        Belum ada kategori.
                    </p>

                    <p class="
                        text-xs
                        text-neutral-400
                        mt-1
                    ">
                        Tambahkan kategori pertama untuk barang.
                    </p>

                </div>

            <?php else: ?>

                <!-- DESKTOP -->

                <div class="
                    hidden
                    md:block
                    overflow-x-auto
                ">

                    <table class="w-full">

                        <thead>

                            <tr class="
                                border-b
                                border-neutral-100
                            ">

                                <th class="
                                    text-left
                                    px-6 py-4
                                    text-xs
                                    font-semibold
                                    text-neutral-400
                                    uppercase
                                    tracking-wide
                                ">
                                    Kategori
                                </th>

                                <th class="
                                    text-left
                                    px-6 py-4
                                    text-xs
                                    font-semibold
                                    text-neutral-400
                                    uppercase
                                    tracking-wide
                                ">
                                    Jumlah Barang
                                </th>

                                <th class="
                                    text-left
                                    px-6 py-4
                                    text-xs
                                    font-semibold
                                    text-neutral-400
                                    uppercase
                                    tracking-wide
                                ">
                                    Status
                                </th>

                                <th class="
                                    text-right
                                    px-6 py-4
                                    text-xs
                                    font-semibold
                                    text-neutral-400
                                    uppercase
                                    tracking-wide
                                ">
                                    Aksi
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($categories as $category): ?>

                                <tr class="
                                    border-b
                                    border-neutral-100
                                    last:border-0
                                    hover:bg-neutral-50
                                    transition
                                ">

                                    <!-- NAMA -->

                                    <td class="px-6 py-4">

                                        <div class="
                                            text-sm
                                            font-medium
                                            text-neutral-900
                                        ">
                                            <?= e($category['name']) ?>
                                        </div>

                                    </td>


                                    <!-- JUMLAH BARANG -->

                                    <td class="px-6 py-4">

                                        <span class="
                                            text-sm
                                            text-neutral-600
                                        ">
                                            <?= (int) $category['product_count'] ?>
                                            barang
                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td class="px-6 py-4">

                                        <form
                                            method="POST"
                                            action="/pages/barang/kategori/status.php"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $category['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>"
                                            >

                                            <?php if ($category['status'] === 'ACTIVE'): ?>

                                                <button
                                                    type="submit"
                                                    class="
                                                        inline-flex
                                                        items-center
                                                        gap-2
                                                        px-3 py-1.5
                                                        rounded-lg
                                                        text-xs
                                                        font-medium
                                                        bg-emerald-50
                                                        text-emerald-700
                                                        hover:bg-emerald-100
                                                        transition
                                                    "
                                                    title="Klik untuk menonaktifkan"
                                                >

                                                    <span class="
                                                        w-1.5 h-1.5
                                                        rounded-full
                                                        bg-emerald-500
                                                    "></span>

                                                    Aktif

                                                </button>

                                            <?php else: ?>

                                                <button
                                                    type="submit"
                                                    class="
                                                        inline-flex
                                                        items-center
                                                        gap-2
                                                        px-3 py-1.5
                                                        rounded-lg
                                                        text-xs
                                                        font-medium
                                                        bg-neutral-100
                                                        text-neutral-500
                                                        hover:bg-neutral-200
                                                        transition
                                                    "
                                                    title="Klik untuk mengaktifkan"
                                                >

                                                    <span class="
                                                        w-1.5 h-1.5
                                                        rounded-full
                                                        bg-neutral-400
                                                    "></span>

                                                    Tidak Aktif

                                                </button>

                                            <?php endif; ?>

                                        </form>

                                    </td>


                                    <!-- AKSI -->

                                    <td class="px-6 py-4 text-right">

                                        <a
                                            href="/pages/barang/kategori/edit.php?id=<?= (int) $category['id'] ?>"
                                            class="
                                                inline-flex
                                                w-9 h-9
                                                rounded-xl
                                                items-center
                                                justify-center
                                                text-neutral-400
                                                hover:bg-neutral-100
                                                hover:text-neutral-700
                                                transition
                                            "
                                            title="Edit kategori"
                                        >

                                            <i
                                                data-lucide="pencil"
                                                class="w-4 h-4"
                                            ></i>

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- MOBILE -->

                <div class="md:hidden divide-y divide-neutral-100">

                    <?php foreach ($categories as $category): ?>

                        <div class="p-5">

                            <div class="
                                flex
                                items-start
                                justify-between
                                gap-4
                            ">

                                <div class="min-w-0">

                                    <p class="
                                        text-sm
                                        font-medium
                                        text-neutral-900
                                    ">
                                        <?= e($category['name']) ?>
                                    </p>

                                    <p class="
                                        text-xs
                                        text-neutral-400
                                        mt-1
                                    ">
                                        <?= (int) $category['product_count'] ?>
                                        barang
                                    </p>

                                </div>


                                <a
                                    href="/pages/barang/kategori/edit.php?id=<?= (int) $category['id'] ?>"
                                    class="
                                        w-9 h-9
                                        shrink-0
                                        rounded-xl
                                        flex items-center justify-center
                                        text-neutral-400
                                        hover:bg-neutral-100
                                    "
                                    title="Edit"
                                >

                                    <i
                                        data-lucide="pencil"
                                        class="w-4 h-4"
                                    ></i>

                                </a>

                            </div>


                            <form
                                method="POST"
                                action="/pages/barang/kategori/status.php"
                                class="mt-4"
                            >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $category['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                >

                                <?php if ($category['status'] === 'ACTIVE'): ?>

                                    <button
                                        type="submit"
                                        class="
                                            inline-flex
                                            items-center
                                            gap-2
                                            px-3 py-1.5
                                            rounded-lg
                                            text-xs
                                            font-medium
                                            bg-emerald-50
                                            text-emerald-700
                                        "
                                    >

                                        <span class="
                                            w-1.5 h-1.5
                                            rounded-full
                                            bg-emerald-500
                                        "></span>

                                        Aktif

                                    </button>

                                <?php else: ?>

                                    <button
                                        type="submit"
                                        class="
                                            inline-flex
                                            items-center
                                            gap-2
                                            px-3 py-1.5
                                            rounded-lg
                                            text-xs
                                            font-medium
                                            bg-neutral-100
                                            text-neutral-500
                                        "
                                    >

                                        <span class="
                                            w-1.5 h-1.5
                                            rounded-full
                                            bg-neutral-400
                                        "></span>

                                        Tidak Aktif

                                    </button>

                                <?php endif; ?>

                            </form>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

});
</script>


<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>