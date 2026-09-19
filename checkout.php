<?php
declare(strict_types=1);

/*
 * RESTOCK - Package Checkout
 * Scope 2I-6 + 2I-7:
 * registration -> checkout -> PENDING bank transfer payment -> proof upload.
 */

require_once __DIR__ . '/includes/auth.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$checkoutOrigin = (string) ($_SESSION['checkout_origin'] ?? '');
if (!in_array($checkoutOrigin, ['/daftar-paket.php', '/renewal.php'], true)) {
    $checkoutOrigin = '/daftar-paket.php';
}

$isRenewalCheckout = $checkoutOrigin === '/renewal.php';
$checkoutBackLabel = $isRenewalCheckout
    ? 'Kembali ke renewal'
    : 'Kembali ke pilih paket';

if (empty($_SESSION['selected_package_id'])) {
    header('Location: ' . $checkoutOrigin);
    exit;
}

$accountId = (int) ($_SESSION['account_id'] ?? 0);
$storeId = (int) ($_SESSION['store_id'] ?? 0);
$packageId = (int) ($_SESSION['selected_package_id'] ?? 0);

if ($accountId <= 0 || $storeId <= 0 || $packageId <= 0) {
    unset($_SESSION['selected_package_id'], $_SESSION['payment_id']);
    header('Location: ' . $checkoutOrigin);
    exit;
}

if (empty($_SESSION['checkout_csrf_token'])) {
    $_SESSION['checkout_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['checkout_csrf_token'];

$packageStmt = $pdo->prepare(
    "SELECT id, name, slug, price, duration_days, min_store_count, max_store_count, description
     FROM packages
     WHERE id = :package_id AND status = 'ACTIVE' LIMIT 1"
);
$packageStmt->execute([
    ':package_id' => $packageId,
]);
$package = $packageStmt->fetch();

if (!$package) {
    unset($_SESSION['selected_package_id'], $_SESSION['payment_id']);
    header('Location: ' . $checkoutOrigin);
    exit;
}

/*
 * Existing pending payment for this exact account/store/package.
 * This is intentionally checked before creating a new payment.
 */
$pendingStmt = $pdo->prepare(
    "SELECT id, amount, payment_method, status, proof_file, expired_at, created_at
     FROM payments
     WHERE account_id = :account_id
       AND store_id = :store_id
       AND package_id = :package_id
       AND status = 'PENDING'
       AND (expired_at IS NULL OR expired_at > CURRENT_TIMESTAMP)
     ORDER BY id DESC
     LIMIT 1"
);
$pendingStmt->execute([
    ':account_id' => $accountId,
    ':store_id' => $storeId,
    ':package_id' => $packageId,
]);
$pendingPayment = $pendingStmt->fetch();

$paymentAccountsStmt = $pdo->query(
    "SELECT id, bank_name, account_name, account_number, notes
     FROM payment_accounts
     WHERE status = 'ACTIVE'
     ORDER BY sort_order ASC, id ASC"
);
$paymentAccounts = $paymentAccountsStmt->fetchAll();


$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = '';
$proofSuccess = '';
$proofError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        $error = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $action = strtoupper(trim((string) ($_POST['action'] ?? 'CREATE_PAYMENT')));

        if ($action === 'UPLOAD_PROOF') {
            $paymentId = (int) ($_SESSION['payment_id'] ?? 0);

            if ($paymentId <= 0) {
                $proofError = 'Pembayaran belum tersedia. Buat pembayaran terlebih dahulu.';
            } elseif (
                !isset($_FILES['proof_file']) ||
                !is_array($_FILES['proof_file']) ||
                ($_FILES['proof_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            ) {
                $uploadError = (int) ($_FILES['proof_file']['error'] ?? UPLOAD_ERR_NO_FILE);
                $proofError = match ($uploadError) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file terlalu besar.',
                    UPLOAD_ERR_PARTIAL => 'Upload file tidak selesai. Silakan coba lagi.',
                    UPLOAD_ERR_NO_FILE => 'Pilih file bukti pembayaran terlebih dahulu.',
                    default => 'Bukti pembayaran gagal diunggah.',
                };
            } else {
                $file = $_FILES['proof_file'];
                $maxBytes = 5 * 1024 * 1024;

                if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
                    $proofError = 'Ukuran bukti pembayaran maksimal 5 MB.';
                } elseif (!is_uploaded_file($file['tmp_name'])) {
                    $proofError = 'File upload tidak valid.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);

                    $allowedMimes = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'application/pdf' => 'pdf',
                    ];

                    if (!isset($allowedMimes[$mime])) {
                        $proofError = 'Format file harus JPG, PNG, WEBP, atau PDF.';
                    } else {
                        $paymentStmt = $pdo->prepare(
                            "SELECT id, proof_file, status
                             FROM payments
                             WHERE id = :payment_id
                               AND account_id = :account_id
                               AND store_id = :store_id
                             LIMIT 1"
                        );
                        $paymentStmt->execute([
                            ':payment_id' => $paymentId,
                            ':account_id' => $accountId,
                            ':store_id' => $storeId,
                        ]);
                        $proofPayment = $paymentStmt->fetch();

                        if (!$proofPayment) {
                            $proofError = 'Pembayaran tidak ditemukan.';
                        } elseif ($proofPayment['status'] !== 'PENDING') {
                            $proofError = 'Bukti hanya dapat diunggah untuk pembayaran yang masih PENDING.';
                        } else {
                            $uploadDir = __DIR__ . '/uploads/payments';

                            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                                $proofError = 'Folder upload belum dapat disiapkan.';
                            } else {
                                $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
                                $destination = $uploadDir . '/' . $filename;
                                $relativePath = '/uploads/payments/' . $filename;

                                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                                    $proofError = 'Bukti pembayaran gagal disimpan.';
                                } else {
                                    $updateStmt = $pdo->prepare(
                                        "UPDATE payments
                                         SET proof_file = :proof_file,
                                             updated_at = CURRENT_TIMESTAMP
                                         WHERE id = :payment_id
                                           AND account_id = :account_id
                                           AND store_id = :store_id
                                           AND status = 'PENDING'"
                                    );
                                    $updateStmt->execute([
                                        ':proof_file' => $relativePath,
                                        ':payment_id' => $paymentId,
                                        ':account_id' => $accountId,
                                        ':store_id' => $storeId,
                                    ]);

                                    if ($updateStmt->rowCount() !== 1) {
                                        @unlink($destination);
                                        $proofError = 'Pembayaran berubah saat upload diproses. Silakan coba lagi.';
                                    } else {
                                        if (!empty($proofPayment['proof_file'])) {
                                            $oldPath = (string) $proofPayment['proof_file'];
                                            $oldRelativePrefix = '/uploads/payments/';
                                            if (str_starts_with($oldPath, $oldRelativePrefix)) {
                                                $oldFilename = basename($oldPath);
                                                if ($oldFilename !== basename($relativePath)) {
                                                    @unlink($uploadDir . '/' . $oldFilename);
                                                }
                                            }
                                        }

                                        $proofSuccess = 'Bukti pembayaran berhasil diunggah dan menunggu verifikasi.';
                                        $pendingPayment['proof_file'] = $relativePath;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            /*
             * Never trust submitted amount/payment data.
             * Re-read the package and its current ACTIVE price from MySQL.
             */
            $packageStmt = $pdo->prepare(
                "SELECT id, name, slug, price, duration_days, description
                 FROM packages
                 WHERE id = :package_id AND status = 'ACTIVE' LIMIT 1"
            );
            $packageStmt->execute([
                ':package_id' => $packageId,
            ]);
            $package = $packageStmt->fetch();

            if (!$package) {
                unset($_SESSION['selected_package_id'], $_SESSION['payment_id']);
                header('Location: ' . $checkoutOrigin);
                exit;
            }

            $pendingStmt = $pdo->prepare(
                "SELECT id, amount, payment_method, status, proof_file, expired_at, created_at
                 FROM payments
                 WHERE account_id = :account_id
                   AND store_id = :store_id
                   AND package_id = :package_id
                   AND status = 'PENDING'
                   AND (expired_at IS NULL OR expired_at > CURRENT_TIMESTAMP)
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $pendingStmt->execute([
                ':account_id' => $accountId,
                ':store_id' => $storeId,
                ':package_id' => $packageId,
            ]);
            $pendingPayment = $pendingStmt->fetch();

            if ($pendingPayment) {
                $_SESSION['payment_id'] = (int) $pendingPayment['id'];
                $success = true;
            } else {
                if (!$paymentAccounts) {
                    $error = 'Rekening pembayaran belum tersedia. Silakan hubungi admin.';
                } else {
                    $expiredAt = date('Y-m-d H:i:s', time() + 86400);

                    try {
                    $paymentStmt = $pdo->prepare(
                        "INSERT INTO payments
                            (account_id, store_id, package_id, amount, payment_method, status, expired_at, created_at, updated_at)
                         VALUES
                            (:account_id, :store_id, :package_id, :amount, 'BANK_TRANSFER', 'PENDING', :expired_at, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
                    );

                    $paymentStmt->execute([
                        ':account_id' => $accountId,
                        ':store_id' => $storeId,
                        ':package_id' => $packageId,
                        ':amount' => $package['price'],
                        ':expired_at' => $expiredAt,
                    ]);

                    $_SESSION['payment_id'] = (int) $pdo->lastInsertId();

                    header('Location: /checkout.php?success=1');
                    exit;
                    } catch (PDOException $e) {
                        $error = 'Pembayaran belum dapat dibuat. Silakan coba lagi.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Checkout';
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
<body class="min-h-screen bg-neutral-50 text-neutral-900">
    <main class="mx-auto flex min-h-screen max-w-3xl items-center px-4 py-8 sm:px-6">
        <section class="w-full">
            <div class="mb-6">
                <a href="<?= e($checkoutOrigin) ?>" class="text-sm font-medium text-neutral-500 hover:text-neutral-900">
                    ← <?= e($checkoutBackLabel) ?>
                </a>
                <p class="mt-6 text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">RESTOCK CHECKOUT</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Selesaikan pembayaran</h1>
                <p class="mt-2 text-sm leading-6 text-neutral-500">
                    Pembayaran dibuat sebagai transfer bank dan menunggu verifikasi.
                </p>
            </div>

            <?php if ($error): ?>
                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success && $pendingPayment): ?>
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800">
                    Pembayaran pending sudah tercatat. Tidak dibuat tagihan baru.
                </div>
            <?php endif; ?>

            <?php if ($proofSuccess): ?>
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800">
                    <?= e($proofSuccess) ?>
                </div>
            <?php endif; ?>

            <?php if ($proofError): ?>
                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700">
                    <?= e($proofError) ?>
                </div>
            <?php endif; ?>

            <div class="grid gap-4 md:grid-cols-[1.2fr_.8fr]">
                <article class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-400">Paket</p>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight"><?= e($package['name']) ?></h2>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs text-neutral-400">Harga</p>
                            <p class="mt-1 text-xl font-semibold"><?= rupiah($package['price']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-neutral-400">Durasi</p>
                            <p class="mt-1 text-sm font-medium"><?= (int) $package['duration_days'] ?> hari</p>
                        </div>
                    </div>

                    <?php if (!empty($package['description'])): ?>
                        <div class="mt-6 border-t border-neutral-100 pt-5">
                            <p class="text-sm leading-6 text-neutral-500"><?= e($package['description']) ?></p>
                        </div>
                    <?php endif; ?>
                </article>

                <aside class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-400">Metode</p>
                    <div class="mt-3 rounded-2xl bg-neutral-50 px-4 py-3">
                        <p class="text-sm font-semibold">Bank Transfer</p>
                        <p class="mt-1 text-xs text-neutral-500">Status awal: Pending</p>
                    </div>

                    <?php if ($paymentAccounts): ?>
                        <div class="mt-5 border-t border-neutral-100 pt-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-400">Rekening Pembayaran</p>
                            <div class="mt-3 space-y-3">
                                <?php foreach ($paymentAccounts as $paymentAccount): ?>
                                    <div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold"><?= e($paymentAccount['bank_name']) ?></p>
                                                <p class="mt-1 text-lg font-semibold tracking-tight"><?= e($paymentAccount['account_number']) ?></p>
                                                <p class="mt-1 text-xs text-neutral-500">a.n. <?= e($paymentAccount['account_name']) ?></p>
                                            </div>
                                            <button
                                                type="button"
                                                class="copy-checkout-account inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-xs font-semibold border border-neutral-200"
                                                data-copy="<?= e($paymentAccount['bank_name'] . ' ' . $paymentAccount['account_number'] . ' a.n. ' . $paymentAccount['account_name']) ?>"
                                            >
                                                <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copy
                                            </button>
                                        </div>
                                        <?php if (!empty($paymentAccount['notes'])): ?>
                                            <p class="mt-2 text-[11px] leading-5 text-neutral-400"><?= e($paymentAccount['notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                            Rekening pembayaran belum tersedia. Pembayaran belum dapat dibuat.
                        </div>
                    <?php endif; ?>

                    <?php if ($pendingPayment): ?>
                        <div class="mt-5">
                            <p class="text-xs text-neutral-400">Tagihan pending</p>
                            <p class="mt-1 text-lg font-semibold"><?= rupiah($pendingPayment['amount']) ?></p>
                            <?php if (!empty($pendingPayment['expired_at'])): ?>
                                <p class="mt-1 text-xs text-neutral-400">
                                    Berlaku sampai <?= e(date('d M Y H:i', strtotime($pendingPayment['expired_at']))) ?>
                                </p>
                            <?php endif; ?>

                            <div class="mt-5 border-t border-neutral-100 pt-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-neutral-400">Bukti Transfer</p>

                                <?php if (!empty($pendingPayment['proof_file'])): ?>
                                    <div class="mt-3 rounded-2xl bg-emerald-50 px-4 py-3">
                                        <p class="text-sm font-medium text-emerald-800">Bukti sudah diunggah</p>
                                        <a href="<?= e($pendingPayment['proof_file']) ?>" target="_blank" rel="noopener" class="mt-2 inline-flex text-xs font-medium text-emerald-700 underline">
                                            Lihat bukti
                                        </a>
                                    </div>
                                    <p class="mt-3 text-xs text-neutral-400">Kamu masih dapat mengganti bukti selama pembayaran belum diverifikasi.</p>
                                <?php else: ?>
                                    <p class="mt-2 text-xs leading-5 text-neutral-500">Upload bukti transfer setelah pembayaran dilakukan.</p>
                                <?php endif; ?>

                                <form method="POST" enctype="multipart/form-data" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="UPLOAD_PROOF">
                                    <label class="block">
                                        <span class="text-xs font-medium text-neutral-600">File bukti</span>
                                        <input
                                            type="file"
                                            name="proof_file"
                                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                                            class="mt-2 block w-full rounded-xl border border-neutral-200 bg-white px-3 py-2.5 text-xs"
                                            required
                                        >
                                    </label>
                                    <p class="mt-2 text-[11px] text-neutral-400">JPG, PNG, WEBP, atau PDF · maksimal 5 MB</p>
                                    <button type="submit" class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-neutral-900 px-4 py-3 text-sm font-semibold text-white">
                                        <?= !empty($pendingPayment['proof_file']) ? 'Ganti Bukti Transfer' : 'Upload Bukti Transfer' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="mt-5" id="checkoutForm">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <button
                                type="submit"
                                id="checkoutButton"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-neutral-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Buat Pembayaran
                            </button>
                        </form>
                    <?php endif; ?>
                </aside>
            </div>
        </section>
    </main>

    <script>
        document.querySelectorAll('.copy-checkout-account').forEach(function (button) {
            button.addEventListener('click', async function () {
                try {
                    await navigator.clipboard.writeText(button.dataset.copy || '');
                    const original = button.innerHTML;
                    button.innerHTML = '<i data-lucide="check" class="w-3.5 h-3.5"></i> Tersalin';
                    if (window.lucide) lucide.createIcons();
                    setTimeout(function () {
                        button.innerHTML = original;
                        if (window.lucide) lucide.createIcons();
                    }, 3000);
                } catch (error) {}
            });
        });

        const form = document.getElementById('checkoutForm');
        const button = document.getElementById('checkoutButton');

        if (form && button) {
            form.addEventListener('submit', function () {
                button.disabled = true;
                button.textContent = 'Membuat pembayaran...';
            });
        }
    </script>
</body>
</html>
