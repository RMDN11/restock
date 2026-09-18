<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/free_plan.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$invite = $token !== '' ? restockFreePlanFindInvite($pdo, $token) : null;

if (!$invite) {
    http_response_code(410);
    exit('Link Free Plan sudah tidak aktif, sudah habis, atau tidak ditemukan.');
}

if ((int) ($_SESSION['user_id'] ?? 0) > 0) {
    $accountId = (int) ($_SESSION['account_id'] ?? 0);

    if ($accountId <= 0) {
        http_response_code(403);
        exit('Session akun tidak valid. Silakan masuk kembali.');
    }

    try {
        $pdo->beginTransaction();
        $result = restockFreePlanRedeem($pdo, $token, $accountId);

        if (!$result['success']) {
            $pdo->rollBack();
            http_response_code(409);
            exit(e($result['message']));
        }

        $pdo->commit();
        unset($_SESSION['pending_free_plan_token']);
        header('Location: /?free_plan=activated');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(500);
        exit('Free Plan belum dapat diaktifkan. Silakan coba lagi.');
    }
}

$_SESSION['pending_free_plan_token'] = $token;

$registerUrl = '/daftar.php?free_token=' . rawurlencode($token);
$loginUrl = '/login.php';

$benefitName = $invite['package_name'] ?: 'Free Plan Lifetime';
$benefitDuration = $invite['package_name']
    ? number_format((int) $invite['duration_days']) . ' hari'
    : 'Tanpa batas waktu';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f5f5">
    <title>Free Plan · RE-STOCK</title>
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; background:#f5f5f5; color:#171717; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; -webkit-font-smoothing:antialiased; }
        .shell { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; background:radial-gradient(circle at 10% 10%,rgba(255,255,255,.95),transparent 32%),radial-gradient(circle at 90% 90%,rgba(229,229,229,.8),transparent 35%),#f5f5f5; }
        .card { width:100%; max-width:520px; background:rgba(255,255,255,.94); border:1px solid #e5e5e5; border-radius:28px; box-shadow:0 24px 70px rgba(0,0,0,.08); padding:30px; }
        @media(max-width:640px){.shell{padding:12px}.card{border-radius:22px;padding:22px}}
    </style>
</head>
<body>
<div class="shell">
    <main class="card">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-neutral-50 border border-neutral-200 flex items-center justify-center overflow-hidden">
                <img src="/assets/images/logo.png" alt="RESTOCK" class="w-7 h-7 object-contain">
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-neutral-400">RESTOCK</p>
                <p class="text-sm font-semibold mt-0.5">Free Plan</p>
            </div>
        </div>

        <div class="mt-8">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center">
                <i data-lucide="gift" class="w-6 h-6 text-emerald-700"></i>
            </div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight mt-5">Akses Free Plan untukmu</h1>
            <p class="text-sm text-neutral-500 leading-6 mt-3">Undangan ini akan mengaktifkan Free Plan di account-mu. Setelah aktif, kamu cukup login seperti biasa tanpa menggunakan link ini lagi.</p>
        </div>

        <div class="mt-6 rounded-2xl border border-neutral-200 bg-neutral-50 p-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs text-neutral-400">Benefit</p>
                    <p class="text-sm font-semibold mt-1"><?= e($benefitName) ?></p>
                </div>
                <span class="text-xs font-medium text-neutral-600"><?= e($benefitDuration) ?></span>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="<?= e($loginUrl) ?>" class="inline-flex items-center justify-center min-h-11 rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white hover:bg-neutral-800">
                Saya Sudah Punya Akun
            </a>
            <a href="<?= e($registerUrl) ?>" class="inline-flex items-center justify-center min-h-11 rounded-xl border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-800 hover:bg-neutral-50">
                Buat Akun Baru
            </a>
        </div>

        <p class="text-[11px] text-neutral-400 text-center leading-5 mt-5">Link ini hanya digunakan untuk mengaktifkan benefit. Login berikutnya tetap menggunakan username dan password biasa.</p>
    </main>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script>document.addEventListener('DOMContentLoaded',()=>{if(typeof lucide!=='undefined')lucide.createIcons();});</script>
</body>
</html>
