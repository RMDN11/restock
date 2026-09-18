<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$userId = (int) ($authUserId ?? $_SESSION['user_id'] ?? 0);
$storeId = (int) ($authStoreId ?? $_SESSION['store_id'] ?? 0);

if ($userId <= 0 || $storeId <= 0) {
    header('Location: /login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$stmt = $pdo->prepare("
    SELECT id, name, username, role, status, created_at, updated_at
    FROM users
    WHERE id = :user_id
    LIMIT 1
");
$stmt->execute([':user_id' => $userId]);
$user = $stmt->fetch();

if (!$user || $user['status'] !== 'ACTIVE') {
    $_SESSION = [];
    session_destroy();
    header('Location: /login.php');
    exit;
}

$membershipStmt = $pdo->prepare("
    SELECT su.role, su.status, s.status AS store_status, a.status AS account_status
    FROM store_users su
    INNER JOIN stores s ON s.id = su.store_id
    INNER JOIN accounts a ON a.id = s.account_id
    WHERE su.user_id = :membership_user_id
      AND su.store_id = :membership_store_id
    LIMIT 1
");
$membershipStmt->execute([
    ':membership_user_id' => $userId,
    ':membership_store_id' => $storeId
]);
$membership = $membershipStmt->fetch();

if (!$membership || $membership['status'] !== 'ACTIVE' || $membership['store_status'] !== 'ACTIVE' || $membership['account_status'] !== 'ACTIVE') {
    header('Location: /login.php');
    exit;
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $membership['role'];
$_SESSION['account_id'] = (int) ($authAccountId ?? $_SESSION['account_id'] ?? 0);
$_SESSION['store_id'] = $storeId;

$name = $user['name'];
$username = $user['username'];
$errors = [];

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!$postedToken || !hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    }

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');

    if ($name === '') {
        $errors[] = 'Nama wajib diisi.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Nama maksimal 100 karakter.';
    }

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    } elseif (mb_strlen($username) > 50) {
        $errors[] = 'Username maksimal 50 karakter.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
        $errors[] = 'Username hanya boleh menggunakan huruf, angka, titik, underscore, dan strip.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = :username
              AND id <> :current_user_id
            LIMIT 1
        ");
        $stmt->execute([
            ':username' => $username,
            ':current_user_id' => $userId
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Username tersebut sudah digunakan akun lain.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE users
                SET name = :name,
                    username = :username,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :user_id
            ");
            $stmt->execute([
                ':name' => $name,
                ':username' => $username,
                ':user_id' => $userId
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs
                    (store_id, user_id, action, table_name, record_id, description, created_at)
                VALUES
                    (:audit_store_id, :audit_user_id, 'UPDATE', 'users', :record_id, :description, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                ':audit_store_id' => $storeId,
                ':audit_user_id' => $userId,
                ':record_id' => $userId,
                ':description' => 'Mengubah data akun sendiri: ' . $name . ' (' . $username . ')'
            ]);

            $pdo->commit();

            $_SESSION['user_name'] = $name;
            $_SESSION['username'] = $username;
            $_SESSION['flash_success'] = 'Data akun berhasil diperbarui.';
            header('Location: /pages/pengaturan/');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Data akun gagal diperbarui. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Pengaturan';

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

?>


<!--
|--------------------------------------------------------------------------
| Main Content
|--------------------------------------------------------------------------
-->
<main class="main-content">

    <div class="p-4 md:p-8">

        <!--
        |--------------------------------------------------------------------------
        | Page Header
        |--------------------------------------------------------------------------
        -->
        <div class="mb-7">

            <div class="text-xs text-neutral-400 mb-2">
                PENGATURAN
            </div>

            <h1 class="
                text-2xl
                md:text-3xl
                font-semibold
                tracking-tight
            ">
                Akun Saya
            </h1>

            <p class="text-sm text-neutral-500 mt-2">
                Kelola informasi akun yang sedang digunakan.
            </p>

        </div>

        <!--
        |--------------------------------------------------------------------------
        | Flash Success
        |--------------------------------------------------------------------------
        -->
        <?php if ($flashSuccess): ?>

            <div class="
                mb-5
                rounded-2xl
                border border-green-200
                bg-green-50
                p-4
            ">

                <div class="flex items-center gap-3">

                    <div class="
                        w-9 h-9
                        rounded-xl
                        bg-green-100
                        flex items-center justify-center
                        flex-none
                    ">
                        <i
                            data-lucide="check"
                            class="w-5 h-5 text-green-600"
                        ></i>
                    </div>

                    <div class="text-sm font-medium text-green-800">
                        <?= e($flashSuccess) ?>
                    </div>

                </div>

            </div>

        <?php endif; ?>

        <!--
        |--------------------------------------------------------------------------
        | Flash Error
        |--------------------------------------------------------------------------
        -->
        <?php if ($flashError): ?>

            <div class="
                mb-5
                rounded-2xl
                border border-red-200
                bg-red-50
                p-4
            ">

                <div class="flex items-center gap-3">

                    <div class="
                        w-9 h-9
                        rounded-xl
                        bg-red-100
                        flex items-center justify-center
                        flex-none
                    ">
                        <i
                            data-lucide="alert-circle"
                            class="w-5 h-5 text-red-600"
                        ></i>
                    </div>

                    <div class="text-sm font-medium text-red-800">
                        <?= e($flashError) ?>
                    </div>

                </div>

            </div>

        <?php endif; ?>

        <!--
        |--------------------------------------------------------------------------
        | Validation Errors
        |--------------------------------------------------------------------------
        -->
        <?php if (!empty($errors)): ?>

            <div class="
                mb-5
                rounded-2xl
                border border-red-200
                bg-red-50
                p-4
            ">

                <div class="flex items-start gap-3">

                    <div class="
                        w-9 h-9
                        rounded-xl
                        bg-red-100
                        flex items-center justify-center
                        flex-none
                    ">
                        <i
                            data-lucide="alert-circle"
                            class="w-5 h-5 text-red-600"
                        ></i>
                    </div>

                    <div>

                        <div class="font-medium text-red-800">
                            Data belum bisa disimpan
                        </div>

                        <ul class="
                            mt-1
                            text-sm
                            text-red-700
                            space-y-1
                        ">

                            <?php foreach ($errors as $error): ?>

                                <li>
                                    • <?= e($error) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                </div>

            </div>

        <?php endif; ?>

        <!--
        |--------------------------------------------------------------------------
        | Main Grid
        |--------------------------------------------------------------------------
        -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

            <!--
            |--------------------------------------------------------------------------
            | Account Form
            |--------------------------------------------------------------------------
            -->
            <div class="xl:col-span-2">

                <form
                    method="POST"
                    class="bento-card p-5 md:p-7"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <!-- Section Header -->
                    <div class="
                        flex items-start gap-4
                        pb-6
                        border-b border-neutral-100
                    ">

                        <div class="
                            w-12 h-12
                            rounded-2xl
                            bg-neutral-100
                            flex items-center justify-center
                            flex-none
                        ">
                            <i
                                data-lucide="user-round"
                                class="w-6 h-6 text-neutral-700"
                            ></i>
                        </div>

                        <div>

                            <h2 class="font-semibold">
                                Informasi Akun
                            </h2>

                            <p class="text-xs text-neutral-400 mt-1">
                                Informasi dasar akun yang sedang login.
                            </p>

                        </div>

                    </div>

                    <!-- Form Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-6">

                        <!-- Name -->
                        <div>

                            <label
                                for="name"
                                class="
                                    block
                                    text-sm
                                    font-medium
                                    text-neutral-700
                                    mb-2
                                "
                            >
                                Nama
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="<?= e($name) ?>"
                                maxlength="100"
                                required
                                autocomplete="name"
                                class="
                                    w-full
                                    px-4 py-3
                                    rounded-xl
                                    border border-neutral-200
                                    bg-white
                                    text-sm
                                    outline-none
                                    focus:border-neutral-500
                                    focus:ring-2
                                    focus:ring-neutral-100
                                    transition
                                "
                                placeholder="Nama kamu"
                            >

                        </div>

                        <!-- Username -->
                        <div>

                            <label
                                for="username"
                                class="
                                    block
                                    text-sm
                                    font-medium
                                    text-neutral-700
                                    mb-2
                                "
                            >
                                Username
                            </label>

                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="<?= e($username) ?>"
                                maxlength="50"
                                required
                                autocomplete="username"
                                spellcheck="false"
                                class="
                                    w-full
                                    px-4 py-3
                                    rounded-xl
                                    border border-neutral-200
                                    bg-white
                                    text-sm
                                    outline-none
                                    focus:border-neutral-500
                                    focus:ring-2
                                    focus:ring-neutral-100
                                    transition
                                "
                                placeholder="Username"
                            >

                            <p class="text-xs text-neutral-400 mt-2">
                                Huruf, angka, titik, underscore, atau strip.
                            </p>

                        </div>

                    </div>

                    <!-- Save -->
                    <div class="
                        mt-7
                        pt-5
                        border-t border-neutral-100
                        flex justify-end
                    ">

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

                            Simpan Perubahan

                        </button>

                    </div>

                </form>

            </div>

            <!--
            |--------------------------------------------------------------------------
            | Account Summary
            |--------------------------------------------------------------------------
            -->
            <div>

                <div class="bento-card p-5 md:p-6">

                    <div class="
                        flex items-start gap-4
                        pb-5
                        border-b border-neutral-100
                    ">

                        <div class="
                            w-11 h-11
                            rounded-2xl
                            bg-neutral-100
                            flex items-center justify-center
                            flex-none
                        ">
                            <i
                                data-lucide="shield-check"
                                class="w-5 h-5 text-neutral-700"
                            ></i>
                        </div>

                        <div>

                            <h2 class="font-semibold">
                                Status Akun
                            </h2>

                            <p class="text-xs text-neutral-400 mt-1">
                                Informasi akses akun.
                            </p>

                        </div>

                    </div>

                    <div class="space-y-4 mt-5">

                        <!-- Role -->
                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                PERAN
                            </div>

                            <div class="flex items-center gap-2">

                                <span class="
                                    inline-flex
                                    items-center
                                    gap-1.5
                                    px-3
                                    py-1.5
                                    rounded-full
                                    bg-neutral-100
                                    text-neutral-700
                                    text-xs
                                    font-medium
                                ">

                                    <?php if ($user['role'] === 'ADMIN'): ?>

                                        <i
                                            data-lucide="crown"
                                            class="w-3.5 h-3.5"
                                        ></i>

                                        Owner

                                    <?php else: ?>

                                        <i
                                            data-lucide="user-round"
                                            class="w-3.5 h-3.5"
                                        ></i>

                                        Staff

                                    <?php endif; ?>

                                </span>

                            </div>

                        </div>

                        <!-- Status -->
                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                STATUS
                            </div>

                            <?php if ($user['status'] === 'ACTIVE'): ?>

                                <span class="
                                    inline-flex
                                    items-center
                                    gap-1.5
                                    px-3
                                    py-1.5
                                    rounded-full
                                    bg-green-50
                                    text-green-700
                                    text-xs
                                    font-medium
                                ">

                                    <span class="
                                        w-1.5 h-1.5
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
                                    px-3
                                    py-1.5
                                    rounded-full
                                    bg-neutral-100
                                    text-neutral-500
                                    text-xs
                                    font-medium
                                ">

                                    <span class="
                                        w-1.5 h-1.5
                                        rounded-full
                                        bg-neutral-400
                                    "></span>

                                    Nonaktif

                                </span>

                            <?php endif; ?>

                        </div>

                        <!-- Username -->
                        <div>

                            <div class="
                                text-xs
                                text-neutral-400
                                mb-1
                            ">
                                USERNAME
                            </div>

                            <div class="
                                text-sm
                                font-medium
                                text-neutral-800
                                break-all
                            ">
                                <?= e($user['username']) ?>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!--
        |--------------------------------------------------------------------------
        | Change Password
        |--------------------------------------------------------------------------
        -->
        <div class="mt-5">

            <div class="bento-card p-5 md:p-6">

                <div class="
                    flex flex-col
                    sm:flex-row
                    sm:items-center
                    sm:justify-between
                    gap-5
                ">

                    <div class="flex items-start gap-4">

                        <div class="
                            w-11 h-11
                            rounded-2xl
                            bg-neutral-100
                            flex items-center justify-center
                            flex-none
                        ">
                            <i
                                data-lucide="lock-keyhole"
                                class="w-5 h-5 text-neutral-700"
                            ></i>
                        </div>

                        <div>

                            <h2 class="font-semibold">
                                Keamanan Akun
                            </h2>

                            <p class="text-xs text-neutral-400 mt-1">
                                Ubah password untuk menjaga keamanan akun.
                            </p>

                        </div>

                    </div>

                    <a
                        href="#"
                        onclick="openPasswordModal(); return false;"
                        class="
                            inline-flex
                            items-center
                            justify-center
                            gap-2
                            px-4
                            py-2.5
                            rounded-xl
                            border border-neutral-200
                            bg-white
                            text-neutral-700
                            text-sm
                            font-medium
                            hover:bg-neutral-50
                            transition
                        "
                    >

                        <i
                            data-lucide="key-round"
                            class="w-4 h-4"
                        ></i>

                        Ubah Password

                    </a>

                </div>

            </div>

        </div>

        <!--
        |--------------------------------------------------------------------------
        | Owner Only: Staff Management
        |--------------------------------------------------------------------------
        -->
        <?php if ($user['role'] === 'ADMIN'): ?>

            <div class="mt-5">

                <div class="bento-card p-5 md:p-6">

                    <div class="
                        flex flex-col
                        sm:flex-row
                        sm:items-center
                        sm:justify-between
                        gap-5
                    ">

                        <div class="flex items-start gap-4">

                            <div class="
                                w-11 h-11
                                rounded-2xl
                                bg-neutral-100
                                flex items-center justify-center
                                flex-none
                            ">
                                <i
                                    data-lucide="users"
                                    class="w-5 h-5 text-neutral-700"
                                ></i>
                            </div>

                            <div>

                                <h2 class="font-semibold">
                                    Kelola Staff
                                </h2>

                                <p class="text-xs text-neutral-400 mt-1">
                                    Tambah dan kelola akun staff toko.
                                </p>

                            </div>

                        </div>

                        <a
                            href="/pages/pengaturan/staff/"
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
                                data-lucide="users"
                                class="w-4 h-4"
                            ></i>

                            Kelola Staff

                        </a>

                    </div>

                    <div class="
                        mt-5
                        pt-4
                        border-t border-neutral-100
                        flex items-center gap-2
                        text-xs text-neutral-400
                    ">

                        <i
                            data-lucide="shield-check"
                            class="w-4 h-4"
                        ></i>

                        Fitur ini hanya dapat diakses oleh Owner.

                    </div>

                </div>

            </div>

        <?php endif; ?>

        <!--
        |--------------------------------------------------------------------------
        | Store Management
        |--------------------------------------------------------------------------
        -->
        <div class="mt-5">

            <div class="bento-card p-5 md:p-6">

                <div class="
                    flex flex-col
                    sm:flex-row
                    sm:items-center
                    sm:justify-between
                    gap-5
                ">

                    <div class="flex items-start gap-4">

                        <div class="
                            w-11 h-11
                            rounded-2xl
                            bg-neutral-100
                            flex items-center justify-center
                            flex-none
                        ">
                            <i
                                data-lucide="store"
                                class="w-5 h-5 text-neutral-700"
                            ></i>
                        </div>

                        <div>

                            <h2 class="font-semibold">
                                Kelola Toko
                            </h2>

                            <p class="text-xs text-neutral-400 mt-1">
                                Lihat, kelola, dan pilih toko yang sedang digunakan.
                            </p>

                        </div>

                    </div>

                    <a
                        href="/pages/pengaturan/store/"
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
                            data-lucide="store"
                            class="w-4 h-4"
                        ></i>

                        Kelola Toko

                    </a>

                </div>

            </div>

        </div>

    </div>

</main>

<!--
|--------------------------------------------------------------------------
| Password Modal
|--------------------------------------------------------------------------
-->
<div
    id="passwordModal"
    class="
        fixed inset-0
        z-[100]
        hidden
        items-center justify-center
        bg-black/30
        backdrop-blur-sm
        p-4
    "
>

    <div
        class="
            w-full
            max-w-md
            bg-white
            rounded-3xl
            shadow-2xl
            border border-neutral-200
            overflow-hidden
        "
    >

        <div class="p-5 md:p-6">

            <div class="flex items-start justify-between gap-4">

                <div>

                    <div class="
                        w-11 h-11
                        rounded-2xl
                        bg-neutral-100
                        flex items-center justify-center
                        mb-4
                    ">
                        <i
                            data-lucide="lock-keyhole"
                            class="w-5 h-5"
                        ></i>
                    </div>

                    <h2 class="text-lg font-semibold">
                        Ubah Password
                    </h2>

                    <p class="text-xs text-neutral-400 mt-1">
                        Gunakan password baru minimal 8 karakter.
                    </p>

                </div>

                <button
                    type="button"
                    onclick="closePasswordModal()"
                    class="
                        w-9 h-9
                        rounded-xl
                        border border-neutral-200
                        flex items-center justify-center
                        hover:bg-neutral-50
                    "
                    aria-label="Tutup"
                >

                    <i
                        data-lucide="x"
                        class="w-4 h-4"
                    ></i>

                </button>

            </div>

            <form
                method="POST"
                action="/pages/pengaturan/password.php"
                class="mt-6"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <!-- Current Password -->
                <div>

                    <label
                        for="current_password"
                        class="
                            block
                            text-sm
                            font-medium
                            text-neutral-700
                            mb-2
                        "
                    >
                        Password Saat Ini
                    </label>

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        required
                        autocomplete="current-password"
                        class="
                            w-full
                            px-4 py-3
                            rounded-xl
                            border border-neutral-200
                            text-sm
                            outline-none
                            focus:border-neutral-500
                            focus:ring-2
                            focus:ring-neutral-100
                        "
                    >

                </div>

                <!-- New Password -->
                <div class="mt-4">

                    <label
                        for="new_password"
                        class="
                            block
                            text-sm
                            font-medium
                            text-neutral-700
                            mb-2
                        "
                    >
                        Password Baru
                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        minlength="8"
                        required
                        autocomplete="new-password"
                        class="
                            w-full
                            px-4 py-3
                            rounded-xl
                            border border-neutral-200
                            text-sm
                            outline-none
                            focus:border-neutral-500
                            focus:ring-2
                            focus:ring-neutral-100
                        "
                    >

                </div>

                <!-- Confirm Password -->
                <div class="mt-4">

                    <label
                        for="confirm_password"
                        class="
                            block
                            text-sm
                            font-medium
                            text-neutral-700
                            mb-2
                        "
                    >
                        Ulangi Password Baru
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        required
                        autocomplete="new-password"
                        class="
                            w-full
                            px-4 py-3
                            rounded-xl
                            border border-neutral-200
                            text-sm
                            outline-none
                            focus:border-neutral-500
                            focus:ring-2
                            focus:ring-neutral-100
                        "
                    >

                </div>

                <!-- Actions -->
                <div class="
                    mt-6
                    flex
                    flex-col-reverse
                    sm:flex-row
                    sm:justify-end
                    gap-2
                ">

                    <button
                        type="button"
                        onclick="closePasswordModal()"
                        class="
                            px-4 py-3
                            rounded-xl
                            border border-neutral-200
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
                            px-4 py-3
                            rounded-xl
                            bg-neutral-900
                            text-white
                            text-sm
                            font-medium
                            hover:bg-neutral-800
                        "
                    >
                        Simpan Password
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!--
|--------------------------------------------------------------------------
| Password Modal JS
|--------------------------------------------------------------------------
-->
<script>

function openPasswordModal() {

    const modal = document.getElementById('passwordModal');

    if (!modal) {
        return;
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    document.body.classList.add('overflow-hidden');

    const currentPassword =
        document.getElementById('current_password');

    if (currentPassword) {
        setTimeout(function () {
            currentPassword.focus();
        }, 100);
    }
}

function closePasswordModal() {

    const modal = document.getElementById('passwordModal');

    if (!modal) {
        return;
    }

    modal.classList.add('hidden');
    modal.classList.remove('flex');

    document.body.classList.remove('overflow-hidden');
}

document.addEventListener('keydown', function (event) {

    if (event.key === 'Escape') {
        closePasswordModal();
    }

});

document
    .getElementById('passwordModal')
    ?.addEventListener('click', function (event) {

        if (event.target === this) {
            closePasswordModal();
        }

    });

</script>

<?php require_once '../../includes/footer.php'; ?>