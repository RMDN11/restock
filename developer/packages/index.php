<?php
require_once __DIR__ . '/../../includes/developer_auth.php';
require_once __DIR__ . '/../../includes/package_pricing.php';

$pageTitle = 'Paket';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$search = trim((string) ($_GET['search'] ?? ''));
$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
if (!in_array($status, ['', 'ACTIVE', 'INACTIVE'], true)) {
    $status = '';
}

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE :search_name OR slug LIKE :search_slug)';
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_slug'] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT id, name, slug, price, duration_days, min_store_count, max_store_count, description, status, created_at, updated_at
    FROM packages $whereSql ORDER BY id DESC");
$stmt->execute($params);
$packages = $stmt->fetchAll();

$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="lg:ml-64 pt-16 min-h-screen">
    <div class="p-4 md:p-8 max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer · Finance</p>
                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight mt-1">Paket</h1>
                <p class="text-sm text-neutral-500 mt-2">Atur paket langganan yang tersedia untuk checkout.</p>
            </div>
            <a href="/developer/packages/create.php" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Paket
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="bento-card p-4 md:p-5 mb-5">
            <form method="get" class="grid grid-cols-1 md:grid-cols-[1fr_180px_auto] gap-3">
                <input type="search" name="search" value="<?= e($search) ?>" placeholder="Cari nama atau slug paket..."
                    class="h-11 rounded-xl border border-neutral-200 bg-white px-4 text-sm outline-none focus:border-neutral-400">
                <select name="status" class="h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm outline-none focus:border-neutral-400">
                    <option value="">Semua status</option>
                    <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                    <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                </select>
                <button class="h-11 px-5 rounded-xl bg-neutral-100 text-neutral-800 text-sm font-medium hover:bg-neutral-200">Filter</button>
            </form>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php if (!$packages): ?>
                <div class="bento-card p-10 text-center md:col-span-2 xl:col-span-3">
                    <p class="font-medium">Belum ada paket.</p>
                    <p class="text-xs text-neutral-400 mt-1">Buat paket pertama untuk digunakan pada checkout.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($packages as $package): ?>
                <article class="bento-card p-5 flex flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-lg"><?= e($package['name']) ?></h2>
                            <p class="text-xs text-neutral-400 mt-1">/<?= e($package['slug']) ?></p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium <?= $package['status'] === 'ACTIVE' ? 'text-emerald-700' : 'text-neutral-500' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $package['status'] === 'ACTIVE' ? 'bg-emerald-500' : 'bg-neutral-400' ?>"></span>
                            <?= e($package['status']) ?>
                        </span>
                    </div>

                    <div class="mt-6">
                        <?php $packageTiers = restockGetPackageTiers($pdo, (int) $package['id']); ?>
                        <p class="text-2xl font-semibold">Rp <?= number_format(restockGetPackageStartingPrice(['price' => $package['price'], 'tiers' => $packageTiers]), 0, ',', '.') ?></p>
                        <p class="text-xs text-neutral-400 mt-1"><?= number_format((int) $package['duration_days']) ?> hari · <?= count($packageTiers) ?> tier ACTIVE</p>
                    </div>

                    <?php if ($package['description']): ?>
                        <p class="text-sm text-neutral-500 mt-4 line-clamp-3"><?= e($package['description']) ?></p>
                    <?php else: ?>
                        <p class="text-sm text-neutral-400 mt-4">Tidak ada deskripsi.</p>
                    <?php endif; ?>

                    <div class="mt-auto pt-5 flex gap-2">
                        <a href="/developer/packages/pricing.php?id=<?= (int) $package['id'] ?>" class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-neutral-200 bg-white text-sm font-medium hover:bg-neutral-50">
                            <i data-lucide="badge-dollar-sign" class="w-4 h-4"></i> Pricing
                        </a>
                        <a href="/developer/packages/edit.php?id=<?= (int) $package['id'] ?>" class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-neutral-100 text-sm font-medium hover:bg-neutral-200">
                            <i data-lucide="pencil" class="w-4 h-4"></i> Edit
                        </a>
                        <form method="post" action="/developer/packages/status.php" class="flex-none">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
                            <input type="hidden" name="status" value="<?= $package['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                            <button type="submit" class="w-full px-3 py-2.5 rounded-xl border border-neutral-200 text-sm hover:bg-neutral-50" title="<?= $package['status'] === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                <?= $package['status'] === 'ACTIVE' ? 'Nonaktif' : 'Aktifkan' ?>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
