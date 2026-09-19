<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Subscription';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$reason = trim((string) ($_GET['reason'] ?? ''));

$subscriptionStmt = $pdo->prepare(
    "SELECT
        sub.id,
        sub.starts_at,
        sub.ends_at,
        sub.status,
        sub.store_count,
        sub.pricing_tier_id,
        p.name AS package_name,
        p.price,
        p.duration_days
     FROM subscriptions sub
     INNER JOIN packages p ON p.id = sub.package_id
     WHERE sub.account_id = :account_id
       AND sub.store_id = :store_id
     ORDER BY
        CASE sub.status
            WHEN 'ACTIVE' THEN 0
            WHEN 'EXPIRED' THEN 1
            ELSE 2
        END,
        sub.ends_at DESC,
        sub.id DESC
     LIMIT 1"
);

$subscriptionStmt->execute([
    ':account_id' => $authAccountId,
    ':store_id'   => $authStoreId,
]);

$subscription = $subscriptionStmt->fetch();

$pendingStmt = $pdo->prepare(
    "SELECT
        pay.id,
        pay.amount,
        pay.status,
        pay.proof_file,
        pay.expired_at,
        pay.created_at,
        p.name AS package_name,
        p.duration_days
     FROM payments pay
     INNER JOIN packages p ON p.id = pay.package_id
     WHERE pay.account_id = :account_id
       AND pay.store_id = :store_id
       AND pay.status = 'PENDING'
     ORDER BY pay.id DESC
     LIMIT 1"
);

$pendingStmt->execute([
    ':account_id' => $authAccountId,
    ':store_id'   => $authStoreId,
]);

$pendingPayment = $pendingStmt->fetch();

$message = '';

if ($reason === 'required') {
    $message = 'Subscription aktif diperlukan untuk menggunakan fitur RESTOCK.';
}

if ($reason === 'expired') {
    $message = 'Subscription kamu sudah berakhir. Perpanjang untuk melanjutkan penggunaan RESTOCK.';
}

$hasActive = $subscription
    && $subscription['status'] === 'ACTIVE'
    && strtotime((string) $subscription['ends_at']) > time();

$daysRemaining = null;

if ($hasActive) {
    $seconds = max(0, strtotime((string) $subscription['ends_at']) - time());
    $daysRemaining = (int) ceil($seconds / 86400);
}
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
</head>
<body class="bg-neutral-50 text-neutral-900">

<div class="min-h-screen flex items-center justify-center p-4 md:p-8">
    <main class="w-full max-w-4xl">
        <div class="mb-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">RESTOCK</p>
            <h1 class="text-3xl md:text-4xl font-semibold tracking-tight mt-2">Subscription</h1>
            <p class="text-sm text-neutral-500 mt-2">
                Kelola masa aktif akses RESTOCK untuk toko ini.
            </p>
        </div>

        <?php if ($message): ?>
            <div id="subscriptionNotice" class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bento-card p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs text-neutral-400">Status Saat Ini</p>

                        <?php if ($hasActive): ?>
                            <h2 class="text-2xl font-semibold mt-2">ACTIVE</h2>
                            <p class="text-sm text-neutral-500 mt-2">
                                <?= e($subscription['package_name']) ?>
                                <?php if (!empty($subscription['store_count'])): ?> · <?= e($subscription['store_count']) ?> toko<?php endif; ?>
                            </p>
                        <?php elseif ($subscription): ?>
                            <h2 class="text-2xl font-semibold mt-2"><?= e($subscription['status']) ?></h2>
                            <p class="text-sm text-neutral-500 mt-2">
                                <?= e($subscription['package_name']) ?>
                            </p>
                        <?php else: ?>
                            <h2 class="text-2xl font-semibold mt-2">BELUM ADA</h2>
                            <p class="text-sm text-neutral-500 mt-2">
                                Belum ada subscription yang aktif.
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasActive): ?>
                        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700">
                            Aktif
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($hasActive): ?>
                    <div class="mt-6 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-neutral-400">Mulai</p>
                            <p class="mt-1 text-sm font-medium">
                                <?= e(date('d M Y, H:i', strtotime((string) $subscription['starts_at']))) ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-neutral-400">Berakhir</p>
                            <p class="mt-1 text-sm font-medium">
                                <?= e(date('d M Y, H:i', strtotime((string) $subscription['ends_at']))) ?>
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl bg-neutral-50 px-4 py-4">
                        <p class="text-xs text-neutral-400">Sisa masa aktif</p>
                        <p class="text-2xl font-semibold mt-1">
                            <?= number_format((int) $daysRemaining) ?> hari
                        </p>
                    </div>
                <?php else: ?>
                    <div class="mt-6 rounded-2xl bg-neutral-50 px-4 py-4 text-sm text-neutral-600">
                        Akses fitur aplikasi akan tersedia setelah pembayaran diverifikasi dan subscription aktif.
                    </div>
                <?php endif; ?>
            </div>

            <div class="bento-card p-6">
                <p class="text-xs text-neutral-400">Perpanjang Akses</p>
                <h2 class="text-xl font-semibold mt-2">Pilih paket dan lakukan pembayaran.</h2>
                <p class="text-sm text-neutral-500 mt-2">
                    Renewal menggunakan alur checkout dan verifikasi yang sama seperti pendaftaran awal.
                </p>

                <?php if ($pendingPayment): ?>
                    <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
                        <p class="text-xs font-medium text-amber-800">Pembayaran masih menunggu verifikasi</p>
                        <p class="text-lg font-semibold text-amber-900 mt-1">
                            <?= rupiah($pendingPayment['amount']) ?>
                        </p>
                        <p class="text-xs text-amber-700 mt-1">
                            Payment #<?= e($pendingPayment['id']) ?> · <?= e($pendingPayment['package_name']) ?>
                        </p>

                        <a
                            href="/checkout.php"
                            class="inline-flex items-center justify-center min-h-11 mt-4 px-4 rounded-xl bg-neutral-900 text-white text-sm font-semibold"
                        >
                            Lanjut ke Checkout
                        </a>
                    </div>
                <?php else: ?>
                    <a
                        href="/renewal.php"
                        class="inline-flex items-center justify-center w-full min-h-11 mt-6 px-4 rounded-xl bg-neutral-900 text-white text-sm font-semibold"
                    >
                        Lihat Paket
                    </a>

                    <?php if ($subscription): ?>
                        <p class="text-xs text-neutral-400 mt-3">
                            Subscription terakhir: berakhir
                            <?= e(date('d M Y, H:i', strtotime((string) $subscription['ends_at']))) ?>.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const notice = document.getElementById('subscriptionNotice');

    if (notice) {
        window.setTimeout(function () {
            notice.style.transition = 'opacity 200ms ease, transform 200ms ease';
            notice.style.opacity = '0';
            notice.style.transform = 'translateY(-4px)';

            window.setTimeout(function () {
                notice.remove();
            }, 220);
        }, 3000);
    }
});
</script>

</body>
</html>
