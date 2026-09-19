<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/package_pricing.php';

$packageId = (int) ($_GET['package_id'] ?? 0);
$storeCount = (int) ($_GET['store_count'] ?? 0);

if ($packageId <= 0) {
    header('Location: /renewal.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id
     FROM packages
     WHERE id = :package_id
       AND status = 'ACTIVE'
     LIMIT 1"
);
$stmt->execute([':package_id' => $packageId]);

if (!$stmt->fetch()) {
    header('Location: /renewal.php');
    exit;
}

if ($storeCount < 1 || $storeCount < restockGetActiveStoreCount($pdo, $authAccountId)) {
    header('Location: /renewal.php');
    exit;
}

$tier = restockFindPackagePriceTier($pdo, $packageId, $storeCount);
if (!$tier) {
    header('Location: /renewal.php');
    exit;
}

$_SESSION['selected_package_id'] = $packageId;
$_SESSION['selected_store_count'] = $storeCount;
$_SESSION['selected_pricing_tier_id'] = (int) $tier['id'];
$_SESSION['checkout_origin'] = '/renewal.php';
unset($_SESSION['payment_id']);

header('Location: /checkout.php');
exit;
