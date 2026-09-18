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

$pageTitle = 'Edit Pengeluaran';


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

function parseMoney($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return 0;
    }

    $value = str_replace(
        ['Rp', 'rp', ' '],
        '',
        $value
    );

    $value = preg_replace(
        '/[^0-9,.\-]/',
        '',
        $value
    );

    if (strpos($value, ',') !== false) {

        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

    } else {

        $value = str_replace('.', '', $value);
    }

    return (float) $value;
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
| LOAD KATEGORI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name FROM expense_categories
    WHERE store_id = :categories_store_id
    ORDER BY name ASC
");
$stmt->execute([':categories_store_id' => $storeId]);
$categories = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| LOAD PENGELUARAN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        expense_date,
        category_id,
        description,
        amount,
        payment_method,
        notes
    FROM expenses
    WHERE id = :expense_id
      AND store_id = :expense_store_id
    LIMIT 1
");

$stmt->execute([
    ':expense_id' => $id,
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
| FORM
|--------------------------------------------------------------------------
*/

$form = [

    'expense_date' =>
        $expense['expense_date'],

    'category_id' =>
        $expense['category_id'],

    'description' =>
        $expense['description'],

    'amount' =>
        $expense['amount'],

    'payment_method' =>
        $expense['payment_method'],

    'notes' =>
        $expense['notes'] ?? '',
];

$error = '';


/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $csrfToken,
            $_POST['csrf_token']
        )
    ) {

        $error =
            'Sesi formulir sudah tidak berlaku. Silakan coba lagi.';
    }


    /*
    |--------------------------------------------------------------------------
    | INPUT
    |--------------------------------------------------------------------------
    */

    $form['expense_date'] =
        trim(
            $_POST['expense_date']
            ?? ''
        );

    $form['category_id'] =
        trim(
            $_POST['category_id']
            ?? ''
        );

    $form['description'] =
        trim(
            $_POST['description']
            ?? ''
        );

    $form['amount'] =
        trim(
            $_POST['amount']
            ?? ''
        );

    $form['payment_method'] =
        $_POST['payment_method']
        ?? 'CASH';

    $form['notes'] =
        trim(
            $_POST['notes']
            ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | NORMALIZE
    |--------------------------------------------------------------------------
    */

    $amount =
        parseMoney(
            $form['amount']
        );

    $categoryId =
        $form['category_id'] !== ''
            ? (int) $form['category_id']
            : null;


    /*
    |--------------------------------------------------------------------------
    | VALIDASI
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $form['description'] === ''
    ) {

        $error =
            'Keterangan pengeluaran wajib diisi.';
    }


    if (
        $error === '' &&
        $amount <= 0
    ) {

        $error =
            'Jumlah pengeluaran harus lebih dari Rp0.';
    }


    $allowedPaymentMethods = [
        'CASH',
        'QRIS',
        'TRANSFER',
        'OTHER',
    ];

    if (
        $error === '' &&
        !in_array(
            $form['payment_method'],
            $allowedPaymentMethods,
            true
        )
    ) {

        $error =
            'Metode pembayaran tidak valid.';
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE DATETIME
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $dateObj =
            DateTime::createFromFormat(
                'Y-m-d\TH:i',
                $form['expense_date']
            );

        /*
        | Jika browser mengirim format MySQL datetime
        */

        if (
            !$dateObj ||
            $dateObj->format('Y-m-d\TH:i')
                !== $form['expense_date']
        ) {

            $dateObj =
                DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    $form['expense_date']
                );
        }

        if (!$dateObj) {

            $error =
                'Tanggal dan waktu pengeluaran tidak valid.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE CATEGORY
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $categoryId !== null
    ) {

        $stmt = $pdo->prepare("
            SELECT
                id
            FROM expense_categories
            WHERE id = :category_id
              AND store_id = :category_store_id
            LIMIT 1
        ");

        $stmt->execute([
            ':category_id' => $categoryId,
            ':category_store_id' => $storeId
        ]);

        if (!$stmt->fetch()) {

            $error =
                'Kategori pengeluaran tidak ditemukan.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | NORMALIZE DATETIME
            |--------------------------------------------------------------------------
            */

            $saveDate =
                $dateObj->format(
                    'Y-m-d H:i:s'
                );


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE expenses
                SET
                    expense_date = :expense_date,
                    category_id = :category_id,
                    description = :description,
                    amount = :amount,
                    payment_method = :payment_method,
                    notes = :notes
                WHERE id = :expense_id
                  AND store_id = :expense_store_id
            ");

            $stmt->execute([

                ':expense_date' =>
                    $saveDate,

                ':category_id' =>
                    $categoryId,

                ':description' =>
                    $form['description'],

                ':amount' =>
                    $amount,

                ':payment_method' =>
                    $form['payment_method'],

                ':notes' =>
                    $form['notes'] !== ''
                        ? $form['notes']
                        : null,

                ':expense_id' => $id,
                ':expense_store_id' => $storeId,
            ]);


            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (
                    store_id,
                    user_id,
                    action,
                    table_name,
                    record_id,
                    description
                ) VALUES (
                    :store_id,
                    :user_id,
                    'UPDATE',
                    'expenses',
                    :record_id,
                    :description
                )
            ");

            $stmt->execute([

                ':store_id' => $storeId,
                ':user_id' =>
                    $_SESSION['user_id'] ?? null,

                ':record_id' =>
                    $id,

                ':description' =>
                    'Mengubah pengeluaran: ' .
                    $form['description'],
            ]);


            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | REDIRECT
            |--------------------------------------------------------------------------
            */

            header(
                'Location: /pages/pengeluaran/view.php?id=' .
                $id .
                '&success=updated'
            );

            exit;


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error =
                'Pengeluaran gagal diperbarui: ' .
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| FORMAT DATETIME LOCAL
|--------------------------------------------------------------------------
*/

$datetimeLocal = '';

if (!empty($form['expense_date'])) {

    $dt =
        DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $form['expense_date']
        );

    if ($dt) {

        $datetimeLocal =
            $dt->format(
                'Y-m-d\TH:i'
            );

    } else {

        $datetimeLocal =
            $form['expense_date'];
    }
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
        max-w-4xl
    ">


        <!-- HEADER -->

        <div class="mb-8">

            <a
                href="/pages/pengeluaran/view.php?id=<?= $id ?>"
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

                Kembali ke Detail

            </a>


            <h1 class="
                text-2xl
                md:text-3xl
                font-semibold
                tracking-tight
                text-neutral-900
            ">
                Edit Pengeluaran
            </h1>


            <p class="
                text-sm
                text-neutral-500
                mt-2
            ">
                Perbarui data pengeluaran yang sudah dicatat.
            </p>

        </div>


        <!-- ERROR -->

        <?php if ($error !== ''): ?>

            <div class="
                mb-6
                rounded-2xl
                border
                border-red-200
                bg-red-50
                p-4
                text-sm
                text-red-700
            ">

                <div class="
                    flex
                    items-start
                    gap-3
                ">

                    <i
                        data-lucide="circle-alert"
                        class="w-5 h-5 shrink-0"
                    ></i>

                    <div>
                        <?= e($error) ?>
                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- FORM -->

        <form
            method="POST"
            id="expenseEditForm"
            class="space-y-6"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >


            <!-- DATA -->

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
                        Data Pengeluaran
                    </h2>

                    <p class="
                        text-sm
                        text-neutral-500
                        mt-1
                    ">
                        Ubah informasi transaksi di bawah ini.
                    </p>

                </div>


                <div class="
                    grid
                    grid-cols-1
                    md:grid-cols-2
                    gap-5
                ">


                    <!-- TANGGAL -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Tanggal & Waktu
                            <span class="text-red-500">*</span>
                        </label>


                        <input
                            type="datetime-local"
                            name="expense_date"
                            value="<?= e(
                                $datetimeLocal
                            ) ?>"
                            required
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


                    <!-- KATEGORI -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Kategori
                        </label>


                        <select
                            name="category_id"
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

                            <option value="">
                                Tanpa Kategori
                            </option>


                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int) $category['id'] ?>"
                                    <?= (string)
                                        $form['category_id']
                                        === (string)
                                        $category['id']
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= e(
                                        $category['name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- KETERANGAN -->

                    <div class="md:col-span-2">

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Keterangan
                            <span class="text-red-500">*</span>
                        </label>


                        <input
                            type="text"
                            name="description"
                            value="<?= e(
                                $form['description']
                            ) ?>"
                            required
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


                    <!-- NOMINAL -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Jumlah Pengeluaran
                            <span class="text-red-500">*</span>
                        </label>


                        <input
                            type="text"
                            name="amount"
                            id="amount"
                            value="<?= e(
                                'Rp' .
                                number_format(
                                    (float) $form['amount'],
                                    0,
                                    ',',
                                    '.'
                                )
                            ) ?>"
                            inputmode="numeric"
                            required
                            class="
                                money-input
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


                    <!-- PEMBAYARAN -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Metode Pembayaran
                        </label>


                        <select
                            name="payment_method"
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

                            <option
                                value="CASH"
                                <?= $form['payment_method']
                                    === 'CASH'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Tunai
                            </option>

                            <option
                                value="QRIS"
                                <?= $form['payment_method']
                                    === 'QRIS'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                QRIS
                            </option>

                            <option
                                value="TRANSFER"
                                <?= $form['payment_method']
                                    === 'TRANSFER'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Transfer
                            </option>

                            <option
                                value="OTHER"
                                <?= $form['payment_method']
                                    === 'OTHER'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Lainnya
                            </option>

                        </select>

                    </div>


                    <!-- CATATAN -->

                    <div class="md:col-span-2">

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Catatan
                        </label>


                        <textarea
                            name="notes"
                            rows="4"
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
                                resize-none
                                focus:border-neutral-400
                            "
                        ><?= e(
                            $form['notes']
                        ) ?></textarea>

                    </div>

                </div>

            </div>


            <!-- ACTION -->

            <div class="
                flex
                flex-col-reverse
                sm:flex-row
                sm:justify-end
                gap-2
            ">

                <a
                    href="/pages/pengeluaran/view.php?id=<?= $id ?>"
                    class="
                        inline-flex
                        items-center
                        justify-center
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
                    Batal
                </a>


                <button
                    type="submit"
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
                        data-lucide="save"
                        class="w-4 h-4"
                    ></i>

                    Simpan Perubahan

                </button>

            </div>

        </form>

    </div>

</main>


<script>

/*
|--------------------------------------------------------------------------
| FORMAT RUPIAH
|--------------------------------------------------------------------------
*/

function formatRupiah(value)
{
    value = String(value)
        .replace(/\D/g, '');

    if (!value) {
        return '';
    }

    return 'Rp' +
        Number(value)
            .toLocaleString('id-ID');
}


function rawNumber(value)
{
    return String(value)
        .replace(/\D/g, '');
}


const amountInput =
    document.getElementById('amount');


if (amountInput) {

    amountInput.addEventListener(
        'input',
        function() {

            const raw =
                rawNumber(
                    this.value
                );

            this.value =
                raw
                    ? formatRupiah(raw)
                    : '';

        }
    );

}


const expenseEditForm =
    document.getElementById(
        'expenseEditForm'
    );


if (expenseEditForm) {

    expenseEditForm.addEventListener(
        'submit',
        function() {

            if (amountInput) {

                amountInput.value =
                    rawNumber(
                        amountInput.value
                    );
            }

        }
    );

}


if (
    typeof lucide !== 'undefined'
) {
    lucide.createIcons();
}

</script>


<?php require_once '../../includes/footer.php'; ?>