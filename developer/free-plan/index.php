<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/developer_auth.php';

$pageTitle = 'Free Plan';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatDateTime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d M Y, H:i', $timestamp) : e($value);
}

$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$createdToken = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');

    if ($postedCsrf === '' || !hash_equals($csrfToken, $postedCsrf)) {
        $error = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $packageId = filter_var($_POST['package_id'] ?? '', FILTER_VALIDATE_INT);
        $packageId = $packageId !== false && $packageId > 0 ? (int) $packageId : null;

        $maxUses = filter_var($_POST['max_uses'] ?? '', FILTER_VALIDATE_INT);
        $expiryOption = (string) ($_POST['invite_expiry'] ?? '7');

        if ($maxUses === false || $maxUses < 1 || $maxUses > 10000) {
            $error = 'Jumlah owner maksimal 1 sampai 10.000.';
        } elseif (!in_array($expiryOption, ['1', '7', '30', 'lifetime'], true)) {
            $error = 'Masa berlaku link tidak valid.';
        } else {
            $package = null;

            if ($packageId !== null) {
                $packageStmt = $pdo->prepare(
                    "SELECT id, name, duration_days, min_store_count, max_store_count
                     FROM packages
                     WHERE id = :id AND status = 'ACTIVE'
                     LIMIT 1"
                );
                $packageStmt->execute([':id' => $packageId]);
                $package = $packageStmt->fetch();

                if (!$package) {
                    $error = 'Paket yang dipilih tidak tersedia atau sudah tidak aktif.';
                }
            }

            if ($error === null || $error === '') {
                try {
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = $expiryOption === 'lifetime'
                        ? null
                        : date('Y-m-d H:i:s', time() + ((int) $expiryOption * 86400));

                    $insert = $pdo->prepare(
                        "INSERT INTO free_plan_invites
                            (package_id, created_by, token_hash, max_uses, used_count, access_scope, expires_at, status, created_at, updated_at)
                         VALUES
                            (:package_id, :created_by, :token_hash, :max_uses, 0, 'ACCOUNT', :expires_at, 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
                    );

                    $insert->execute([
                        ':package_id' => $packageId,
                        ':created_by' => $authUserId,
                        ':token_hash' => hash('sha256', $token),
                        ':max_uses' => $maxUses,
                        ':expires_at' => $expiresAt,
                    ]);

                    $createdToken = $token;
                    $flash = 'Link Free Plan berhasil dibuat.';
                } catch (PDOException $e) {
                    $error = 'Link Free Plan belum dapat dibuat. Pastikan migration 011 sudah dijalankan di database production.';
                }
            }
        }
    }
}

try {
    $packages = $pdo->query(
        "SELECT id, name, price, duration_days, min_store_count, max_store_count
         FROM packages
         WHERE status = 'ACTIVE'
         ORDER BY price ASC, id ASC"
    )->fetchAll();

    $invites = $pdo->query(
        "SELECT
            fpi.id,
            fpi.package_id,
            fpi.max_uses,
            fpi.used_count,
            fpi.expires_at,
            fpi.status,
            fpi.last_used_at,
            fpi.created_at,
            p.name AS package_name,
            p.duration_days,
            u.name AS creator_name
         FROM free_plan_invites fpi
         LEFT JOIN packages p ON p.id = fpi.package_id
         LEFT JOIN users u ON u.id = fpi.created_by
         ORDER BY fpi.id DESC
         LIMIT 20"
    )->fetchAll();
} catch (Throwable $e) {
    $packages = [];
    $invites = [];
    $error = $error ?: 'Data Free Plan belum dapat dimuat. Pastikan migration 011 sudah dijalankan.';
}

$activeInvites = 0;
$totalRemaining = 0;
foreach ($invites as $invite) {
    $isExpired = $invite['status'] === 'ACTIVE'
        && $invite['expires_at'] !== null
        && strtotime((string) $invite['expires_at']) <= time();

    if ($invite['status'] === 'ACTIVE' && !$isExpired) {
        $activeInvites++;
        $totalRemaining += max(0, (int) $invite['max_uses'] - (int) $invite['used_count']);
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="lg:ml-64 pt-16 min-h-screen">
    <div class="p-4 md:p-8 max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer · Access</p>
                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight mt-1">Free Plan</h1>
                <p class="text-sm text-neutral-500 mt-2">Buat undangan Free Plan untuk onboarding owner baru. Setelah aktivasi, owner login normal tanpa token.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-neutral-400">
                <span class="inline-flex w-2 h-2 rounded-full bg-emerald-500"></span>
                Account-level entitlement
            </div>
        </div>

        <?php if ($flash): ?>
            <div id="freePlanNotice" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($flash) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div id="freePlanNotice" class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($createdToken): ?>
            <?php
            $baseUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://')
                . ($_SERVER['HTTP_HOST'] ?? 'restock.reqra.my.id');
            $inviteUrl = $baseUrl . '/free-plan.php?token=' . $createdToken;
            ?>
            <section class="bento-card mb-5 p-5 md:p-6 border border-emerald-100">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 flex items-center justify-center shrink-0">
                        <i data-lucide="link" class="w-5 h-5 text-emerald-700"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">Link Free Plan siap dibagikan</p>
                        <p class="text-xs text-neutral-500 mt-1">Token hanya ditampilkan sekali. Simpan atau bagikan link ini ke owner yang dituju.</p>
                        <div class="mt-3 flex flex-col sm:flex-row gap-2">
                            <input id="freePlanInviteLink" type="text" readonly value="<?= e($inviteUrl) ?>" class="min-h-11 min-w-0 flex-1 rounded-xl border border-neutral-200 bg-white px-3 text-xs text-neutral-700">
                            <button type="button" id="copyFreePlanInvite" class="min-h-11 rounded-xl border border-neutral-200 bg-white px-4 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Salin Link</button>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="grid grid-cols-1 xl:grid-cols-[1.05fr_.95fr] gap-4 mb-6">
            <div class="bento-card p-5 md:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">Buat undangan</p>
                        <h2 class="text-lg font-semibold mt-1">Free Plan Owner</h2>
                        <p class="text-sm text-neutral-500 mt-2">Satu penggunaan mewakili satu account/owner. Kuota dapat diatur sesuai campaign atau kebutuhan onboarding.</p>
                    </div>
                    <span class="w-10 h-10 rounded-2xl bg-neutral-100 flex items-center justify-center">
                        <i data-lucide="gift" class="w-5 h-5 text-neutral-700"></i>
                    </span>
                </div>

                <form method="post" class="mt-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div>
                        <label class="block text-sm font-medium mb-2" for="package_id">Benefit / Paket</label>
                        <select id="package_id" name="package_id" class="w-full min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm outline-none focus:border-neutral-400">
                            <option value="">Free lifetime · tanpa paket berbayar</option>
                            <?php foreach ($packages as $package): ?>
                                <option value="<?= (int) $package['id'] ?>">
                                    <?= e($package['name']) ?> · <?= number_format((int) $package['duration_days']) ?> hari<?php if ($package['min_store_count'] !== null): ?> · mulai <?= number_format((int) $package['min_store_count']) ?> toko<?php endif; ?><?php if ($package['max_store_count'] !== null): ?> · sampai <?= number_format((int) $package['max_store_count']) ?> toko<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-neutral-400 mt-2">Harga paket tidak ditagihkan. Paket dipakai sebagai konfigurasi benefit dan durasi Free Plan saat onboarding.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" for="max_uses">Jumlah owner yang boleh memakai link</label>
                        <div class="relative">
                            <input id="max_uses" name="max_uses" type="number" min="1" max="10000" value="1" required class="w-full min-h-11 rounded-xl border border-neutral-200 px-3 pr-16 text-sm outline-none focus:border-neutral-400">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-neutral-400">owner</span>
                        </div>
                        <p class="text-xs text-neutral-400 mt-2">Contoh: isi 10 untuk membuat satu campaign dengan kuota 10 owner Free Plan.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2" for="invite_expiry">Link berlaku selama</label>
                        <select id="invite_expiry" name="invite_expiry" class="w-full min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm outline-none focus:border-neutral-400">
                            <option value="1">1 hari</option>
                            <option value="7" selected>7 hari</option>
                            <option value="30">30 hari</option>
                            <option value="lifetime">Lifetime</option>
                        </select>
                        <p class="text-xs text-neutral-400 mt-2">Ini masa berlaku link undangan, bukan otomatis masa berlaku benefit Free Plan.</p>
                    </div>

                    <div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-4">
                        <div class="flex gap-3">
                            <i data-lucide="info" class="w-4 h-4 text-neutral-500 mt-0.5 shrink-0"></i>
                            <p class="text-xs leading-5 text-neutral-500">Setelah owner menyelesaikan onboarding, Free Plan tersimpan di account. Login berikutnya menggunakan username/password biasa dan tidak membutuhkan link lagi.</p>
                        </div>
                    </div>

                    <button type="submit" class="w-full min-h-11 rounded-xl bg-neutral-900 px-5 text-sm font-semibold text-white hover:bg-neutral-800">
                        Buat Link Free Plan
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bento-card p-5">
                    <p class="text-sm text-neutral-500">Invite Aktif</p>
                    <p class="text-3xl font-semibold tracking-tight mt-3"><?= number_format($activeInvites) ?></p>
                    <p class="text-xs text-neutral-400 mt-1">Link masih dapat digunakan</p>
                </div>
                <div class="bento-card p-5">
                    <p class="text-sm text-neutral-500">Sisa Kuota</p>
                    <p class="text-3xl font-semibold tracking-tight mt-3"><?= number_format($totalRemaining) ?></p>
                    <p class="text-xs text-neutral-400 mt-1">Owner dari invite aktif</p>
                </div>
                <div class="bento-card col-span-2 p-5">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-neutral-100 flex items-center justify-center shrink-0">
                            <i data-lucide="shield-check" class="w-5 h-5 text-neutral-700"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold">Model akses</p>
                            <p class="text-xs text-neutral-500 leading-5 mt-1">Free Plan adalah entitlement account. Special Access tetap dipisahkan untuk demo, support, atau akses sementara dari Developer.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bento-card overflow-hidden">
            <div class="px-5 py-4 md:px-6 border-b border-neutral-100 flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold">Riwayat Undangan</h2>
                    <p class="text-xs text-neutral-400 mt-1">20 invite terakhir. Token tidak pernah disimpan dalam bentuk plaintext.</p>
                </div>
                <span class="text-xs text-neutral-400"><?= number_format(count($invites)) ?> data</span>
            </div>

            <?php if (!$invites): ?>
                <div class="px-5 py-14 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-neutral-100 flex items-center justify-center mx-auto">
                        <i data-lucide="ticket" class="w-5 h-5 text-neutral-500"></i>
                    </div>
                    <p class="text-sm font-medium mt-3">Belum ada undangan</p>
                    <p class="text-xs text-neutral-400 mt-1">Buat invite pertama untuk onboarding Free Plan.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[850px] text-sm">
                        <thead class="bg-neutral-50 border-b border-neutral-100">
                            <tr class="text-left text-xs text-neutral-400">
                                <th class="px-5 md:px-6 py-3 font-medium">Invite</th>
                                <th class="px-5 md:px-6 py-3 font-medium">Benefit</th>
                                <th class="px-5 md:px-6 py-3 font-medium">Kuota</th>
                                <th class="px-5 md:px-6 py-3 font-medium">Berlaku</th>
                                <th class="px-5 md:px-6 py-3 font-medium">Status</th>
                                <th class="px-5 md:px-6 py-3 font-medium">Dibuat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <?php foreach ($invites as $invite): ?>
                                <?php
                                $inviteStatus = $invite['status'];
                                if ($inviteStatus === 'ACTIVE' && $invite['expires_at'] !== null && strtotime((string) $invite['expires_at']) <= time()) {
                                    $inviteStatus = 'EXPIRED';
                                }
                                $remaining = max(0, (int) $invite['max_uses'] - (int) $invite['used_count']);
                                ?>
                                <tr class="align-top hover:bg-neutral-50/70">
                                    <td class="px-5 md:px-6 py-4">
                                        <p class="font-medium text-neutral-900">#<?= (int) $invite['id'] ?></p>
                                        <p class="text-xs text-neutral-400 mt-1">ACCOUNT</p>
                                    </td>
                                    <td class="px-5 md:px-6 py-4">
                                        <p class="font-medium text-neutral-900"><?= e($invite['package_name'] ?: 'Free Lifetime') ?></p>
                                        <?php if ($invite['package_name']): ?>
                                            <p class="text-xs text-neutral-400 mt-1"><?= number_format((int) $invite['duration_days']) ?> hari</p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 md:px-6 py-4">
                                        <p class="font-medium"><?= number_format((int) $invite['used_count']) ?> / <?= number_format((int) $invite['max_uses']) ?></p>
                                        <p class="text-xs text-neutral-400 mt-1"><?= number_format($remaining) ?> tersisa</p>
                                    </td>
                                    <td class="px-5 md:px-6 py-4">
                                        <p class="font-medium"><?= $invite['expires_at'] === null ? 'Lifetime' : e(formatDateTime($invite['expires_at'])) ?></p>
                                        <?php if ($invite['last_used_at']): ?>
                                            <p class="text-xs text-neutral-400 mt-1">Terakhir dipakai <?= e(formatDateTime($invite['last_used_at'])) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 md:px-6 py-4">
                                        <span class="inline-flex rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-600"><?= e($inviteStatus) ?></span>
                                    </td>
                                    <td class="px-5 md:px-6 py-4">
                                        <p class="font-medium"><?= e($invite['creator_name'] ?: 'System') ?></p>
                                        <p class="text-xs text-neutral-400 mt-1"><?= e(formatDateTime($invite['created_at'])) ?></p>
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

<script>
setTimeout(() => {
    const notice = document.getElementById('freePlanNotice');
    if (notice) notice.remove();
}, 3000);

const copyButton = document.getElementById('copyFreePlanInvite');
const inviteInput = document.getElementById('freePlanInviteLink');

if (copyButton && inviteInput) {
    copyButton.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(inviteInput.value);
            copyButton.textContent = 'Tersalin';
            setTimeout(() => { copyButton.textContent = 'Salin Link'; }, 1800);
        } catch (error) {
            inviteInput.select();
            document.execCommand('copy');
            copyButton.textContent = 'Tersalin';
            setTimeout(() => { copyButton.textContent = 'Salin Link'; }, 1800);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
