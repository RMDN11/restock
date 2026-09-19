<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/package_pricing.php';

$pageTitle = 'Renewal';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$stmt = $pdo->prepare(
    "SELECT id, name, slug, price, duration_days, min_store_count, max_store_count, description
     FROM packages
     WHERE status = 'ACTIVE'
     ORDER BY price ASC, id ASC"
);
$stmt->execute();
$packages = $stmt->fetchAll();

$activeSubscription = restockGetActiveSubscription(
    $pdo,
    $authAccountId,
    $authStoreId
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f5f5">
    <title><?= e($pageTitle) ?> · RESTOCK</title>
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-900">
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <div class="mb-7">
            <a href="/subscription.php" class="text-sm font-medium text-neutral-500 hover:text-neutral-900">
                ← Kembali ke subscription
            </a>
            <p class="mt-6 text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">RESTOCK RENEWAL</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">Perpanjang akses RESTOCK</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-500">
                Pilih paket. Pembayaran renewal akan dibuat sebagai PENDING dan masa baru mengikuti periode subscription yang sedang berjalan.
            </p>
        </div>

        <?php if ($activeSubscription): ?>
            <div class="mb-6 rounded-2xl border border-neutral-200 bg-white px-4 py-3 text-sm text-neutral-600">
                Subscription aktif sampai
                <strong><?= e(date('d M Y, H:i', strtotime((string) $activeSubscription['ends_at']))) ?></strong>.
                Renewal akan dimulai setelah periode tersebut berakhir.
            </div>
        <?php endif; ?>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php if (!$packages): ?>
                <div class="bento-card col-span-full px-6 py-12 text-center">
                    <p class="text-sm font-medium">Belum ada paket tersedia.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($packages as $package): ?>
                <?php
                $tiers = restockGetPackageTiers($pdo, (int) $package['id']);
                $defaultStoreCount = !empty($tiers) ? (int) $tiers[0]['min_store_count'] : max(1, (int) ($package['min_store_count'] ?? 1));
                $maxStore = !empty($tiers) && $tiers[count($tiers) - 1]['max_store_count'] !== null
                    ? (int) $tiers[count($tiers) - 1]['max_store_count']
                    : ($package['max_store_count'] !== null ? (int) $package['max_store_count'] : null);
                $startingPrice = restockGetPackageStartingPrice(['price' => $package['price'], 'tiers' => $tiers]);
                ?>
                <article class="renewal-card bento-card flex flex-col p-5 sm:p-6" data-tiers="<?= e(json_encode(array_map(static fn (array $tier): array => ['min' => (int) $tier['min_store_count'], 'max' => $tier['max_store_count'] !== null ? (int) $tier['max_store_count'] : null, 'price' => (float) $tier['price']], $tiers), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight"><?= e($package['name']) ?></h2>
                        <p class="mt-1 text-xs text-neutral-400"><?= (int) $package['duration_days'] ?> hari · pilih kapasitas toko</p>
                        <p class="renewal-price mt-6 text-3xl font-semibold tracking-tight"><?= rupiah($startingPrice) ?></p>
                        <?php if (!empty($package['description'])): ?>
                            <p class="mt-4 text-sm leading-6 text-neutral-500"><?= e($package['description']) ?></p>
                        <?php endif; ?>
                    </div>

                    <form method="get" action="/renewal/select.php" class="mt-auto pt-5">
                        <input type="hidden" name="package_id" value="<?= (int) $package['id'] ?>">
                        <label class="block text-xs font-semibold text-neutral-600" for="renewal_store_count_<?= (int) $package['id'] ?>">Jumlah toko</label>
                        <input id="renewal_store_count_<?= (int) $package['id'] ?>" name="store_count" type="number" min="<?= max(1, $defaultStoreCount) ?>" <?= $maxStore !== null ? 'max="' . $maxStore . '"' : '' ?> value="<?= $defaultStoreCount ?>" required class="renewal-store-count mt-2 w-full min-h-11 rounded-xl border border-neutral-200 px-4 text-sm font-medium outline-none focus:border-neutral-400">
                        <p class="renewal-tier mt-2 text-xs text-neutral-400 min-h-10"></p>
                        <button type="submit" class="renewal-submit mt-3 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-neutral-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-neutral-800">
                            Pilih untuk Renewal
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>

<script>
function renewalRupiah(value) {
    return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value);
}
document.querySelectorAll('.renewal-card').forEach(function (card) {
    const tiers = JSON.parse(card.dataset.tiers || '[]');
    const input = card.querySelector('.renewal-store-count');
    const price = card.querySelector('.renewal-price');
    const note = card.querySelector('.renewal-tier');
    const submit = card.querySelector('.renewal-submit');
    function refresh() {
        const count = Number.parseInt(input.value || '0', 10);
        const tier = tiers.find(function (item) {
            return count >= item.min && (item.max === null || count <= item.max);
        });
        if (!tier) {
            price.textContent = 'Harga belum tersedia';
            note.textContent = 'Pilih jumlah toko yang masuk dalam tier pricing aktif.';
            submit.disabled = true;
            submit.classList.add('opacity-50', 'cursor-not-allowed');
            return;
        }
        price.textContent = renewalRupiah(tier.price);
        note.textContent = tier.max === null ? tier.min + '+ toko' : (tier.min === tier.max ? tier.min + ' toko' : tier.min + '–' + tier.max + ' toko');
        submit.disabled = false;
        submit.classList.remove('opacity-50', 'cursor-not-allowed');
    }
    input.addEventListener('input', refresh);
    refresh();
});
</script>
