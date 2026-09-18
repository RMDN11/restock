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
$freeRegistrationUrl = '/daftar.php?free=1';
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
                <h1 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight">Pilih paket untuk mulai</h1>
                <p class="mt-3 text-sm text-neutral-500 max-w-lg mx-auto">
                    Mulai dari gratis atau pilih paket sesuai jumlah toko. Setelah memilih, kamu langsung lanjut ke pengaturan akun dan toko.
                </p>
            </div>

            <?php if (!$packages): ?>
                <div class="rounded-3xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                    <p class="font-semibold">Belum ada paket yang tersedia.</p>
                    <p class="mt-2 text-sm text-neutral-500">Silakan kembali lagi setelah paket diaktifkan.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4 items-stretch"><article class="group rounded-[28px] border border-emerald-200 bg-gradient-to-b from-emerald-50 to-white p-6 shadow-sm flex flex-col min-h-[430px] transition duration-200 hover:-translate-y-1 hover:shadow-lg">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[.12em] text-emerald-700">Gratis</p>
        <h2 class="mt-2 text-xl font-bold tracking-tight">Free</h2>
        <p class="mt-2 text-sm leading-6 text-neutral-600">Untuk 1 owner dengan 1 toko.</p>
    </div>
    <div class="mt-6">
        <div class="text-3xl font-bold tracking-tight text-emerald-700">Rp 0</div>
        <div class="mt-1 text-xs text-neutral-500">Gratis</div>
    </div>
    <div class="mt-5 rounded-2xl bg-white/80 border border-emerald-100 px-4 py-3 text-sm min-h-[72px]">
        <div class="font-semibold">1 owner · 1 toko</div>
        <div class="mt-1 text-neutral-500">Cocok untuk mulai menggunakan RESTOCK.</div>
    </div>
    <a href="<?= e($freeRegistrationUrl) ?>" class="mt-auto pt-6 w-full"><span class="w-full h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm font-semibold hover:bg-emerald-700 transition shadow-sm">
        Mulai Gratis
    </span></a>
</article>


                    <?php foreach ($packages as $package): ?>
                        <article class="group rounded-[28px] border border-neutral-200 bg-white p-6 shadow-sm flex flex-col min-h-[430px] transition duration-200 hover:-translate-y-1 hover:border-neutral-300 hover:shadow-lg">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[.12em] text-neutral-400">Paket</p>
                                <h2 class="mt-2 text-xl font-bold tracking-tight"><?= e($package['name']) ?></h2>
                                <?php if (!empty($package['description'])): ?>
                                    <p class="mt-2 text-sm leading-6 text-neutral-500"><?= e($package['description']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="mt-6">
                                <div class="text-3xl font-bold tracking-tight"><?= rupiah($package['price']) ?></div>
                                <div class="mt-1 text-xs text-neutral-400">
                                    <?= (int) $package['duration_days'] ?> hari
                                </div>
                            </div>

                            <?php
                            $minStores = $package['min_store_count'] !== null ? (int) $package['min_store_count'] : null;
                            $maxStores = $package['max_store_count'] !== null ? (int) $package['max_store_count'] : null;
                            ?>
                            <?php if ($minStores !== null || $maxStores !== null): ?>
                                <div class="mt-5 rounded-2xl bg-neutral-50 border border-neutral-100 px-4 py-3 text-sm min-h-[72px]">
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
                               class="mt-auto w-full h-12 rounded-xl bg-neutral-900 text-white flex items-center justify-center text-sm font-semibold hover:bg-neutral-800 transition shadow-sm">
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
