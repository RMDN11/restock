<?php
require_once __DIR__ . '/../includes/developer_auth.php';

$pageTitle = 'Subscription';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function formatDateTime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d M Y, H:i', $timestamp) : e($value);
}

function statusClass(string $status): string
{
    return match ($status) {
        'ACTIVE' => 'text-emerald-700 bg-emerald-50',
        'EXPIRED' => 'text-neutral-600 bg-neutral-100',
        'CANCELLED' => 'text-red-700 bg-red-50',
        default => 'text-neutral-600 bg-neutral-100',
    };
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = strtoupper(trim((string) ($_GET['status'] ?? '')));

$allowedStatuses = ['ACTIVE', 'EXPIRED', 'CANCELLED'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

/*
|--------------------------------------------------------------------------
| Sinkronisasi subscription expired
|--------------------------------------------------------------------------
| Lifecycle Task 8 tidak membutuhkan cron untuk correctness saat dibaca.
|--------------------------------------------------------------------------
*/
$pdo->exec(
    "UPDATE subscriptions
     SET status = 'EXPIRED',
         updated_at = CURRENT_TIMESTAMP
     WHERE status = 'ACTIVE'
       AND ends_at <= CURRENT_TIMESTAMP"
);

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        CAST(sub.id AS CHAR) LIKE :search
        OR a.name LIKE :search
        OR s.name LIKE :search
        OR p.name LIKE :search
        OR u.name LIKE :search
        OR u.email LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

if ($status !== '') {
    $where[] = "sub.status = :status";
    $params[':status'] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT
        sub.id,
        sub.account_id,
        sub.store_id,
        sub.package_id,
        sub.payment_id,
        sub.starts_at,
        sub.ends_at,
        sub.status,
        sub.created_at,
        a.name AS account_name,
        s.name AS store_name,
        p.name AS package_name,
        p.price AS package_price,
        p.duration_days,
        u.name AS user_name,
        u.email AS user_email
     FROM subscriptions sub
     INNER JOIN accounts a ON a.id = sub.account_id
     INNER JOIN stores s ON s.id = sub.store_id
     INNER JOIN packages p ON p.id = sub.package_id
     LEFT JOIN store_users su
        ON su.store_id = s.id
       AND su.role = 'ADMIN'
       AND su.status = 'ACTIVE'
     LEFT JOIN users u
        ON u.id = su.user_id
       AND u.status = 'ACTIVE'
     {$whereSql}
     ORDER BY
        CASE sub.status WHEN 'ACTIVE' THEN 0 WHEN 'EXPIRED' THEN 1 ELSE 2 END,
        sub.ends_at DESC,
        sub.id DESC
     LIMIT 200"
);

$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

$totalSubscriptions = count($subscriptions);
$activeSubscriptions = 0;
$expiredSubscriptions = 0;
$cancelledSubscriptions = 0;
$totalFilteredAmount = 0.0;

foreach ($subscriptions as $subscription) {
    switch ($subscription['status']) {
        case 'ACTIVE':
            $activeSubscriptions++;
            break;
        case 'EXPIRED':
            $expiredSubscriptions++;
            break;
        case 'CANCELLED':
            $cancelledSubscriptions++;
            break;
    }

    if ($subscription['status'] === 'ACTIVE' || $subscription['status'] === 'EXPIRED') {
        $totalFilteredAmount += (float) $subscription['package_price'];
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="lg:ml-64 pt-16 min-h-screen">
    <div class="p-4 md:p-8 max-w-7xl mx-auto">
        <div class="mb-6">
            <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer · Finance</p>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight mt-1">Subscription</h1>
            <p class="text-sm text-neutral-500 mt-2">Pantau periode subscription account dan store.</p>
        </div>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bento-card p-5">
                <p class="text-sm text-neutral-500">Nilai Paket</p>
                <p class="text-2xl font-semibold tracking-tight mt-3"><?= rupiah($totalFilteredAmount) ?></p>
                <p class="text-xs text-neutral-400 mt-1">ACTIVE + EXPIRED sesuai filter</p>
            </div>
            <div class="bento-card p-5">
                <p class="text-sm text-neutral-500">Total</p>
                <p class="text-3xl font-semibold tracking-tight mt-3"><?= number_format($totalSubscriptions) ?></p>
                <p class="text-xs text-neutral-400 mt-1">Hasil sesuai filter</p>
            </div>
            <div class="bento-card p-5">
                <p class="text-sm text-neutral-500">Aktif</p>
                <p class="text-3xl font-semibold tracking-tight mt-3"><?= number_format($activeSubscriptions) ?></p>
                <p class="text-xs text-neutral-400 mt-1">Masih dapat mengakses aplikasi</p>
            </div>
            <div class="bento-card p-5">
                <p class="text-sm text-neutral-500">Expired</p>
                <p class="text-3xl font-semibold tracking-tight mt-3"><?= number_format($expiredSubscriptions) ?></p>
                <p class="text-xs text-neutral-400 mt-1">Periode sudah berakhir</p>
            </div>
            <div class="bento-card p-5">
                <p class="text-sm text-neutral-500">Cancelled</p>
                <p class="text-3xl font-semibold tracking-tight mt-3"><?= number_format($cancelledSubscriptions) ?></p>
                <p class="text-xs text-neutral-400 mt-1">Subscription dibatalkan</p>
            </div>
        </section>

        <section class="bento-card p-4 md:p-5 mb-5">
            <form method="get" class="grid grid-cols-1 md:grid-cols-[1fr_180px_auto] gap-3">
                <input
                    type="search"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Cari account, store, paket, user, email..."
                    class="min-h-11 rounded-xl border border-neutral-200 px-3 text-sm outline-none focus:border-neutral-400"
                >
                <select
                    name="status"
                    class="min-h-11 rounded-xl border border-neutral-200 px-3 text-sm bg-white"
                >
                    <option value="">Semua status</option>
                    <?php foreach ($allowedStatuses as $option): ?>
                        <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                            <?= e($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button
                    type="submit"
                    class="min-h-11 rounded-xl bg-neutral-900 px-5 text-sm font-semibold text-white"
                >
                    Filter
                </button>
            </form>
        </section>

        <section class="bento-card overflow-hidden">
            <div class="px-5 py-4 md:px-6 border-b border-neutral-100 flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold">Daftar Subscription</h2>
                    <p class="text-xs text-neutral-400 mt-1">Status expired disinkronkan saat halaman dibuka.</p>
                </div>
                <span class="text-xs text-neutral-400"><?= number_format($totalSubscriptions) ?> data</span>
            </div>

            <?php if (!$subscriptions): ?>
                <div class="px-5 py-12 text-center">
                    <div class="w-11 h-11 rounded-2xl bg-neutral-100 flex items-center justify-center mx-auto">
                        <i data-lucide="credit-card" class="w-5 h-5 text-neutral-500"></i>
                    </div>
                    <p class="text-sm font-medium mt-3">Belum ada subscription</p>
                    <p class="text-xs text-neutral-400 mt-1">Subscription yang diaktifkan melalui pembayaran terverifikasi akan muncul di sini.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-neutral-50 border-b border-neutral-100">
                            <tr class="text-left text-xs text-neutral-400">
                                <th class="px-5 md:px-6 py-3 font-medium">Subscription</th>
                                <th class="px-5 md:px-6 py-3 font-medium">Account / Store</th>
                                <th class="px-5 md:px-6 py-3 font-medium">Paket</th>
                                <th class="px-5 md:px-6 py-3 font-medium">Periode</th>
                                <th class="px-5 md:px-6 py-3 font-medium">Status</th>
                                <th class="px-5 md:px-6 py-3 font-medium text-right">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <?php foreach ($subscriptions as $subscription): ?>
                                <tr class="align-top hover:bg-neutral-50/70">
                                    <td class="px-5 md:px-6 py-4">

                                        <p class="font-medium text-neutral-900">#<?= e($subscription['id']) ?></p>
                                        <p class="text-xs text-neutral-400 mt-1">
                                            Payment #<?= e($subscription['payment_id']) ?>
                                        </p>
                                    </td>
                                    <td class="px-5 md:px-6 py-4">
                                        <p class="font-medium text-neutral-900"><?= e($subscription['account_name']) ?></p>
                                        <p class="text-xs text-neutral-500 mt-1"><?= e($subscription['store_name']) ?></p>
                                        <?php if (!empty($subscription['user_name'])): ?>
                                            <p class="text-xs text-neutral-400 mt-1">
                                                <?= e($subscription['user_name']) ?>
                                                <?php if (!empty($subscription['user_email'])): ?>
                                                    · <?= e($subscription['user_email']) ?>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 md:px-6 py-4">
                                        <p class="font-medium text-neutral-900"><?= e($subscription['package_name']) ?></p>
                                        <p class="text-xs text-neutral-400 mt-1">
                                            <?= (int) $subscription['duration_days'] ?> hari
                                        </p>
                                    </td>
                                    <td class="px-5 md:px-6 py-4">
                                        <p class="text-xs text-neutral-400">Mulai</p>
                                        <p class="font-medium"><?= e(formatDateTime($subscription['starts_at'])) ?></p>
                                        <p class="text-xs text-neutral-400 mt-3">Berakhir</p>
                                        <p class="font-medium"><?= e(formatDateTime($subscription['ends_at'])) ?></p>
                                    </td>
                                    <td class="px-5 md:px-6 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium <?= e(statusClass((string) $subscription['status'])) ?>">
                                            <?= e($subscription['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-5 md:px-6 py-4 text-right">
                                        <a
                                            href="/developer/payments/view.php?id=<?= (int) $subscription['payment_id'] ?>"
                                            class="inline-flex min-h-9 items-center justify-center rounded-lg border border-neutral-200 bg-white px-3 text-xs font-medium text-neutral-700 hover:bg-neutral-50"
                                        >
                                            Lihat Payment
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
