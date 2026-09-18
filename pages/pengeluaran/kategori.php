<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

$storeId = (int) ($_SESSION['store_id'] ?? 0);
if ($storeId <= 0) {
    http_response_code(403);
    exit('Store aktif tidak ditemukan.');
}

$pageTitle = 'Kategori Pengeluaran';

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


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

$csrfToken = $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$success = $_SESSION['category_success'] ?? '';
$error   = $_SESSION['category_error'] ?? '';

unset(
    $_SESSION['category_success'],
    $_SESSION['category_error']
);


/*
|--------------------------------------------------------------------------
| ACTION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $csrfToken,
            $postedToken
        )
    ) {

        $_SESSION['category_error'] =
            'Permintaan tidak valid. Silakan coba lagi.';

        header(
            'Location: /pages/pengeluaran/kategori.php'
        );

        exit;
    }


    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | TAMBAH KATEGORI
    |--------------------------------------------------------------------------
    */

    if ($action === 'create') {

        $name = trim(
            $_POST['name'] ?? ''
        );


        if ($name === '') {

            $_SESSION['category_error'] =
                'Nama kategori wajib diisi.';

        } elseif (mb_strlen($name) > 100) {

            $_SESSION['category_error'] =
                'Nama kategori maksimal 100 karakter.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | CEK DUPLIKAT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM expense_categories
                WHERE LOWER(name) = LOWER(:name)
                  AND store_id = :duplicate_store_id
                LIMIT 1
            ");

            $stmt->execute([
                ':name' => $name,
                ':duplicate_store_id' => $storeId
            ]);

            $existing = $stmt->fetch();


            if ($existing) {

                $_SESSION['category_error'] =
                    'Kategori tersebut sudah ada.';

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO expense_categories
                        (store_id, name, status)
                    VALUES
                        (:store_id, :name, 'ACTIVE')
                ");

                $stmt->execute([
                    ':store_id' => $storeId,
                    ':name' => $name
                ]);

                $categoryId = (int) $pdo->lastInsertId();


                /*
                |--------------------------------------------------------------------------
                | AUDIT LOG
                |--------------------------------------------------------------------------
                */

                $userId = $_SESSION['user_id'] ?? null;

                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs
                        (
                            store_id,
                            user_id,
                            action,
                            table_name,
                            record_id,
                            description
                        )
                    VALUES
                        (
                            :store_id,
                            :user_id,
                            'CREATE',
                            'expense_categories',
                            :record_id,
                            :description
                        )
                ");

                $stmt->execute([
                    ':store_id' => $storeId,
                    ':user_id' => $userId,
                    ':record_id' => $categoryId,
                    ':description' =>
                        'Menambahkan kategori pengeluaran: ' . $name
                ]);


                $_SESSION['category_success'] =
                    'Kategori berhasil ditambahkan.';
            }
        }


        header(
            'Location: /pages/pengeluaran/kategori.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT KATEGORI
    |--------------------------------------------------------------------------
    */

    if ($action === 'edit') {

        $id = (int) (
            $_POST['id'] ?? 0
        );

        $name = trim(
            $_POST['name'] ?? ''
        );


        if ($id <= 0) {

            $_SESSION['category_error'] =
                'Kategori tidak ditemukan.';

        } elseif ($name === '') {

            $_SESSION['category_error'] =
                'Nama kategori wajib diisi.';

        } elseif (mb_strlen($name) > 100) {

            $_SESSION['category_error'] =
                'Nama kategori maksimal 100 karakter.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | CEK KATEGORI
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id, name, status
                FROM expense_categories
                WHERE id = :category_id
                  AND store_id = :category_store_id
                LIMIT 1
            ");

            $stmt->execute([
                ':category_id' => $id,
                ':category_store_id' => $storeId
            ]);

            $category = $stmt->fetch();


            if (!$category) {

                $_SESSION['category_error'] =
                    'Kategori tidak ditemukan.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | CEK DUPLIKAT
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM expense_categories
                    WHERE LOWER(name) = LOWER(:name)
                    AND id <> :id_duplicate
                    AND store_id = :duplicate_store_id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':name' => $name,
                    ':id_duplicate' => $id,
                    ':duplicate_store_id' => $storeId
                ]);

                $duplicate = $stmt->fetch();


                if ($duplicate) {

                    $_SESSION['category_error'] =
                        'Nama kategori tersebut sudah digunakan.';

                } else {

                    $stmt = $pdo->prepare("
                        UPDATE expense_categories
                        SET name = :name
                        WHERE id = :category_id
                          AND store_id = :category_store_id
                    ");

                    $stmt->execute([
                        ':name' => $name,
                        ':category_id' => $id,
                        ':category_store_id' => $storeId
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | AUDIT LOG
                    |--------------------------------------------------------------------------
                    */

                    $userId = $_SESSION['user_id'] ?? null;

                    $stmt = $pdo->prepare("
                        INSERT INTO audit_logs
                            (
                                store_id,
                                user_id,
                                action,
                                table_name,
                                record_id,
                                description
                            )
                        VALUES
                            (
                                :store_id,
                                :user_id,
                                'UPDATE',
                                'expense_categories',
                                :record_id,
                                :description
                            )
                    ");

                    $stmt->execute([
                        ':store_id' => $storeId,
                        ':user_id' => $userId,
                        ':record_id' => $id,
                        ':description' =>
                            'Mengubah kategori pengeluaran menjadi: ' . $name
                    ]);


                    $_SESSION['category_success'] =
                        'Kategori berhasil diperbarui.';
                }
            }
        }


        header(
            'Location: /pages/pengeluaran/kategori.php'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */

    if ($action === 'toggle_status') {

        $id = (int) (
            $_POST['id'] ?? 0
        );


        if ($id <= 0) {

            $_SESSION['category_error'] =
                'Kategori tidak ditemukan.';

        } else {

            $stmt = $pdo->prepare("
                SELECT id, name, status
                FROM expense_categories
                WHERE id = :category_id
                  AND store_id = :category_store_id
                LIMIT 1
            ");

            $stmt->execute([
                ':category_id' => $id,
                ':category_store_id' => $storeId
            ]);

            $category = $stmt->fetch();


            if (!$category) {

                $_SESSION['category_error'] =
                    'Kategori tidak ditemukan.';

            } else {

                $newStatus =
                    $category['status'] === 'ACTIVE'
                        ? 'INACTIVE'
                        : 'ACTIVE';


                $stmt = $pdo->prepare("
                    UPDATE expense_categories
                    SET status = :status
                    WHERE id = :category_id
                      AND store_id = :category_store_id
                ");

                $stmt->execute([
                    ':status' => $newStatus,
                    ':category_id' => $id,
                    ':category_store_id' => $storeId
                ]);


                /*
                |--------------------------------------------------------------------------
                | AUDIT LOG
                |--------------------------------------------------------------------------
                */

                $userId = $_SESSION['user_id'] ?? null;

                $statusLabel =
                    $newStatus === 'ACTIVE'
                        ? 'mengaktifkan'
                        : 'menonaktifkan';

                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs
                        (
                            store_id,
                            user_id,
                            action,
                            table_name,
                            record_id,
                            description
                        )
                    VALUES
                        (
                            :store_id,
                            :user_id,
                            'STATUS',
                            'expense_categories',
                            :record_id,
                            :description
                        )
                ");

                $stmt->execute([
                    ':store_id' => $storeId,
                    ':user_id' => $userId,
                    ':record_id' => $id,
                    ':description' =>
                        ucfirst($statusLabel) .
                        ' kategori pengeluaran: ' .
                        $category['name']
                ]);


                $_SESSION['category_success'] =
                    $newStatus === 'ACTIVE'
                        ? 'Kategori berhasil diaktifkan.'
                        : 'Kategori berhasil dinonaktifkan.';
            }
        }


        header(
            'Location: /pages/pengeluaran/kategori.php'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$statusFilter =
    strtoupper(
        trim(
            $_GET['status'] ?? 'ALL'
        )
    );

$allowedStatus = [
    'ALL',
    'ACTIVE',
    'INACTIVE'
];

if (!in_array(
    $statusFilter,
    $allowedStatus,
    true
)) {
    $statusFilter = 'ALL';
}


/*
|--------------------------------------------------------------------------
| DATA KATEGORI
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        ec.id,
        ec.name,
        ec.status,
        ec.created_at,
        ec.updated_at,

        COUNT(e.id) AS total_pengeluaran

    FROM expense_categories ec

    LEFT JOIN expenses e
        ON e.category_id = ec.id
       AND e.store_id = ec.store_id
";

$params = [
    ':categories_store_id' => $storeId
];


if ($statusFilter === 'ACTIVE') {

    $sql .= "
        WHERE ec.store_id = :categories_store_id
          AND ec.status = :status_active
    ";

    $params[':status_active'] = 'ACTIVE';

} elseif ($statusFilter === 'INACTIVE') {

    $sql .= "
        WHERE ec.store_id = :categories_store_id
          AND ec.status = :status_inactive
    ";

    $params[':status_inactive'] = 'INACTIVE';
}


if ($statusFilter !== 'ACTIVE' && $statusFilter !== 'INACTIVE') {
    $sql .= "
        WHERE ec.store_id = :categories_store_id
    ";
}

$sql .= "
    GROUP BY
        ec.id,
        ec.name,
        ec.status,
        ec.created_at,
        ec.updated_at

    ORDER BY
        ec.status ASC,
        ec.name ASC
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

?>


<?php include '../../includes/header.php'; ?>

<?php include '../../includes/sidebar.php'; ?>


<div class="main-content">


    <!-- =====================================================
         MOBILE HEADER
    ====================================================== -->

<!-- =====================================================
         CONTENT
    ====================================================== -->

    <main class="p-4 md:p-8">


        <!-- =================================================
             HEADER
        ================================================== -->

        <div
            class="
                flex
                flex-col
                md:flex-row
                md:items-end
                md:justify-between
                gap-5
                mb-8
            "
        >

            <div>

                <div
                    class="
                        text-xs
                        font-medium
                        tracking-widest
                        text-neutral-400
                        uppercase
                        mb-2
                    "
                >
                    Pengeluaran
                </div>


                <h1
                    class="
                        text-2xl
                        md:text-3xl
                        font-semibold
                        tracking-tight
                    "
                >
                    Kategori
                </h1>


                <p
                    class="
                        text-sm
                        text-neutral-500
                        mt-2
                    "
                >
                    Kelola kategori untuk pengeluaran toko.
                </p>

            </div>


            <a
                href="/pages/pengeluaran/"
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
                    transition
                "
            >

                <i
                    data-lucide="arrow-left"
                    class="w-4 h-4"
                ></i>

                Kembali

            </a>

        </div>


        <!-- =================================================
             FLASH MESSAGE
        ================================================== -->

        <?php if ($success): ?>

            <div
                class="
                    mb-6
                    rounded-2xl
                    border
                    border-emerald-200
                    bg-emerald-50
                    px-4
                    py-3
                    text-sm
                    text-emerald-700
                    flex
                    items-center
                    gap-3
                "
            >

                <i
                    data-lucide="circle-check"
                    class="w-5 h-5 flex-none"
                ></i>

                <span>
                    <?= e($success) ?>
                </span>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div
                class="
                    mb-6
                    rounded-2xl
                    border
                    border-red-200
                    bg-red-50
                    px-4
                    py-3
                    text-sm
                    text-red-700
                    flex
                    items-center
                    gap-3
                "
            >

                <i
                    data-lucide="circle-alert"
                    class="w-5 h-5 flex-none"
                ></i>

                <span>
                    <?= e($error) ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- =================================================
             TAMBAH KATEGORI
        ================================================== -->

        <div class="bento-card p-5 md:p-6 mb-6">

            <div class="mb-5">

                <h2 class="font-semibold">
                    Tambah Kategori
                </h2>

                <p
                    class="
                        text-xs
                        text-neutral-400
                        mt-1
                    "
                >
                    Buat kategori baru untuk memudahkan pencatatan pengeluaran.
                </p>

            </div>


            <form
                method="POST"
                class="
                    flex
                    flex-col
                    md:flex-row
                    gap-3
                "
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="create"
                >


                <input
                    type="text"
                    name="name"
                    maxlength="100"
                    required
                    placeholder="Contoh: Listrik"
                    class="
                        flex-1
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
                        data-lucide="plus"
                        class="w-4 h-4"
                    ></i>

                    Tambah

                </button>

            </form>

        </div>


        <!-- =================================================
             FILTER
        ================================================== -->

        <div
            class="
                bento-card
                p-4
                md:p-5
                mb-6
            "
        >

            <form
                method="GET"
                class="
                    flex
                    flex-col
                    sm:flex-row
                    sm:items-center
                    gap-3
                "
            >

                <div class="text-sm font-medium">
                    Status
                </div>


                <select
                    name="status"
                    onchange="this.form.submit()"
                    class="
                        w-full
                        sm:w-auto
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


                <div
                    class="
                        text-xs
                        text-neutral-400
                        sm:ml-auto
                    "
                >
                    <?= $totalCategories ?> kategori
                </div>

            </form>

        </div>


        <!-- =================================================
             LIST
        ================================================== -->

        <div class="bento-card overflow-hidden">


            <div
                class="
                    px-5
                    py-4
                    border-b
                    border-neutral-100
                    flex
                    items-center
                    justify-between
                    gap-3
                "
            >

                <div>

                    <h2 class="font-semibold">
                        Daftar Kategori
                    </h2>

                    <p
                        class="
                            text-xs
                            text-neutral-400
                            mt-1
                        "
                    >
                        Kategori yang digunakan saat mencatat pengeluaran.
                    </p>

                </div>

            </div>


            <?php if (!$categories): ?>

                <div
                    class="
                        p-10
                        text-center
                    "
                >

                    <div
                        class="
                            w-12
                            h-12
                            rounded-2xl
                            bg-neutral-100
                            mx-auto
                            flex
                            items-center
                            justify-center
                            mb-4
                        "
                    >

                        <i
                            data-lucide="layers-2"
                            class="
                                w-6
                                h-6
                                text-neutral-400
                            "
                        ></i>

                    </div>


                    <div
                        class="
                            text-sm
                            font-medium
                            text-neutral-700
                        "
                    >
                        Belum ada kategori
                    </div>


                    <div
                        class="
                            text-xs
                            text-neutral-400
                            mt-1
                        "
                    >
                        Tambahkan kategori pertama untuk pengeluaran toko.
                    </div>

                </div>

            <?php else: ?>


                <!-- DESKTOP -->

                <div class="hidden md:block">

                    <table class="w-full">

                        <thead>

                            <tr
                                class="
                                    border-b
                                    border-neutral-100
                                    text-left
                                "
                            >

                                <th
                                    class="
                                        px-5
                                        py-4
                                        text-xs
                                        font-medium
                                        text-neutral-400
                                    "
                                >
                                    Kategori
                                </th>

                                <th
                                    class="
                                        px-5
                                        py-4
                                        text-xs
                                        font-medium
                                        text-neutral-400
                                    "
                                >
                                    Pengeluaran
                                </th>

                                <th
                                    class="
                                        px-5
                                        py-4
                                        text-xs
                                        font-medium
                                        text-neutral-400
                                    "
                                >
                                    Status
                                </th>

                                <th
                                    class="
                                        px-5
                                        py-4
                                        text-xs
                                        font-medium
                                        text-neutral-400
                                        text-right
                                    "
                                >
                                    Aksi
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($categories as $category): ?>

                                <tr
                                    class="
                                        border-b
                                        border-neutral-100
                                        last:border-0
                                    "
                                >

                                    <td class="px-5 py-4">

                                        <div
                                            class="
                                                text-sm
                                                font-medium
                                            "
                                        >
                                            <?= e($category['name']) ?>
                                        </div>

                                    </td>


                                    <td class="px-5 py-4">

                                        <span
                                            class="
                                                text-sm
                                                text-neutral-600
                                            "
                                        >
                                            <?= number_format(
                                                (int) $category['total_pengeluaran'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?>
                                            transaksi
                                        </span>

                                    </td>


                                    <td class="px-5 py-4">

                                        <?php if (
                                            $category['status'] === 'ACTIVE'
                                        ): ?>

                                            <span
                                                class="
                                                    inline-flex
                                                    items-center
                                                    gap-1.5
                                                    px-2.5
                                                    py-1
                                                    rounded-full
                                                    bg-emerald-50
                                                    text-emerald-700
                                                    text-xs
                                                    font-medium
                                                "
                                            >

                                                <span
                                                    class="
                                                        w-1.5
                                                        h-1.5
                                                        rounded-full
                                                        bg-emerald-500
                                                    "
                                                ></span>

                                                Aktif

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="
                                                    inline-flex
                                                    items-center
                                                    gap-1.5
                                                    px-2.5
                                                    py-1
                                                    rounded-full
                                                    bg-neutral-100
                                                    text-neutral-500
                                                    text-xs
                                                    font-medium
                                                "
                                            >

                                                <span
                                                    class="
                                                        w-1.5
                                                        h-1.5
                                                        rounded-full
                                                        bg-neutral-400
                                                    "
                                                ></span>

                                                Tidak Aktif

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td class="px-5 py-4">

                                        <div
                                            class="
                                                flex
                                                items-center
                                                justify-end
                                                gap-2
                                            "
                                        >

                                            <button
                                                type="button"
                                                onclick="openEditModal(
                                                    <?= (int) $category['id'] ?>,
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $category['name'],
                                                            JSON_HEX_TAG |
                                                            JSON_HEX_APOS |
                                                            JSON_HEX_QUOT |
                                                            JSON_HEX_AMP
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                )"
                                                class="
                                                    inline-flex
                                                    items-center
                                                    gap-1.5
                                                    px-3
                                                    py-2
                                                    rounded-xl
                                                    border
                                                    border-neutral-200
                                                    bg-white
                                                    text-xs
                                                    font-medium
                                                    hover:bg-neutral-50
                                                "
                                            >

                                                <i
                                                    data-lucide="pencil"
                                                    class="w-3.5 h-3.5"
                                                ></i>

                                                Edit

                                            </button>


                                            <form
                                                method="POST"
                                                onsubmit="return confirmToggle(
                                                    <?= htmlspecialchars(
                                                        json_encode(
                                                            $category['name'],
                                                            JSON_HEX_TAG |
                                                            JSON_HEX_APOS |
                                                            JSON_HEX_QUOT |
                                                            JSON_HEX_AMP
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>,
                                                    '<?= $category['status'] === 'ACTIVE'
                                                        ? 'menonaktifkan'
                                                        : 'mengaktifkan' ?>'
                                                );"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($csrfToken) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="toggle_status"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $category['id'] ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="
                                                        inline-flex
                                                        items-center
                                                        gap-1.5
                                                        px-3
                                                        py-2
                                                        rounded-xl
                                                        border
                                                        border-neutral-200
                                                        bg-white
                                                        text-xs
                                                        font-medium
                                                        hover:bg-neutral-50
                                                    "
                                                >

                                                    <i
                                                        data-lucide="<?= $category['status'] === 'ACTIVE'
                                                            ? 'power-off'
                                                            : 'power' ?>"
                                                        class="w-3.5 h-3.5"
                                                    ></i>

                                                    <?= $category['status'] === 'ACTIVE'
                                                        ? 'Nonaktifkan'
                                                        : 'Aktifkan' ?>

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

                    <?php foreach ($categories as $category): ?>

                        <div class="p-5">

                            <div
                                class="
                                    flex
                                    items-start
                                    justify-between
                                    gap-4
                                "
                            >

                                <div class="min-w-0">

                                    <div
                                        class="
                                            text-sm
                                            font-medium
                                        "
                                    >
                                        <?= e($category['name']) ?>
                                    </div>


                                    <div
                                        class="
                                            text-xs
                                            text-neutral-400
                                            mt-1
                                        "
                                    >
                                        <?= number_format(
                                            (int) $category['total_pengeluaran'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>
                                        transaksi
                                    </div>

                                </div>


                                <?php if (
                                    $category['status'] === 'ACTIVE'
                                ): ?>

                                    <span
                                        class="
                                            flex-none
                                            inline-flex
                                            items-center
                                            gap-1.5
                                            px-2.5
                                            py-1
                                            rounded-full
                                            bg-emerald-50
                                            text-emerald-700
                                            text-xs
                                            font-medium
                                        "
                                    >

                                        <span
                                            class="
                                                w-1.5
                                                h-1.5
                                                rounded-full
                                                bg-emerald-500
                                            "
                                        ></span>

                                        Aktif

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            flex-none
                                            inline-flex
                                            items-center
                                            gap-1.5
                                            px-2.5
                                            py-1
                                            rounded-full
                                            bg-neutral-100
                                            text-neutral-500
                                            text-xs
                                            font-medium
                                        "
                                    >

                                        Tidak Aktif

                                    </span>

                                <?php endif; ?>

                            </div>


                            <div
                                class="
                                    grid
                                    grid-cols-2
                                    gap-2
                                    mt-4
                                "
                            >

                                <button
                                    type="button"
                                    onclick="openEditModal(
                                        <?= (int) $category['id'] ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $category['name'],
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    )"
                                    class="
                                        inline-flex
                                        items-center
                                        justify-center
                                        gap-1.5
                                        px-3
                                        py-2.5
                                        rounded-xl
                                        border
                                        border-neutral-200
                                        bg-white
                                        text-xs
                                        font-medium
                                        hover:bg-neutral-50
                                    "
                                >

                                    <i
                                        data-lucide="pencil"
                                        class="w-3.5 h-3.5"
                                    ></i>

                                    Edit

                                </button>


                                <form
                                    method="POST"
                                    onsubmit="return confirmToggle(
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $category['name'],
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>,
                                        '<?= $category['status'] === 'ACTIVE'
                                            ? 'menonaktifkan'
                                            : 'mengaktifkan' ?>'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle_status"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $category['id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            w-full
                                            inline-flex
                                            items-center
                                            justify-center
                                            gap-1.5
                                            px-3
                                            py-2.5
                                            rounded-xl
                                            border
                                            border-neutral-200
                                            bg-white
                                            text-xs
                                            font-medium
                                            hover:bg-neutral-50
                                        "
                                    >

                                        <i
                                            data-lucide="<?= $category['status'] === 'ACTIVE'
                                                ? 'power-off'
                                                : 'power' ?>"
                                            class="w-3.5 h-3.5"
                                        ></i>

                                        <?= $category['status'] === 'ACTIVE'
                                            ? 'Nonaktifkan'
                                            : 'Aktifkan' ?>

                                    </button>

                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>


<!-- =========================================================
     EDIT MODAL
========================================================= -->

<div
    id="editModal"
    class="
        fixed
        inset-0
        z-[100]
        hidden
        items-center
        justify-center
        p-4
    "
>

    <div
        class="
            absolute
            inset-0
            bg-black/40
            backdrop-blur-sm
        "
        onclick="closeEditModal()"
    ></div>


    <div
        class="
            relative
            w-full
            max-w-md
            bg-white
            rounded-3xl
            shadow-2xl
            overflow-hidden
        "
    >

        <div
            class="
                p-5
                border-b
                border-neutral-100
                flex
                items-center
                justify-between
            "
        >

            <div>

                <h2 class="font-semibold">
                    Edit Kategori
                </h2>

                <p
                    class="
                        text-xs
                        text-neutral-400
                        mt-1
                    "
                >
                    Ubah nama kategori pengeluaran.
                </p>

            </div>


            <button
                type="button"
                onclick="closeEditModal()"
                class="
                    w-9
                    h-9
                    rounded-xl
                    flex
                    items-center
                    justify-center
                    hover:bg-neutral-100
                "
                aria-label="Tutup"
            >

                <i
                    data-lucide="x"
                    class="w-5 h-5"
                ></i>

            </button>

        </div>


        <form
            method="POST"
            class="p-5"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="edit"
            >

            <input
                type="hidden"
                name="id"
                id="editCategoryId"
            >


            <label
                class="
                    block
                    text-xs
                    font-medium
                    text-neutral-500
                    mb-2
                "
            >
                Nama Kategori
            </label>


            <input
                type="text"
                name="name"
                id="editCategoryName"
                maxlength="100"
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


            <div
                class="
                    flex
                    gap-2
                    mt-5
                "
            >

                <button
                    type="button"
                    onclick="closeEditModal()"
                    class="
                        flex-1
                        px-4
                        py-3
                        rounded-xl
                        border
                        border-neutral-200
                        text-sm
                        font-medium
                        hover:bg-neutral-50
                    "
                >
                    Batal
                </button>


                <button
                    type="submit"
                    class="
                        flex-1
                        px-4
                        py-3
                        rounded-xl
                        bg-neutral-900
                        text-white
                        text-sm
                        font-medium
                        hover:bg-neutral-800
                    "
                >
                    Simpan
                </button>

            </div>

        </form>

    </div>

</div>


<script>

function openEditModal(id, name) {

    const modal =
        document.getElementById('editModal');

    const idInput =
        document.getElementById('editCategoryId');

    const nameInput =
        document.getElementById('editCategoryName');


    if (!modal || !idInput || !nameInput) {
        return;
    }


    idInput.value = id;
    nameInput.value = name;


    modal.classList.remove('hidden');
    modal.classList.add('flex');


    setTimeout(function () {

        nameInput.focus();
        nameInput.select();

    }, 50);
}


function closeEditModal() {

    const modal =
        document.getElementById('editModal');

    if (!modal) {
        return;
    }


    modal.classList.add('hidden');
    modal.classList.remove('flex');
}


function confirmToggle(name, action) {

    return confirm(
        'Yakin ingin ' +
        action +
        ' kategori "' +
        name +
        '"?'
    );
}


document.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Escape') {
            closeEditModal();
        }

    }
);

</script>


<?php include '../../includes/footer.php'; ?>