<?php

require_once '../../../config/database.php';
require_once '../../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$ownerId = (int) ($authUserId ?? $_SESSION['user_id'] ?? 0);
$storeId = (int) ($authStoreId ?? $_SESSION['store_id'] ?? 0);
$accountId = (int) ($authAccountId ?? $_SESSION['account_id'] ?? 0);

if ($ownerId <= 0 || $storeId <= 0 || $accountId <= 0 || ($authRole ?? $_SESSION['role'] ?? '') !== 'ADMIN') {
    header('Location: /pages/pengaturan/');
    exit;
}

$ownerCheck = $pdo->prepare("
    SELECT su.id
    FROM store_users su
    INNER JOIN stores s ON s.id = su.store_id
    INNER JOIN accounts a ON a.id = s.account_id
    WHERE su.user_id = :owner_user_id
      AND su.store_id = :owner_store_id
      AND su.role = 'ADMIN'
      AND su.status = 'ACTIVE'
      AND s.status = 'ACTIVE'
      AND a.status = 'ACTIVE'
    LIMIT 1
");
$ownerCheck->execute([
    ':owner_user_id' => $ownerId,
    ':owner_store_id' => $storeId
]);
if (!$ownerCheck->fetch()) {
    header('Location: /pages/pengaturan/');
    exit;
}

$staffId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$staffId || $staffId <= 0) {
    header('Location: /pages/pengaturan/staff/');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        u.id, u.name, u.username, u.email, u.role AS legacy_role, u.status AS account_status,
        su.role AS store_role, su.status AS membership_status,
        u.created_at, u.updated_at
    FROM users u
    INNER JOIN store_users su ON su.user_id = u.id
    INNER JOIN stores s ON s.id = su.store_id
    WHERE u.id = :staff_user_id
      AND su.store_id = :target_store_id
      AND su.role = 'STAFF'
    LIMIT 1
");
$stmt->execute([
    ':staff_user_id' => $staffId,
    ':target_store_id' => $storeId
]);
$staff = $stmt->fetch();

if (!$staff) {
    $_SESSION['flash_error'] = 'Data Staff tidak ditemukan di Store aktif.';
    header('Location: /pages/pengaturan/staff/');
    exit;
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

$name = $staff['name'];
$username = $staff['username'];
$email = $staff['email'] ?? '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    }

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($email === '') $errors[] = 'Email wajib diisi.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    elseif (mb_strlen($email) > 190) $errors[] = 'Email maksimal 190 karakter.';

    if ($name === '') $errors[] = 'Nama wajib diisi.';
    elseif (mb_strlen($name) > 100) $errors[] = 'Nama maksimal 100 karakter.';

    if ($username === '') $errors[] = 'Username wajib diisi.';
    elseif (mb_strlen($username) > 50) $errors[] = 'Username maksimal 50 karakter.';
    elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) $errors[] = 'Username hanya boleh menggunakan huruf, angka, titik, underscore, dan strip.';

    if (!$errors) {
        $check = $pdo->prepare("
            SELECT id
            FROM users
            WHERE (username = :username OR email = :email)
              AND id <> :current_user_id
            LIMIT 1
        ");
        $check->execute([
            ':username' => $username,
            ':email' => $email,
            ':current_user_id' => $staffId
        ]);
        if ($check->fetch()) $errors[] = 'Username tersebut sudah digunakan akun lain.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE users
                SET name = :name,
                    username = :username,
                    email = :email,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :staff_user_id
            ");
            $stmt->execute([
                ':name' => $name,
                ':username' => $username,
                ':email' => $email,
                ':staff_user_id' => $staffId
            ]);

            $audit = $pdo->prepare("
                INSERT INTO audit_logs
                    (store_id, user_id, action, table_name, record_id, description, created_at)
                VALUES
                    (:audit_store_id, :audit_user_id, 'UPDATE', 'users', :record_id, :description, CURRENT_TIMESTAMP)
            ");
            $audit->execute([
                ':audit_store_id' => $storeId,
                ':audit_user_id' => $ownerId,
                ':record_id' => $staffId,
                ':description' => 'Mengubah akun Staff di Store: ' . $name . ' (' . $username . ')'
            ]);

            $pdo->commit();
            $_SESSION['flash_success'] = 'Data Staff berhasil diperbarui.';
            header('Location: /pages/pengaturan/staff/');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Data Staff gagal diperbarui. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Edit Staff';
require_once '../../../includes/header.php';
require_once '../../../includes/sidebar.php';



?>
<main class="main-content">
    <div class="p-4 md:p-8">

        <div class="mb-7">
            <div class="flex items-center gap-2 text-xs text-neutral-400 mb-2">
                <a href="/pages/pengaturan/" class="hover:text-neutral-700">PENGATURAN</a>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <a href="/pages/pengaturan/staff/" class="hover:text-neutral-700">STAFF</a>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <span>EDIT</span>
            </div>

            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                Edit Staff
            </h1>

            <p class="text-sm text-neutral-500 mt-2">
                Perbarui informasi dasar akun Staff.
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center flex-none">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                    </div>

                    <div>
                        <div class="font-medium text-red-800">Data belum bisa disimpan</div>
                        <ul class="mt-1 text-sm text-red-700 space-y-1">
                            <?php foreach ($errors as $error): ?>
                                <li>• <?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="max-w-3xl">
            <form method="POST" class="bento-card p-5 md:p-7">

                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="flex items-start justify-between gap-4 pb-6 border-b border-neutral-100">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-neutral-100 flex items-center justify-center flex-none">
                            <i data-lucide="user-round-cog" class="w-6 h-6 text-neutral-700"></i>
                        </div>

                        <div>
                            <h2 class="font-semibold">Informasi Staff</h2>
                            <p class="text-xs text-neutral-400 mt-1">
                                ID akun #<?= (int) $staff['id'] ?>
                            </p>
                        </div>
                    </div>

                    <?php if ($staff['status'] === 'ACTIVE'): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-50 text-green-700 text-xs font-medium flex-none">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                            Aktif
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-neutral-100 text-neutral-500 text-xs font-medium flex-none">
                            <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>
                            Nonaktif
                        </span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-6">

                    <div>
                        <label for="name" class="block text-sm font-medium text-neutral-700 mb-2">
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
                            class="w-full px-4 py-3 rounded-xl border border-neutral-200 bg-white text-sm outline-none focus:border-neutral-500 focus:ring-2 focus:ring-neutral-100"
                        >
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-neutral-700 mb-2">
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
                            class="w-full px-4 py-3 rounded-xl border border-neutral-200 bg-white text-sm outline-none focus:border-neutral-500 focus:ring-2 focus:ring-neutral-100"
                        >

                        <p class="text-xs text-neutral-400 mt-2">
                            Huruf, angka, titik, underscore, atau strip.
                        </p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-neutral-700 mb-2">Email Recovery</label>
                        <input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="190" required autocomplete="email"
                            class="w-full px-4 py-3 rounded-xl border border-neutral-200 bg-white text-sm outline-none focus:border-neutral-500 focus:ring-2 focus:ring-neutral-100" placeholder="nama@email.com">
                    </div>

                </div>

                <div class="mt-6 rounded-2xl bg-neutral-50 border border-neutral-100 p-4">
                    <div class="flex items-start gap-3">
                        <i data-lucide="info" class="w-4 h-4 text-neutral-500 mt-0.5 flex-none"></i>
                        <div class="text-xs text-neutral-500 leading-relaxed">
                            Perubahan password dilakukan melalui pengaturan akun Staff.
                            Role Staff dan status akun tidak diubah dari halaman ini.
                        </div>
                    </div>
                </div>

                <div class="mt-7 pt-5 border-t border-neutral-100 flex flex-col-reverse sm:flex-row sm:justify-between gap-2">

                    <div class="flex flex-col sm:flex-row gap-2">
                        <a href="/pages/pengaturan/staff/reset-password.php?id=<?= (int) $staffId ?>" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border border-neutral-200 text-sm font-medium hover:bg-neutral-50">
                            <i data-lucide="key-round" class="w-4 h-4"></i>
                            Reset Password
                        </a>
                        <a
                        href="/pages/pengaturan/staff/"
                        class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border border-neutral-200 text-sm font-medium hover:bg-neutral-50"
                    >
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Kembali
                    </a>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
                    >
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Simpan Perubahan
                    </button>

                </div>

            </form>
        </div>

    </div>
</main>

<?php require_once '../../../includes/footer.php'; ?>
