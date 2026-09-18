<?php
require_once __DIR__ . '/../../../includes/auth.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->prepare("
    SELECT
        su.id AS membership_id,
        su.store_id,
        su.role,
        su.status AS membership_status,
        s.name,
        s.slug,
        s.phone,
        s.address,
        s.status AS store_status
    FROM store_users su
    INNER JOIN stores s ON s.id = su.store_id
    WHERE su.user_id = :user_id
    ORDER BY
        CASE WHEN su.store_id = :active_store_id THEN 0 ELSE 1 END,
        s.name ASC
");

$stmt->execute([
    ':user_id' => $authUserId,
    ':active_store_id' => $authStoreId,
]);

$stores = $stmt->fetchAll();

$pageTitle = 'Store Saya';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<main class="main-content min-h-screen bg-neutral-50">
    <div class="max-w-5xl mx-auto px-4 py-6 md:px-8 md:py-8">

        <div class="mb-7">
            <p class="text-xs font-semibold uppercase tracking-widest text-neutral-400">Pengaturan</p>
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-neutral-900">Store Saya</h1>
                    <p class="mt-1 text-sm text-neutral-500">
                        Kelola Store dalam Account ini dan pilih Store yang sedang digunakan.
                    </p>
                </div>

                <?php if ($authRole === 'ADMIN'): ?>
                    <a href="/pages/pengaturan/store/create.php"
                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-neutral-800">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Tambah Store
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <div class="grid gap-4 md:grid-cols-2">
            <?php foreach ($stores as $store): ?>
                <?php $isActive = ((int) $store['store_id'] === $authStoreId); ?>

                <div class="rounded-2xl border <?= $isActive ? 'border-neutral-900' : 'border-neutral-200' ?> bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h2 class="truncate text-lg font-bold text-neutral-900">
                                    <?= htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8') ?>
                                </h2>

                                <?php if ($isActive): ?>
                                    <span class="rounded-full bg-neutral-900 px-2 py-1 text-[10px] font-semibold text-white">
                                        AKTIF
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p class="mt-1 text-xs text-neutral-400">
                                <?= htmlspecialchars($store['slug'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>

                        <span class="rounded-lg bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-600">
                            <?= $store['role'] === 'ADMIN' ? 'Owner' : 'Staff' ?>
                        </span>
                    </div>

                    <div class="mt-5 space-y-2 text-sm text-neutral-500">
                        <?php if ($store['phone']): ?>
                            <div class="flex gap-2">
                                <i data-lucide="phone" class="mt-0.5 w-4 h-4 shrink-0"></i>
                                <span><?= htmlspecialchars($store['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($store['address']): ?>
                            <div class="flex gap-2">
                                <i data-lucide="map-pin" class="mt-0.5 w-4 h-4 shrink-0"></i>
                                <span><?= nl2br(htmlspecialchars($store['address'], ENT_QUOTES, 'UTF-8')) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2 border-t border-neutral-100 pt-4">
                        <?php if (!$isActive && $store['membership_status'] === 'ACTIVE' && $store['store_status'] === 'ACTIVE'): ?>
                            <form method="POST" action="/pages/pengaturan/store/switch.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="store_id" value="<?= (int) $store['store_id'] ?>">
                                <button type="submit"
                                        class="inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-3.5 py-2 text-xs font-semibold text-white hover:bg-neutral-800">
                                    <i data-lucide="arrow-right-left" class="w-4 h-4"></i>
                                    Gunakan Store
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($authRole === 'ADMIN'): ?>
                            <a href="/pages/pengaturan/store/edit.php?id=<?= (int) $store['store_id'] ?>"
                               class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                                Edit
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
