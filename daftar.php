<?php
/*
 * RESTOCK - Account Registration & First Store Setup
 */
const RESTOCK_SESSION_LIFETIME = 86400;

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string) RESTOCK_SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => RESTOCK_SESSION_LIFETIME,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/config/database.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function makeStoreSlug(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?? '';
    return strtolower(trim($value, '-'));
}

if (!empty($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$name = '';
$username = '';
$email = '';
$storeName = '';
$slug = '';
$packageId = null;
$selectedPackage = null;
$errors = [];

$rawPackageId = filter_input(INPUT_GET, 'package_id', FILTER_VALIDATE_INT);
if ($rawPackageId !== null && $rawPackageId !== false) {
    $packageId = (int) $rawPackageId;
}

function activeStoreCountForAccount(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM stores
         WHERE account_id = :account_id
           AND status = 'ACTIVE'"
    );
    $stmt->execute([':account_id' => $accountId]);

    return (int) $stmt->fetchColumn();
}

function findActivePackage(PDO $pdo, ?int $packageId): ?array
{
    if ($packageId === null || $packageId <= 0) {
        return null;
    }

    $packageStmt = $pdo->prepare(
        "SELECT id, name, price, duration_days, min_store_count, max_store_count FROM packages
         WHERE id = :package_id AND status = 'ACTIVE' LIMIT 1"
    );
    $packageStmt->execute([':package_id' => $packageId]);
    $package = $packageStmt->fetch();

    return $package ?: null;
}

if ($packageId !== null) {
    $selectedPackage = findActivePackage($pdo, $packageId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $password_confirm = (string) ($_POST['password_confirm'] ?? '');
    $storeName = trim((string) ($_POST['store_name'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? ''));
    if ($_POST['package_id'] ?? null) {
        $postedPackageId = filter_var($_POST['package_id'], FILTER_VALIDATE_INT);
        if ($postedPackageId !== false && $postedPackageId !== null) {
            $packageId = (int) $postedPackageId;
        }
    }

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

    if ($email === '') {
        $errors[] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    } elseif (mb_strlen($email) > 190) {
        $errors[] = 'Email maksimal 190 karakter.';
    }

    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }

    if ($password !== $password_confirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    if ($storeName === '') {
        $errors[] = 'Nama toko wajib diisi.';
    } elseif (mb_strlen($storeName) > 150) {
        $errors[] = 'Nama toko maksimal 150 karakter.';
    }

    if ($slug === '') {
        $slug = makeStoreSlug($storeName);
    } else {
        $slug = strtolower($slug);
    }

    if ($slug === '') {
        $errors[] = 'Nama toko tidak dapat digunakan untuk membuat slug. Isi slug secara manual.';
    } elseif (mb_strlen($slug) > 180) {
        $errors[] = 'Slug toko maksimal 180 karakter.';
    } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $errors[] = 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.';
    }

    if ($packageId === null || $packageId <= 0) {
        $errors[] = 'Pilih paket terlebih dahulu sebelum membuat akun.';
    } else {
        $selectedPackage = findActivePackage($pdo, $packageId);
        if (!$selectedPackage) {
            $errors[] = 'Paket yang dipilih tidak tersedia atau sudah tidak aktif. Silakan pilih paket lain.';
        }
    }

    if (!$errors) {
        $checkUser = $pdo->prepare(
            'SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1'
        );
        $checkUser->execute([
            ':username' => $username,
            ':email' => $email,
        ]);
        if ($checkUser->fetch()) {
            $errors[] = 'Username atau email tersebut sudah digunakan.';
        }

        $checkSlug = $pdo->prepare(
            'SELECT id FROM stores WHERE slug = :store_slug LIMIT 1'
        );
        $checkSlug->execute([':store_slug' => $slug]);
        if ($checkSlug->fetch()) {
            $errors[] = 'Slug toko tersebut sudah digunakan. Gunakan nama/slug lain.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $accountStmt = $pdo->prepare(
                "INSERT INTO accounts (name, status, created_at, updated_at)
                 VALUES (:account_name, 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $accountStmt->execute([':account_name' => $name]);
            $accountId = (int) $pdo->lastInsertId();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                throw new RuntimeException('Password hash gagal dibuat.');
            }

            $userStmt = $pdo->prepare(
                "INSERT INTO users (name, username, email, password, role, status, created_at, updated_at)
                 VALUES (:user_name, :username, :email, :password_hash, 'ADMIN', 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $userStmt->execute([
                ':user_name' => $name,
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $passwordHash,
            ]);
            $userId = (int) $pdo->lastInsertId();

            $storeStmt = $pdo->prepare(
                "INSERT INTO stores (account_id, name, slug, status, created_at, updated_at)
                 VALUES (:account_id, :store_name, :store_slug, 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $storeStmt->execute([
                ':account_id' => $accountId,
                ':store_name' => $storeName,
                ':store_slug' => $slug,
            ]);
            $storeId = (int) $pdo->lastInsertId();

            $membershipStmt = $pdo->prepare(
                "INSERT INTO store_users (store_id, user_id, role, status, created_at, updated_at)
                 VALUES (:store_id, :user_id, 'ADMIN', 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $membershipStmt->execute([
                ':store_id' => $storeId,
                ':user_id' => $userId,
            ]);

            $pdo->commit();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'ADMIN';
            $_SESSION['account_id'] = $accountId;
            $_SESSION['store_id'] = $storeId;
            $_SESSION['store_name'] = $storeName;
            $_SESSION['store_slug'] = $slug;
            $_SESSION['selected_package_id'] = $packageId;
            $_SESSION['login_at'] = time();

            header('Location: /checkout.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                $errors[] = 'Data sudah digunakan akun lain. Periksa username, email, atau slug toko.';
            } else {
                $errors[] = 'Registrasi gagal. Silakan coba lagi.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Registrasi gagal. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f5f5">
    <title>Daftar · RE-STOCK</title>
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; background:#f5f5f5; color:#171717; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; -webkit-font-smoothing:antialiased; }
        .register-shell { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; background:radial-gradient(circle at 10% 10%,rgba(255,255,255,.95),transparent 32%),radial-gradient(circle at 90% 90%,rgba(229,229,229,.8),transparent 35%),#f5f5f5; }
        .register-card { width:100%; max-width:760px; background:rgba(255,255,255,.94); border:1px solid #e5e5e5; border-radius:28px; box-shadow:0 24px 70px rgba(0,0,0,.08); overflow:hidden; }
        .register-head { padding:30px 30px 22px; border-bottom:1px solid #f0f0f0; }
        .register-logo { width:46px; height:46px; border-radius:15px; background:#fafafa; border:1px solid #e5e5e5; display:flex; align-items:center; justify-content:center; overflow:hidden; }
        .register-logo img { width:30px; height:30px; object-fit:contain; }
        .register-body { padding:28px 30px 30px; }
        .section-title { font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#737373; }
        .field-label { display:block; font-size:13px; font-weight:600; color:#404040; margin-bottom:8px; }
        .register-input { width:100%; padding:12px 14px; border:1px solid #e5e5e5; border-radius:13px; outline:none; background:#fff; font-size:14px; transition:.15s; }
        .register-input:focus { border-color:#a3a3a3; box-shadow:0 0 0 4px rgba(0,0,0,.04); }
        .register-button { width:100%; border:0; border-radius:14px; padding:13px 16px; background:#171717; color:#fff; font-size:14px; font-weight:600; cursor:pointer; transition:.15s; }
        .register-button:hover { background:#262626; }
        .error-box { border:1px solid #fecaca; background:#fef2f2; color:#b91c1c; border-radius:14px; padding:12px 14px; font-size:13px; }
        @media(max-width:640px){ .register-shell{padding:12px}.register-card{border-radius:22px}.register-head,.register-body{padding:22px 18px}.grid-gap{gap:14px!important} }
    </style>
</head>
<body>
<div class="register-shell">
    <div class="register-card">
        <div class="register-head flex items-center gap-4">
            <div class="register-logo"><img src="/assets/images/logo.png" alt="RESTOCK"></div>
            <div class="min-w-0">
                <h1 class="text-xl md:text-2xl font-bold tracking-tight">Buat akunmu!</h1>
                <p class="text-sm text-neutral-500 mt-1">Daftar sekali, langsung siap mengelola toko.</p>
            </div>
        </div>

        <div class="register-body">
            <?php if ($errors): ?>
                <div class="error-box mb-6">
                    <ul class="space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li>• <?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" id="registerForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="package_id" value="<?= e($packageId) ?>">

                <?php if ($selectedPackage || $packageId !== null): ?>
                    <div class="mb-6 rounded-2xl border border-neutral-200 bg-neutral-50 px-4 py-3">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-neutral-400">Paket dipilih</p>
                                <?php if ($selectedPackage): ?>
                                    <p class="mt-1 truncate text-sm font-semibold text-neutral-900"><?= e($selectedPackage['name']) ?></p>
                                <?php else: ?>
                                    <p class="mt-1 text-sm font-semibold text-neutral-900">Paket akan diverifikasi</p>
                                <?php endif; ?>
                            </div>
                            <?php if ($selectedPackage): ?>
                                <span class="shrink-0 text-sm font-semibold text-neutral-900">Rp <?= number_format((float) $selectedPackage['price'], 0, ',', '.') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="section-title">Akun</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 grid-gap mt-4">
                    <div>
                        <label class="field-label" for="name">Nama</label>
                        <input class="register-input" id="name" name="name" value="<?= e($name) ?>" maxlength="100" autocomplete="name" required placeholder="Nama kamu">
                    </div>
                    <div>
                        <label class="field-label" for="username">Username</label>
                        <input class="register-input" id="username" name="username" value="<?= e($username) ?>" maxlength="50" autocomplete="username" required placeholder="contoh: han">
                    </div>
                    <div class="md:col-span-2">
                        <label class="field-label" for="email">Email</label>
                        <input class="register-input" type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="190" autocomplete="email" required placeholder="nama@email.com">
                    </div>
                    <div>
                        <label class="field-label" for="password">Password</label>
                        <input class="register-input" type="password" id="password" name="password" minlength="8" autocomplete="new-password" required placeholder="Minimal 8 karakter">
                    </div>
                    <div>
                        <label class="field-label" for="password_confirm">Konfirmasi Password</label>
                        <input class="register-input" type="password" id="password_confirm" name="password_confirm" minlength="8" autocomplete="new-password" required placeholder="Ulangi password">
                    </div>
                </div>

                <div class="section-title mt-8">Toko Pertama</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 grid-gap mt-4">
                    <div>
                        <label class="field-label" for="store_name">Nama Toko</label>
                        <input class="register-input" id="store_name" name="store_name" value="<?= e($storeName) ?>" maxlength="150" required placeholder="Nama toko kamu">
                    </div>
                    <div>
                        <label class="field-label" for="slug">Slug Toko <span class="font-normal text-neutral-400">(opsional)</span></label>
                        <input class="register-input" id="slug" name="slug" value="<?= e($slug) ?>" maxlength="180" placeholder="nama-toko">
                        <p class="text-xs text-neutral-400 mt-2">Kosongkan untuk dibuat otomatis dari nama toko.</p>
                    </div>
                </div>

                <div class="mt-8">
                    <button class="register-button" type="submit" id="registerButton">Buat Akun &amp; Toko</button>
                </div>
            </form>

            <div class="text-center text-sm text-neutral-500 mt-5">
                Sudah punya akun?
                <a href="/login.php" class="font-semibold text-neutral-900 hover:underline">Masuk</a>
            </div>
        </div>
    </div>
</div>
<script>
const form = document.getElementById('registerForm');
const button = document.getElementById('registerButton');
if (form && button) {
    form.addEventListener('submit', function () {
        button.disabled = true;
        button.textContent = 'Membuat akun...';
    });
}
const storeName = document.getElementById('store_name');
const slug = document.getElementById('slug');
let slugTouched = <?= $slug !== '' && isset($_POST['slug']) ? 'true' : 'false' ?>;
if (slug) slug.addEventListener('input', () => { slugTouched = true; });
if (storeName && slug) storeName.addEventListener('input', function () {
    if (slugTouched) return;
    slug.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
});
</script>
</body>
</html>
