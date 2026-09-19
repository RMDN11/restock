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
$durationDays = (string) $package['duration_days'];
$description = (string) ($package['description'] ?? '');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Sesi keamanan tidak valid. Silakan coba lagi.';

    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
    $durationDays = trim((string) ($_POST['duration_days'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Nama paket wajib diisi dan maksimal 100 karakter.';
    if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || mb_strlen($slug) > 120) $errors[] = 'Slug wajib berisi huruf kecil, angka, dan strip.';

    $durationNumber = filter_var($durationDays, FILTER_VALIDATE_INT);
    if ($durationNumber === false || $durationNumber < 1 || $durationNumber > 36500) $errors[] = 'Durasi harus berupa angka minimal 1 hari.';

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM packages WHERE slug = :slug AND id <> :id LIMIT 1');
        $check->execute([':slug' => $slug, ':id' => $id]);
        if ($check->fetch()) $errors[] = 'Slug paket sudah digunakan.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE packages SET name = :name, slug = :slug, duration_days = :duration_days,
            description = :description, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':duration_days' => $durationNumber,
            ':description' => $description !== '' ? $description : null,
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
    <p class="text-sm text-neutral-500 mt-2 mb-6">Harga dan kapasitas toko dikelola di menu Pricing.</p>

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
        <div>
            <label class="block text-sm font-medium mb-2" for="duration_days">Durasi</label>
                <div class="relative">
                    <input id="duration_days" name="duration_days" type="number" min="1" max="36500" required value="<?= e($durationDays) ?>" class="w-full h-11 rounded-xl border border-neutral-200 px-4 pr-16 text-sm outline-none focus:border-neutral-400">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-neutral-400">hari</span>
                </div>
            </div>
        </div>
        </div>
        <div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-neutral-400">Pricing</p>
            <p class="text-xs text-neutral-500 mt-1">Harga dan kapasitas toko sekarang dikelola melalui menu <strong>Pricing</strong>. Perubahan paket di halaman ini hanya mengubah identitas dan durasi paket.</p>
            <a href="/developer/packages/pricing.php?id=<?= (int) $id ?>" class="inline-flex mt-3 items-center gap-2 rounded-xl bg-white border border-neutral-200 px-3 py-2 text-xs font-semibold hover:bg-neutral-50">Kelola Pricing</a>
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
