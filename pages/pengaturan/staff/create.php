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

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

$name = '';
$username = '';
$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    }

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '') $errors[] = 'Nama wajib diisi.';
    elseif (mb_strlen($name) > 100) $errors[] = 'Nama maksimal 100 karakter.';

    if ($email === '') $errors[] = 'Email wajib diisi.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    elseif (mb_strlen($email) > 190) $errors[] = 'Email maksimal 190 karakter.';

    if ($username === '') $errors[] = 'Username wajib diisi.';
    elseif (mb_strlen($username) > 50) $errors[] = 'Username maksimal 50 karakter.';
    elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) $errors[] = 'Username hanya boleh menggunakan huruf, angka, titik, underscore, dan strip.';

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirm'] ?? '');
    if ($password === '') $errors[] = 'Password wajib diisi.';
    elseif (strlen($password) < 8) $errors[] = 'Password minimal 8 karakter.';
    if ($password !== $passwordConfirmation) $errors[] = 'Konfirmasi password tidak cocok.';

    if (!$errors) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
        $check->execute([':username' => $username, ':email' => $email]);
        if ($check->fetch()) $errors[] = 'Username atau email tersebut sudah digunakan akun lain.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if (!$passwordHash) throw new RuntimeException('Password hash gagal dibuat.');

            $stmt = $pdo->prepare("
                INSERT INTO users (name, username, email, password, role, status, created_at, updated_at)
                VALUES (:name, :username, :email, :password, 'STAFF', 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                ':name' => $name,
                ':username' => $username,
                ':email' => $email,
                ':password' => $passwordHash
            ]);
            $newStaffId = (int) $pdo->lastInsertId();

            $membership = $pdo->prepare("
                INSERT INTO store_users (store_id, user_id, role, status, created_at, updated_at)
                VALUES (:store_id, :staff_user_id, 'STAFF', 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $membership->execute([
                ':store_id' => $storeId,
                ':staff_user_id' => $newStaffId
            ]);

            $audit = $pdo->prepare("
                INSERT INTO audit_logs
                    (store_id, user_id, action, table_name, record_id, description, created_at)
                VALUES
                    (:audit_store_id, :audit_user_id, 'CREATE', 'users', :record_id, :description, CURRENT_TIMESTAMP)
            ");
            $audit->execute([
                ':audit_store_id' => $storeId,
                ':audit_user_id' => $ownerId,
                ':record_id' => $newStaffId,
                ':description' => 'Menambahkan akun Staff ke Store: ' . $name . ' (' . $username . ')'
            ]);

            $pdo->commit();
            $_SESSION['flash_success'] = 'Staff "' . $name . '" berhasil ditambahkan.';
            header('Location: /pages/pengaturan/staff/');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Staff gagal ditambahkan. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Tambah Staff';
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
                <span>TAMBAH</span>
            </div>

            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                Tambah Staff
            </h1>

            <p class="text-sm text-neutral-500 mt-2">
                Buat akun Staff baru untuk akses operasional RESTOCK.
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

                <div class="flex items-start gap-4 pb-6 border-b border-neutral-100">
                    <div class="w-12 h-12 rounded-2xl bg-neutral-100 flex items-center justify-center flex-none">
                        <i data-lucide="user-plus" class="w-6 h-6 text-neutral-700"></i>
                    </div>

                    <div>
                        <h2 class="font-semibold">Informasi Staff</h2>
                        <p class="text-xs text-neutral-400 mt-1">
                            Akun baru akan dibuat sebagai Staff dan langsung aktif.
                        </p>
                    </div>
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
                            placeholder="Nama staff"
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
                            placeholder="username"
                        >

                        <p class="text-xs text-neutral-400 mt-2">
                            Huruf, angka, titik, underscore, atau strip.
                        </p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-neutral-700 mb-2">Email Recovery</label>
                        <input type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="190" required autocomplete="email"
                            class="w-full px-4 py-3 rounded-xl border border-neutral-200 bg-white text-sm outline-none focus:border-neutral-500 focus:ring-2 focus:ring-neutral-100" placeholder="nama@email.com">
                        <p class="text-xs text-neutral-400 mt-2">Dipakai jika Staff perlu memulihkan password.</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-neutral-700 mb-2">
                            Password
                        </label>

                        <div class="relative">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                minlength="8"
                                required
                                autocomplete="new-password"
                                class="w-full px-4 py-3 pr-12 rounded-xl border border-neutral-200 bg-white text-sm outline-none focus:border-neutral-500 focus:ring-2 focus:ring-neutral-100"
                                placeholder="Minimal 8 karakter"
                            >

                            <button
                                type="button"
                                onclick="togglePassword('password', this)"
                                class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg flex items-center justify-center text-neutral-400 hover:text-neutral-700"
                                aria-label="Tampilkan password"
                            >
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="password_confirm" class="block text-sm font-medium text-neutral-700 mb-2">
                            Konfirmasi Password
                        </label>

                        <div class="relative">
                            <input
                                type="password"
                                id="password_confirm"
                                name="password_confirm"
                                minlength="8"
                                required
                                autocomplete="new-password"
                                class="w-full px-4 py-3 pr-12 rounded-xl border border-neutral-200 bg-white text-sm outline-none focus:border-neutral-500 focus:ring-2 focus:ring-neutral-100"
                                placeholder="Ulangi password"
                            >

                            <button
                                type="button"
                                onclick="togglePassword('password_confirm', this)"
                                class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg flex items-center justify-center text-neutral-400 hover:text-neutral-700"
                                aria-label="Tampilkan password"
                            >
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                </div>

                <div class="mt-6 rounded-2xl bg-neutral-50 border border-neutral-100 p-4">
                    <div class="flex items-start gap-3">
                        <i data-lucide="shield-check" class="w-4 h-4 text-neutral-500 mt-0.5 flex-none"></i>
                        <div class="text-xs text-neutral-500 leading-relaxed">
                            Akun ini akan memiliki role <strong class="text-neutral-700">Staff</strong>.
                            Staff tidak memiliki akses ke menu Kelola Staff.
                        </div>
                    </div>
                </div>

                <div class="mt-7 pt-5 border-t border-neutral-100 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <a
                        href="/pages/pengaturan/staff/"
                        class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border border-neutral-200 text-sm font-medium hover:bg-neutral-50"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
                    >
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                        Tambah Staff
                    </button>
                </div>

            </form>
        </div>

    </div>
</main>

<script>
function togglePassword(id, button) {
    const input = document.getElementById(id);
    if (!input) return;

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    const icon = button.querySelector('i');
    if (icon) {
        icon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>
