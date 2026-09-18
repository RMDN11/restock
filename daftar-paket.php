<?php
/*
 * RESTOCK - Public Package Selection
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

require_once __DIR__ . '/config/database.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$packages = [];

$stmt = $pdo->query(
    "SELECT id, name, description, price, duration_days, min_store_count, max_store_count
     FROM packages
     WHERE status = 'ACTIVE'
     ORDER BY price ASC, id ASC"
);
$packages = $stmt->fetchAll();

$freeToken = trim((string) ($_GET['free_token'] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f5f5">
    <title>Pilih Paket · RE-STOCK</title>
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-900 antialiased">
    <main class="min-h-screen flex items-center justify-center p-5 py-10">
        <div class="w-full max-w-5xl">
            <div class="text-center mb-8">
                <div class="mx-auto mb-4 w-14 h-14 rounded-2xl overflow-hidden bg-neutral-900 shadow-lg">
                    <img src="/assets/images/logo.png" alt="RESTOCK" class="w-full h-full object-cover">
                </div>
                <p class="text-[11px] font-bold tracking-[.16em] text-neutral-400">RE-STOCK</p>
                <h1 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight">Pilih paket</h1>
                <p class="mt-3 text-sm text-neutral-500 max-w-lg mx-auto">
                    Pilih paket terlebih dahulu, lalu lanjut isi data akun dan toko. Jadi tidak ada lagi form pendaftaran yang tiba-tiba bertanya paketnya setelah semuanya diisi.
                </p>
            </div>

            <?php if (!$packages): ?>
                <div class="rounded-3xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                    <p class="font-semibold">Belum ada paket yang tersedia.</p>
                    <p class="mt-2 text-sm text-neutral-500">Silakan kembali lagi setelah paket diaktifkan.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($packages as $package): ?>
                        <article class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm flex flex-col">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[.12em] text-neutral-400">Paket</p>
                                <h2 class="mt-2 text-xl font-bold tracking-tight"><?= e($package['name']) ?></h2>
                                <?php if (!empty($package['description'])): ?>
                                    <p class="mt-2 text-sm leading-6 text-neutral-500"><?= e($package['description']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="mt-6">
                                <div class="text-2xl font-bold"><?= rupiah($package['price']) ?></div>
                                <div class="mt-1 text-xs text-neutral-400">
                                    <?= (int) $package['duration_days'] ?> hari
                                </div>
                            </div>

                            <?php
                            $minStores = $package['min_store_count'] !== null ? (int) $package['min_store_count'] : null;
                            $maxStores = $package['max_store_count'] !== null ? (int) $package['max_store_count'] : null;
                            ?>
                            <?php if ($minStores !== null || $maxStores !== null): ?>
                                <div class="mt-5 rounded-2xl bg-neutral-50 border border-neutral-100 px-4 py-3 text-sm">
                                    <div class="font-semibold">Cakupan toko</div>
                                    <div class="mt-1 text-neutral-500">
                                        <?php if ($maxStores === null): ?>
                                            <?= $minStores ?>+ toko
                                        <?php elseif ($minStores === $maxStores): ?>
                                            <?= $minStores ?> toko
                                        <?php else: ?>
                                            <?= $minStores ?>–<?= $maxStores ?> toko
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <a href="/daftar.php?package_id=<?= (int) $package['id'] ?>"
                               class="mt-6 w-full h-11 rounded-xl bg-neutral-900 text-white flex items-center justify-center text-sm font-semibold hover:bg-neutral-800 transition">
                                Pilih paket
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mt-6 text-center space-y-3">
                <p class="text-sm text-neutral-500">
                    Punya undangan Free Plan?
                    <a href="/daftar-pilih.php<?= $freeToken !== '' ? '?free_token=' . rawurlencode($freeToken) : '' ?>"
                       class="font-semibold text-neutral-900 hover:underline">
                        Gunakan undangan
                    </a>
                </p>
                <a href="/daftar-pilih.php" class="inline-block text-sm font-medium text-neutral-400 hover:text-neutral-900">
                    ← Kembali ke pilihan pendaftaran
                </a>
            </div>
        </div>
    </main>
</body>
</html>
