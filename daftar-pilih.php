<?php
/*
 * RESTOCK - Registration Flow Selection
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

if (!empty($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

$freeToken = trim((string) ($_GET['free_token'] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f5f5">
    <title>Daftar · RE-STOCK</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-900 antialiased">
    <main class="min-h-screen flex items-center justify-center p-5">
        <div class="w-full max-w-2xl">
            <div class="text-center mb-8">
                <div class="mx-auto mb-4 w-14 h-14 rounded-2xl overflow-hidden bg-neutral-900 shadow-lg">
                    <img src="/assets/images/logo.png" alt="RESTOCK" class="w-full h-full object-cover">
                </div>
                <p class="text-[11px] font-bold tracking-[.16em] text-neutral-400">RE-STOCK</p>
                <h1 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight">Mulai menggunakan RESTOCK</h1>
                <p class="mt-3 text-sm text-neutral-500 max-w-md mx-auto">
                    Pilih jalur pendaftaran yang sesuai. Biar alurnya jelas dulu, baru bikin akun. Manusia ternyata butuh tombol yang tidak mengejutkan.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <a href="/daftar-paket.php"
                   class="group rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm hover:-translate-y-1 hover:shadow-md transition">
                    <div class="w-11 h-11 rounded-2xl bg-neutral-900 text-white flex items-center justify-center">
                        <i data-lucide="user-plus" class="w-5 h-5"></i>
                    </div>
                    <h2 class="mt-5 text-lg font-semibold">Daftar akun baru</h2>
                    <p class="mt-2 text-sm leading-6 text-neutral-500">
                        Belum punya akun? Buat akun RESTOCK dan pilih paket yang tersedia.
                    </p>
                    <div class="mt-5 flex items-center gap-2 text-sm font-semibold">
                        Lanjut daftar
                        <i data-lucide="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1"></i>
                    </div>
                </a>

                <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <div class="w-11 h-11 rounded-2xl bg-neutral-100 text-neutral-900 flex items-center justify-center">
                        <i data-lucide="ticket" class="w-5 h-5"></i>
                    </div>
                    <h2 class="mt-5 text-lg font-semibold">Punya undangan Free Plan?</h2>
                    <p class="mt-2 text-sm leading-6 text-neutral-500">
                        Masukkan token dari link undangan. Setelah diaktifkan, login berikutnya tetap menggunakan username dan password biasa.
                    </p>

                    <form action="/free-plan.php" method="GET" class="mt-5">
                        <label for="token" class="sr-only">Token Free Plan</label>
                        <input
                            id="token"
                            name="token"
                            value="<?= htmlspecialchars($freeToken, ENT_QUOTES, 'UTF-8') ?>"
                            required
                            maxlength="64"
                            pattern="[A-Fa-f0-9]{64}"
                            placeholder="Tempel token 64 karakter"
                            class="w-full h-11 rounded-xl border border-neutral-200 bg-neutral-50 px-3 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-100"
                        >
                        <button type="submit"
                                class="mt-3 w-full h-11 rounded-xl bg-neutral-900 text-white text-sm font-semibold hover:bg-neutral-800 transition">
                            Buka Free Plan
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-6 text-center">
                <a href="/login.php" class="text-sm font-medium text-neutral-500 hover:text-neutral-900">
                    Sudah punya akun? Masuk
                </a>
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
