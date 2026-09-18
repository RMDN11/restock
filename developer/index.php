<?php
require_once __DIR__ . '/../includes/developer_auth.php';

$pageTitle = 'Developer Console';

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
