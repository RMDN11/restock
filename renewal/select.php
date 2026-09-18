<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$packageId = (int) ($_GET['package_id'] ?? 0);

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

$_SESSION['selected_package_id'] = $packageId;
unset($_SESSION['payment_id']);

header('Location: /checkout.php');
exit;
