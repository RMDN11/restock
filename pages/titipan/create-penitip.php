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

$pageTitle = 'Tambah Penitip';

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
| DEFAULT
|--------------------------------------------------------------------------
*/

$old = [
    'name'    => '',
    'phone'   => '',
    'address' => '',
    'notes'   => '',
];

$error = '';

/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($csrfToken, $_POST['csrf_token'])
    ) {
        $error = 'Sesi formulir sudah tidak berlaku. Silakan coba lagi.';
    }

    $old['name']    = trim($_POST['name'] ?? '');
    $old['phone']   = trim($_POST['phone'] ?? '');
    $old['address'] = trim($_POST['address'] ?? '');
    $old['notes']   = trim($_POST['notes'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | VALIDASI
    |--------------------------------------------------------------------------
    */

    if ($error === '' && $old['name'] === '') {
        $error = 'Nama penitip wajib diisi.';
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            /*
            |--------------------------------------------------------------------------
            | CEK NAMA DUPLIKAT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM consignors
                WHERE name = :name
                AND store_id = $storeId
                LIMIT 1
            ");

            $stmt->execute([
                ':name' => $old['name']
            ]);

            $existing = $stmt->fetch();

            if ($existing) {

                $error = 'Penitip dengan nama tersebut sudah terdaftar.';

            } else {

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | INSERT PENITIP
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO consignors (
                        store_id,
                        name,
                        phone,
                        address,
                        notes,
                        status
                    ) VALUES (
                        $storeId,
                        :name,
                        :phone,
                        :address,
                        :notes,
                        'ACTIVE'
                    )
                ");

                $stmt->execute([
                    ':name'    => $old['name'],
                    ':phone'   => $old['phone'] !== '' ? $old['phone'] : null,
                    ':address' => $old['address'] !== '' ? $old['address'] : null,
                    ':notes'   => $old['notes'] !== '' ? $old['notes'] : null,
                ]);

                $consignorId = (int) $pdo->lastInsertId();

                /*
                |--------------------------------------------------------------------------
                | AUDIT LOG
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
                        $storeId,
                        :user_id,
                        'CREATE',
                        'consignors',
                        :record_id,
                        :description
                    )
                ");

                $stmt->execute([
                    ':user_id' => $_SESSION['user_id'] ?? null,
                    ':record_id' => $consignorId,
                    ':description' => 'Menambahkan penitip: ' . $old['name'],
                ]);

                $pdo->commit();

                header(
                    'Location: /pages/titipan/penitip.php?success=created'
                );

                exit;
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Penitip gagal disimpan: ' . $e->getMessage();
        }
    }
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

    <div class="p-5 md:p-8 lg:p-10 max-w-4xl">

        <!-- =========================================================
             HEADER
        ========================================================== -->

        <div class="mb-8">

            <a
                href="/pages/titipan/penitip.php"
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

                Kembali ke Data Penitip

            </a>


            <h1 class="
                text-2xl
                md:text-3xl
                font-semibold
                tracking-tight
                text-neutral-900
            ">
                Tambah Penitip
            </h1>


            <p class="
                text-sm
                text-neutral-500
                mt-2
            ">
                Tambahkan data orang yang menitipkan barang di toko.
            </p>

        </div>


        <!-- =========================================================
             ERROR
        ========================================================== -->

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
                        class="
                            w-5
                            h-5
                            shrink-0
                        "
                    ></i>

                    <div>
                        <?= e($error) ?>
                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- =========================================================
             FORM
        ========================================================== -->

        <form
            method="POST"
            class="space-y-6"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >


            <!-- =====================================================
                 DATA UTAMA
            ====================================================== -->

            <div class="bento-card p-5 md:p-7">

                <div class="mb-6">

                    <h2 class="
                        text-lg
                        font-semibold
                        text-neutral-900
                    ">
                        Data Penitip
                    </h2>

                    <p class="
                        text-sm
                        text-neutral-500
                        mt-1
                    ">
                        Informasi dasar penitip.
                    </p>

                </div>


                <div class="space-y-5">


                    <!-- NAMA -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Nama Penitip
                            <span class="text-red-500">*</span>
                        </label>


                        <input
                            type="text"
                            name="name"
                            value="<?= e($old['name']) ?>"
                            placeholder="Contoh: Ahmad Fauzan"
                            required
                            autofocus
                            class="
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                text-neutral-900
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                    </div>


                    <!-- PHONE -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Nomor WhatsApp
                        </label>


                        <input
                            type="text"
                            name="phone"
                            value="<?= e($old['phone']) ?>"
                            placeholder="Contoh: 081234567890"
                            inputmode="tel"
                            class="
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                text-neutral-900
                                outline-none
                                focus:border-neutral-400
                                transition
                            "
                        >

                    </div>


                    <!-- ALAMAT -->

                    <div>

                        <label class="
                            block
                            text-sm
                            font-medium
                            text-neutral-800
                            mb-2
                        ">
                            Alamat
                        </label>


                        <textarea
                            name="address"
                            rows="3"
                            placeholder="Alamat penitip..."
                            class="
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                text-neutral-900
                                outline-none
                                resize-none
                                focus:border-neutral-400
                                transition
                            "
                        ><?= e($old['address']) ?></textarea>

                    </div>


                    <!-- CATATAN -->

                    <div>

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
                            rows="3"
                            placeholder="Catatan tambahan jika ada..."
                            class="
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                bg-white
                                text-sm
                                text-neutral-900
                                outline-none
                                resize-none
                                focus:border-neutral-400
                                transition
                            "
                        ><?= e($old['notes']) ?></textarea>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 BUTTON
            ====================================================== -->

            <div class="
                flex
                flex-col-reverse
                sm:flex-row
                sm:justify-end
                gap-2
            ">

                <a
                    href="/pages/titipan/penitip.php"
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
                        transition
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
                        transition
                    "
                >

                    <i
                        data-lucide="save"
                        class="w-4 h-4"
                    ></i>

                    Simpan Penitip

                </button>

            </div>

        </form>

    </div>

</main>


<?php require_once '../../includes/footer.php'; ?>
