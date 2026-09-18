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

$pageTitle = 'Barang Titipan';


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
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$statusFilter =
    $_GET['status'] ?? 'ALL';

$consignorFilter =
    isset($_GET['consignor_id'])
        ? (int) $_GET['consignor_id']
        : 0;

$search =
    trim($_GET['search'] ?? '');


$allowedStatus = [
    'ALL',
    'AVAILABLE',
    'EMPTY',
    'INACTIVE'
];

if (
    !in_array(
        $statusFilter,
        $allowedStatus,
        true
    )
) {
    $statusFilter = 'ALL';
}


/*
|--------------------------------------------------------------------------
| PENITIP
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

$consignors =
    $stmt->fetchAll();


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
        p.unit,
        p.selling_price,
        p.current_stock,
        p.minimum_stock,
        p.status AS product_status,

        pc.name AS category_name,

        c.id AS consignment_id,
        c.consignor_id,
        c.initial_price,
        c.fee_type,
        c.fee_value,
        c.start_date,
        c.end_date,

        co.name AS consignor_name,
        co.phone AS consignor_phone

    FROM products p

    LEFT JOIN product_categories pc
        ON pc.id = p.category_id
        AND pc.store_id = p.store_id

    LEFT JOIN consignments c
        ON c.id = (
            SELECT c2.id
            FROM consignments c2
            WHERE c2.product_id = p.id
            AND c2.store_id = p.store_id
            ORDER BY
                CASE
                    WHEN c2.status = 'ACTIVE'
                    THEN 0
                    ELSE 1
                END,
                c2.id DESC
            LIMIT 1
        )

    LEFT JOIN consignors co
        ON co.id = c.consignor_id
        AND co.store_id = p.store_id

    WHERE p.product_type = 'TITIPAN'
    AND p.store_id = $storeId
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
            p.name LIKE :search
            OR p.sku LIKE :search
            OR p.barcode LIKE :search
            OR co.name LIKE :search
        )
    ";

    $params[':search'] =
        '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| FILTER PENITIP
|--------------------------------------------------------------------------
*/

if ($consignorFilter > 0) {

    $sql .= "
        AND c.consignor_id = :consignor_id
    ";

    $params[':consignor_id'] =
        $consignorFilter;
}


/*
|--------------------------------------------------------------------------
| FILTER STATUS
|--------------------------------------------------------------------------
|
| AVAILABLE = aktif + stok > 0
| EMPTY     = aktif + stok <= 0
| INACTIVE  = data nonaktif
|
*/

if ($statusFilter === 'AVAILABLE') {

    $sql .= "
        AND p.status = 'ACTIVE'
        AND p.current_stock > 0
    ";

}

elseif ($statusFilter === 'EMPTY') {

    $sql .= "
        AND p.status = 'ACTIVE'
        AND p.current_stock <= 0
    ";

}

elseif ($statusFilter === 'INACTIVE') {

    $sql .= "
        AND p.status = 'INACTIVE'
    ";
}


$sql .= "
    ORDER BY
        p.status ASC,
        p.name ASC
";


$stmt =
    $pdo->prepare($sql);

$stmt->execute($params);

$products =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| HITUNG FEE
|--------------------------------------------------------------------------
*/

foreach ($products as &$product) {

    $selling =
        (float) $product['selling_price'];

    $feeValue =
        (float) $product['fee_value'];

    if (
        $product['fee_type']
        === 'PERCENTAGE'
    ) {

        $storeFee =
            $selling *
            ($feeValue / 100);

    } else {

        $storeFee =
            $feeValue;
    }

    $consignorAmount =
        max(
            0,
            $selling - $storeFee
        );


    $product['store_fee'] =
        $storeFee;

    $product['consignor_amount'] =
        $consignorAmount;
}

unset($product);


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$success =
    $_GET['success'] ?? '';

?>

<?php require_once '../../includes/header.php'; ?>

<?php require_once '../../includes/sidebar.php'; ?>


<main class="main-content">


    <!-- =========================================================
         MOBILE HEADER
    ========================================================== -->

<!-- =========================================================
         CONTENT
    ========================================================== -->

    <div class="
        p-5
        md:p-8
        lg:p-10
    ">


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
                ">
                    Barang Titipan
                </h1>

                <p class="
                    text-sm
                    text-neutral-500
                    mt-2
                ">
                    Kelola barang dan penitip.
                </p>

            </div>


            <div class="
                flex
                flex-col
                sm:flex-row
                gap-2
            ">

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
                        text-sm
                        font-medium
                        hover:bg-neutral-50
                    "
                >

                    <i
                        data-lucide="users"
                        class="w-4 h-4"
                    ></i>

                    Data Penitip

                </a>

<a
    href="/pages/titipan/pembayaran.php"
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
    <i data-lucide="wallet-cards" class="w-4 h-4"></i>
    Pembayaran
</a>


                <a
                    href="/pages/titipan/create.php"
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
                        data-lucide="plus"
                        class="w-4 h-4"
                    ></i>

                    Tambah Barang

                </a>

            </div>

        </div>


        <!-- FLASH -->

        <?php if ($success === 'created'): ?>

            <div class="
                mb-6
                p-4
                rounded-2xl
                bg-green-50
                border
                border-green-200
                text-sm
                text-green-700
            ">
                Barang titipan berhasil ditambahkan.
            </div>

        <?php elseif ($success === 'updated'): ?>

            <div class="
                mb-6
                p-4
                rounded-2xl
                bg-green-50
                border
                border-green-200
                text-sm
                text-green-700
            ">
                Barang titipan berhasil diperbarui.
            </div>

        <?php endif; ?>


        <!-- =====================================================
             FILTER
        ====================================================== -->

        <div class="
            bento-card
            p-4
            md:p-5
            mb-6
        ">

            <form
                method="GET"
                class="
                    grid
                    grid-cols-1
                    md:grid-cols-4
                    gap-3
                "
            >


                <!-- SEARCH -->

                <div class="md:col-span-2">

                    <input
                        type="text"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Cari barang, SKU, atau penitip..."
                        class="
                            w-full
                            px-4
                            py-3
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


                <!-- PENITIP -->

                <div>

                    <select
                        name="consignor_id"
                        class="
                            w-full
                            px-4
                            py-3
                            rounded-xl
                            border
                            border-neutral-200
                            bg-white
                            text-sm
                            outline-none
                        "
                    >

                        <option value="0">
                            Semua Penitip
                        </option>

                        <?php foreach ($consignors as $consignor): ?>

                            <option
                                value="<?= (int) $consignor['id'] ?>"
                                <?= $consignorFilter
                                    === (int) $consignor['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= e($consignor['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- STATUS -->

                <div>

                    <select
                        name="status"
                        class="
                            w-full
                            px-4
                            py-3
                            rounded-xl
                            border
                            border-neutral-200
                            bg-white
                            text-sm
                            outline-none
                        "
                    >

                        <option
                            value="ALL"
                            <?= $statusFilter === 'ALL'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Semua Status
                        </option>

                        <option
                            value="AVAILABLE"
                            <?= $statusFilter === 'AVAILABLE'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Tersedia
                        </option>

                        <option
                            value="EMPTY"
                            <?= $statusFilter === 'EMPTY'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Habis
                        </option>

                        <option
                            value="INACTIVE"
                            <?= $statusFilter === 'INACTIVE'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Tidak Aktif
                        </option>

                    </select>

                </div>


                <div class="
                    md:col-span-4
                    flex
                    flex-col
                    sm:flex-row
                    gap-2
                ">

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
                        "
                    >

                        <i
                            data-lucide="search"
                            class="w-4 h-4"
                        ></i>

                        Cari

                    </button>


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
                            text-neutral-600
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

        <div class="
            hidden
            md:block
            bento-card
            overflow-hidden
        ">

            <div class="overflow-x-auto">

                <table class="w-full">

                    <thead>

                        <tr class="
                            border-b
                            border-neutral-200
                            bg-neutral-50
                        ">

                            <th class="
                                px-6
                                py-4
                                text-left
                                text-xs
                                font-semibold
                                text-neutral-500
                                uppercase
                                tracking-wide
                            ">
                                Barang
                            </th>

                            <th class="
                                px-6
                                py-4
                                text-left
                                text-xs
                                font-semibold
                                text-neutral-500
                                uppercase
                                tracking-wide
                            ">
                                Penitip
                            </th>

                            <th class="
                                px-6
                                py-4
                                text-right
                                text-xs
                                font-semibold
                                text-neutral-500
                                uppercase
                                tracking-wide
                            ">
                                Harga Jual
                            </th>

                            <th class="
                                px-6
                                py-4
                                text-right
                                text-xs
                                font-semibold
                                text-neutral-500
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
                                font-semibold
                                text-neutral-500
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
                                font-semibold
                                text-neutral-500
                                uppercase
                                tracking-wide
                            ">
                                Aksi
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($products)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="
                                    px-6
                                    py-14
                                    text-center
                                    text-sm
                                    text-neutral-400
                                "
                            >

                                Tidak ada barang titipan.

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($products as $product): ?>


                            <?php

                            $isInactive =
                                $product['product_status']
                                === 'INACTIVE';

                            $isEmpty =
                                !$isInactive &&
                                (int) $product['current_stock']
                                <= 0;

                            ?>


                            <tr class="
                                border-b
                                border-neutral-100
                                last:border-0
                                hover:bg-neutral-50
                            ">


                                <!-- BARANG -->

                                <td class="px-6 py-4">

                                    <a
                                        href="/pages/titipan/view.php?id=<?= (int) $product['id'] ?>"
                                        class="
                                            block
                                            font-medium
                                            text-sm
                                            text-neutral-900
                                            hover:underline
                                            underline-offset-4
                                        "
                                    >
                                        <?= e($product['name']) ?>
                                    </a>


                                    <div class="
                                        text-xs
                                        text-neutral-400
                                        mt-1
                                    ">

                                        <?= e(
                                            $product['sku']
                                        ) ?>

                                    </div>

                                </td>


                                <!-- PENITIP -->

                                <td class="px-6 py-4">

                                    <div class="
                                        text-sm
                                        text-neutral-800
                                    ">
                                        <?= e(
                                            $product['consignor_name']
                                        ) ?>
                                    </div>

                                </td>


                                <!-- HARGA -->

                                <td class="
                                    px-6
                                    py-4
                                    text-right
                                    text-sm
                                    font-medium
                                ">

                                    <?= rupiah(
                                        $product['selling_price']
                                    ) ?>

                                </td>


                                <!-- STOK -->

                                <td class="
                                    px-6
                                    py-4
                                    text-right
                                ">

                                    <span class="
                                        text-sm
                                        font-medium
                                        <?= $isEmpty
                                            ? 'text-neutral-400'
                                            : 'text-neutral-900'
                                        ?>
                                    ">

                                        <?= number_format(
                                            (int) $product['current_stock'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                    </span>

                                    <span class="
                                        text-xs
                                        text-neutral-400
                                    ">
                                        <?= e(
                                            $product['unit']
                                        ) ?>
                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td class="
                                    px-6
                                    py-4
                                    text-center
                                ">


                                    <?php if ($isInactive): ?>

                                        <span class="
                                            inline-flex
                                            items-center
                                            px-3
                                            py-1.5
                                            rounded-full
                                            bg-neutral-100
                                            text-neutral-500
                                            text-xs
                                            font-medium
                                        ">
                                            Tidak Aktif
                                        </span>


                                    <?php elseif ($isEmpty): ?>

                                        <span class="
                                            inline-flex
                                            items-center
                                            px-3
                                            py-1.5
                                            rounded-full
                                            bg-neutral-100
                                            text-neutral-500
                                            text-xs
                                            font-medium
                                        ">
                                            Habis
                                        </span>


                                    <?php else: ?>

                                        <span class="
                                            inline-flex
                                            items-center
                                            px-3
                                            py-1.5
                                            rounded-full
                                            bg-green-50
                                            text-green-700
                                            text-xs
                                            font-medium
                                        ">
                                            Tersedia
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- AKSI -->

                                <td class="
                                    px-6
                                    py-4
                                    text-right
                                ">

                                    <div class="
                                        inline-flex
                                        items-center
                                        gap-1
                                    ">

                                        <a
                                            href="/pages/titipan/view.php?id=<?= (int) $product['id'] ?>"
                                            title="Detail"
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
                                            "
                                        >

                                            <i
                                                data-lucide="eye"
                                                class="w-4 h-4"
                                            ></i>

                                        </a>


                                        <a
                                            href="/pages/titipan/edit.php?id=<?= (int) $product['id'] ?>"
                                            title="Edit"
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
                                            "
                                        >

                                            <i
                                                data-lucide="pencil"
                                                class="w-4 h-4"
                                            ></i>

                                        </a>


                                        <?php if ($isInactive): ?>

                                            <form
                                                method="POST"
                                                action="/pages/titipan/status.php"
                                                onsubmit="return confirm('Aktifkan kembali barang ini?')"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($csrfToken) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $product['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    title="Aktifkan"
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
                                                    "
                                                >

                                                    <i
                                                        data-lucide="power"
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
             MOBILE CARDS
        ====================================================== -->

        <div class="
            md:hidden
            space-y-3
        ">

            <?php if (empty($products)): ?>

                <div class="
                    bento-card
                    p-8
                    text-center
                    text-sm
                    text-neutral-400
                ">
                    Tidak ada barang titipan.
                </div>

            <?php else: ?>


                <?php foreach ($products as $product): ?>


                    <?php

                    $isInactive =
                        $product['product_status']
                        === 'INACTIVE';

                    $isEmpty =
                        !$isInactive &&
                        (int) $product['current_stock']
                        <= 0;

                    ?>


                    <div class="
                        bento-card
                        p-4
                    ">


                        <div class="
                            flex
                            items-start
                            justify-between
                            gap-3
                        ">


                            <div class="min-w-0">

                                <a
                                    href="/pages/titipan/view.php?id=<?= (int) $product['id'] ?>"
                                    class="
                                        font-medium
                                        text-sm
                                        text-neutral-900
                                    "
                                >
                                    <?= e(
                                        $product['name']
                                    ) ?>
                                </a>


                                <div class="
                                    text-xs
                                    text-neutral-400
                                    mt-1
                                ">
                                    <?= e(
                                        $product['sku']
                                    ) ?>
                                </div>

                            </div>


                            <?php if ($isInactive): ?>

                                <span class="
                                    shrink-0
                                    px-2.5
                                    py-1
                                    rounded-full
                                    bg-neutral-100
                                    text-neutral-500
                                    text-xs
                                ">
                                    Tidak Aktif
                                </span>

                            <?php elseif ($isEmpty): ?>

                                <span class="
                                    shrink-0
                                    px-2.5
                                    py-1
                                    rounded-full
                                    bg-neutral-100
                                    text-neutral-500
                                    text-xs
                                ">
                                    Habis
                                </span>

                            <?php else: ?>

                                <span class="
                                    shrink-0
                                    px-2.5
                                    py-1
                                    rounded-full
                                    bg-green-50
                                    text-green-700
                                    text-xs
                                ">
                                    Tersedia
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="
                            grid
                            grid-cols-2
                            gap-3
                            mt-5
                        ">

                            <div>

                                <div class="
                                    text-xs
                                    text-neutral-400
                                ">
                                    Penitip
                                </div>

                                <div class="
                                    text-sm
                                    font-medium
                                    mt-1
                                ">
                                    <?= e(
                                        $product['consignor_name']
                                    ) ?>
                                </div>

                            </div>


                            <div class="text-right">

                                <div class="
                                    text-xs
                                    text-neutral-400
                                ">
                                    Harga Jual
                                </div>

                                <div class="
                                    text-sm
                                    font-medium
                                    mt-1
                                ">
                                    <?= rupiah(
                                        $product['selling_price']
                                    ) ?>
                                </div>

                            </div>


                            <div>

                                <div class="
                                    text-xs
                                    text-neutral-400
                                ">
                                    Stok
                                </div>

                                <div class="
                                    text-sm
                                    font-medium
                                    mt-1
                                ">
                                    <?= number_format(
                                        (int) $product['current_stock'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                    <?= e(
                                        $product['unit']
                                    ) ?>
                                </div>

                            </div>


                            <div class="text-right">

                                <div class="
                                    text-xs
                                    text-neutral-400
                                ">
                                    Hak Penitip
                                </div>

                                <div class="
                                    text-sm
                                    font-medium
                                    mt-1
                                ">
                                    <?= rupiah(
                                        $product['consignor_amount']
                                    ) ?>
                                </div>

                            </div>

                        </div>


                        <div class="
                            flex
                            gap-2
                            mt-5
                            pt-4
                            border-t
                            border-neutral-100
                        ">

                            <a
                                href="/pages/titipan/view.php?id=<?= (int) $product['id'] ?>"
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
                                "
                            >

                                <i
                                    data-lucide="eye"
                                    class="w-4 h-4"
                                ></i>

                                Detail

                            </a>


                            <a
                                href="/pages/titipan/edit.php?id=<?= (int) $product['id'] ?>"
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
                                "
                            >

                                <i
                                    data-lucide="pencil"
                                    class="w-4 h-4"
                                ></i>

                                Edit

                            </a>


                            <?php if ($isInactive): ?>

                                <form
                                    method="POST"
                                    action="/pages/titipan/status.php"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $product['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        title="Aktifkan"
                                        class="
                                            w-11
                                            h-11
                                            rounded-xl
                                            border
                                            border-neutral-200
                                            flex
                                            items-center
                                            justify-center
                                        "
                                    >

                                        <i
                                            data-lucide="power"
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
