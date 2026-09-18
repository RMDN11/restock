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

$pageTitle = 'Detail Pengeluaran';


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
| ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {

    header(
        'Location: /pages/pengeluaran/'
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

    WHERE e.id = :id
      AND e.store_id = :expense_store_id

    LIMIT 1
");

$stmt->execute([
    ':id' => $id,
    ':expense_store_id' => $storeId
]);

$expense =
    $stmt->fetch();


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
    $expense['payment_method'];


/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

$expenseDate =
    !empty($expense['expense_date'])
        ? date(
            'd M Y',
            strtotime(
                $expense['expense_date']
            )
        )
        : '-';


/*
|--------------------------------------------------------------------------
| CREATED AT
|--------------------------------------------------------------------------
*/

$createdAt = '-';

if (!empty($expense['created_at'])) {

    $createdAt =
        date(
            'd M Y, H:i',
            strtotime(
                $expense['created_at']
            )
        );
}

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
        max-w-5xl
    ">


        <!-- =====================================================
             TOP
        ====================================================== -->

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

                <a
                    href="/pages/pengeluaran/"
                    class="
                        inline-flex
                        items-center
                        gap-2
                        text-sm
                        text-neutral-500
                        hover:text-neutral-900
                        mb-5
                    "
                >

                    <i
                        data-lucide="arrow-left"
                        class="w-4 h-4"
                    ></i>

                    Kembali ke Pengeluaran

                </a>


                <h1 class="
                    text-2xl
                    md:text-3xl
                    font-semibold
                    tracking-tight
                    text-neutral-900
                ">
                    Detail Pengeluaran
                </h1>


                <p class="
                    text-sm
                    text-neutral-500
                    mt-2
                ">
                    Informasi lengkap transaksi pengeluaran.
                </p>

            </div>


            <!-- ACTION -->

            <div class="
                flex
                flex-col
                sm:flex-row
                gap-2
            ">


                <!-- EDIT -->

                <a
                    href="/pages/pengeluaran/edit.php?id=<?= (int) $expense['id'] ?>"
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
                        data-lucide="pencil"
                        class="w-4 h-4"
                    ></i>

                    Edit

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
        value="<?= e($_SESSION['csrf_token']) ?>"
    >

    <input
        type="hidden"
        name="id"
        value="<?= (int) $expense['id'] ?>"
    >

    <button
        type="submit"
        class="
            w-full
            inline-flex
            items-center
            justify-center
            gap-2
            px-4
            py-2.5
            rounded-xl
            border
            border-red-200
            bg-white
            text-red-600
            text-sm
            font-medium
            hover:bg-red-50
            transition
        "
    >

        <i
            data-lucide="trash-2"
            class="w-4 h-4"
        ></i>

        Hapus

    </button>

</form>

            </div>

        </div>


        <!-- =====================================================
             MAIN SUMMARY
        ====================================================== -->

        <div class="
            grid
            grid-cols-1
            lg:grid-cols-3
            gap-5
            mb-5
        ">


            <!-- TOTAL -->

            <div class="
                lg:col-span-2
                bento-card
                p-6
                md:p-8
            ">

                <div class="
                    flex
                    items-start
                    justify-between
                    gap-5
                ">


                    <div>

                        <div class="
                            text-sm
                            text-neutral-500
                        ">
                            Jumlah Pengeluaran
                        </div>


                        <div class="
                            text-3xl
                            md:text-4xl
                            font-semibold
                            tracking-tight
                            text-neutral-900
                            mt-2
                        ">

                            <?= rupiah(
                                $expense['amount']
                            ) ?>

                        </div>

                    </div>


                    <div class="
                        w-12
                        h-12
                        rounded-2xl
                        bg-neutral-100
                        flex
                        items-center
                        justify-center
                        shrink-0
                    ">

                        <i
                            data-lucide="wallet"
                            class="
                                w-6
                                h-6
                                text-neutral-600
                            "
                        ></i>

                    </div>

                </div>


                <div class="
                    mt-8
                    pt-5
                    border-t
                    border-neutral-100
                ">

                    <div class="
                        text-xs
                        text-neutral-400
                        uppercase
                        tracking-wide
                    ">
                        Keterangan
                    </div>


                    <div class="
                        text-base
                        md:text-lg
                        font-medium
                        text-neutral-900
                        mt-2
                    ">

                        <?= e(
                            $expense['description']
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- PAYMENT -->

            <div class="
                bento-card
                p-6
                md:p-8
            ">

                <div class="
                    text-sm
                    text-neutral-500
                ">
                    Metode Pembayaran
                </div>


                <div class="
                    flex
                    items-center
                    gap-3
                    mt-4
                ">

                    <div class="
                        w-11
                        h-11
                        rounded-xl
                        bg-neutral-100
                        flex
                        items-center
                        justify-center
                    ">

                        <?php

                        $paymentIcon = [
                            'CASH' =>
                                'banknote',

                            'QRIS' =>
                                'qr-code',

                            'TRANSFER' =>
                                'arrow-right-left',

                            'OTHER' =>
                                'credit-card',
                        ];

                        ?>

                        <i
                            data-lucide="<?= e(
                                $paymentIcon[
                                    $expense['payment_method']
                                ]
                                ??
                                'credit-card'
                            ) ?>"
                            class="w-5 h-5 text-neutral-600"
                        ></i>

                    </div>


                    <div>

                        <div class="
                            text-base
                            font-semibold
                            text-neutral-900
                        ">
                            <?= e(
                                $paymentLabel
                            ) ?>
                        </div>

                        <div class="
                            text-xs
                            text-neutral-400
                            mt-1
                        ">
                            Metode pembayaran
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             DETAIL
        ====================================================== -->

        <div class="
            bento-card
            p-5
            md:p-7
        ">


            <div class="mb-6">

                <h2 class="
                    text-lg
                    font-semibold
                    text-neutral-900
                ">
                    Informasi Pengeluaran
                </h2>

                <p class="
                    text-sm
                    text-neutral-500
                    mt-1
                ">
                    Detail transaksi yang tercatat.
                </p>

            </div>


            <div class="
                grid
                grid-cols-1
                md:grid-cols-2
                gap-x-8
                gap-y-6
            ">


                <!-- TANGGAL -->

                <div>

                    <div class="
                        text-xs
                        text-neutral-400
                        uppercase
                        tracking-wide
                    ">
                        Tanggal
                    </div>

                    <div class="
                        text-sm
                        font-medium
                        text-neutral-900
                        mt-1.5
                    ">
                        <?= e(
                            $expenseDate
                        ) ?>
                    </div>

                </div>


                <!-- KATEGORI -->

                <div>

                    <div class="
                        text-xs
                        text-neutral-400
                        uppercase
                        tracking-wide
                    ">
                        Kategori
                    </div>

                    <div class="mt-1.5">

                        <?php if (!empty($expense['category_name'])): ?>

                            <span class="
                                inline-flex
                                items-center
                                px-3
                                py-1.5
                                rounded-full
                                bg-neutral-100
                                text-neutral-700
                                text-xs
                                font-medium
                            ">
                                <?= e(
                                    $expense['category_name']
                                ) ?>
                            </span>

                        <?php else: ?>

                            <span class="
                                text-sm
                                text-neutral-400
                            ">
                                Tanpa kategori
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- METODE -->

                <div>

                    <div class="
                        text-xs
                        text-neutral-400
                        uppercase
                        tracking-wide
                    ">
                        Pembayaran
                    </div>

                    <div class="
                        text-sm
                        font-medium
                        text-neutral-900
                        mt-1.5
                    ">
                        <?= e(
                            $paymentLabel
                        ) ?>
                    </div>

                </div>


                <!-- DIBUAT -->

                <div>

                    <div class="
                        text-xs
                        text-neutral-400
                        uppercase
                        tracking-wide
                    ">
                        Dicatat Pada
                    </div>

                    <div class="
                        text-sm
                        font-medium
                        text-neutral-900
                        mt-1.5
                    ">
                        <?= e(
                            $createdAt
                        ) ?>
                    </div>

                </div>


                <!-- USER -->

                <div>

                    <div class="
                        text-xs
                        text-neutral-400
                        uppercase
                        tracking-wide
                    ">
                        Dicatat Oleh
                    </div>

                    <div class="
                        text-sm
                        font-medium
                        text-neutral-900
                        mt-1.5
                    ">

                        <?= !empty(
                            $expense['created_by_name']
                        )
                            ? e(
                                $expense['created_by_name']
                            )
                            : 'Tidak diketahui'
                        ?>

                    </div>

                </div>


                <!-- ID -->

                <div>

                    <div class="
                        text-xs
                        text-neutral-400
                        uppercase
                        tracking-wide
                    ">
                        ID Transaksi
                    </div>

                    <div class="
                        text-sm
                        font-mono
                        text-neutral-600
                        mt-1.5
                    ">
                        #<?= (int) $expense['id'] ?>
                    </div>

                </div>

            </div>


            <!-- =================================================
                 CATATAN
            ================================================== -->

            <?php if (!empty($expense['notes'])): ?>

                <div class="
                    mt-8
                    pt-6
                    border-t
                    border-neutral-100
                ">

                    <div class="
                        text-xs
                        text-neutral-400
                        uppercase
                        tracking-wide
                    ">
                        Catatan
                    </div>


                    <div class="
                        mt-3
                        p-4
                        rounded-2xl
                        bg-neutral-50
                        border
                        border-neutral-100
                        text-sm
                        leading-6
                        text-neutral-700
                        whitespace-pre-line
                    ">

                        <?= e(
                            $expense['notes']
                        ) ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>


        <!-- =====================================================
             BOTTOM ACTION
        ====================================================== -->

        <div class="
            mt-6
            flex
            flex-col
            sm:flex-row
            sm:justify-end
            gap-2
        ">


            <a
                href="/pages/pengeluaran/"
                class="
                    inline-flex
                    items-center
                    justify-center
                    gap-2
                    px-5
                    py-3
                    rounded-xl
                    border
                    border-neutral-200
                    bg-white
                    text-sm
                    font-medium
                    text-neutral-700
                    hover:bg-neutral-50
                "
            >

                <i
                    data-lucide="arrow-left"
                    class="w-4 h-4"
                ></i>

                Kembali

            </a>


            <a
                href="/pages/pengeluaran/edit.php?id=<?= (int) $expense['id'] ?>"
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
                    hover:bg-neutral-800
                "
            >

                <i
                    data-lucide="pencil"
                    class="w-4 h-4"
                ></i>

                Edit Pengeluaran

            </a>

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