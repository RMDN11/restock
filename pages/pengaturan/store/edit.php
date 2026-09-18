<?php
require_once __DIR__ . '/../../../includes/auth.php';

if ($authRole !== 'ADMIN') {
    header('Location: /pages/pengaturan/');
    exit;
}

$storeId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT id, name, slug, phone, address, status
    FROM stores
    WHERE id = :store_id
      AND account_id = :account_id
    LIMIT 1
");
$stmt->execute([
    ':store_id' => $storeId,
    ':account_id' => $authAccountId,
]);

$store = $stmt->fetch();

if (!$store) {
    header('Location: /pages/pengaturan/store/');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Permintaan tidak valid.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '' || $slug === '') {
            $error = 'Nama dan slug Store wajib diisi.';
        } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $error = 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.';
        } else {
            $check = $pdo->prepare("
                SELECT id
                FROM stores
                WHERE slug = :slug
                  AND id <> :current_store_id
                LIMIT 1
            ");
            $check->execute([
                ':slug' => $slug,
                ':current_store_id' => $storeId,
            ]);

            if ($check->fetch()) {
                $error = 'Slug Store sudah digunakan.';
            } else {
                $update = $pdo->prepare("
                    UPDATE stores
                    SET name = :name,
                        slug = :slug,
                        phone = :phone,
                        address = :address,
                        updated_at = NOW()
                    WHERE id = :store_id
                      AND account_id = :account_id
                ");

                $update->execute([
                    ':name' => $name,
                    ':slug' => $slug,
                    ':phone' => $phone !== '' ? $phone : null,
                    ':address' => $address !== '' ? $address : null,
                    ':store_id' => $storeId,
                    ':account_id' => $authAccountId,
                ]);

                $audit = $pdo->prepare("
                    INSERT INTO audit_logs
                        (store_id, user_id, action, table_name, record_id, description, created_at)
                    VALUES
                        (:audit_store_id, :audit_user_id, 'UPDATE', 'stores', :record_id, :description, CURRENT_TIMESTAMP)
                ");
                $audit->execute([
                    ':audit_store_id' => $storeId,
                    ':audit_user_id' => $authUserId,
                    ':record_id' => $storeId,
                    ':description' => 'Mengubah Store: ' . $name . ' (' . $slug . ')'
                ]);

                if ($storeId === $authStoreId) {
                    $_SESSION['store_name'] = $name;
                    $_SESSION['store_slug'] = $slug;
                }

                $_SESSION['flash_success'] = 'Store berhasil diperbarui.';
                header('Location: /pages/pengaturan/store/');
                exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $store['name'] = $_POST['name'] ?? $store['name'];
    $store['slug'] = $_POST['slug'] ?? $store['slug'];
    $store['phone'] = $_POST['phone'] ?? $store['phone'];
    $store['address'] = $_POST['address'] ?? $store['address'];
}

$pageTitle = 'Edit Store';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';
?>

<main class="main-content min-h-screen bg-neutral-50">
    <div class="max-w-3xl mx-auto px-4 py-6 md:px-8 md:py-8">
        <a href="/pages/pengaturan/store/" class="inline-flex items-center gap-2 text-sm text-neutral-500 hover:text-neutral-900">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Kembali ke Store
        </a>

        <div class="mt-6 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm md:p-7">
            <h1 class="text-xl font-bold tracking-tight">Edit Store</h1>

            <?php if ($error): ?>
                <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="mt-6 space-y-5">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label class="mb-2 block text-sm font-semibold">Nama Store</label>
                    <input name="name" required value="<?= htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-500">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">Slug</label>
                    <input name="slug" required value="<?= htmlspecialchars($store['slug'], ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-500">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">No. Telepon</label>
                    <input name="phone" value="<?= htmlspecialchars($store['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-500">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">Alamat</label>
                    <textarea name="address" rows="4"
                              class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-500"><?= htmlspecialchars($store['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button class="inline-flex items-center justify-center gap-2 rounded-xl bg-neutral-900 px-5 py-3 text-sm font-semibold text-white hover:bg-neutral-800">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Simpan Perubahan
                </button>
            </form>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
