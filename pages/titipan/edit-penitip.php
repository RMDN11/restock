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

$pageTitle = 'Edit Penitip';

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
| ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /pages/titipan/penitip.php');
    exit;
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
| AMBIL DATA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
        address,
        notes,
        status
    FROM consignors
    WHERE id = :id
    AND store_id = $storeId
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$consignor = $stmt->fetch();

if (!$consignor) {
    header('Location: /pages/titipan/penitip.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| DEFAULT
|--------------------------------------------------------------------------
*/

$old = [
    'name'    => $consignor['name'],
    'phone'   => $consignor['phone'],
    'address' => $consignor['address'],
    'notes'   => $consignor['notes'],
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
        !hash_equals($csrfToken, $_POST['csrf_token'])
    ) {
        $error = 'Sesi formulir sudah tidak berlaku. Silakan coba lagi.';
    }

    /*
    |--------------------------------------------------------------------------
    | AMBIL INPUT
    |--------------------------------------------------------------------------
    */

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
                AND id != :id
                LIMIT 1
            ");

            $stmt->execute([
                ':name' => $old['name'],
                ':id'   => $id,
            ]);

            $existing = $stmt->fetch();

            if ($existing) {

                $error = 'Penitip dengan nama tersebut sudah terdaftar.';

            } else {

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | UPDATE
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE consignors
                    SET
                        name = :name,
                        phone = :phone,
                        address = :address,
                        notes = :notes
                    WHERE id = :id
                    AND store_id = $storeId
                ");

                $stmt->execute([
                    ':name'    => $old['name'],
                    ':phone'   => $old['phone'] !== '' ? $old['phone'] : null,
                    ':address' => $old['address'] !== '' ? $old['address'] : null,
                    ':notes'   => $old['notes'] !== '' ? $old['notes'] : null,
                    ':id'      => $id,
                ]);

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
                        'UPDATE',
                        'consignors',
                        :record_id,
                        :description
                    )
                ");

                $stmt->execute([
                    ':user_id' => $_SESSION['user_id'] ?? null,
                    ':record_id' => $id,
                    ':description' => 'Mengubah data penitip: ' . $old['name'],
                ]);

                $pdo->commit();

                header(
                    'Location: /pages/titipan/penitip.php?success=updated'
                );

                exit;
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Data penitip gagal diperbarui: ' . $e->getMessage();
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
                Edit Penitip
            </h1>


            <p class="
                text-sm
                text-neutral-500
                mt-2
            ">
                Perbarui informasi penitip.
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

                <div class="flex items-start gap-3">

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
                            placeholder="Nama penitip"
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
                            placeholder="Catatan tambahan..."
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
                            "
                        ><?= e($old['notes']) ?></textarea>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 INFO STATUS
            ====================================================== -->

            <div class="
                bento-card
                p-5
                md:p-6
            ">

                <div class="
                    flex
                    items-center
                    justify-between
                    gap-4
                ">

                    <div>

                        <p class="
                            text-sm
                            font-medium
                            text-neutral-800
                        ">
                            Status Penitip
                        </p>

                        <p class="
                            text-xs
                            text-neutral-400
                            mt-1
                        ">
                            Status diubah dari halaman Data Penitip.
                        </p>

                    </div>


                    <?php if ($consignor['status'] === 'ACTIVE'): ?>

                        <span class="
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
                        ">

                            <span class="
                                w-1.5
                                h-1.5
                                rounded-full
                                bg-green-500
                            "></span>

                            Aktif

                        </span>

                    <?php else: ?>

                        <span class="
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
                        ">

                            <span class="
                                w-1.5
                                h-1.5
                                rounded-full
                                bg-neutral-400
                            "></span>

                            Tidak Aktif

                        </span>

                    <?php endif; ?>

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


<?php require_once '../../includes/footer.php'; ?>
