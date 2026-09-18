<?php

require_once '../../../config/database.php';
require_once '../../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (($authRole ?? '') !== 'ADMIN' || (int)($authStoreId ?? 0) <= 0) {
    header('Location: /pages/pengaturan/');
    exit;
}

$storeId = (int) $authStoreId;

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.username,
        u.created_at,
        su.status AS membership_status
    FROM store_users su
    INNER JOIN users u ON u.id = su.user_id
    WHERE su.store_id = :store_id
      AND su.role = 'STAFF'
    ORDER BY u.name ASC
");
$stmt->execute([':store_id' => $storeId]);
$staffs = $stmt->fetchAll();

$activeCount = 0;
foreach ($staffs as $staff) {
    if ($staff['membership_status'] === 'ACTIVE') $activeCount++;
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pageTitle = 'Staff';
require_once '../../../includes/header.php';
require_once '../../../includes/sidebar.php';
?>

<main class="main-content min-h-screen bg-neutral-50">
    <div class="p-4 md:p-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between mb-7">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-widest text-neutral-400 mb-2">Pengaturan</div>
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">Staff</h1>
                    <p class="text-sm text-neutral-500 mt-2">
                        Kelola akses Staff pada Store yang sedang aktif.
                    </p>
                </div>
                <a href="/pages/pengaturan/staff/create.php"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-neutral-800">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Tambah Staff
                </a>
            </div>

            <?php if ($flashSuccess): ?>
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($flashError) ?></div>
            <?php endif; ?>

            <div class="grid gap-4 md:grid-cols-3 mb-6">
                <div class="bento-card p-5">
                    <p class="text-xs text-neutral-400">Total Staff</p>
                    <p class="mt-2 text-2xl font-bold"><?= count($staffs) ?></p>
                </div>
                <div class="bento-card p-5">
                    <p class="text-xs text-neutral-400">Staff Aktif</p>
                    <p class="mt-2 text-2xl font-bold"><?= $activeCount ?></p>
                </div>
                <div class="bento-card p-5">
                    <p class="text-xs text-neutral-400">Store Aktif</p>
                    <p class="mt-2 text-sm font-bold"><?= e($_SESSION['store_name'] ?? 'Store') ?></p>
                </div>
            </div>

            <div class="bento-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-neutral-100 bg-neutral-50/70">
                            <tr>
                                <th class="px-5 py-4 text-left font-semibold text-neutral-500">Nama</th>
                                <th class="px-5 py-4 text-left font-semibold text-neutral-500">Username</th>
                                <th class="px-5 py-4 text-left font-semibold text-neutral-500">Status</th>
                                <th class="px-5 py-4 text-right font-semibold text-neutral-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                        <?php if (!$staffs): ?>
                            <tr><td colspan="4" class="px-5 py-12 text-center text-neutral-400">Belum ada Staff di Store ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($staffs as $staff): ?>
                                <tr>
                                    <td class="px-5 py-4 font-semibold text-neutral-900"><?= e($staff['name']) ?></td>
                                    <td class="px-5 py-4 text-neutral-500"><?= e($staff['username']) ?></td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $staff['membership_status'] === 'ACTIVE' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-500' ?>">
                                            <?= $staff['membership_status'] === 'ACTIVE' ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="/pages/pengaturan/staff/edit.php?id=<?= (int)$staff['id'] ?>"
                                               class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
                                            </a>
                                            <form method="POST" action="/pages/pengaturan/staff/status.php">
                                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$staff['id'] ?>">
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                                                        onclick="return confirm('Ubah status akses Staff ini?')">
                                                    <i data-lucide="power" class="w-3.5 h-3.5"></i>
                                                    <?= $staff['membership_status'] === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../../includes/footer.php'; ?>
