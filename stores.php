<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/store_management.php';

$pageTitle = 'Toko Saya';
$userId = (int) $authUserId;
$accountId = (int) $authAccountId;
$errors = [];
$isStoreAdmin = strtoupper((string) $authRole) === 'ADMIN';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isStoreAdmin) {
        $errors[] = 'Hanya owner/admin account yang dapat menambah toko.';
    }

    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    }

    $storeName = trim((string) ($_POST['store_name'] ?? ''));
    $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));

    if ($storeName === '') {
        $errors[] = 'Nama toko wajib diisi.';
    } elseif (mb_strlen($storeName) > 150) {
        $errors[] = 'Nama toko maksimal 150 karakter.';
    }

    if ($slug === '') {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $storeName), '-'));
    }

    if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        $errors[] = 'Slug toko hanya boleh berisi huruf kecil, angka, dan tanda hubung.';
    } elseif (mb_strlen($slug) > 180) {
        $errors[] = 'Slug toko maksimal 180 karakter.';
    }

    if (!$errors) {
        $check = restockCanAddAccountStore($pdo, $accountId);
        if (!$check['allowed']) {
            $errors[] = $check['reason'];
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            /* Lock the account row so concurrent requests cannot both pass the store limit. */
            $accountLock = $pdo->prepare(
                "SELECT id, plan_type, free_plan_expires_at
                 FROM accounts
                 WHERE id = :account_id
                 LIMIT 1
                 FOR UPDATE"
            );
            $accountLock->execute([':account_id' => $accountId]);
            if (!$accountLock->fetch()) {
                throw new RuntimeException('Account tidak ditemukan.');
            }

            $check = restockCanAddAccountStore($pdo, $accountId);
            if (!$check['allowed']) {
                throw new RuntimeException($check['reason']);
            }

            $slugCheck = $pdo->prepare(
                "SELECT id
                 FROM stores
                 WHERE slug = :slug
                 LIMIT 1"
            );
            $slugCheck->execute([':slug' => $slug]);
            if ($slugCheck->fetch()) {
                throw new RuntimeException('Slug toko tersebut sudah digunakan.');
            }

            $storeStmt = $pdo->prepare(
                "INSERT INTO stores
                    (account_id, name, slug, status, created_at, updated_at)
                 VALUES
                    (:account_id, :name, :slug, 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $storeStmt->execute([
                ':account_id' => $accountId,
                ':name' => $storeName,
                ':slug' => $slug,
            ]);
            $newStoreId = (int) $pdo->lastInsertId();

            $membershipStmt = $pdo->prepare(
                "INSERT INTO store_users
                    (store_id, user_id, role, status, created_at, updated_at)
                 VALUES
                    (:store_id, :user_id, 'ADMIN', 'ACTIVE', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $membershipStmt->execute([
                ':store_id' => $newStoreId,
                ':user_id' => $userId,
            ]);

            $pdo->commit();

            $_SESSION['flash_success'] = 'Toko berhasil ditambahkan. Sekarang kamu berada di toko baru.';
            $_SESSION['store_id'] = $newStoreId;
            $_SESSION['store_name'] = $storeName;
            $_SESSION['store_slug'] = $slug;

            header('Location: /');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Toko gagal ditambahkan. Silakan coba lagi.';
        }
    }
}

$entitlement = restockGetAccountStoreEntitlement($pdo, $accountId);
$currentCount = restockGetActiveAccountStoreCount($pdo, $accountId);
$canAdd = restockCanAddAccountStore($pdo, $accountId);

$storesStmt = $pdo->prepare(
    "SELECT id, name, slug, status, created_at
     FROM stores
     WHERE account_id = :account_id
     ORDER BY CASE WHEN status = 'ACTIVE' THEN 0 ELSE 1 END, id ASC"
);
$storesStmt->execute([':account_id' => $accountId]);
$stores = $storesStmt->fetchAll();

$flash = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="main-content">
<div class="p-4 md:p-8 max-w-6xl mx-auto">
    <div class="mb-7">
        <p class="text-xs font-medium uppercase tracking-wider text-neutral-400">PENGATURAN</p>
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mt-1">
            <div>
                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">Toko Saya</h1>
                <p class="text-sm text-neutral-500 mt-2">Kelola toko dalam satu account dan pindah toko tanpa login ulang.</p>
            </div>
            <a href="/" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl border border-neutral-200 bg-white text-sm font-medium hover:bg-neutral-50">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div id="storeFlash" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="space-y-1"><?php foreach ($errors as $error): ?><li>• <?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
        <div class="bento-card p-5 border-l-4 border-teal-500">
            <p class="text-xs text-teal-700 font-medium">Paket</p>
            <p class="text-xl font-semibold mt-2"><?= e($entitlement['package_name'] ?? 'Belum aktif') ?></p>
            <p class="text-xs text-neutral-400 mt-1"><?= $entitlement ? e($entitlement['type']) : 'Tidak ada entitlement aktif' ?></p>
        </div>
        <div class="bento-card p-5 border-l-4 border-blue-500">
            <p class="text-xs text-blue-700 font-medium">Toko Aktif</p>
            <p class="text-2xl font-semibold mt-2"><?= $currentCount ?></p>
            <p class="text-xs text-neutral-400 mt-1">dari <?= $canAdd['max_count'] === null ? 'tanpa batas' : $canAdd['max_count'] ?></p>
        </div>
        <div class="bento-card p-5 border-l-4 border-violet-500">
            <p class="text-xs text-violet-700 font-medium">Batas</p>
            <p class="text-xl font-semibold mt-2"><?= $canAdd['max_count'] === null ? '∞' : $canAdd['max_count'] . ' toko' ?></p>
            <p class="text-xs text-neutral-400 mt-1">berdasarkan paket aktif</p>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-[1fr_.85fr] gap-5">
        <div class="bento-card overflow-hidden">
            <div class="px-5 md:px-6 py-5 border-b border-neutral-100">
                <h2 class="font-semibold">Daftar Toko</h2>
                <p class="text-xs text-neutral-400 mt-1">Toko aktif dapat dipilih sebagai toko kerja saat ini.</p>
            </div>
            <div class="divide-y divide-neutral-100">
                <?php foreach ($stores as $store): ?>
                    <div class="px-5 md:px-6 py-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-medium truncate"><?= e($store['name']) ?></p>
                                <?php if ((int) $store['id'] === $authStoreId): ?>
                                    <span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700">Sedang aktif</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-neutral-400 mt-1">/<?= e($store['slug']) ?> · <?= e($store['status']) ?></p>
                        </div>
                        <?php if ($store['status'] === 'ACTIVE' && (int) $store['id'] !== $authStoreId): ?>
                            <form method="post" action="/switch-store.php" class="shrink-0">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="store_id" value="<?= (int) $store['id'] ?>">
                                <button class="h-10 px-4 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800">Gunakan</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bento-card p-5 md:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Tambah Toko</h2>
                    <p class="text-sm text-neutral-500 mt-1">Buat toko baru dalam account yang sama.</p>
                </div>
                <i data-lucide="store" class="w-5 h-5 text-neutral-400"></i>
            </div>

            <?php if (!$isStoreAdmin): ?>
                <div class="mt-5 rounded-2xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-600">
                    Hanya owner/admin account yang dapat menambah toko.
                </div>
            <?php elseif (!$canAdd['allowed']): ?>
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <?= e($canAdd['reason']) ?>
                    <?php if (!$entitlement): ?>
                        <a href="/paket/" class="block mt-2 font-semibold underline">Lihat paket</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="post" class="mt-5 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div>
                        <label class="block text-sm font-medium mb-2" for="store_name">Nama Toko</label>
                        <input id="store_name" name="store_name" required maxlength="150" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400" placeholder="Contoh: Toko Cabang 2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2" for="slug">Slug</label>
                        <input id="slug" name="slug" maxlength="180" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400" placeholder="otomatis dari nama toko">
                    </div>
                    <button type="submit" class="w-full h-11 rounded-xl bg-neutral-900 text-white text-sm font-semibold hover:bg-neutral-800">Tambah Toko</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
</main>
<script>
setTimeout(function () {
    document.getElementById('storeFlash')?.remove();
}, 3000);
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
