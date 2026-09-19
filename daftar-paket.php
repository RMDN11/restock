<?php
declare(strict_types=1);

/*
 * RESTOCK - Public Package Selection
 * Canonical route: /daftar-paket.php
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

// Package selection remains accessible after registration so the checkout flow
// does not unexpectedly send a newly registered user to the application dashboard.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/package_pricing.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$packagesStmt = $pdo->query(
    "SELECT id, name, slug, price, duration_days, min_store_count, max_store_count, description
     FROM packages
     WHERE status = 'ACTIVE'
       AND EXISTS (
           SELECT 1 FROM package_price_tiers t
           WHERE t.package_id = packages.id
             AND t.status = 'ACTIVE'
       )
     ORDER BY price ASC, id ASC"
);
$packages = $packagesStmt->fetchAll();

foreach ($packages as &$package) {
    $package['tiers'] = restockGetPackageTiers($pdo, (int) $package['id']);
}
unset($package);

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
        <div class="w-full max-w-6xl">
            <div class="text-center mb-8">
                <div class="mx-auto mb-4 w-14 h-14 rounded-2xl overflow-hidden bg-neutral-900 shadow-lg">
                    <img src="/assets/images/logo.png" alt="RESTOCK" class="w-full h-full object-cover">
                </div>
                <p class="text-[11px] font-bold tracking-[.16em] text-neutral-400">RE-STOCK</p>
                <h1 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight">Pilih paket untuk mulai</h1>
                <p class="mt-3 text-sm text-neutral-500 max-w-2xl mx-auto">
                    Pilih jumlah toko yang ingin dicakup. Harga mengikuti tier pricing yang aktif, lalu dikunci kembali saat checkout.
                </p>
            </div>

            <?php if (!$packages): ?>
                <div class="rounded-3xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                    <p class="font-semibold">Belum ada paket yang tersedia.</p>
                    <p class="mt-2 text-sm text-neutral-500">Silakan kembali lagi setelah paket diaktifkan.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4 items-stretch">
                    <article class="group rounded-[28px] border border-emerald-200 bg-gradient-to-b from-emerald-50 to-white p-6 shadow-sm flex flex-col min-h-[470px] transition duration-200 hover:-translate-y-1 hover:shadow-lg">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[.12em] text-emerald-700">Gratis</p>
                            <h2 class="mt-2 text-xl font-bold tracking-tight">Free</h2>
                            <p class="mt-2 text-sm leading-6 text-neutral-600">Untuk mulai menggunakan RESTOCK tanpa pembayaran.</p>
                        </div>
                        <div class="mt-6">
                            <div class="text-3xl font-bold tracking-tight text-emerald-700">Rp 0</div>
                            <div class="mt-1 text-xs text-neutral-500">1 owner · 1 toko</div>
                        </div>
                        <div class="mt-5 rounded-2xl bg-white/80 border border-emerald-100 px-4 py-3 text-sm min-h-[72px]">
                            <div class="font-semibold">1 toko</div>
                            <div class="mt-1 text-neutral-500">Tidak ada pembayaran atau checkout.</div>
                        </div>
                        <a href="/daftar.php?free=1" class="mt-auto pt-6 w-full">
                            <span class="w-full h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm font-semibold hover:bg-emerald-700 transition shadow-sm">
                                Mulai Gratis
                            </span>
                        </a>
                    </article>

                    <?php foreach ($packages as $package): ?>
                        <?php
                        $tiers = $package['tiers'];
                        $defaultStoreCount = !empty($tiers)
                            ? max(1, (int) $tiers[0]['min_store_count'])
                            : max(1, (int) ($package['min_store_count'] ?? 1));
                        $minStore = !empty($tiers)
                            ? (int) $tiers[0]['min_store_count']
                            : (int) ($package['min_store_count'] ?? 1);
                        $lastTier = !empty($tiers) ? $tiers[count($tiers) - 1] : null;
                        $maxStore = $lastTier && $lastTier['max_store_count'] !== null
                            ? (int) $lastTier['max_store_count']
                            : ($package['max_store_count'] !== null ? (int) $package['max_store_count'] : null);
                        $startingPrice = restockGetPackageStartingPrice($package);
                        $tierData = array_map(
                            static fn (array $tier): array => [
                                'id' => (int) $tier['id'],
                                'min' => (int) $tier['min_store_count'],
                                'max' => $tier['max_store_count'] !== null ? (int) $tier['max_store_count'] : null,
                                'price' => (float) $tier['price'],
                            ],
                            $tiers
                        );
                        ?>
                        <article class="package-card group rounded-[28px] border border-neutral-200 bg-white p-6 shadow-sm flex flex-col min-h-[470px] transition duration-200 hover:-translate-y-1 hover:border-neutral-300 hover:shadow-lg"
                                 data-tiers="<?= e(json_encode($tierData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[.12em] text-neutral-400">Paket</p>
                                <h2 class="mt-2 text-xl font-bold tracking-tight"><?= e($package['name']) ?></h2>
                                <?php if (!empty($package['description'])): ?>
                                    <p class="mt-2 text-sm leading-6 text-neutral-500"><?= e($package['description']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="mt-6">
                                <p class="text-xs text-neutral-400">Mulai dari</p>
                                <div class="package-price mt-1 text-3xl font-bold tracking-tight"><?= rupiah($startingPrice) ?></div>
                                <div class="mt-1 text-xs text-neutral-400"><?= (int) $package['duration_days'] ?> hari</div>
                            </div>

                            <form action="/daftar.php" method="get" class="mt-5 flex flex-col flex-1 package-form">
                                <input type="hidden" name="package_id" value="<?= (int) $package['id'] ?>">
                                <label class="text-xs font-semibold text-neutral-600" for="store_count_<?= (int) $package['id'] ?>">
                                    Jumlah toko
                                </label>
                                <div class="mt-2 flex items-center gap-2">
                                    <input
                                        id="store_count_<?= (int) $package['id'] ?>"
                                        name="store_count"
                                        type="number"
                                        min="<?= max(1, $minStore) ?>"
                                        <?= $maxStore !== null ? 'max="' . $maxStore . '"' : '' ?>
                                        value="<?= $defaultStoreCount ?>"
                                        required
                                        class="store-count-input w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm font-medium outline-none focus:border-neutral-400"
                                    >
                                    <span class="shrink-0 text-xs text-neutral-400">toko</span>
                                </div>

                                <div class="tier-status mt-3 rounded-2xl bg-neutral-50 border border-neutral-100 px-4 py-3 text-xs">
                                    <p class="tier-label font-semibold text-neutral-700"></p>
                                    <p class="tier-price mt-1 text-neutral-500"></p>
                                </div>

                                <button type="submit" class="package-submit mt-auto pt-6 w-full">
                                    <span class="w-full h-12 rounded-xl bg-neutral-900 text-white flex items-center justify-center text-sm font-semibold hover:bg-neutral-800 transition shadow-sm">
                                        Pilih Paket
                                    </span>
                                </button>
                            </form>
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

<script>
function formatRupiah(value) {
    return 'Rp ' + new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0
    }).format(value);
}

document.querySelectorAll('.package-card').forEach(function (card) {
    const tiers = JSON.parse(card.dataset.tiers || '[]');
    const input = card.querySelector('.store-count-input');
    const label = card.querySelector('.tier-label');
    const price = card.querySelector('.tier-price');
    const submit = card.querySelector('.package-submit');

    function refreshTier() {
        const count = Number.parseInt(input.value || '0', 10);
        const tier = tiers.find(function (item) {
            return count >= item.min && (item.max === null || count <= item.max);
        });

        if (!tier) {
            label.textContent = 'Jumlah toko belum tercakup';
            price.textContent = 'Pilih jumlah toko sesuai tier yang tersedia.';
            submit.disabled = true;
            submit.classList.add('opacity-50', 'cursor-not-allowed');
            return;
        }

        label.textContent = tier.max === null
            ? tier.min + '+ toko'
            : (tier.min === tier.max ? tier.min + ' toko' : tier.min + '–' + tier.max + ' toko');
        price.textContent = formatRupiah(tier.price) + ' untuk kapasitas ' + count + ' toko';
        submit.disabled = false;
        submit.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    input.addEventListener('input', refreshTier);
    refreshTier();
});
</script>
</body>
</html>
