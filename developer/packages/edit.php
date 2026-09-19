<?php
require_once __DIR__ . '/../../includes/developer_auth.php';

$pageTitle = 'Edit Paket';
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { header('Location: /developer/packages/'); exit; }

$stmt = $pdo->prepare('SELECT id, name, slug, price, duration_days, min_store_count, max_store_count, description, status FROM packages WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$package = $stmt->fetch();

if (!$package) { http_response_code(404); die('Paket tidak ditemukan.'); }

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

$name = (string) $package['name'];
$slug = (string) $package['slug'];
$price = (string) $package['price'];
$durationDays = (string) $package['duration_days'];
$description = (string) ($package['description'] ?? '');
$minStoreCount = (string) ($package['min_store_count'] ?? '');
$maxStoreCount = (string) ($package['max_store_count'] ?? '');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Sesi keamanan tidak valid. Silakan coba lagi.';

    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
    $price = trim((string) ($_POST['price'] ?? ''));
    $durationDays = trim((string) ($_POST['duration_days'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $minStoreCount = trim((string) ($_POST['min_store_count'] ?? ''));
    $maxStoreCount = trim((string) ($_POST['max_store_count'] ?? ''));

    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Nama paket wajib diisi dan maksimal 100 karakter.';
    if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || mb_strlen($slug) > 120) $errors[] = 'Slug wajib berisi huruf kecil, angka, dan strip.';

    $priceNumber = filter_var($price, FILTER_VALIDATE_FLOAT);
    if ($priceNumber === false || $priceNumber < 0 || $priceNumber > 999999999999.99) $errors[] = 'Harga tidak valid.';
    $durationNumber = filter_var($durationDays, FILTER_VALIDATE_INT);
    if ($durationNumber === false || $durationNumber < 1 || $durationNumber > 36500) $errors[] = 'Durasi harus berupa angka minimal 1 hari.';

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM packages WHERE slug = :slug AND id <> :id LIMIT 1');
        $check->execute([':slug' => $slug, ':id' => $id]);
        if ($check->fetch()) $errors[] = 'Slug paket sudah digunakan.';
    }

    if (!$errors) {
        $minStoreNumber = $minStoreCount === '' ? null : filter_var($minStoreCount, FILTER_VALIDATE_INT);
        $maxStoreNumber = $maxStoreCount === '' ? null : filter_var($maxStoreCount, FILTER_VALIDATE_INT);
        if ($minStoreCount !== '' && ($minStoreNumber === false || $minStoreNumber < 1)) $errors[] = 'Minimal jumlah toko tidak valid.';
        if ($maxStoreCount !== '' && ($maxStoreNumber === false || $maxStoreNumber < 1)) $errors[] = 'Maksimal jumlah toko tidak valid.';
        if (!$errors && $maxStoreNumber !== null && $minStoreNumber !== null && $maxStoreNumber < $minStoreNumber) $errors[] = 'Maksimal jumlah toko tidak boleh kurang dari minimal.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE packages SET name = :name, slug = :slug, price = :price, duration_days = :duration_days,
            description = :description, min_store_count = :min_store_count, max_store_count = :max_store_count, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':price' => number_format((float) $priceNumber, 2, '.', ''),
            ':duration_days' => $durationNumber,
            ':description' => $description !== '' ? $description : null,
            ':min_store_count' => $minStoreNumber,
            ':max_store_count' => $maxStoreNumber,
            ':id' => $id
        ]);
        $_SESSION['flash_success'] = 'Paket berhasil diperbarui.';
        header('Location: /developer/packages/');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="lg:ml-64 pt-16 min-h-screen">
<div class="p-4 md:p-8 max-w-3xl mx-auto">
    <a href="/developer/packages/" class="inline-flex items-center gap-1.5 text-xs text-neutral-500 hover:text-neutral-900 mb-5"><i data-lucide="arrow-left" class="w-4 h-4"></i>Kembali ke Paket</a>
    <p class="text-xs uppercase tracking-wider text-neutral-400">RESTOCK Developer · Finance</p>
    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight mt-1">Edit Paket</h1>
    <p class="text-sm text-neutral-500 mt-2 mb-6">Perubahan harga akan digunakan pada checkout berikutnya.</p>

    <?php if ($errors): ?>
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="space-y-1"><?php foreach ($errors as $error): ?><li>• <?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" class="bento-card p-5 md:p-7 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <div>
            <label class="block text-sm font-medium mb-2" for="name">Nama Paket</label>
            <input id="name" name="name" required maxlength="100" value="<?= e($name) ?>" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400">
        </div>
        <div>
            <label class="block text-sm font-medium mb-2" for="slug">Slug</label>
            <input id="slug" name="slug" required maxlength="120" value="<?= e($slug) ?>" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium mb-2" for="price">Harga</label>
                <input id="price" name="price" type="number" min="0" step="0.01" required value="<?= e($price) ?>" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2" for="duration_days">Durasi</label>
                <div class="relative">
                    <input id="duration_days" name="duration_days" type="number" min="1" max="36500" required value="<?= e($durationDays) ?>" class="w-full h-11 rounded-xl border border-neutral-200 px-4 pr-16 text-sm outline-none focus:border-neutral-400">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-neutral-400">hari</span>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium mb-2" for="min_store_count">Minimal Toko</label>
                <input id="min_store_count" name="min_store_count" type="number" min="1" value="<?= e($minStoreCount) ?>" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400" placeholder="1">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2" for="max_store_count">Maksimal Toko</label>
                <input id="max_store_count" name="max_store_count" type="number" min="1" value="<?= e($maxStoreCount) ?>" class="w-full h-11 rounded-xl border border-neutral-200 px-4 text-sm outline-none focus:border-neutral-400" placeholder="Kosong = tanpa batas">
            </div>
        </div>
        <div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-neutral-400">Cakupan Toko</p>
            <p class="text-xs text-neutral-500 mt-1">Batas ini menentukan jumlah toko aktif yang dapat digunakan pada paket saat ini.</p>
        </div>
        <div>
            <label class="block text-sm font-medium mb-2" for="description">Deskripsi</label>
            <textarea id="description" name="description" rows="4" class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm outline-none focus:border-neutral-400"><?= e($description) ?></textarea>
        </div>
        <div class="pt-2 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <a href="/developer/packages/" class="inline-flex justify-center px-4 py-3 rounded-xl border border-neutral-200 text-sm font-medium hover:bg-neutral-50">Batal</a>
            <button type="submit" class="inline-flex justify-center items-center gap-2 px-5 py-3 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"><i data-lucide="save" class="w-4 h-4"></i>Simpan Perubahan</button>
        </div>
    </form>
</div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
