<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

$storeId = (int) ($authStoreId ?? 0);
if ($storeId <= 0) { http_response_code(403); exit('Store aktif tidak ditemukan.'); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($value)
{
    return 'Rp ' . number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}

$consignorId = (int) ($_GET['consignor_id'] ?? 0);

if ($consignorId <= 0) {
    header('Location: /pages/titipan/pembayaran.php?error=invalid');
    exit;
}

/*
|--------------------------------------------------------------------------
| Penitip
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone
    FROM consignors
    WHERE id = :id
    AND store_id = $storeId
    LIMIT 1
");

$stmt->execute([
    ':id' => $consignorId
]);

$consignor = $stmt->fetch();

if (!$consignor) {
    header('Location: /pages/titipan/pembayaran.php?error=notfound');
    exit;
}

/*
|--------------------------------------------------------------------------
| Barang / transaksi yang belum dibayar
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        si.id,
        s.id AS sale_id,
        s.invoice_number,
        s.sale_date,

        p.name AS product_name,

        si.quantity,
        si.selling_price,
        si.subtotal,
        si.consignor_amount,

        COALESCE(
            paid.total_paid,
            0
        ) AS total_paid,

        (
            si.consignor_amount
            -
            COALESCE(paid.total_paid, 0)
        ) AS remaining

    FROM sale_items si

    INNER JOIN sales s
        ON s.id = si.sale_id
        AND s.store_id = $storeId

    INNER JOIN products p
        ON p.id = si.product_id
        AND p.store_id = $storeId

    INNER JOIN consignments cs
        ON cs.product_id = p.id
        AND cs.store_id = $storeId
        AND cs.consignor_id = :consignor_id
        AND cs.store_id = $storeId

    LEFT JOIN (
        SELECT
            sale_item_id,
            SUM(amount) AS total_paid
        FROM consignor_settlement_items
        WHERE store_id = $storeId
        GROUP BY sale_item_id
    ) paid
        ON paid.sale_item_id = si.id

    WHERE
        si.store_id = $storeId
        AND s.status = 'COMPLETED'
        AND p.product_type = 'TITIPAN'

        AND (
            si.consignor_amount
            -
            COALESCE(paid.total_paid, 0)
        ) > 0

    ORDER BY
        s.sale_date ASC,
        si.id ASC
");

$stmt->execute([
    ':consignor_id' => $consignorId
]);

$items = $stmt->fetchAll();

$totalRemaining = 0;

foreach ($items as $item) {
    $totalRemaining += max(
        0,
        (float) $item['remaining']
    );
}

$pageTitle = 'Bayar Penitip';

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">

    <!-- MOBILE HEADER -->
<main class="p-4 md:p-8 max-w-5xl mx-auto">

        <!-- HEADER -->

        <div class="
            flex
            flex-col
            md:flex-row
            md:items-end
            md:justify-between
            gap-5
            mb-8
        ">

            <div>

                <div class="text-xs text-neutral-400 mb-2">
                    PEMBAYARAN PENITIP
                </div>

                <h1 class="
                    text-2xl
                    md:text-3xl
                    font-semibold
                    tracking-tight
                ">
                    <?= e($consignor['name']) ?>
                </h1>

                <?php if (!empty($consignor['phone'])): ?>

                    <p class="text-sm text-neutral-500 mt-2">
                        <?= e($consignor['phone']) ?>
                    </p>

                <?php endif; ?>

            </div>

            <a
                href="/pages/titipan/pembayaran.php"
                class="
                    inline-flex
                    items-center
                    justify-center
                    gap-2
                    px-4
                    py-2.5
                    rounded-xl
                    border
                    border-neutral-200
                    bg-white
                    text-sm
                    font-medium
                "
            >
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Kembali
            </a>

        </div>

        <!-- TOTAL -->

        <div class="bento-card p-6 mb-6">

            <div class="text-sm text-neutral-500">
                Total yang Belum Dibayar
            </div>

            <div class="
                text-3xl
                md:text-4xl
                font-semibold
                tracking-tight
                mt-2
            ">
                <?= rupiah($totalRemaining) ?>
            </div>

        </div>

        <?php if (!$items): ?>

            <div class="bento-card p-10 text-center">

                <div class="
                    w-12
                    h-12
                    rounded-2xl
                    bg-green-50
                    text-green-600
                    flex
                    items-center
                    justify-center
                    mx-auto
                ">
                    <i data-lucide="check" class="w-6 h-6"></i>
                </div>

                <h2 class="font-semibold mt-4">
                    Tidak ada tagihan
                </h2>

                <p class="text-sm text-neutral-500 mt-2">
                    Semua hak penitip sudah dibayarkan.
                </p>

            </div>

        <?php else: ?>

            <form
                method="POST"
                action="/pages/titipan/bayar-process.php"
                class="space-y-6"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="consignor_id"
                    value="<?= $consignorId ?>"
                >

                <!-- ITEMS -->

                <div class="bento-card overflow-hidden">

                    <div class="p-5 border-b border-neutral-100">

                        <h2 class="font-semibold">
                            Tagihan Barang
                        </h2>

                        <p class="text-sm text-neutral-500 mt-1">
                            Pilih barang yang ingin dibayarkan.
                        </p>

                    </div>

                    <div class="divide-y divide-neutral-100">

                        <?php foreach ($items as $item): ?>

                            <label class="
                                block
                                p-5
                                cursor-pointer
                                hover:bg-neutral-50
                            ">

                                <div class="flex gap-4">

                                    <input
                                        type="checkbox"
                                        name="items[]"
                                        value="<?= (int) $item['id'] ?>"
                                        data-amount="<?= (float) $item['remaining'] ?>"
                                        class="
                                            mt-1
                                            w-5
                                            h-5
                                        "
                                    >

                                    <div class="flex-1">

                                        <div class="
                                            flex
                                            flex-col
                                            md:flex-row
                                            md:justify-between
                                            gap-2
                                        ">

                                            <div>

                                                <div class="font-medium">
                                                    <?= e($item['product_name']) ?>
                                                </div>

                                                <div class="
                                                    text-xs
                                                    text-neutral-400
                                                    mt-1
                                                ">
                                                    <?= e($item['invoice_number']) ?>
                                                    ·
                                                    <?= date(
                                                        'd M Y H:i',
                                                        strtotime($item['sale_date'])
                                                    ) ?>
                                                </div>

                                            </div>

                                            <div class="
                                                font-semibold
                                                text-sm
                                            ">
                                                <?= rupiah($item['remaining']) ?>
                                            </div>

                                        </div>

                                        <div class="
                                            text-xs
                                            text-neutral-500
                                            mt-3
                                        ">
                                            <?= (int) $item['quantity'] ?>
                                            ×
                                            <?= rupiah($item['selling_price']) ?>

                                            <span class="mx-1">·</span>

                                            Hak penitip:
                                            <?= rupiah($item['consignor_amount']) ?>
                                        </div>

                                    </div>

                                </div>

                            </label>

                        <?php endforeach; ?>

                    </div>

                </div>

                <!-- PAYMENT -->

                <div class="bento-card p-5">

                    <h2 class="font-semibold mb-4">
                        Pembayaran
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div>

                            <label class="
                                block
                                text-sm
                                font-medium
                                mb-2
                            ">
                                Tanggal Pembayaran
                            </label>

                            <input
                                type="datetime-local"
                                name="settlement_date"
                                value="<?= date('Y-m-d\TH:i') ?>"
                                required
                                class="
                                    w-full
                                    px-4
                                    py-2.5
                                    rounded-xl
                                    border
                                    border-neutral-200
                                "
                            >

                        </div>

                        <div>

                            <label class="
                                block
                                text-sm
                                font-medium
                                mb-2
                            ">
                                Metode Pembayaran
                            </label>

                            <select
                                name="payment_method"
                                required
                                class="
                                    w-full
                                    px-4
                                    py-2.5
                                    rounded-xl
                                    border
                                    border-neutral-200
                                    bg-white
                                "
                            >
                                <option value="CASH">
                                    Cash
                                </option>

                                <option value="TRANSFER">
                                    Transfer
                                </option>

                                <option value="OTHER">
                                    Lainnya
                                </option>
                            </select>

                        </div>

                    </div>

                    <div class="mt-4">

                        <label class="
                            block
                            text-sm
                            font-medium
                            mb-2
                        ">
                            Catatan
                        </label>

                        <textarea
                            name="notes"
                            rows="3"
                            placeholder="Catatan pembayaran..."
                            class="
                                w-full
                                px-4
                                py-3
                                rounded-xl
                                border
                                border-neutral-200
                                resize-none
                            "
                        ></textarea>

                    </div>

                </div>

                <!-- FOOTER -->

                <div class="
                    bento-card
                    p-5
                    flex
                    flex-col
                    sm:flex-row
                    sm:items-center
                    sm:justify-between
                    gap-4
                ">

                    <div>

                        <div class="text-sm text-neutral-500">
                            Total Pembayaran
                        </div>

                        <div
                            id="paymentTotal"
                            class="
                                text-2xl
                                font-semibold
                                mt-1
                            "
                        >
                            Rp 0
                        </div>

                    </div>

                    <button
                        type="submit"
                        class="
                            inline-flex
                            items-center
                            justify-center
                            gap-2
                            px-5
                            py-3
                            rounded-xl
                            bg-neutral-900
                            text-white
                            text-sm
                            font-medium
                            hover:bg-neutral-800
                        "
                    >
                        <i data-lucide="banknote" class="w-4 h-4"></i>
                        Bayar Penitip
                    </button>

                </div>

            </form>

        <?php endif; ?>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const checkboxes = document.querySelectorAll(
        'input[name="items[]"]'
    );

    const totalElement =
        document.getElementById('paymentTotal');

    function updateTotal() {

        let total = 0;

        checkboxes.forEach(function (checkbox) {

            if (checkbox.checked) {
                total += parseFloat(
                    checkbox.dataset.amount || 0
                );
            }

        });

        totalElement.textContent =
            'Rp ' +
            new Intl.NumberFormat('id-ID').format(total);
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener(
            'change',
            updateTotal
        );
    });

});
</script>

<?php include '../../includes/footer.php'; ?>