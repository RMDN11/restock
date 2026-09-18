<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

$storeId = (int) ($authStoreId ?? 0);
if ($storeId <= 0) { http_response_code(403); exit('Store aktif tidak ditemukan.'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/titipan/pembayaran.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['csrf_token']) ||
    empty($_POST['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
) {
    header('Location: /pages/titipan/pembayaran.php?error=csrf');
    exit;
}

$consignorId = (int) ($_POST['consignor_id'] ?? 0);

$items = $_POST['items'] ?? [];

$settlementDate =
    trim($_POST['settlement_date'] ?? '');

$paymentMethod =
    $_POST['payment_method'] ?? 'CASH';

$notes =
    trim($_POST['notes'] ?? '');

$allowedPayment = [
    'CASH',
    'TRANSFER',
    'OTHER'
];

if ($consignorId <= 0 || empty($items)) {
    header(
        'Location: /pages/titipan/pembayaran.php?error=invalid'
    );
    exit;
}

if (!in_array($paymentMethod, $allowedPayment, true)) {
    header(
        'Location: /pages/titipan/pembayaran.php?error=payment'
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Nomor Pembayaran
|--------------------------------------------------------------------------
*/

function generateSettlementNumber(PDO $pdo)
{
    $prefix = 'BYR-' . date('Ymd') . '-';

    $stmt = $pdo->prepare("
        SELECT settlement_number
        FROM consignor_settlements
        WHERE settlement_number LIKE :prefix
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':prefix' => $prefix . '%'
    ]);

    $last = $stmt->fetchColumn();

    if ($last) {
        $number = (int) substr(
            $last,
            -3
        ) + 1;
    } else {
        $number = 1;
    }

    return $prefix .
        str_pad(
            $number,
            3,
            '0',
            STR_PAD_LEFT
        );
}

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Validasi Penitip
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id, name
        FROM consignors
        WHERE id = :id
        AND store_id = $storeId
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':id' => $consignorId
    ]);

    $consignor = $stmt->fetch();

    if (!$consignor) {
        throw new Exception(
            'Penitip tidak ditemukan.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Bersihkan ID
    |--------------------------------------------------------------------------
    */

    $itemIds = array_values(
        array_unique(
            array_map(
                'intval',
                $items
            )
        )
    );

    if (!$itemIds) {
        throw new Exception(
            'Tidak ada item pembayaran.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Buat settlement
    |--------------------------------------------------------------------------
    */

    $settlementNumber =
        generateSettlementNumber($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO consignor_settlements (
            store_id,
            settlement_number,
            consignor_id,
            settlement_date,
            total_amount,
            payment_method,
            notes,
            created_by
        )
        VALUES (
            $storeId,
            :settlement_number,
            :consignor_id,
            :settlement_date,
            0,
            :payment_method,
            :notes,
            :created_by
        )
    ");

    $stmt->execute([
        ':settlement_number' => $settlementNumber,
        ':consignor_id' => $consignorId,
        ':settlement_date' => $settlementDate !== ''
            ? date(
                'Y-m-d H:i:s',
                strtotime($settlementDate)
            )
            : date('Y-m-d H:i:s'),
        ':payment_method' => $paymentMethod,
        ':notes' => $notes !== ''
            ? $notes
            : null,
        ':created_by' => $_SESSION['user_id'] ?? null
    ]);

    $settlementId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Masukkan item pembayaran
    |--------------------------------------------------------------------------
    */

    $totalAmount = 0;

    $selectItem = $pdo->prepare("
        SELECT
            si.id,
            si.consignor_amount,

            COALESCE(
                (
                    SELECT SUM(csi.amount)
                    FROM consignor_settlement_items csi
                    WHERE csi.sale_item_id = si.id
                    AND csi.store_id = $storeId
                ),
                0
            ) AS total_paid

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
    
        WHERE
            si.id = :sale_item_id
            AND si.store_id = $storeId
            AND s.status = 'COMPLETED'
            AND p.product_type = 'TITIPAN'

        LIMIT 1

        FOR UPDATE
    ");

    $insertItem = $pdo->prepare("
        INSERT INTO consignor_settlement_items (
            store_id,
            settlement_id,
            sale_item_id,
            amount
        )
        VALUES (
            $storeId,
            :settlement_id,
            :sale_item_id,
            :amount
        )
    ");

    foreach ($itemIds as $saleItemId) {

        /*
        | Penting:
        | ID yang dikirim browser tetap diverifikasi
        | ulang di server.
        */

        $selectItem->execute([
            ':consignor_id' => $consignorId,
            ':sale_item_id' => $saleItemId
        ]);

        $item = $selectItem->fetch();

        if (!$item) {
            throw new Exception(
                'Item pembayaran tidak valid.'
            );
        }

        $remaining =
            (float) $item['consignor_amount']
            -
            (float) $item['total_paid'];

        if ($remaining <= 0) {
            throw new Exception(
                'Ada item yang sudah dibayar.'
            );
        }

        $insertItem->execute([
            ':settlement_id' => $settlementId,
            ':sale_item_id' => $saleItemId,
            ':amount' => $remaining
        ]);

        $totalAmount += $remaining;
    }

    /*
    |--------------------------------------------------------------------------
    | Update Total Settlement
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE consignor_settlements
        SET total_amount = :total_amount
        WHERE id = :id
        AND store_id = $storeId
    ");

    $stmt->execute([
        ':total_amount' => $totalAmount,
        ':id' => $settlementId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (
            store_id,
            user_id,
            action,
            table_name,
            record_id,
            description
        )
        VALUES (
            $storeId,
            :user_id,
            'CREATE',
            'consignor_settlements',
            :record_id,
            :description
        )
    ");

    $stmt->execute([
        ':user_id' =>
            $_SESSION['user_id'] ?? null,

        ':record_id' =>
            $settlementId,

        ':description' =>
            'Pembayaran penitip ' .
            $consignor['name'] .
            ' sebesar Rp ' .
            number_format(
                $totalAmount,
                0,
                ',',
                '.'
            )
    ]);

    $pdo->commit();

    header(
        'Location: /pages/titipan/pembayaran-view.php?id=' .
        $settlementId .
        '&success=created'
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'RESTOCK CONSIGNOR PAYMENT ERROR: ' .
        $e->getMessage()
    );

    header(
        'Location: /pages/titipan/pembayaran.php?error=payment'
    );

    exit;
}