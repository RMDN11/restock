<?php
require_once __DIR__ . '/../../includes/developer_auth.php';

$pageTitle = 'Accounts';

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
    $where[] = '(a.name LIKE :search_name OR owner_usernames.usernames LIKE :search_username)';
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_username'] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = 'a.status = :status';
    $params[':status'] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        a.id,
        a.name,
        a.status,
        a.created_at,
        COUNT(DISTINCT s.id) AS store_count,
        COALESCE(owner_usernames.usernames, '') AS usernames
    FROM accounts a
    LEFT JOIN stores s
        ON s.account_id = a.id
    LEFT JOIN (
        SELECT
            s2.account_id,
            GROUP_CONCAT(DISTINCT u2.username ORDER BY u2.username SEPARATOR ', ') AS usernames
        FROM store_users su2
        INNER JOIN stores s2 ON s2.id = su2.store_id
        INNER JOIN users u2 ON u2.id = su2.user_id
        WHERE su2.role = 'ADMIN'
          AND su2.status = 'ACTIVE'
          AND u2.status = 'ACTIVE'
        GROUP BY s2.account_id
    ) owner_usernames
        ON owner_usernames.account_id = a.id
    $whereSql
    GROUP BY a.id, a.name, a.status, a.created_at, owner_usernames.usernames
    ORDER BY a.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="lg:ml-64 pt-16 min-h-screen">
    <div class="p-4 md:p-8 max-w-7xl mx-auto">
        <div class="mb-6">
            <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer</p>
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mt-1">
                <div>
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">Accounts</h1>
                    <p class="text-sm text-neutral-500 mt-2">Seluruh account yang terdaftar di platform RESTOCK.</p>
                </div>
                <span class="inline-flex w-fit items-center gap-2 px-3 py-2 rounded-xl bg-white border border-neutral-200 text-xs text-neutral-500">
                    <i data-lucide="users" class="w-4 h-4"></i><?= number_format(count($accounts)) ?> account
                </span>
            </div>
        </div>

        <section class="bento-card p-4 md:p-5 mb-5">
            <form method="get" class="grid grid-cols-1 md:grid-cols-[1fr_180px_auto] gap-3">
                <label class="relative block">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400"></i>
                    <input type="search" name="search" value="<?= e($search) ?>" placeholder="Cari nama account atau username..." class="w-full h-11 rounded-xl border border-neutral-200 bg-white pl-10 pr-3 text-sm outline-none focus:border-neutral-400">
                </label>
                <select name="status" class="h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm outline-none focus:border-neutral-400">
                    <option value="">Semua status</option>
                    <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                    <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                </select>
                <button type="submit" class="h-11 px-5 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800">Filter</button>
            </form>
        </section>

        <section class="bento-card overflow-hidden">
            <div class="px-5 py-4 md:px-6 border-b border-neutral-100">
                <h2 class="font-semibold">Daftar Account</h2>
                <p class="text-xs text-neutral-400 mt-1">Data platform, tidak dibatasi store aktif Developer.</p>
            </div>
            <?php if (!$accounts): ?>
                <div class="px-5 py-12 text-center">
                    <div class="w-11 h-11 rounded-2xl bg-neutral-100 flex items-center justify-center mx-auto">
                        <i data-lucide="users" class="w-5 h-5 text-neutral-500"></i>
                    </div>
                    <p class="text-sm font-medium mt-3">Account tidak ditemukan</p>
                    <p class="text-xs text-neutral-400 mt-1">Coba ubah kata kunci atau filter status.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-neutral-50/70 text-xs text-neutral-400">
                            <tr>
                                <th class="text-left font-medium px-5 md:px-6 py-3">Account</th>
                                <th class="text-left font-medium px-5 py-3">Username Admin</th>
                                <th class="text-center font-medium px-5 py-3">Toko</th>
                                <th class="text-left font-medium px-5 py-3">Status</th>
                                <th class="text-left font-medium px-5 py-3">Dibuat</th>
                                <th class="text-right font-medium px-5 md:px-6 py-3">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                        <?php foreach ($accounts as $account): ?>
                            <tr class="hover:bg-neutral-50/70">
                                <td class="px-5 md:px-6 py-4">
                                    <div class="font-medium text-neutral-800"><?= e($account['name']) ?></div>
                                    <div class="text-xs text-neutral-400 mt-1">Account #<?= e($account['id']) ?></div>
                                </td>
                                <td class="px-5 py-4 text-neutral-600"><?= e($account['usernames'] ?: '-') ?></td>
                                <td class="px-5 py-4 text-center font-medium"><?= number_format((int) $account['store_count']) ?></td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $account['status'] === 'ACTIVE' ? 'text-emerald-700' : 'text-neutral-500' ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $account['status'] === 'ACTIVE' ? 'bg-emerald-500' : 'bg-neutral-400' ?>"></span><?= e($account['status']) ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-xs text-neutral-500"><?= e(date('d M Y', strtotime($account['created_at'] ?? 'now'))) ?></td>
                                <td class="px-5 md:px-6 py-4 text-right">
                                    <a href="/developer/accounts/view.php?id=<?= e($account['id']) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-neutral-100 text-xs font-medium text-neutral-700 hover:bg-neutral-200">
                                        Lihat <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
