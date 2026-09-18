<?php
require_once __DIR__ . '/../../includes/developer_auth.php';

$pageTitle = 'Detail Account';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$accountId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$accountId || $accountId < 1) {
    header('Location: /developer/accounts/');
    exit;
}

$stmt = $pdo->prepare("SELECT a.id, a.name, a.status, a.created_at, a.updated_at FROM accounts a WHERE a.id = :account_id LIMIT 1");
$stmt->execute([':account_id' => $accountId]);
$account = $stmt->fetch();

if (!$account) {
    http_response_code(404);
    $pageTitle = 'Account Tidak Ditemukan';
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../includes/sidebar.php';
    ?>
    <main class="lg:ml-64 pt-16 min-h-screen"><div class="p-4 md:p-8 max-w-5xl mx-auto"><div class="bento-card p-8 text-center"><p class="font-medium">Account tidak ditemukan.</p><a href="/developer/accounts/" class="inline-flex mt-4 px-4 py-2 rounded-xl bg-neutral-900 text-white text-sm">Kembali ke Accounts</a></div></div></main>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("\n    SELECT\n        s.id, s.name, s.slug, s.status, s.phone, s.address, s.created_at,\n        COUNT(DISTINCT su.id) AS user_count,\n        COALESCE(GROUP_CONCAT(DISTINCT CASE WHEN su.role = 'ADMIN' THEN u.username END ORDER BY u.username SEPARATOR ', '), '') AS admin_usernames\n    FROM stores s\n    LEFT JOIN store_users su ON su.store_id = s.id AND su.status = 'ACTIVE'\n    LEFT JOIN users u ON u.id = su.user_id\n    JOIN accounts a ON a.id = s.account_id\n    WHERE a.id = :account_id\n    GROUP BY s.id, s.name, s.slug, s.status, s.phone, s.address, s.created_at\n    ORDER BY s.id ASC\n");
$stmt->execute([':account_id' => $accountId]);
$stores = $stmt->fetchAll();

$stmt = $pdo->prepare("\n    SELECT\n        al.id, al.action, al.table_name, al.record_id, al.description, al.created_at,\n        u.name AS user_name, s.name AS store_name\n    FROM audit_logs al\n    INNER JOIN stores s ON s.id = al.store_id\n    LEFT JOIN users u ON u.id = al.user_id\n    WHERE s.account_id = :account_id\n    ORDER BY al.created_at DESC, al.id DESC\n    LIMIT 10\n");
$stmt->execute([':account_id' => $accountId]);
$activities = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="lg:ml-64 pt-16 min-h-screen">
    <div class="p-4 md:p-8 max-w-7xl mx-auto">
        <div class="mb-6">
            <a href="/developer/accounts/" class="inline-flex items-center gap-1.5 text-xs text-neutral-500 hover:text-neutral-900 mb-4"><i data-lucide="arrow-left" class="w-4 h-4"></i>Kembali ke Accounts</a>
            <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer</p>
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mt-1">
                <div>
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight"><?= e($account['name']) ?></h1>
                    <p class="text-sm text-neutral-500 mt-2">Detail account #<?= e($account['id']) ?> dan seluruh toko yang terhubung.</p>
                </div>
                <span class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white border border-neutral-200 text-xs font-medium <?= $account['status'] === 'ACTIVE' ? 'text-emerald-700' : 'text-neutral-500' ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $account['status'] === 'ACTIVE' ? 'bg-emerald-500' : 'bg-neutral-400' ?>"></span><?= e($account['status']) ?>
                </span>
            </div>
        </div>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bento-card p-5"><p class="text-xs uppercase tracking-wider text-neutral-400">Account ID</p><p class="text-2xl font-semibold mt-3">#<?= e($account['id']) ?></p></div>
            <div class="bento-card p-5"><p class="text-xs uppercase tracking-wider text-neutral-400">Total Toko</p><p class="text-2xl font-semibold mt-3"><?= number_format(count($stores)) ?></p></div>
            <div class="bento-card p-5"><p class="text-xs uppercase tracking-wider text-neutral-400">Terdaftar</p><p class="text-2xl font-semibold mt-3"><?= e(date('d M Y', strtotime($account['created_at'] ?? 'now'))) ?></p></div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-[1.25fr_.75fr] gap-5">
            <div class="bento-card overflow-hidden">
                <div class="px-5 py-4 md:px-6 border-b border-neutral-100"><h2 class="font-semibold">Toko</h2><p class="text-xs text-neutral-400 mt-1">Seluruh toko yang dimiliki account ini.</p></div>
                <?php if (!$stores): ?>
                    <div class="px-5 py-10 text-center text-sm text-neutral-400">Belum ada toko.</div>
                <?php else: ?>
                    <div class="divide-y divide-neutral-100">
                        <?php foreach ($stores as $store): ?>
                            <div class="px-5 py-4 md:px-6 flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="font-medium"><?= e($store['name']) ?></div>
                                    <div class="text-xs text-neutral-400 mt-1">/<?= e($store['slug']) ?> · <?= number_format((int) $store['user_count']) ?> user</div>
                                    <?php if ($store['admin_usernames']): ?><div class="text-xs text-neutral-500 mt-2">Admin: <?= e($store['admin_usernames']) ?></div><?php endif; ?>
                                </div>
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $store['status'] === 'ACTIVE' ? 'text-emerald-700' : 'text-neutral-500' ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $store['status'] === 'ACTIVE' ? 'bg-emerald-500' : 'bg-neutral-400' ?>"></span><?= e($store['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bento-card overflow-hidden">
                <div class="px-5 py-4 md:px-6 border-b border-neutral-100"><h2 class="font-semibold">Aktivitas Account</h2><p class="text-xs text-neutral-400 mt-1">Aktivitas yang terkait dengan toko account ini.</p></div>
                <?php if (!$activities): ?>
                    <div class="px-5 py-10 text-center text-sm text-neutral-400">Belum ada aktivitas yang tercatat.</div>
                <?php else: ?>
                    <div class="divide-y divide-neutral-100">
                        <?php foreach ($activities as $activity): ?>
                            <div class="px-5 py-4">
                                <p class="text-sm font-medium text-neutral-800"><?= e($activity['description'] ?: strtoupper((string) $activity['action'])) ?></p>
                                <p class="text-xs text-neutral-400 mt-1"><?= e($activity['user_name'] ?: 'System') ?><?php if ($activity['store_name']): ?> <span class="mx-1">•</span><?= e($activity['store_name']) ?><?php endif; ?></p>
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
