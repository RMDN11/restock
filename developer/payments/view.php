<?php
require_once __DIR__ . '/../../includes/developer_auth.php';
$pageTitle = 'Detail Pembayaran';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function rupiah($value): string { return 'Rp ' . number_format((float) $value, 0, ',', '.'); }
$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$paymentId || $paymentId < 1) { header('Location: /developer/payments/'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['payment_flash_error'] = 'Sesi keamanan tidak valid.';
        header('Location: /developer/payments/view.php?id=' . $paymentId); exit;
    }
    $action = strtoupper(trim((string) ($_POST['action'] ?? '')));
    if (!in_array($action, ['VERIFY','REJECT'], true)) {
        $_SESSION['payment_flash_error'] = 'Aksi tidak valid.';
        header('Location: /developer/payments/view.php?id=' . $paymentId); exit;
    }
    $stmt = $pdo->prepare("SELECT id, status FROM payments WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $paymentId]);
    $payment = $stmt->fetch();
    if (!$payment) {
        $_SESSION['payment_flash_error'] = 'Pembayaran tidak ditemukan.';
        header('Location: /developer/payments/'); exit;
    }
    if ($payment['status'] !== 'PENDING') {
        $_SESSION['payment_flash_error'] = 'Pembayaran ini sudah diproses.';
        header('Location: /developer/payments/view.php?id=' . $paymentId); exit;
    }

    if ($action === 'VERIFY') {
        $proofStmt = $pdo->prepare("SELECT proof_file FROM payments WHERE id = :payment_id LIMIT 1");
        $proofStmt->execute([':payment_id' => $paymentId]);
        $proofFile = $proofStmt->fetchColumn();

        if (empty($proofFile)) {
            $_SESSION['payment_flash_error'] = 'Pembayaran belum memiliki bukti transfer.';
            header('Location: /developer/payments/view.php?id=' . $paymentId);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE payments
                SET status='VERIFIED', verified_by=:verified_by, verified_at=CURRENT_TIMESTAMP,
                    rejection_reason=NULL, updated_at=CURRENT_TIMESTAMP
                WHERE id=:id AND status='PENDING'");
            $stmt->execute([':verified_by'=>$authUserId, ':id'=>$paymentId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Pembayaran sudah diproses.');
            }

            $paymentInfoStmt = $pdo->prepare("SELECT account_id, store_id, package_id FROM payments WHERE id=:id LIMIT 1");
            $paymentInfoStmt->execute([':id'=>$paymentId]);
            $paymentInfo = $paymentInfoStmt->fetch();
            if (!$paymentInfo) {
                throw new RuntimeException('Data pembayaran tidak ditemukan.');
            }

            $existingStmt = $pdo->prepare("SELECT id FROM subscriptions WHERE payment_id=:payment_id LIMIT 1");
            $existingStmt->execute([':payment_id'=>$paymentId]);

            if (!$existingStmt->fetchColumn()) {
                $startStmt = $pdo->prepare("SELECT GREATEST(
                    CURRENT_TIMESTAMP,
                    COALESCE(MAX(CASE WHEN status='ACTIVE' AND ends_at>CURRENT_TIMESTAMP THEN ends_at END), CURRENT_TIMESTAMP)
                ) FROM subscriptions WHERE account_id=:account_id AND store_id=:store_id");
                $startStmt->execute([
                    ':account_id'=>$paymentInfo['account_id'],
                    ':store_id'=>$paymentInfo['store_id'],
                ]);
                $startsAt = $startStmt->fetchColumn();

                $durationStmt = $pdo->prepare("SELECT duration_days FROM packages WHERE id=:package_id LIMIT 1");
                $durationStmt->execute([':package_id'=>$paymentInfo['package_id']]);
                $durationDays = (int)$durationStmt->fetchColumn();
                if ($durationDays < 1) {
                    throw new RuntimeException('Durasi paket tidak valid.');
                }

                $insert = $pdo->prepare("INSERT INTO subscriptions
                    (account_id, store_id, package_id, payment_id, starts_at, ends_at, status, created_at, updated_at)
                    VALUES
                    (:account_id, :store_id, :package_id, :payment_id, :starts_at,
                     DATE_ADD(:ends_at_base, INTERVAL :duration_days DAY),
                     'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $insert->execute([
                    ':account_id'=>$paymentInfo['account_id'],
                    ':store_id'=>$paymentInfo['store_id'],
                    ':package_id'=>$paymentInfo['package_id'],
                    ':payment_id'=>$paymentId,
                    ':starts_at'=>$startsAt,
                    ':ends_at_base'=>$startsAt,
                    ':duration_days'=>$durationDays,
                ]);
            }

            $pdo->commit();
            $_SESSION['payment_flash_success'] = 'Pembayaran diverifikasi dan subscription diaktifkan.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['payment_flash_error'] = 'Verifikasi gagal diproses. Tidak ada perubahan yang disimpan.';
        }
    } else {
        $reason = trim((string) ($_POST['rejection_reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 500) {
            $_SESSION['payment_flash_error'] = 'Alasan penolakan wajib diisi dan maksimal 500 karakter.';
            header('Location: /developer/payments/view.php?id=' . $paymentId); exit;
        }
        $stmt = $pdo->prepare("UPDATE payments
            SET status='REJECTED', verified_by=:verified_by, verified_at=CURRENT_TIMESTAMP,
                rejection_reason=:reason, updated_at=CURRENT_TIMESTAMP
            WHERE id=:id AND status='PENDING'");
        $stmt->execute([':verified_by'=>$authUserId, ':id'=>$paymentId, ':reason'=>$reason]);
        $_SESSION['payment_flash_success'] = 'Pembayaran berhasil ditolak.';
    }
    header('Location: /developer/payments/view.php?id=' . $paymentId); exit;
}

$stmt = $pdo->prepare("SELECT
    pay.id, pay.amount, pay.payment_method, pay.status, pay.proof_file, pay.verified_at,
    pay.rejection_reason, pay.expired_at, pay.created_at,
    a.id AS account_id, a.name AS account_name,
    s.id AS store_id, s.name AS store_name,
    p.name AS package_name, p.price AS package_current_price, p.duration_days,
    u.name AS user_name, u.username, u.email AS user_email,
    vu.name AS verifier_name
FROM payments pay
INNER JOIN accounts a ON a.id=pay.account_id
INNER JOIN stores s ON s.id=pay.store_id
INNER JOIN packages p ON p.id=pay.package_id
LEFT JOIN store_users su ON su.store_id=s.id AND su.role='ADMIN' AND su.status='ACTIVE'
LEFT JOIN users u ON u.id=su.user_id AND u.status='ACTIVE'
LEFT JOIN users vu ON vu.id=pay.verified_by
WHERE pay.id=:id LIMIT 1");
$stmt->execute([':id'=>$paymentId]);
$payment = $stmt->fetch();

if (!$payment) { http_response_code(404); exit('Pembayaran tidak ditemukan.'); }
$statusClass = match ($payment['status']) { 'PENDING'=>'text-amber-700 bg-amber-50', 'VERIFIED'=>'text-emerald-700 bg-emerald-50', 'REJECTED'=>'text-red-700 bg-red-50', default=>'text-neutral-600 bg-neutral-100' };

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="lg:ml-64 pt-16 min-h-screen"><div class="p-4 md:p-8 max-w-6xl mx-auto">
<div class="mb-6"><a href="/developer/payments/" class="inline-flex items-center gap-1.5 text-xs text-neutral-500 mb-4">← Kembali ke Pembayaran</a>
<p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer · Finance</p>
<div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mt-1"><div><h1 class="text-2xl md:text-3xl font-semibold">Pembayaran #<?= e($payment['id']) ?></h1><p class="text-sm text-neutral-500 mt-2"><?= e($payment['payment_method']) ?></p></div><span class="inline-flex rounded-full px-3 py-1.5 text-xs font-medium <?= $statusClass ?>"><?= e($payment['status']) ?></span></div></div>
<div class="grid grid-cols-1 xl:grid-cols-[1.05fr_.95fr] gap-5">
<section class="bento-card p-5 md:p-6"><h2 class="font-semibold">Ringkasan Pembayaran</h2>
<dl class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-5 text-sm">
<div><dt class="text-xs text-neutral-400">Jumlah</dt><dd class="mt-1 text-xl font-semibold"><?= rupiah($payment['amount']) ?></dd></div>
<div><dt class="text-xs text-neutral-400">Expired</dt><dd class="mt-1 font-medium"><?= e($payment['expired_at'] ? date('d M Y, H:i', strtotime($payment['expired_at'])) : '-') ?></dd></div>
<div><dt class="text-xs text-neutral-400">Paket</dt><dd class="mt-1 font-medium"><?= e($payment['package_name']) ?></dd></div>
<div><dt class="text-xs text-neutral-400">Harga Paket Saat Ini</dt><dd class="mt-1 font-medium"><?= rupiah($payment['package_current_price']) ?></dd></div>
<div><dt class="text-xs text-neutral-400">Account</dt><dd class="mt-1 font-medium"><?= e($payment['account_name']) ?> (#<?= e($payment['account_id']) ?>)</dd></div>
<div><dt class="text-xs text-neutral-400">Store</dt><dd class="mt-1 font-medium"><?= e($payment['store_name']) ?></dd></div>
<div><dt class="text-xs text-neutral-400">User</dt><dd class="mt-1 font-medium"><?= e($payment['user_name'] ?: '-') ?></dd></div>
<div><dt class="text-xs text-neutral-400">Email</dt><dd class="mt-1 font-medium break-all"><?= e($payment['user_email'] ?: '-') ?></dd></div>
</dl>
<div class="mt-6 border-t border-neutral-100 pt-5"><h3 class="font-medium text-sm">Bukti Pembayaran</h3>
<?php if ($payment['proof_file']): ?><p class="mt-3 text-sm break-all"><?= e($payment['proof_file']) ?></p><?php else: ?><p class="mt-3 text-sm text-neutral-400">Belum ada bukti pembayaran.</p><?php endif; ?>
</div></section>
<aside class="bento-card p-5 md:p-6 h-fit"><h2 class="font-semibold">Tindakan</h2>
<?php if ($payment['status']==='PENDING'): ?>
<form method="post" class="mt-5">
<input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
<button name="action" value="VERIFY" class="w-full min-h-11 rounded-xl bg-neutral-900 text-white text-sm font-semibold">Verifikasi Pembayaran</button>
<label class="block mt-5 text-xs font-medium text-neutral-600">Alasan Penolakan</label>
<textarea name="rejection_reason" rows="4" maxlength="500" class="mt-2 w-full rounded-xl border border-neutral-200 px-3 py-3 text-sm" placeholder="Tulis alasan jika ditolak..."></textarea>
<button name="action" value="REJECT" class="mt-3 w-full min-h-11 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-semibold">Tolak Pembayaran</button>
</form>
<?php else: ?><div class="mt-5 rounded-2xl bg-neutral-50 px-4 py-4 text-sm text-neutral-600">Pembayaran sudah diproses dan tidak dapat diubah lagi.</div>
<?php if ($payment['verifier_name'] || $payment['verified_at']): ?><div class="mt-5 border-t border-neutral-100 pt-4 text-sm"><p class="text-xs text-neutral-400">Diproses oleh</p><p class="mt-1 font-medium"><?= e($payment['verifier_name'] ?: '-') ?></p><p class="text-xs text-neutral-400 mt-1"><?= e($payment['verified_at'] ? date('d M Y, H:i', strtotime($payment['verified_at'])) : '-') ?></p></div><?php endif; ?>
<?php if ($payment['rejection_reason']): ?><div class="mt-5 border-t border-neutral-100 pt-4"><p class="text-xs text-neutral-400">Alasan penolakan</p><p class="mt-1 text-sm text-red-700"><?= e($payment['rejection_reason']) ?></p></div><?php endif; ?>
<?php endif; ?>
</aside></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>