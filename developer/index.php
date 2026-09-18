<?php
require_once __DIR__ . '/../includes/developer_auth.php';

$pageTitle = 'Developer Console';

if (empty($_SESSION['developer_access_csrf'])) {
    $_SESSION['developer_access_csrf'] = bin2hex(random_bytes(32));
}
$developerAccessCsrf = $_SESSION['developer_access_csrf'];
$accessCreatedToken = '';
$accessMessage = '';
$accessError = '';

$storeStmt = $pdo->query(
    "SELECT
        s.id,
        s.name,
        a.name AS account_name
     FROM stores s
     INNER JOIN accounts a ON a.id = s.account_id
     WHERE s.status = 'ACTIVE'
       AND a.status = 'ACTIVE'
     ORDER BY a.name ASC, s.name ASC"
);
$developerStores = $storeStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'CREATE_SPECIAL_ACCESS') {
    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');

    if ($postedCsrf === '' || !hash_equals($developerAccessCsrf, $postedCsrf)) {
        $accessError = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $storeId = (int) ($_POST['store_id'] ?? 0);
        $accessScope = strtoupper(trim((string) ($_POST['access_scope'] ?? 'ACCOUNT')));
        $lifetime = ($_POST['lifetime'] ?? '') === '1';

        if (!in_array($accessScope, ['STORE', 'ACCOUNT'], true)) {
            $accessError = 'Scope akses tidak valid.';
        } else {
            $storeLookup = $pdo->prepare(
                "SELECT id, account_id
                 FROM stores
                 WHERE id = :store_id
                   AND status = 'ACTIVE'
                 LIMIT 1"
            );
            $storeLookup->execute([':store_id' => $storeId]);
            $store = $storeLookup->fetch();

            if (!$store) {
                $accessError = 'Store tidak ditemukan atau tidak aktif.';
            } else {
                try {
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = $lifetime ? null : date('Y-m-d H:i:s', time() + 86400);
                    $insert = $pdo->prepare(
                        "INSERT INTO special_access_links
                            (account_id, store_id, created_by, token_hash, access_scope, expires_at, status, created_at, updated_at)
                         VALUES
                            (:account_id, :store_id, :created_by, :token_hash, :access_scope, :expires_at, 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
                    );
                    $insert->execute([
                        ':account_id' => (int) $store['account_id'],
                        ':store_id' => $storeId,
                        ':created_by' => $authUserId,
                        ':token_hash' => hash('sha256', $token),
                        ':access_scope' => $accessScope,
                        ':expires_at' => $expiresAt,
                    ]);

                    $accessCreatedToken = $token;
                    $accessMessage = 'Link akses khusus berhasil dibuat. Simpan dan berikan hanya kepada user tujuan.';
                } catch (PDOException $e) {
                    $accessError = 'Link akses belum dapat dibuat. Silakan coba lagi.';
                }
            }
        }
    }
}

$specialAccessStmt = $pdo->query(
    "SELECT
        sal.id,
        sal.expires_at,
        sal.status,
        sal.last_used_at,
        sal.created_at,
        s.name AS store_name,
        a.name AS account_name
     FROM special_access_links sal
     INNER JOIN stores s ON s.id = sal.store_id
     INNER JOIN accounts a ON a.id = sal.account_id
     ORDER BY sal.id DESC
     LIMIT 12"
);
$specialAccessLinks = $specialAccessStmt->fetchAll();


function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function developerDateTime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d M Y, H:i', $timestamp) : e($value);
}

/*
|--------------------------------------------------------------------------
| PLATFORM OVERVIEW
|--------------------------------------------------------------------------
| Developer melihat data lintas Account/Store. Tidak menggunakan store_id
| dari session karena Developer memang bekerja pada level platform.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("SELECT COUNT(*) FROM accounts");
$totalAccounts = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM stores");
$totalStores = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM accounts WHERE status = 'ACTIVE'");
$activeAccounts = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM stores WHERE status = 'ACTIVE'");
$activeStores = (int) $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT
        al.id,
        al.action,
        al.table_name,
        al.record_id,
        al.description,
        al.created_at,
        u.name AS user_name,
        s.name AS store_name
    FROM audit_logs al
    LEFT JOIN users u
        ON u.id = al.user_id
    LEFT JOIN stores s
        ON s.id = al.store_id
    ORDER BY al.created_at DESC, al.id DESC
    LIMIT 8
");
$recentActivities = $stmt->fetchAll();

$activityIcons = [
    'CREATE' => 'plus-circle',
    'INSERT' => 'plus-circle',
    'UPDATE' => 'pencil',
    'DELETE' => 'trash-2',
    'STATUS' => 'refresh-cw',
    'LOGIN' => 'log-in',
];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="lg:ml-64 pt-16 min-h-screen">
    <div class="p-4 md:p-8 max-w-7xl mx-auto">
        <div class="mb-6">
            <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">RESTOCK Developer</p>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight mt-1">Developer Console</h1>
            <p class="text-sm text-neutral-500 mt-2">Halo, <?= e($authName) ?>. Overview platform RESTOCK.</p>
        </div>


        <section class="grid grid-cols-1 xl:grid-cols-[1.05fr_.95fr] gap-4 mb-6">
            <div class="bento-card p-5 md:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">Akses khusus</p>
                        <h2 class="text-lg font-semibold mt-1">Buat Link Tanpa Subscription</h2>
                        <p class="text-sm text-neutral-500 mt-2">Pilih satu toko sebagai representasi account. Scope ACCOUNT akan berlaku untuk owner dan seluruh toko milik account tersebut. Lifetime tidak memiliki tanggal kedaluwarsa.</p>
                    </div>
                    <span class="w-10 h-10 rounded-2xl bg-neutral-100 flex items-center justify-center">
                        <i data-lucide="key-round" class="w-5 h-5 text-neutral-700"></i>
                    </span>
                </div>
                <?php if ($accessMessage): ?>
                    <div id="specialAccessNotice" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($accessMessage) ?></div>
                <?php endif; ?>
                <?php if ($accessError): ?>
                    <div id="specialAccessNotice" class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($accessError) ?></div>
                <?php endif; ?>
                <?php if ($accessCreatedToken): ?>
                    <div class="mt-4 rounded-2xl border border-neutral-200 bg-neutral-50 p-4">
                        <p class="text-xs text-neutral-500">Link yang baru dibuat</p>
                        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                            <input id="specialAccessLink" type="text" readonly value="<?= e(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'restock.reqra.my.id') . '/developer-access.php?token=' . $accessCreatedToken) ?>" class="min-h-11 min-w-0 flex-1 rounded-xl border border-neutral-200 bg-white px-3 text-xs text-neutral-700">
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('specialAccessLink').value)" class="min-h-11 rounded-xl border border-neutral-200 bg-white px-4 text-sm font-medium text-neutral-700">Salin</button>
                        </div>
                    </div>
                <?php endif; ?>
                <form method="post" class="mt-5 grid grid-cols-1 gap-3">
                    <input type="hidden" name="csrf_token" value="<?= e($developerAccessCsrf) ?>">
                    <input type="hidden" name="action" value="CREATE_SPECIAL_ACCESS">
                    <select name="store_id" required class="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm">
                        <option value="">Pilih account / toko</option>
                        <?php foreach ($developerStores as $store): ?>
                            <option value="<?= (int) $store['id'] ?>"><?= e($store['account_name'] . ' · ' . $store['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <select name="access_scope" class="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 text-sm">
                            <option value="ACCOUNT">Owner + seluruh toko account</option>
                            <option value="STORE">Satu toko saja</option>
                        </select>
                        <label class="min-h-11 rounded-xl border border-neutral-200 bg-white px-3 items-center gap-3 px-3 text-sm flex">
                            <input type="checkbox" name="lifetime" value="1" class="rounded border-neutral-300">
                            <span>Lifetime</span>
                        </label>
                    </div>
                    <button type="submit" class="min-h-11 rounded-xl bg-neutral-900 px-5 text-sm font-semibold text-white">Buat Link</button>
                </form>
            </div>
            <div class="bento-card overflow-hidden">
                <div class="px-5 py-4 border-b border-neutral-100">
                    <h2 class="font-semibold">Link Akses Terbaru</h2>
                    <p class="text-xs text-neutral-400 mt-1">Link ACCOUNT berlaku untuk owner dan seluruh toko pada account yang sama.</p>
                </div>
                <?php if (!$specialAccessLinks): ?>
                    <div class="px-5 py-10 text-center text-sm text-neutral-400">Belum ada link akses khusus.</div>
                <?php else: ?>
                    <div class="divide-y divide-neutral-100">
                        <?php foreach ($specialAccessLinks as $link): ?>
                            <?php
                            $linkStatus = $link['status'];
                            if ($linkStatus === 'ACTIVE' && $link['expires_at'] !== null && strtotime((string) $link['expires_at']) <= time()) {
                                $linkStatus = 'EXPIRED';
                            }
                            ?>
                            <div class="px-5 py-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium truncate"><?= e($link['account_name']) ?></p>
                                        <p class="text-xs text-neutral-500 mt-1"><?= e($link['access_scope'] === 'ACCOUNT' ? 'Owner + seluruh toko account' : ($link['store_name'] ?? 'Store')) ?></p>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full bg-neutral-100 px-2.5 py-1 text-[11px] font-medium text-neutral-600"><?= e($linkStatus) ?></span>
                                </div>
                                <div class="mt-3 text-[11px] text-neutral-400"><?= $link['expires_at'] === null ? 'Lifetime' : 'Berakhir ' . e(date('d M Y, H:i', strtotime((string) $link['expires_at']))) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bento-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-neutral-500">Total Account</span>
                    <span class="w-10 h-10 rounded-2xl bg-neutral-100 flex items-center justify-center">
                        <i data-lucide="users" class="w-5 h-5 text-neutral-700"></i>
                    </span>
                </div>
                <p class="text-3xl font-semibold tracking-tight mt-5"><?= number_format($totalAccounts) ?></p>
                <p class="text-xs text-neutral-400 mt-1">Seluruh account terdaftar</p>
            </div>

            <div class="bento-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-neutral-500">Total Toko</span>
                    <span class="w-10 h-10 rounded-2xl bg-neutral-100 flex items-center justify-center">
                        <i data-lucide="store" class="w-5 h-5 text-neutral-700"></i>
                    </span>
                </div>
                <p class="text-3xl font-semibold tracking-tight mt-5"><?= number_format($totalStores) ?></p>
                <p class="text-xs text-neutral-400 mt-1">Seluruh toko terdaftar</p>
            </div>

            <div class="bento-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-neutral-500">Account Aktif</span>
                    <span class="w-10 h-10 rounded-2xl bg-neutral-100 flex items-center justify-center">
                        <i data-lucide="user-check" class="w-5 h-5 text-neutral-700"></i>
                    </span>
                </div>
                <p class="text-3xl font-semibold tracking-tight mt-5"><?= number_format($activeAccounts) ?></p>
                <p class="text-xs text-neutral-400 mt-1">Status ACTIVE</p>
            </div>

            <div class="bento-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-neutral-500">Toko Aktif</span>
                    <span class="w-10 h-10 rounded-2xl bg-neutral-100 flex items-center justify-center">
                        <i data-lucide="store" class="w-5 h-5 text-neutral-700"></i>
                    </span>
                </div>
                <p class="text-3xl font-semibold tracking-tight mt-5"><?= number_format($activeStores) ?></p>
                <p class="text-xs text-neutral-400 mt-1">Status ACTIVE</p>
            </div>
        </section>

        <section class="bento-card overflow-hidden">
            <div class="px-5 py-4 md:px-6 border-b border-neutral-100 flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold">Aktivitas Terbaru</h2>
                    <p class="text-xs text-neutral-400 mt-1">Aktivitas terbaru yang tercatat di platform.</p>
                </div>
                <span class="text-xs text-neutral-400"><?= number_format(count($recentActivities)) ?> aktivitas</span>
            </div>

            <?php if (!$recentActivities): ?>
                <div class="px-5 py-10 md:px-6 text-center">
                    <div class="w-11 h-11 rounded-2xl bg-neutral-100 flex items-center justify-center mx-auto">
                        <i data-lucide="activity" class="w-5 h-5 text-neutral-500"></i>
                    </div>
                    <p class="text-sm font-medium mt-3">Belum ada aktivitas</p>
                    <p class="text-xs text-neutral-400 mt-1">Audit log akan muncul di sini saat aktivitas tercatat.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-neutral-100">
                    <?php foreach ($recentActivities as $activity): ?>
                        <?php
                        $action = strtoupper((string) ($activity['action'] ?? 'ACTIVITY'));
                        $icon = $activityIcons[$action] ?? 'activity';
                        $actor = trim((string) ($activity['user_name'] ?? '')) ?: 'System';
                        $storeName = trim((string) ($activity['store_name'] ?? ''));
                        ?>
                        <div class="px-5 py-4 md:px-6 flex items-start gap-3">
                            <div class="w-9 h-9 rounded-xl bg-neutral-100 flex items-center justify-center flex-none">
                                <i data-lucide="<?= e($icon) ?>" class="w-4 h-4 text-neutral-600"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1 sm:gap-4">
                                    <p class="text-sm font-medium text-neutral-800 break-words">
                                        <?= e($activity['description'] ?? ($action . ' ' . ($activity['table_name'] ?? ''))) ?>
                                    </p>
                                    <time class="text-xs text-neutral-400 whitespace-nowrap">
                                        <?= e(developerDateTime($activity['created_at'] ?? null)) ?>
                                    </time>
                                </div>
                                <p class="text-xs text-neutral-400 mt-1">
                                    <?= e($actor) ?>
                                    <?php if ($storeName): ?>
                                        <span class="mx-1">•</span><?= e($storeName) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($activity['table_name'])): ?>
                                        <span class="mx-1">•</span><?= e($activity['table_name']) ?><?= !empty($activity['record_id']) ? ' #' . e($activity['record_id']) : '' ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
