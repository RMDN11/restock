<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

$storeId = (int) ($authStoreId ?? 0);
if ($storeId <= 0) { http_response_code(403); exit('Store aktif tidak ditemukan.'); }

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

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    header('Location: /pages/titipan/pembayaran.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        cs.id,
        cs.settlement_number,
        cs.settlement_date,
        cs.total_amount,
        cs.payment_method,
        cs.notes,

        c.name AS consignor_name,
        c.phone,

        u.name AS created_by_name

    FROM consignor_settlements cs

    INNER JOIN consignors c
        ON c.id = cs.consignor_id
        AND c.store_id = $storeId

    LEFT JOIN users u
        ON u.id = cs.created_by

    WHERE cs.id = :id
    AND cs.store_id = $storeId

    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$settlement = $stmt->fetch();

if (!$settlement) {
    header('Location: /pages/titipan/pembayaran.php?error=notfound');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        csi.amount,

        si.quantity,
        si.selling_price,

        p.name AS product_name,

        s.invoice_number,
        s.sale_date

    FROM consignor_settlement_items csi

    INNER JOIN sale_items si
        ON si.id = csi.sale_item_id
        AND si.store_id = $storeId

    INNER JOIN products p
        ON p.id = si.product_id
        AND p.store_id = $storeId

    INNER JOIN sales s
        ON s.id = si.sale_id
        AND s.store_id = $storeId

    WHERE
        csi.settlement_id = :settlement_id
        AND csi.store_id = $storeId

    ORDER BY csi.id ASC
");

$stmt->execute([
    ':settlement_id' => $id
]);

$items = $stmt->fetchAll();

$paymentLabels = [
    'CASH' => 'Cash',
    'TRANSFER' => 'Transfer',
    'OTHER' => 'Lainnya'
];

$pageTitle = 'Detail Pembayaran';

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">

    <!-- MOBILE HEADER -->

<main class="p-4 md:p-8 max-w-4xl mx-auto">

        <div class="
            flex
            flex-col
            sm:flex-row
            sm:items-center
            sm:justify-between
            gap-4
            mb-8
        ">

            <div>

    <div class="text-xs text-neutral-400 mb-2">
        PEMBAYARAN BERHASIL
    </div>

    <h1 class="
        text-2xl
        md:text-3xl
        font-semibold
        tracking-tight
    ">
        <?= e($settlement['settlement_number']) ?>
    </h1>

</div>

<div class="
    flex
    flex-col
    sm:flex-row
    gap-2
">

    <a
        href="/pages/titipan/invoice.php?id=<?= (int) $settlement['id'] ?>"
        target="_blank"
        class="
            inline-flex
            items-center
            justify-center
            gap-2
            px-4
            py-2.5
            rounded-xl
            bg-neutral-900
            text-white
            text-sm
            font-medium
            hover:bg-neutral-800
            transition
        "
    >
        <i data-lucide="file-text" class="w-4 h-4"></i>
        Invoice
    </a>

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
            text-neutral-700
            text-sm
            font-medium
            hover:bg-neutral-50
            transition
        "
    >
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        Kembali
    </a>

</div>

</div>

        <div class="bento-card p-6 mb-6">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div>

                    <div class="text-xs text-neutral-400">
                        PENITIP
                    </div>

                    <div class="font-semibold mt-1">
                        <?= e($settlement['consignor_name']) ?>
                    </div>

                    <?php if (!empty($settlement['phone'])): ?>

                        <div class="text-sm text-neutral-500 mt-1">
                            <?= e($settlement['phone']) ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div>

                    <div class="text-xs text-neutral-400">
                        TOTAL DIBAYARKAN
                    </div>

                    <div class="
                        text-2xl
                        font-semibold
                        mt-1
                    ">
                        <?= rupiah($settlement['total_amount']) ?>
                    </div>

                </div>

                <div>

                    <div class="text-xs text-neutral-400">
                        TANGGAL
                    </div>

                    <div class="text-sm mt-1">
                        <?= date(
                            'd M Y H:i',
                            strtotime($settlement['settlement_date'])
                        ) ?>
                    </div>

                </div>

                <div>

                    <div class="text-xs text-neutral-400">
                        METODE
                    </div>

                    <div class="text-sm mt-1">
                        <?= e(
                            $paymentLabels[
                                $settlement['payment_method']
                            ]
                            ?? $settlement['payment_method']
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

        <div class="bento-card overflow-hidden">

            <div class="p-5 border-b border-neutral-100">

                <h2 class="font-semibold">
                    Rincian Barang
                </h2>

            </div>

            <div class="divide-y divide-neutral-100">

                <?php foreach ($items as $item): ?>

                    <div class="p-5">

                        <div class="
                            flex
                            flex-col
                            sm:flex-row
                            sm:items-center
                            sm:justify-between
                            gap-3
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
                                <?= rupiah($item['amount']) ?>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="
                p-5
                border-t
                border-neutral-100
                flex
                justify-between
                items-center
            ">

                <span class="text-sm text-neutral-500">
                    Total
                </span>

                <span class="text-xl font-semibold">
                    <?= rupiah($settlement['total_amount']) ?>
                </span>

            </div>

        </div>

        <?php if (!empty($settlement['notes'])): ?>

            <div class="bento-card p-5 mt-6">

                <div class="text-xs text-neutral-400 mb-2">
                    CATATAN
                </div>

                <div class="text-sm text-neutral-700">
                    <?= nl2br(e($settlement['notes'])) ?>
                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

<?php include '../../includes/footer.php'; ?>