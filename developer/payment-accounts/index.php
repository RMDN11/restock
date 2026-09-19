<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/developer_auth.php';

$pageTitle = 'Rekening Pembayaran';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    }

    $action = strtoupper(trim((string) ($_POST['action'] ?? 'ADD')));
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'ADD') {
        $bankName = trim((string) ($_POST['bank_name'] ?? ''));
        $accountName = trim((string) ($_POST['account_name'] ?? ''));
        $accountNumber = trim((string) ($_POST['account_number'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($bankName === '' || mb_strlen($bankName) > 100) {
            $errors[] = 'Nama bank wajib diisi dan maksimal 100 karakter.';
        }
        if ($accountName === '' || mb_strlen($accountName) > 150) {
            $errors[] = 'Nama pemilik rekening wajib diisi dan maksimal 150 karakter.';
        }
        if ($accountNumber === '' || mb_strlen($accountNumber) > 100) {
            $errors[] = 'Nomor rekening wajib diisi dan maksimal 100 karakter.';
        }
        if (mb_strlen($notes) > 255) {
            $errors[] = 'Catatan maksimal 255 karakter.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                "INSERT INTO payment_accounts
                    (bank_name, account_name, account_number, notes, status, sort_order, created_by, created_at, updated_at)
                 VALUES
                    (:bank_name, :account_name, :account_number, :notes, 'ACTIVE', :sort_order, :created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $stmt->execute([
                ':bank_name' => $bankName,
                ':account_name' => $accountName,
                ':account_number' => $accountNumber,
                ':notes' => $notes !== '' ? $notes : null,
                ':sort_order' => $sortOrder,
                ':created_by' => $authUserId,
            ]);
            $_SESSION['flash_success'] = 'Rekening pembayaran berhasil ditambahkan.';
            header('Location: /developer/payment-accounts/');
            exit;
        }
    } elseif ($action === 'TOGGLE' && $id > 0) {
        $stmt = $pdo->prepare(
            "UPDATE payment_accounts
             SET status = CASE WHEN status = 'ACTIVE' THEN 'INACTIVE' ELSE 'ACTIVE' END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $_SESSION['flash_success'] = 'Status rekening berhasil diperbarui.';
        header('Location: /developer/payment-accounts/');
        exit;
    } elseif ($action === 'DELETE' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM payment_accounts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['flash_success'] = 'Rekening pembayaran berhasil dihapus.';
        header('Location: /developer/payment-accounts/');
        exit;
    }
}

$flash = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$stmt = $pdo->query(
    "SELECT id, bank_name, account_name, account_number, notes, status, sort_order, created_at
     FROM payment_accounts
     ORDER BY sort_order ASC, id DESC"
);
$accounts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="lg:ml-64 pt-16 min-h-screen">
<div class="p-4 md:p-8 max-w-6xl mx-auto">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
        <div>
            <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer · Finance</p>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight mt-1">Rekening Pembayaran</h1>
            <p class="text-sm text-neutral-500 mt-2">Rekening aktif akan otomatis ditampilkan di halaman checkout pelanggan.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div id="paymentAccountFlash" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="space-y-1"><?php foreach ($errors as $error): ?><li>• <?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <section class="grid grid-cols-1 xl:grid-cols-[.85fr_1.15fr] gap-5">
        <div class="bento-card p-5 md:p-6 h-fit">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Tambah Rekening</h2>
                    <p class="text-sm text-neutral-500 mt-1">Bisa menyimpan beberapa rekening transfer.</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                    <i data-lucide="landmark" class="w-5 h-5"></i>
                </div>
            </div>

            <form method="post" class="mt-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="ADD">

                <div>
                    <label class="block text-sm font-medium mb-2" for="bank_name">Bank</label>
                    <input id="bank_name" name="bank_name" required maxlength="100" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400" placeholder="BCA">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2" for="account_name">Nama Rekening</label>
                    <input id="account_name" name="account_name" required maxlength="150" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400" placeholder="PT RESTOCK Indonesia">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2" for="account_number">Nomor Rekening</label>
                    <input id="account_number" name="account_number" required maxlength="100" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400" placeholder="1234567890">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2" for="notes">Catatan</label>
                    <input id="notes" name="notes" maxlength="255" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400" placeholder="Transfer hanya melalui rekening ini">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2" for="sort_order">Urutan</label>
                    <input id="sort_order" name="sort_order" type="number" value="0" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400">
                </div>
                <button class="w-full h-11 rounded-xl bg-neutral-900 text-white text-sm font-semibold hover:bg-neutral-800">Simpan Rekening</button>
            </form>
        </div>

        <div class="bento-card overflow-hidden">
            <div class="px-5 md:px-6 py-5 border-b border-neutral-100">
                <h2 class="font-semibold">Rekening Tersedia</h2>
                <p class="text-xs text-neutral-400 mt-1"><?= count($accounts) ?> rekening terdaftar.</p>
            </div>

            <?php if (!$accounts): ?>
                <div class="px-5 py-12 text-center">
                    <i data-lucide="landmark" class="w-7 h-7 text-neutral-300 mx-auto"></i>
                    <p class="text-sm font-medium mt-3">Belum ada rekening</p>
                    <p class="text-xs text-neutral-400 mt-1">Tambahkan rekening agar pelanggan tahu tujuan transfer.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-neutral-100">
                    <?php foreach ($accounts as $account): ?>
                        <div class="p-5 md:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-semibold"><?= e($account['bank_name']) ?></p>
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold <?= $account['status'] === 'ACTIVE' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-500' ?>"><?= e($account['status']) ?></span>
                                    </div>
                                    <p class="mt-2 text-lg font-semibold tracking-tight"><?= e($account['account_number']) ?></p>
                                    <p class="mt-1 text-sm text-neutral-500">a.n. <?= e($account['account_name']) ?></p>
                                    <?php if ($account['notes']): ?><p class="mt-2 text-xs text-neutral-400"><?= e($account['notes']) ?></p><?php endif; ?>
                                </div>
                                <button type="button" class="copy-payment-account shrink-0 inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-neutral-100 text-xs font-semibold" data-copy="<?= e($account['bank_name'] . ' ' . $account['account_number'] . ' a.n. ' . $account['account_name']) ?>">
                                    <i data-lucide="copy" class="w-4 h-4"></i> Copy
                                </button>
                            </div>
                            <div class="mt-4 flex gap-2">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="TOGGLE">
                                    <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                    <button class="px-3 py-2 rounded-xl border border-neutral-200 text-xs font-medium hover:bg-neutral-50"><?= $account['status'] === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                </form>
                                <form method="post" onsubmit="return confirm('Hapus rekening ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="DELETE">
                                    <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                    <button class="px-3 py-2 rounded-xl border border-red-200 bg-red-50 text-red-700 text-xs font-medium">Hapus</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
</main>
<script>
document.querySelectorAll('.copy-payment-account').forEach(function (button) {
    button.addEventListener('click', async function () {
        try {
            await navigator.clipboard.writeText(button.dataset.copy || '');
            const original = button.innerHTML;
            button.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Tersalin';
            if (window.lucide) lucide.createIcons();
            setTimeout(function () {
                button.innerHTML = original;
                if (window.lucide) lucide.createIcons();
            }, 3000);
        } catch (error) {}
    });
});
setTimeout(function () {
    document.getElementById('paymentAccountFlash')?.remove();
}, 3000);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
