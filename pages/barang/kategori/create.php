<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../config/database.php';

$pageTitle = 'Tambah Kategori';


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


$name = '';
$errors = [];


/*
|--------------------------------------------------------------------------
| POST
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
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {

        $errors[] =
            'Sesi formulir tidak valid. Silakan coba lagi.';
    }


    /*
    |--------------------------------------------------------------------------
    | NAME
    |--------------------------------------------------------------------------
    */

    $name = trim(
        $_POST['name'] ?? ''
    );


    if ($name === '') {

        $errors[] =
            'Nama kategori wajib diisi.';

    } elseif (mb_strlen($name) > 100) {

        $errors[] =
            'Nama kategori maksimal 100 karakter.';
    }


    /*
    |--------------------------------------------------------------------------
    | CEK DUPLIKAT
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM product_categories
            WHERE name = :name
              AND store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute([
            ':name' => $name,
            ':store_id' => $authStoreId
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                'Kategori tersebut sudah ada.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            $stmt = $pdo->prepare("
                INSERT INTO product_categories (
                    store_id,
                    name,
                    status
                )
                VALUES (
                    :store_id,
                    :name,
                    'ACTIVE'
                )
            ");

            $stmt->execute([
                ':store_id' => $authStoreId,
                ':name' => $name
            ]);


            $categoryId =
                $pdo->lastInsertId();


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
                )
                VALUES (
                    :store_id,
                    :user_id,
                    'CREATE',
                    'product_categories',
                    :record_id,
                    :description
                )
            ");

            $stmt->execute([
                ':store_id' => $authStoreId,

                ':user_id' =>
                    $_SESSION['user_id'] ?? null,

                ':record_id' =>
                    $categoryId,

                ':description' =>
                    'Menambahkan kategori: ' .
                    $name
            ]);


            $pdo->commit();


            $_SESSION['flash_success'] =
                'Kategori berhasil ditambahkan.';

            header(
                'Location: /pages/barang/kategori/'
            );

            exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'Kategori gagal ditambahkan. ' .
                $e->getMessage();
        }
    }
}


require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

?>

<div class="main-content">

    <main class="p-4 md:p-6 lg:p-8 max-w-3xl mx-auto">


        <!-- HEADER -->

        <div class="flex items-center gap-4 mb-8">

            <a
                href="/pages/barang/kategori/"
                class="
                    w-10 h-10
                    shrink-0
                    rounded-xl
                    bg-white
                    border border-neutral-200
                    flex items-center justify-center
                    hover:bg-neutral-50
                "
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
                    Tambah Kategori
                </h1>

                <p class="
                    text-sm
                    text-neutral-500
                    mt-1
                ">
                    Tambahkan kategori baru untuk barang.
                </p>

            </div>

        </div>


        <!-- ERROR -->

        <?php if (!empty($errors)): ?>

            <div class="
                mb-5
                rounded-2xl
                border border-red-200
                bg-red-50
                p-4
                text-sm
                text-red-700
            ">

                <div class="flex gap-3">

                    <i
                        data-lucide="circle-alert"
                        class="w-5 h-5 shrink-0"
                    ></i>

                    <div class="space-y-1">

                        <?php foreach ($errors as $error): ?>

                            <div>
                                • <?= e($error) ?>
                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- FORM -->

        <form
            method="POST"
            class="bento-card p-5 md:p-6"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >


            <label class="
                block
                text-sm
                font-medium
                mb-2
            ">
                Nama Kategori
                <span class="text-red-500">*</span>
            </label>


            <input
                type="text"
                name="name"
                value="<?= e($name) ?>"
                maxlength="100"
                required
                autofocus
                placeholder="Contoh: Makanan"
                class="
                    w-full
                    h-11
                    px-4
                    rounded-xl
                    border border-neutral-200
                    bg-white
                    outline-none
                    focus:border-neutral-400
                    transition
                "
            >


            <p class="
                text-xs
                text-neutral-400
                mt-2
            ">
                Gunakan nama yang singkat dan mudah dibedakan.
            </p>


            <div class="
                flex
                flex-col-reverse
                sm:flex-row
                sm:justify-end
                gap-3
                mt-8
            ">

                <a
                    href="/pages/barang/kategori/"
                    class="
                        h-11
                        px-5
                        rounded-xl
                        border border-neutral-200
                        bg-white
                        flex
                        items-center
                        justify-center
                        text-sm
                        font-medium
                        hover:bg-neutral-50
                    "
                >
                    Batal
                </a>


                <button
                    type="submit"
                    class="
                        h-11
                        px-5
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
                        data-lucide="save"
                        class="w-4 h-4"
                    ></i>

                    Simpan Kategori

                </button>

            </div>

        </form>

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