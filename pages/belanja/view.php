<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$pageTitle = 'Detail Belanja';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';


$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {

    header('Location: /pages/belanja/');
    exit;

}


/*
|--------------------------------------------------------------------------
| PURCHASE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.*,
        s.name AS supplier_name,
        u.name AS created_by_name
    FROM purchases p
    LEFT JOIN suppliers s
        ON s.id = p.supplier_id
       AND s.store_id = p.store_id
    LEFT JOIN users u
        ON u.id = p.created_by
    WHERE p.id = :id
      AND p.store_id = :purchase_store_id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id,
    ':purchase_store_id' => $authStoreId
]);

$purchase =
    $stmt->fetch();


if (!$purchase) {

    header('Location: /pages/belanja/');
    exit;

}


/*
|--------------------------------------------------------------------------
| ITEMS
|--------------------------------------------------------------------------
*/

$stmtItems = $pdo->prepare("
    SELECT
        pi.*,
        pr.name AS product_name,
        pr.sku,
        pr.unit
    FROM purchase_items pi
    INNER JOIN products pr
        ON pr.id = pi.product_id
       AND pr.store_id = pi.store_id
    WHERE pi.purchase_id = :purchase_id
      AND pi.store_id = :item_store_id
    ORDER BY pi.id ASC
");

$stmtItems->execute([
    ':purchase_id' => $id,
    ':item_store_id' => $authStoreId
]);

$items =
    $stmtItems->fetchAll();


function rupiah($value): string
{
    return 'Rp' . number_format(
        (float) $value,
        0,
        ',',
        '.'
    );
}

?>


<div class="main-content">


    <!-- MOBILE HEADER -->
<main class="p-4 md:p-6 lg:p-8">


        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">

            <div>

                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                    Detail Belanja
                </h1>

                <p class="mt-2 text-sm text-neutral-500">
                    <?= htmlspecialchars(
                        $purchase['purchase_number']
                    ) ?>
                </p>

            </div>


            <div class="flex gap-2">

                <a
                    href="/pages/belanja/"
                    class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl border border-neutral-200 bg-white text-sm font-medium text-neutral-700 hover:bg-neutral-50"
                >
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Kembali
                </a>


                <a
                    href="/pages/belanja/edit.php?id=<?= $id ?>"
                    class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl bg-neutral-900 text-white text-sm font-medium hover:bg-neutral-800"
                >
                    <i data-lucide="pencil" class="w-4 h-4"></i>
                    Edit
                </a>

            </div>

        </div>


        <!-- INFO -->
        <section class="bento-card p-5 md:p-6 mb-5">

            <div class="grid grid-cols-2 md:grid-cols-4 gap-5">


                <div>

                    <div class="text-xs text-neutral-400 mb-1">
                        Nomor Belanja
                    </div>

                    <div class="text-sm font-medium">
                        <?= htmlspecialchars(
                            $purchase['purchase_number']
                        ) ?>
                    </div>

                </div>


                <div>

                    <div class="text-xs text-neutral-400 mb-1">
                        Tanggal
                    </div>

                    <div class="text-sm font-medium">

                        <?= date(
                            'd M Y H:i',
                            strtotime(
                                $purchase['purchase_date']
                            )
                        ) ?>

                    </div>

                </div>


                <div>

                    <div class="text-xs text-neutral-400 mb-1">
                        Supplier
                    </div>

                    <div class="text-sm font-medium">
                        <?= htmlspecialchars(
                            $purchase['supplier_name']
                            ?: '-'
                        ) ?>
                    </div>

                </div>


                <div>

                    <div class="text-xs text-neutral-400 mb-1">
                        Dibuat oleh
                    </div>

                    <div class="text-sm font-medium">
                        <?= htmlspecialchars(
                            $purchase['created_by_name']
                            ?: '-'
                        ) ?>
                    </div>

                </div>


                <div>

                    <div class="text-xs text-neutral-400 mb-1">
                        Total
                    </div>

                    <div class="text-sm font-semibold">
                        <?= rupiah(
                            $purchase['total_amount']
                        ) ?>
                    </div>

                </div>

            </div>


            <?php if (!empty($purchase['notes'])): ?>

                <div class="mt-5 pt-5 border-t border-neutral-100">

                    <div class="text-xs text-neutral-400 mb-1">
                        Catatan
                    </div>

                    <div class="text-sm text-neutral-600">
                        <?= nl2br(
                            htmlspecialchars(
                                $purchase['notes']
                            )
                        ) ?>
                    </div>

                </div>

            <?php endif; ?>

        </section>


        <!-- ITEMS -->
        <section class="bento-card overflow-hidden">

            <div class="px-5 md:px-6 py-5 border-b border-neutral-100">

                <h2 class="font-semibold">
                    Barang yang Dibeli
                </h2>

            </div>


            <div class="overflow-x-auto">

                <table class="w-full">

                    <thead>

                        <tr class="border-b border-neutral-100">

                            <th class="text-left px-6 py-4 text-xs font-semibold text-neutral-400 uppercase">
                                Barang
                            </th>

                            <th class="text-center px-6 py-4 text-xs font-semibold text-neutral-400 uppercase">
                                Jumlah
                            </th>

                            <th class="text-right px-6 py-4 text-xs font-semibold text-neutral-400 uppercase">
                                Harga Modal
                            </th>

                            <th class="text-right px-6 py-4 text-xs font-semibold text-neutral-400 uppercase">
                                Subtotal
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($items as $item): ?>

                        <tr class="border-b border-neutral-100 last:border-0">

                            <td class="px-6 py-4">

                                <div class="text-sm font-medium">
                                    <?= htmlspecialchars(
                                        $item['product_name']
                                    ) ?>
                                </div>

                                <?php if (!empty($item['sku'])): ?>

                                    <div class="text-xs text-neutral-400 mt-1">
                                        SKU <?= htmlspecialchars(
                                            $item['sku']
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td class="px-6 py-4 text-center text-sm">
                                <?= (int) $item['quantity'] ?>
                                <?= htmlspecialchars(
                                    $item['unit']
                                ) ?>
                            </td>


                            <td class="px-6 py-4 text-right text-sm">
                                <?= rupiah(
                                    $item['buying_price']
                                ) ?>
                            </td>


                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <?= rupiah(
                                    $item['subtotal']
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>


                    <tfoot>

                        <tr>

                            <td
                                colspan="3"
                                class="px-6 py-5 text-right text-sm font-medium"
                            >
                                Total Belanja
                            </td>

                            <td class="px-6 py-5 text-right text-lg font-semibold">
                                <?= rupiah(
                                    $purchase['total_amount']
                                ) ?>
                            </td>

                        </tr>

                    </tfoot>

                </table>

            </div>

        </section>

    </main>

</div>


<?php
require_once __DIR__ . '/../../includes/footer.php';
?>