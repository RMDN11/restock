<?php
require_once __DIR__ . '/../../includes/developer_auth.php';
$pageTitle = 'Pembayaran';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function rupiah($value): string { return 'Rp ' . number_format((float) $value, 0, ',', '.'); }

$search = trim((string) ($_GET['search'] ?? ''));
$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
if (!in_array($status, ['', 'PENDING', 'VERIFIED', 'REJECTED', 'EXPIRED'], true)) $status = '';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(a.name LIKE :search_account OR s.name LIKE :search_store OR p.name LIKE :search_package OR u.name LIKE :search_user OR u.email LIKE :search_email)';
    $params[':search_account'] = '%' . $search . '%';
    $params[':search_store'] = '%' . $search . '%';
    $params[':search_package'] = '%' . $search . '%';
    $params[':search_user'] = '%' . $search . '%';
    $params[':search_email'] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = 'pay.status = :status';
    $params[':status'] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT
    pay.id, pay.amount, pay.payment_method, pay.status, pay.expired_at, pay.created_at,
    a.id AS account_id, a.name AS account_name,
    s.id AS store_id, s.name AS store_name,
    p.id AS package_id, p.name AS package_name,
    u.name AS user_name, u.email AS user_email
FROM payments pay
INNER JOIN accounts a ON a.id = pay.account_id
INNER JOIN stores s ON s.id = pay.store_id
INNER JOIN packages p ON p.id = pay.package_id
LEFT JOIN store_users su ON su.store_id = s.id AND su.role = 'ADMIN' AND su.status = 'ACTIVE'
LEFT JOIN users u ON u.id = su.user_id AND u.status = 'ACTIVE'
$whereSql
GROUP BY pay.id, pay.amount, pay.payment_method, pay.status, pay.expired_at, pay.created_at,
a.id, a.name, s.id, s.name, p.id, p.name, u.name, u.email
ORDER BY CASE pay.status WHEN 'PENDING' THEN 0 WHEN 'REJECTED' THEN 1 WHEN 'VERIFIED' THEN 2 WHEN 'EXPIRED' THEN 3 ELSE 4 END, pay.id DESC");
$stmt->execute($params);
$payments = $stmt->fetchAll();

$flash = $_SESSION['payment_flash_success'] ?? null; unset($_SESSION['payment_flash_success']);
$error = $_SESSION['payment_flash_error'] ?? null; unset($_SESSION['payment_flash_error']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="lg:ml-64 pt-16 min-h-screen">
<div class="p-4 md:p-8 max-w-7xl mx-auto">
<div class="mb-6">
<p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer · Finance</p>
<h1 class="text-2xl md:text-3xl font-semibold tracking-tight mt-1">Pembayaran</h1>
<p class="text-sm text-neutral-500 mt-2">Review pembayaran transfer bank dan verifikasi pembayaran masuk.</p>
</div>
<?php if ($flash): ?><div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($error) ?></div><?php endif; ?>
<section class="bento-card p-4 md:p-5 mb-5">
<form method="get" class="grid grid-cols-1 md:grid-cols-[1fr_180px_auto] gap-3">
<input type="search" name="search" value="<?= e($search) ?>" placeholder="Cari account, store, paket, user, email..." class="h-11 rounded-xl border border-neutral-200 bg-white px-4 text-sm outline-none">
<select name="status" class="h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm outline-none">
<option value="">Semua status</option>
<?php foreach (['PENDING','VERIFIED','REJECTED','EXPIRED'] as $opt): ?><option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= $opt ?></option><?php endforeach; ?>
</select>
<button class="h-11 px-5 rounded-xl bg-neutral-100 text-neutral-800 text-sm font-medium">Filter</button>
</form>
</section>
<section class="bento-card overflow-hidden">
<?php if (!$payments): ?>
<div class="px-5 py-12 text-center"><p class="font-medium">Belum ada pembayaran.</p><p class="text-xs text-neutral-400 mt-1">Payment dari checkout akan muncul di sini.</p></div>
<?php else: ?>
<div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-sm">
<thead class="bg-neutral-50/70 text-xs text-neutral-400"><tr>
<th class="text-left font-medium px-5 py-3">ID</th><th class="text-left font-medium px-5 py-3">Pelanggan</th><th class="text-left font-medium px-5 py-3">Store</th><th class="text-left font-medium px-5 py-3">Paket</th><th class="text-right font-medium px-5 py-3">Jumlah</th><th class="text-left font-medium px-5 py-3">Status</th><th class="text-right font-medium px-5 py-3">Detail</th>
</tr></thead>
<tbody class="divide-y divide-neutral-100">
<?php foreach ($payments as $payment): ?>
<?php $statusClass = match ($payment['status']) { 'PENDING'=>'text-amber-700 bg-amber-50', 'VERIFIED'=>'text-emerald-700 bg-emerald-50', 'REJECTED'=>'text-red-700 bg-red-50', default=>'text-neutral-600 bg-neutral-100' }; ?>
<tr class="hover:bg-neutral-50/70">
<td class="px-5 py-4"><div class="font-medium">#<?= e($payment['id']) ?></div><div class="text-xs text-neutral-400 mt-1"><?= e(date('d M Y, H:i', strtotime($payment['created_at']))) ?></div></td>
<td class="px-5 py-4"><div class="font-medium"><?= e($payment['user_name'] ?: $payment['account_name']) ?></div><div class="text-xs text-neutral-400 mt-1"><?= e($payment['user_email'] ?: '-') ?></div></td>
<td class="px-5 py-4"><?= e($payment['store_name']) ?></td>
<td class="px-5 py-4"><?= e($payment['package_name']) ?></td>
<td class="px-5 py-4 text-right font-semibold"><?= rupiah($payment['amount']) ?></td>
<td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium <?= $statusClass ?>"><?= e($payment['status']) ?></span></td>
<td class="px-5 py-4 text-right"><a href="/developer/payments/view.php?id=<?= (int) $payment['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-neutral-100 text-xs font-medium">Lihat</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
</div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>