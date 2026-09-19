<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/developer_auth.php';
require_once __DIR__ . '/../../includes/package_pricing.php';

$pageTitle = 'Pricing Paket';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$packageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$packageId || $packageId < 1) {
    header('Location: /developer/packages/');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$errors = [];

$package = restockGetPackageWithTiers($pdo, (int) $packageId);
if (!$package) {
    http_response_code(404);
    exit('Paket tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Sesi keamanan tidak valid.';
    }

    $action = strtoupper(trim((string) ($_POST['action'] ?? 'SAVE')));
    $tierId = filter_var($_POST['tier_id'] ?? null, FILTER_VALIDATE_INT);
    $minStoreCount = filter_var($_POST['min_store_count'] ?? null, FILTER_VALIDATE_INT);
    $maxRaw = trim((string) ($_POST['max_store_count'] ?? ''));
    $maxStoreCount = $maxRaw === '' ? null : filter_var($maxRaw, FILTER_VALIDATE_INT);
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);

    if ($action === 'DEACTIVATE') {
        if ($tierId === false || $tierId < 1) {
            $errors[] = 'Tier pricing tidak valid.';
        } else {
            $stmt = $pdo->prepare(
                "UPDATE package_price_tiers
                 SET status = 'INACTIVE', updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND package_id = :package_id
                   AND status = 'ACTIVE'"
            );
            $stmt->execute([
                ':id' => $tierId,
                ':package_id' => $packageId,
            ]);
            if ($stmt->rowCount() !== 1) {
                $errors[] = 'Tier pricing tidak ditemukan atau sudah nonaktif.';
            }
        }
    } else {
        if ($minStoreCount === false || $minStoreCount < 1) {
            $errors[] = 'Minimal toko harus minimal 1.';
        }

        if ($maxRaw !== '' && ($maxStoreCount === false || $maxStoreCount < 1)) {
            $errors[] = 'Maksimal toko tidak valid.';
        }

        if (!$errors && $maxStoreCount !== null && $maxStoreCount < $minStoreCount) {
            $errors[] = 'Maksimal toko tidak boleh lebih kecil dari minimal toko.';
        }

        if ($price === false || $price < 0 || $price > 999999999999.99) {
            $errors[] = 'Harga tidak valid.';
        }

        $normalizedTierId = ($tierId !== false && $tierId > 0) ? $tierId : null;

        if (
            !$errors &&
            !restockValidateTierCoverage(
                $pdo,
                $packageId,
                $normalizedTierId,
                (int) $minStoreCount,
                $maxStoreCount !== null ? (int) $maxStoreCount : null
            )
        ) {
            $errors[] = 'Rentang toko bertabrakan dengan tier ACTIVE lain pada paket ini.';
        }

        if (!$errors) {
            if ($normalizedTierId !== null) {
                $stmt = $pdo->prepare(
                    "UPDATE package_price_tiers
                     SET min_store_count = :min_store_count,
                         max_store_count = :max_store_count,
                         price = :price,
                         status = 'ACTIVE',
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id
                       AND package_id = :package_id"
                );
                $stmt->execute([
                    ':min_store_count' => $minStoreCount,
                    ':max_store_count' => $maxStoreCount,
                    ':price' => number_format((float) $price, 2, '.', ''),
                    ':id' => $normalizedTierId,
                    ':package_id' => $packageId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO package_price_tiers
                        (package_id, min_store_count, max_store_count, price, status, sort_order, created_at, updated_at)
                     VALUES
                        (:package_id, :min_store_count, :max_store_count, :price, 'ACTIVE', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
                );
                $stmt->execute([
                    ':package_id' => $packageId,
                    ':min_store_count' => $minStoreCount,
                    ':max_store_count' => $maxStoreCount,
                    ':price' => number_format((float) $price, 2, '.', ''),
                ]);
            }

            $_SESSION['flash_success'] = 'Tier pricing berhasil disimpan.';
            header('Location: /developer/packages/pricing.php?id=' . $packageId);
            exit;
        }
    }

    if (!$errors && $action === 'DEACTIVATE') {
        $_SESSION['flash_success'] = 'Tier pricing dinonaktifkan.';
        header('Location: /developer/packages/pricing.php?id=' . $packageId);
        exit;
    }
}

$package = restockGetPackageWithTiers($pdo, (int) $packageId);
$allTiersStmt = $pdo->prepare(
    "SELECT id, min_store_count, max_store_count, price, status, sort_order, created_at, updated_at
     FROM package_price_tiers
     WHERE package_id = :package_id
     ORDER BY min_store_count ASC, sort_order ASC, id ASC"
);
$allTiersStmt->execute([':package_id' => $packageId]);
$tiers = $allTiersStmt->fetchAll();

$flash = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$activeTiers = array_values(array_filter(
    $tiers,
    static fn (array $tier): bool => $tier['status'] === 'ACTIVE'
));
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
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-neutral-50 text-neutral-900">
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 min-h-screen">
<div class="p-4 md:p-8 max-w-6xl mx-auto">
    <div class="mb-6">
        <a href="/developer/packages/" class="inline-flex items-center gap-1.5 text-xs text-neutral-500 hover:text-neutral-900 mb-5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali ke Paket
        </a>
        <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer · Finance</p>
        <h1 class="text-2xl md:text-3xl font-semibold tracking-tight mt-1">Pricing <?= e($package['name']) ?></h1>
        <p class="text-sm text-neutral-500 mt-2">
            Tentukan harga berdasarkan jumlah toko yang ingin dicakup. Checkout hanya memakai tier ACTIVE yang cocok.
        </p>
    </div>

    <?php if ($flash): ?>
        <div id="pricingFlash" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="space-y-1"><?php foreach ($errors as $error): ?><li>• <?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-[1.2fr_.8fr] gap-5">
        <section class="bento-card overflow-hidden">
            <div class="px-5 md:px-6 py-5 border-b border-neutral-100">
                <h2 class="font-semibold">Tier Pricing</h2>
                <p class="text-xs text-neutral-400 mt-1"><?= count($activeTiers) ?> tier ACTIVE · rentang tidak boleh saling bertabrakan.</p>
            </div>

            <?php if (!$tiers): ?>
                <div class="p-8 text-center text-sm text-neutral-500">Belum ada tier pricing.</div>
            <?php else: ?>
                <div class="divide-y divide-neutral-100">
                    <?php foreach ($tiers as $tier): ?>
                        <div class="px-5 md:px-6 py-5">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-semibold">
                                            <?= e(restockFormatStoreRange(
                                                (int) $tier['min_store_count'],
                                                $tier['max_store_count'] !== null ? (int) $tier['max_store_count'] : null
                                            )) ?>
                                        </p>
                                        <span class="text-[10px] font-semibold rounded-full px-2 py-1 <?= $tier['status'] === 'ACTIVE' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-500' ?>">
                                            <?= e($tier['status']) ?>
                                        </span>
                                    </div>
                                    <p class="mt-1 text-2xl font-semibold"><?= rupiah($tier['price']) ?></p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="edit-tier inline-flex items-center gap-1.5 rounded-xl bg-neutral-100 px-3 py-2 text-xs font-semibold hover:bg-neutral-200"
                                        data-id="<?= (int) $tier['id'] ?>"
                                        data-min="<?= (int) $tier['min_store_count'] ?>"
                                        data-max="<?= $tier['max_store_count'] === null ? '' : (int) $tier['max_store_count'] ?>"
                                        data-price="<?= e((string) $tier['price']) ?>"
                                    >
                                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> <?= $tier['status'] === 'ACTIVE' ? 'Edit' : 'Aktifkan & Edit' ?>
                                    </button>
                                    <?php if ($tier['status'] === 'ACTIVE'): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="DEACTIVATE">
                                            <input type="hidden" name="tier_id" value="<?= (int) $tier['id'] ?>">
                                            <button class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                                                Nonaktifkan
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="bento-card p-5 md:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-neutral-400">Form Tier</p>
            <h2 id="tierFormTitle" class="text-lg font-semibold mt-1">Tambah Tier</h2>

            <form method="post" class="mt-5 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="SAVE">
                <input type="hidden" id="tier_id" name="tier_id" value="">

                <div>
                    <label class="block text-sm font-medium mb-2" for="min_store_count">Minimal toko</label>
                    <input id="min_store_count" name="min_store_count" type="number" min="1" required class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" for="max_store_count">Maksimal toko</label>
                    <input id="max_store_count" name="max_store_count" type="number" min="1" placeholder="Kosong = tanpa batas" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2" for="price">Harga</label>
                    <input id="price" name="price" type="number" min="0" step="0.01" required class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400">
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-4 text-xs leading-5 text-neutral-500">
                    Contoh: <strong>1–1</strong> Rp49.000, <strong>2–3</strong> Rp99.000, <strong>4–10</strong> Rp149.000. Rentang bisa dibuat sesuai bisnis dan tidak boleh overlap.
                </div>

                <div class="flex gap-2 pt-2">
                    <button id="tierReset" type="button" class="flex-1 h-11 rounded-xl border border-neutral-200 text-sm font-medium hover:bg-neutral-50">Reset</button>
                    <button type="submit" class="flex-1 h-11 rounded-xl bg-neutral-900 text-white text-sm font-semibold hover:bg-neutral-800">Simpan Tier</button>
                </div>
            </form>
        </section>
    </div>
</div>
</main>

<script>
const title = document.getElementById('tierFormTitle');
const tierId = document.getElementById('tier_id');
const minInput = document.getElementById('min_store_count');
const maxInput = document.getElementById('max_store_count');
const priceInput = document.getElementById('price');
const resetButton = document.getElementById('tierReset');

function resetTierForm() {
    title.textContent = 'Tambah Tier';
    tierId.value = '';
    minInput.value = '';
    maxInput.value = '';
    priceInput.value = '';
}

document.querySelectorAll('.edit-tier').forEach(function (button) {
    button.addEventListener('click', function () {
        title.textContent = 'Edit Tier';
        tierId.value = button.dataset.id || '';
        minInput.value = button.dataset.min || '';
        maxInput.value = button.dataset.max || '';
        priceInput.value = button.dataset.price || '';
        minInput.focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

resetButton?.addEventListener('click', resetTierForm);

setTimeout(function () {
    document.getElementById('pricingFlash')?.remove();
}, 3000);
</script>
<script>lucide.createIcons();</script>
</body>
</html>
