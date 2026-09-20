<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$paymentId || $paymentId < 1) {
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        pay.id,
        pay.amount,
        pay.status,
        pay.proof_file,
        pay.expired_at,
        pay.created_at,
        pay.verified_at,
        pay.rejection_reason,
        pay.store_count,
        a.name AS account_name,
        s.name AS store_name,
        p.name AS package_name,
        p.duration_days
     FROM payments pay
     INNER JOIN accounts a ON a.id = pay.account_id
     INNER JOIN stores s ON s.id = pay.store_id
     INNER JOIN packages p ON p.id = pay.package_id
     WHERE pay.id = :payment_id
       AND pay.account_id = :account_id
       AND pay.store_id = :store_id
     LIMIT 1"
);

$stmt->execute([
    ':payment_id' => $paymentId,
    ':account_id' => (int) $_SESSION['account_id'],
    ':store_id' => (int) $_SESSION['store_id'],
]);

$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
    exit('Pembayaran tidak ditemukan.');
}

$status = (string) $payment['status'];

$statusConfig = [
    'PENDING' => [
        'label' => 'Menunggu verifikasi',
        'title' => 'Bukti pembayaran sudah diterima',
        'description' => 'Bukti transfer sudah tersimpan. Tim RESTOCK akan memeriksa pembayaran dan mengaktifkan subscription setelah verifikasi.',
        'icon' => 'pending',
    ],
    'VERIFIED' => [
        'label' => 'Pembayaran terverifikasi',
        'title' => 'Pembayaran berhasil diverifikasi',
        'description' => 'Pembayaran sudah diverifikasi dan subscription kamu telah diproses oleh RESTOCK.',
        'icon' => 'success',
    ],
    'REJECTED' => [
        'label' => 'Pembayaran ditolak',
        'title' => 'Pembayaran perlu diperiksa kembali',
        'description' => 'Pembayaran ini ditolak oleh tim RESTOCK. Periksa alasan penolakan di bawah dan lakukan pembayaran kembali bila diperlukan.',
        'icon' => 'rejected',
    ],
    'EXPIRED' => [
        'label' => 'Pembayaran kedaluwarsa',
        'title' => 'Batas pembayaran sudah lewat',
        'description' => 'Tagihan ini sudah kedaluwarsa dan tidak dapat digunakan lagi. Buat pembayaran baru untuk melanjutkan.',
        'icon' => 'expired',
    ],
];

$config = $statusConfig[$status] ?? $statusConfig['PENDING'];

$pageTitle = $config['title'];
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
    <style>
        @keyframes restock-status-pop {
            0% { transform: scale(.55); opacity: 0; }
            70% { transform: scale(1.08); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes restock-status-draw {
            0% { stroke-dashoffset: 48; }
            100% { stroke-dashoffset: 0; }
        }

        .restock-status-icon {
            animation: restock-status-pop .45s ease-out both;
        }

        .restock-status-check {
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: restock-status-draw .45s .2s ease-out forwards;
        }
    </style>
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-900">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center px-4 py-8 sm:px-6">
        <section class="w-full">
            <div class="mb-5">
                <a href="/" class="text-sm font-medium text-neutral-500 hover:text-neutral-900">
                    ← Kembali ke aplikasi
                </a>
            </div>

            <section class="overflow-hidden rounded-[28px] border border-neutral-200 bg-white shadow-sm">
                <div class="px-6 py-10 text-center sm:px-10">
                    <div class="restock-status-icon mx-auto flex h-20 w-20 items-center justify-center rounded-full
                        <?= $status === 'VERIFIED' || $status === 'PENDING' ? 'bg-emerald-50' : 'bg-neutral-100' ?>">
                        <?php if ($status === 'VERIFIED' || $status === 'PENDING'): ?>
                            <svg viewBox="0 0 24 24" class="h-10 w-10 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path class="restock-status-check" d="M5 12.5l4.2 4.2L19 7"></path>
                            </svg>
                        <?php elseif ($status === 'REJECTED'): ?>
                            <svg viewBox="0 0 24 24" class="h-9 w-9 text-neutral-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <path d="M7 7l10 10M17 7L7 17"></path>
                            </svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" class="h-9 w-9 text-neutral-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="8"></circle>
                                <path d="M12 8v4l2.5 2"></path>
                            </svg>
                        <?php endif; ?>
                    </div>

                    <p class="mt-6 text-xs font-semibold uppercase tracking-[0.16em]
                        <?= $status === 'VERIFIED' ? 'text-emerald-600' : ($status === 'PENDING' ? 'text-amber-600' : 'text-neutral-500') ?>">
                        <?= e($config['label']) ?>
                    </p>

                    <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                        <?= e($config['title']) ?>
                    </h1>

                    <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-neutral-500">
                        <?= e($config['description']) ?>
                    </p>
                </div>

                <div class="border-y border-neutral-100 bg-neutral-50/70 px-6 py-5 sm:px-8">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">Paket</p>
                            <p class="mt-1 text-sm font-semibold"><?= e($payment['package_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">Tagihan</p>
                            <p class="mt-1 text-sm font-semibold"><?= rupiah($payment['amount']) ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">Kapasitas</p>
                            <p class="mt-1 text-sm font-semibold"><?= e($payment['store_count']) ?> toko</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">Pembayaran</p>
                            <p class="mt-1 text-sm font-semibold">#<?= e($payment['id']) ?></p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-6 sm:px-8">
                    <?php if ($status === 'PENDING'): ?>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
                            <p class="text-sm font-semibold text-amber-900">Apa yang terjadi sekarang?</p>
                            <ol class="mt-2 space-y-1.5 text-sm leading-6 text-amber-800">
                                <li>1. Bukti transfer sudah tersimpan.</li>
                                <li>2. Tim RESTOCK memeriksa nominal dan bukti pembayaran.</li>
                                <li>3. Setelah disetujui, subscription aktif otomatis.</li>
                                <li>4. Konfirmasi verifikasi dikirim ke email terdaftar.</li>
                            </ol>
                        </div>

                        <?php if (!empty($payment['expired_at'])): ?>
                            <p class="mt-4 text-xs text-neutral-400">
                                Batas tagihan: <?= e(date('d M Y, H:i', strtotime($payment['expired_at']))) ?>
                            </p>
                        <?php endif; ?>

                    <?php elseif ($status === 'VERIFIED'): ?>
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm leading-6 text-emerald-800">
                            Pembayaran sudah diverifikasi. Cek email terdaftar untuk konfirmasi dan detail subscription.
                        </div>

                    <?php elseif ($status === 'REJECTED'): ?>
                        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-red-500">Alasan penolakan</p>
                            <p class="mt-2 text-sm leading-6 text-red-800">
                                <?= e($payment['rejection_reason'] ?: 'Tidak ada alasan yang dicantumkan.') ?>
                            </p>
                        </div>

                    <?php else: ?>
                        <div class="rounded-2xl border border-neutral-200 bg-neutral-50 px-4 py-4 text-sm leading-6 text-neutral-600">
                            Tagihan ini sudah tidak aktif. Buat pembayaran baru untuk melanjutkan.
                        </div>
                    <?php endif; ?>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <a href="/" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-neutral-900 px-4 py-3 text-sm font-semibold text-white">
                            Kembali ke aplikasi
                        </a>
                        <?php if ($status === 'PENDING' && !empty($payment['proof_file'])): ?>
                            <a href="<?= e($payment['proof_file']) ?>" target="_blank" rel="noopener" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-neutral-200 bg-white px-4 py-3 text-sm font-semibold text-neutral-700">
                                Lihat bukti transfer
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </section>
    </main>
</body>
</html>
