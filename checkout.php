<?php
declare(strict_types=1);

/*
 * RESTOCK - Package Checkout
 * Scope 2I-6:
 * registration -> checkout -> PENDING bank transfer payment.
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

if (empty($_SESSION['selected_package_id'])) {
    header('Location: /paket/');
    exit;
}

$accountId = (int) ($_SESSION['account_id'] ?? 0);
$storeId = (int) ($_SESSION['store_id'] ?? 0);
$packageId = (int) ($_SESSION['selected_package_id'] ?? 0);

if ($accountId <= 0 || $storeId <= 0 || $packageId <= 0) {
    unset($_SESSION['selected_package_id'], $_SESSION['payment_id']);
    header('Location: /paket/');
    exit;
}

if (empty($_SESSION['checkout_csrf_token'])) {
    $_SESSION['checkout_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['checkout_csrf_token'];

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
    header('Location: /paket/');
    exit;
}

/*
 * Existing pending payment for this exact account/store/package.
 * This is intentionally checked before creating a new payment.
 */
$pendingStmt = $pdo->prepare(
    "SELECT id, amount, payment_method, status, expired_at, created_at
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

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        $error = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
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
            header('Location: /paket/');
            exit;
        }

        $pendingStmt = $pdo->prepare(
            "SELECT id, amount, payment_method, status, expired_at, created_at
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
                <a href="/paket/" class="text-sm font-medium text-neutral-500 hover:text-neutral-900">
                    ← Kembali ke paket
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

                    <?php if ($pendingPayment): ?>
                        <div class="mt-5">
                            <p class="text-xs text-neutral-400">Tagihan pending</p>
                            <p class="mt-1 text-lg font-semibold"><?= rupiah($pendingPayment['amount']) ?></p>
                            <?php if (!empty($pendingPayment['expired_at'])): ?>
                                <p class="mt-1 text-xs text-neutral-400">
                                    Berlaku sampai <?= e(date('d M Y H:i', strtotime($pendingPayment['expired_at']))) ?>
                                </p>
                            <?php endif; ?>
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
