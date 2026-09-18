<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Pilih Paket';

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$stmt = $pdo->prepare(
    "SELECT id, name, slug, price, duration_days, description
     FROM packages
     WHERE status = 'ACTIVE'
     ORDER BY price ASC, id ASC"
);
$stmt->execute();
$packages = $stmt->fetchAll();

$baseUrl = '/daftar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f5f5">
    <title><?= e($pageTitle) ?> · RESTOCK</title>

    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="min-h-screen bg-neutral-50 text-neutral-900">
    <header class="border-b border-neutral-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex min-h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="/" class="flex items-center gap-3" aria-label="RESTOCK">
                <img
                    src="/assets/images/logo.png"
                    alt="RESTOCK"
                    class="h-9 w-9 rounded-xl object-cover"
                >
                <span class="text-sm font-semibold tracking-tight">RESTOCK</span>
            </a>

            <a
                href="/login.php"
                class="inline-flex min-h-10 items-center justify-center rounded-xl border border-neutral-200 bg-white px-4 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50"
            >
                Masuk
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <section class="mx-auto max-w-2xl text-center">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">
                RESTOCK
            </p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                Pilih paket untuk toko kamu
            </h1>
            <p class="mt-3 text-sm leading-6 text-neutral-500 sm:text-base">
                Pilih paket yang sesuai. Harga dan durasi di halaman ini berasal langsung dari paket yang sedang aktif di sistem.
            </p>
        </section>

        <section class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php if (!$packages): ?>
                <div class="bento-card col-span-full px-6 py-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-500">
                        <i data-lucide="package-open" class="h-6 w-6"></i>
                    </div>
                    <h2 class="mt-4 text-base font-semibold">Belum ada paket tersedia</h2>
                    <p class="mt-2 text-sm text-neutral-500">
                        Belum ada paket ACTIVE yang dapat dipilih untuk pendaftaran.
                    </p>
                </div>
            <?php endif; ?>

            <?php foreach ($packages as $package): ?>
                <?php
                $registrationQuery = http_build_query([
                    'package_id' => (int) $package['id'],
                ]);
                $registrationUrl = $baseUrl . '?' . $registrationQuery;
                ?>
                <article class="bento-card flex flex-col p-5 sm:p-6">
                    <div>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-semibold tracking-tight">
                                    <?= e($package['name']) ?>
                                </h2>
                                <p class="mt-1 text-xs text-neutral-400">
                                    /<?= e($package['slug']) ?>
                                </p>
                            </div>

                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Aktif
                            </span>
                        </div>

                        <div class="mt-7">
                            <p class="text-3xl font-semibold tracking-tight">
                                Rp <?= number_format((float) $package['price'], 0, ',', '.') ?>
                            </p>
                            <p class="mt-1 text-sm text-neutral-500">
                                <?= number_format((int) $package['duration_days']) ?> hari
                            </p>
                        </div>

                        <div class="mt-5 min-h-20">
                            <?php if (!empty($package['description'])): ?>
                                <p class="text-sm leading-6 text-neutral-500">
                                    <?= e($package['description']) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-sm leading-6 text-neutral-400">
                                    Tidak ada deskripsi paket.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <a
                        href="<?= e($registrationUrl) ?>"
                        class="mt-6 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-neutral-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-300 focus:ring-offset-2"
                    >
                        Pilih Paket
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </a>
                </article>
            <?php endforeach; ?>
        </section>

        <p class="mt-8 text-center text-xs leading-5 text-neutral-400">
            Paket yang tidak aktif tidak ditampilkan pada halaman pendaftaran.
        </p>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
