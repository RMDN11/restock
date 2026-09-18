<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

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
    "SELECT id, name, slug, price, duration_days, description
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
                $checkoutUrl = '/renewal/select.php?' . http_build_query([
                    'package_id' => (int) $package['id'],
                ]);
                ?>
                <article class="bento-card flex flex-col p-5 sm:p-6">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight"><?= e($package['name']) ?></h2>
                        <p class="mt-1 text-xs text-neutral-400"><?= (int) $package['duration_days'] ?> hari</p>
                        <p class="mt-6 text-3xl font-semibold tracking-tight"><?= rupiah($package['price']) ?></p>
                        <?php if (!empty($package['description'])): ?>
                            <p class="mt-4 text-sm leading-6 text-neutral-500"><?= e($package['description']) ?></p>
                        <?php endif; ?>
                    </div>

                    <a
                        href="<?= e($checkoutUrl) ?>"
                        class="mt-6 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-neutral-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-neutral-800"
                    >
                        Pilih untuk Renewal
                    </a>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
