<?php
require_once __DIR__ . '/../../includes/developer_auth.php';

$pageTitle = 'Detail Store';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$storeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$storeId || $storeId < 1) {
    header('Location: /developer/stores/');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.account_id,
        s.name,
        s.slug,
        s.phone,
        s.address,
        s.status,
        s.created_at,
        s.updated_at,
        a.name AS account_name,
        a.status AS account_status
    FROM stores s
    INNER JOIN accounts a
        ON a.id = s.account_id
    WHERE s.id = :store_id
    LIMIT 1
");
$stmt->execute([':store_id' => $storeId]);
$store = $stmt->fetch();

if (!$store) {
    http_response_code(404);
    $pageTitle = 'Store Tidak Ditemukan';
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../includes/sidebar.php';
    ?>
    <main class="lg:ml-64 pt-16 min-h-screen">
        <div class="p-4 md:p-8 max-w-5xl mx-auto">
            <div class="bento-card p-8 text-center">
                <p class="font-medium">Store tidak ditemukan.</p>
                <a href="/developer/stores/" class="inline-flex mt-4 px-4 py-2 rounded-xl bg-neutral-900 text-white text-sm">Kembali ke Stores</a>
            </div>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        su.id AS membership_id,
        su.user_id,
        su.role,
        su.status AS membership_status,
        su.created_at AS membership_created_at,
        u.name AS user_name,
        u.username,
        u.email,
        u.status AS user_status
    FROM store_users su
    INNER JOIN users u
        ON u.id = su.user_id
    WHERE su.store_id = :store_id
    ORDER BY
        CASE WHEN su.role = 'ADMIN' AND su.status = 'ACTIVE' AND u.status = 'ACTIVE' THEN 0 ELSE 1 END,
        u.name ASC,
        su.id ASC
");
$stmt->execute([':store_id' => $storeId]);
$memberships = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        al.id,
        al.action,
        al.table_name,
        al.record_id,
        al.description,
        al.created_at,
        u.name AS user_name
    FROM audit_logs al
    LEFT JOIN users u
        ON u.id = al.user_id
    WHERE al.store_id = :store_id
    ORDER BY al.created_at DESC, al.id DESC
    LIMIT 10
");
$stmt->execute([':store_id' => $storeId]);
$activities = $stmt->fetchAll();

$activeUserCount = 0;
$activeAdminUsernames = [];

foreach ($memberships as $membership) {
    $isActiveUser = $membership['membership_status'] === 'ACTIVE'
        && $membership['user_status'] === 'ACTIVE';

    if ($isActiveUser) {
        $activeUserCount++;
    }

    if ($membership['role'] === 'ADMIN' && $isActiveUser) {
        $activeAdminUsernames[] = $membership['username'];
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="lg:ml-64 pt-16 min-h-screen">
    <div class="p-4 md:p-8 max-w-7xl mx-auto">
        <div class="mb-6">
            <a href="/developer/stores/" class="inline-flex items-center gap-1.5 text-xs text-neutral-500 hover:text-neutral-900 mb-4">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Kembali ke Stores
            </a>

            <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer</p>

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mt-1">
                <div>
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight"><?= e($store['name']) ?></h1>
                    <p class="text-sm text-neutral-500 mt-2">Detail Store #<?= e($store['id']) ?> dan aktivitas terkait.</p>
                </div>

                <span class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white border border-neutral-200 text-xs font-medium <?= $store['status'] === 'ACTIVE' ? 'text-emerald-700' : 'text-neutral-500' ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $store['status'] === 'ACTIVE' ? 'bg-emerald-500' : 'bg-neutral-400' ?>"></span>
                    <?= e($store['status']) ?>
                </span>
            </div>
        </div>

        <section class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bento-card p-5">
                <p class="text-xs uppercase tracking-wider text-neutral-400">Account</p>
                <p class="text-lg font-semibold mt-3"><?= e($store['account_name']) ?></p>
                <p class="text-xs text-neutral-400 mt-1">#<?= e($store['account_id']) ?></p>
            </div>
            <div class="bento-card p-5">
                <p class="text-xs uppercase tracking-wider text-neutral-400">User Aktif</p>
                <p class="text-2xl font-semibold mt-3"><?= number_format($activeUserCount) ?></p>
            </div>
            <div class="bento-card p-5">
                <p class="text-xs uppercase tracking-wider text-neutral-400">Admin Aktif</p>
                <p class="text-sm font-semibold mt-3"><?= e($activeAdminUsernames ? implode(', ', $activeAdminUsernames) : '-') ?></p>
            </div>
            <div class="bento-card p-5">
                <p class="text-xs uppercase tracking-wider text-neutral-400">Dibuat</p>
                <p class="text-lg font-semibold mt-3"><?= e(date('d M Y', strtotime($store['created_at'] ?? 'now'))) ?></p>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-[.9fr_1.1fr] gap-5 mb-5">
            <div class="bento-card p-5 md:p-6">
                <h2 class="font-semibold">Informasi Store</h2>

                <dl class="mt-5 space-y-4 text-sm">
                    <div>
                        <dt class="text-xs text-neutral-400">Nama</dt>
                        <dd class="mt-1 font-medium text-neutral-800"><?= e($store['name']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-neutral-400">Slug</dt>
                        <dd class="mt-1 text-neutral-700">/<?= e($store['slug']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-neutral-400">Telepon</dt>
                        <dd class="mt-1 text-neutral-700"><?= e($store['phone'] ?: '-') ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-neutral-400">Alamat</dt>
                        <dd class="mt-1 whitespace-pre-line text-neutral-700"><?= e($store['address'] ?: '-') ?></dd>
                    </div>
                </dl>
            </div>

            <div class="bento-card p-5 md:p-6">
                <h2 class="font-semibold">Account Pemilik</h2>

                <div class="mt-5 flex items-start justify-between gap-4">
                    <div>
                        <p class="font-medium text-neutral-800"><?= e($store['account_name']) ?></p>
                        <p class="text-xs text-neutral-400 mt-1">Account #<?= e($store['account_id']) ?></p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $store['account_status'] === 'ACTIVE' ? 'text-emerald-700' : 'text-neutral-500' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $store['account_status'] === 'ACTIVE' ? 'bg-emerald-500' : 'bg-neutral-400' ?>"></span>
                        <?= e($store['account_status']) ?>
                    </span>
                </div>

                <div class="mt-5 border-t border-neutral-100 pt-4">
                    <p class="text-xs text-neutral-400">Admin aktif</p>
                    <p class="mt-1 text-sm text-neutral-700"><?= e($activeAdminUsernames ? implode(', ', $activeAdminUsernames) : 'Belum ada admin aktif') ?></p>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-[1.15fr_.85fr] gap-5">
            <div class="bento-card overflow-hidden">
                <div class="px-5 py-4 md:px-6 border-b border-neutral-100">
                    <h2 class="font-semibold">Users & Membership</h2>
                    <p class="text-xs text-neutral-400 mt-1">Seluruh membership Store beserta status user.</p>
                </div>

                <?php if (!$memberships): ?>
                    <div class="px-5 py-10 text-center text-sm text-neutral-400">Belum ada membership.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[650px] text-sm">
                            <thead class="bg-neutral-50/70 text-xs text-neutral-400">
                                <tr>
                                    <th class="text-left font-medium px-5 md:px-6 py-3">User</th>
                                    <th class="text-left font-medium px-5 py-3">Username</th>
                                    <th class="text-left font-medium px-5 py-3">Role</th>
                                    <th class="text-left font-medium px-5 py-3">Membership</th>
                                    <th class="text-left font-medium px-5 md:px-6 py-3">User</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                            <?php foreach ($memberships as $membership): ?>
                                <?php
                                $membershipActive = $membership['membership_status'] === 'ACTIVE';
                                $userActive = $membership['user_status'] === 'ACTIVE';
                                ?>
                                <tr class="hover:bg-neutral-50/70">
                                    <td class="px-5 md:px-6 py-4">
                                        <div class="font-medium text-neutral-800"><?= e($membership['user_name']) ?></div>
                                        <?php if ($membership['email']): ?>
                                            <div class="text-xs text-neutral-400 mt-1"><?= e($membership['email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-neutral-600"><?= e($membership['username']) ?></td>
                                    <td class="px-5 py-4 text-neutral-600"><?= e($membership['role']) ?></td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $membershipActive ? 'text-emerald-700' : 'text-neutral-500' ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $membershipActive ? 'bg-emerald-500' : 'bg-neutral-400' ?>"></span>
                                            <?= e($membership['membership_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-5 md:px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $userActive ? 'text-emerald-700' : 'text-neutral-500' ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $userActive ? 'bg-emerald-500' : 'bg-neutral-400' ?>"></span>
                                            <?= e($membership['user_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bento-card overflow-hidden">
                <div class="px-5 py-4 md:px-6 border-b border-neutral-100">
                    <h2 class="font-semibold">Aktivitas Store</h2>
                    <p class="text-xs text-neutral-400 mt-1">10 aktivitas terbaru yang terkait Store ini.</p>
                </div>

                <?php if (!$activities): ?>
                    <div class="px-5 py-10 text-center text-sm text-neutral-400">Belum ada aktivitas yang tercatat.</div>
                <?php else: ?>
                    <div class="divide-y divide-neutral-100">
                        <?php foreach ($activities as $activity): ?>
                            <div class="px-5 py-4">
                                <p class="text-sm font-medium text-neutral-800"><?= e($activity['description'] ?: strtoupper((string) $activity['action'])) ?></p>
                                <p class="text-xs text-neutral-400 mt-1">
                                    <?= e($activity['user_name'] ?: 'System') ?>
                                    <span class="mx-1">•</span>
                                    <?= e(strtoupper((string) $activity['action'])) ?>
                                </p>
                                <p class="text-[11px] text-neutral-400 mt-1"><?= e(date('d M Y, H:i', strtotime($activity['created_at'] ?? 'now'))) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
